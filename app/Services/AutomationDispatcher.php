<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\AutomationSend;
use App\Models\Contact;
use App\Models\Workspace;
use App\Support\InsufficientFundsException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a lifecycle automation for a contact and, if all guards pass, sends
 * it (M15). Guards: rule active, quiet hours, frequency cap (C-22), and the
 * wallet. Wallet-empty is an explicit skip+log+notify — never a silent drop.
 */
class AutomationDispatcher
{
    private const PRICE = 0.0125;

    public function __construct(private WalletService $wallet) {}

    public function dispatch(AutomationRule $rule, Contact $contact, ?CarbonInterface $now = null): string
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();

        if ($rule->status !== 'active') {
            return 'skipped_inactive';
        }

        if ($this->inQuietHours($rule, $now)) {
            return 'skipped_quiet_hours';
        }

        if ($this->withinFrequencyCap($rule, $contact, $now)) {
            return 'skipped_frequency';
        }

        $workspace = Workspace::findOrFail($rule->workspace_id);

        try {
            $this->wallet->debit($workspace, self::PRICE, "Automation: {$rule->name}");
        } catch (InsufficientFundsException $e) {
            // Explicit, observable behaviour — skip, log, notify (G9.2).
            Log::warning('Automation skipped: wallet empty', [
                'rule_id' => $rule->id,
                'contact_id' => $contact->id,
                'shortfall' => $e->shortfall,
            ]);

            return 'skipped_wallet';
        }

        AutomationSend::create([
            'workspace_id' => $rule->workspace_id,
            'automation_rule_id' => $rule->id,
            'contact_id' => $contact->id,
            'sent_at' => $now,
        ]);

        $rule->increment('sent');

        return 'sent';
    }

    private function inQuietHours(AutomationRule $rule, Carbon $now): bool
    {
        $quiet = $rule->timing['quiet_hours'] ?? null;
        if (! is_array($quiet) || count($quiet) !== 2) {
            return false;
        }

        [$start, $end] = $quiet;
        $startMin = $this->toMinutes($start);
        $endMin = $this->toMinutes($end);
        $nowMin = $now->hour * 60 + $now->minute;

        // Handle windows that cross midnight (e.g. 22:00–08:00).
        return $startMin <= $endMin
            ? ($nowMin >= $startMin && $nowMin < $endMin)
            : ($nowMin >= $startMin || $nowMin < $endMin);
    }

    private function withinFrequencyCap(AutomationRule $rule, Contact $contact, Carbon $now): bool
    {
        $hours = (int) ($rule->timing['frequency_cap_hours'] ?? 24);

        return AutomationSend::where('contact_id', $contact->id)
            ->where('sent_at', '>=', $now->copy()->subHours($hours))
            ->exists();
    }

    private function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_pad(explode(':', $hhmm), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
