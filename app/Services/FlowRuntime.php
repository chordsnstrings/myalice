<?php

namespace App\Services;

/**
 * Executes a chatbot flow at runtime (M12). Walks from a node following its
 * `next` pointer, emitting message nodes, until it reaches a branching node
 * (awaiting input), a terminal node, or a dead end. A max-step guard prevents
 * infinite loops even if a cycle slipped past validation (C-10 runtime).
 */
class FlowRuntime
{
    private const TERMINAL = ['handoff', 'end'];

    private const BRANCHING = ['question', 'buttons', 'condition'];

    public function __construct(private int $maxSteps = 50) {}

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>}  $graph
     * @return array{emitted: array<int, string>, stopped: string, at: string|null}
     */
    public function walk(array $graph, ?string $fromId = null): array
    {
        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];
        foreach ($graph['nodes'] ?? [] as $node) {
            $byId[(string) $node['id']] = $node;
        }

        $current = $fromId ?? $this->startId($byId);
        $emitted = [];
        $steps = 0;

        while ($current !== null && isset($byId[$current])) {
            if ($steps++ >= $this->maxSteps) {
                return ['emitted' => $emitted, 'stopped' => 'max_steps', 'at' => $current];
            }

            $node = $byId[$current];
            $type = (string) ($node['type'] ?? '');

            if (in_array($type, self::TERMINAL, true)) {
                return ['emitted' => $emitted, 'stopped' => 'terminal', 'at' => $current];
            }

            if (in_array($type, self::BRANCHING, true)) {
                return ['emitted' => $emitted, 'stopped' => 'awaiting_input', 'at' => $current];
            }

            if (in_array($type, ['message', 'product'], true)) {
                $emitted[] = $current;
            }

            $next = $node['next'] ?? null;
            $current = $next !== null ? (string) $next : null;
        }

        return ['emitted' => $emitted, 'stopped' => 'end', 'at' => null];
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     */
    private function startId(array $byId): ?string
    {
        foreach ($byId as $id => $node) {
            if (($node['type'] ?? null) === 'start') {
                return is_string($node['next'] ?? null) ? $node['next'] : $id;
            }
        }

        return null;
    }
}
