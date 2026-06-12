<?php

namespace App\Ai;

use App\Models\AiAction;
use App\Models\AiProvider;
use App\Models\Conversation;
use App\Models\Tag;

/**
 * Cheap, dedicated LLM pass that labels a conversation with the best-matching
 * topic tag(s) from the workspace's topic taxonomy — kept out of the sales loop
 * so it stays fast and reliable. Best-effort: any failure leaves the chat
 * untagged for a human to triage.
 */
class TopicClassifier
{
    public function __construct(private LlmManager $llm) {}

    public function classify(Conversation $conversation): void
    {
        if (! config('ai.auto_tag', true)) {
            return;
        }
        if ($conversation->tags()->exists()) {
            return; // classify once
        }

        $candidates = Tag::where('kind', 'topic')->get(['id', 'name']);
        if ($candidates->isEmpty()) {
            return;
        }

        $provider = AiProvider::where('status', 'connected')->orderByDesc('is_default')->first();
        if (! $provider) {
            return; // no LLM connected — leave for a human
        }

        $text = $conversation->messages()->where('author', 'customer')->latest('sent_at')
            ->limit((int) config('ai.auto_tag_messages', 6))
            ->pluck('body')->reverse()->implode("\n");
        if (trim($text) === '') {
            return;
        }

        $names = $candidates->pluck('name')->all();
        $system = "You label a customer support conversation with the best-matching topic(s) from this exact list:\n- "
            .implode("\n- ", $names)
            ."\nReply with ONLY a JSON array of up to 2 topic names copied verbatim from the list, or [] if none fit. No other text.";

        try {
            $res = $this->llm->chat(
                [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $text]],
                [],
                ['temperature' => 0],
                $provider,
            );
        } catch (\Throwable) {
            return;
        }

        $picked = $this->parse($res->text, $names);
        $ids = $candidates->whereIn('name', $picked)->pluck('id')->all();
        if ($ids !== []) {
            $conversation->tags()->syncWithoutDetaching($ids);
        }

        AiAction::create([
            'conversation_id' => $conversation->id,
            'ai_agent_id' => null,
            'type' => 'tag',
            'payload' => ['topics' => $picked],
            'status' => $ids === [] ? 'failed' : 'ok',
            'tokens_in' => $res->usage['in'],
            'tokens_out' => $res->usage['out'],
            'created_at' => now(),
        ]);
    }

    /**
     * Extract a JSON array from the model output and keep only names that match
     * the candidate list (case-insensitive), capped at two.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function parse(string $text, array $names): array
    {
        if (! preg_match('/\[.*\]/s', $text, $m)) {
            return [];
        }
        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return [];
        }

        $lower = array_map('mb_strtolower', $names);
        $out = [];
        foreach ($decoded as $value) {
            $i = array_search(mb_strtolower((string) $value), $lower, true);
            if ($i !== false) {
                $out[] = $names[$i];
            }
        }

        return array_slice(array_unique($out), 0, 2);
    }
}
