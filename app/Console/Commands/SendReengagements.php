<?php

namespace App\Console\Commands;

use App\Jobs\SendAiReengagement;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Support\Plans;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Finds stalled, customer-started conversations ~23h after their last message —
 * still inside the WhatsApp 24h window — and queues one tailored AI follow-up
 * each. Scheduled hourly; the 23–24h band is one hour wide so it's covered.
 * SiteGround-safe: cheap SQL filtering, the queue worker does the LLM work.
 */
class SendReengagements extends Command
{
    protected $signature = 'ai:reengage {--dry-run : List candidates without sending}';

    protected $description = 'Send ~23h in-window re-engagement follow-ups for stalled, customer-started chats';

    private const OPT_OUT = ['stop', 'unsubscribe', 'opt out', 'opt-out'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $total = 0;

        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);
            // Only spend the one-shot re-engagement marker when a model can actually
            // reply — no connected provider means the run would just log errors.
            if (Plans::includes($workspace->plan, 'ai_agents')
                && AiProvider::where('status', 'connected')->exists()) {
                $total += $this->scan($dry);
            }
            Tenancy::clear();
        }

        $this->info("Re-engagement candidates: {$total}".($dry ? ' (dry run — nothing sent)' : ''));

        return self::SUCCESS;
    }

    private function scan(bool $dry): int
    {
        // Quiet for 23–24h (still inside the window), never re-engaged, open, and
        // not already handed off / suppressed.
        $candidates = Conversation::with('contact')
            ->whereNull('reengaged_at')
            ->where('window_open', true)
            ->where('status', '!=', 'resolved')
            ->where(fn ($q) => $q->whereNull('ai_status')->orWhereNotIn('ai_status', ['handed_off', 'suppressed']))
            ->whereBetween('last_message_at', [now()->subHours(24), now()->subHours(23)])
            ->get();

        $count = 0;
        foreach ($candidates as $c) {
            $agent = AiAgent::resolveFor($c->channel, $c->channel_id);
            if (! $this->eligible($c, $agent)) {
                continue;
            }

            $count++;
            if ($dry) {
                $this->line("  would re-engage #{$c->id} ({$c->channel}) — {$c->contact->name}");

                continue;
            }

            // Set the marker at dispatch time for idempotency even if the worker is slow.
            $c->update(['reengaged_at' => now()]);
            SendAiReengagement::dispatch($c->workspace_id, $c->id);
        }

        return $count;
    }

    private function eligible(Conversation $c, ?AiAgent $agent): bool
    {
        if (! $agent || ! $agent->enabled || ! in_array($agent->mode, ['auto', 'autopilot'], true)) {
            return false;
        }
        if (! ($agent->guardConfig()['reengage']['enabled'] ?? false)) {
            return false;
        }

        // Human back-off: never re-engage a chat a teammate has touched.
        if ($c->messages()->where('author', 'agent')->exists()) {
            return false;
        }

        // Don't re-pitch a contact who already ordered in this chat.
        if (AiAction::where('conversation_id', $c->id)->where('type', 'create_order')->exists()) {
            return false;
        }

        // Must be a genuine, customer-started thread with real questions.
        $customer = $c->messages()->where('direction', 'in')->where('author', 'customer')->orderBy('sent_at')->get();
        $min = (int) ($agent->guardConfig()['reengage']['min_customer_messages'] ?? 1);
        if ($customer->count() < max(1, $min)) {
            return false;
        }
        $hasQuestion = $customer->contains(fn ($m) => str_contains($m->body, '?'));
        $hasBotReply = $c->messages()->where('author', 'bot')->exists();
        if (! $hasQuestion && ! $hasBotReply) {
            return false;
        }

        // Respect opt-out: if the last thing they said was a stop request, skip.
        $last = $customer->last();
        $body = mb_strtolower((string) $last?->body);
        foreach (self::OPT_OUT as $kw) {
            if (str_contains($body, $kw)) {
                return false;
            }
        }

        return true;
    }
}
