<?php

namespace App\Services;

/**
 * Pre-publish validation for a chatbot flow graph (B5.2 / C-10). Catches the
 * ways bots go wrong before they ship: unreachable nodes, dead ends, missing
 * fallbacks on branching nodes, and loops. Publishing is blocked while issues
 * with severity "error" remain.
 *
 * Graph shape:
 *   nodes: [{ id, type, next?: id, transitions?: [id, ...] }]
 *   a "start" node is the entry point; "handoff" and "end" are terminal.
 */
class FlowValidator
{
    private const TERMINAL = ['handoff', 'end'];

    private const BRANCHING = ['question', 'buttons', 'condition'];

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>}  $graph
     * @return array<int, array{node: string|null, severity: string, message: string}>
     */
    public function validate(array $graph): array
    {
        $nodes = $graph['nodes'] ?? [];
        $issues = [];

        if ($nodes === []) {
            return [['node' => null, 'severity' => 'error', 'message' => 'Flow has no nodes.']];
        }

        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];
        foreach ($nodes as $node) {
            $byId[(string) $node['id']] = $node;
        }

        $start = null;
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'start') {
                $start = (string) $node['id'];
                break;
            }
        }

        if ($start === null) {
            return [['node' => null, 'severity' => 'error', 'message' => 'Flow has no start node.']];
        }

        // Reachability (BFS from start).
        $reachable = [];
        $queue = [$start];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($reachable[$id]) || ! isset($byId[$id])) {
                continue;
            }
            $reachable[$id] = true;
            foreach ($this->outgoing($byId[$id]) as $next) {
                $queue[] = $next;
            }
        }

        foreach ($byId as $id => $node) {
            $type = (string) ($node['type'] ?? '');
            $out = $this->outgoing($node);

            if (! isset($reachable[$id]) && $type !== 'start') {
                $issues[] = ['node' => $id, 'severity' => 'warning', 'message' => "Node \"{$id}\" is unreachable."];
            }

            if (! in_array($type, self::TERMINAL, true) && $out === []) {
                $issues[] = ['node' => $id, 'severity' => 'error', 'message' => "Node \"{$id}\" is a dead end — it has no next step."];
            }

            if (in_array($type, self::BRANCHING, true) && empty($node['fallback'])) {
                $issues[] = ['node' => $id, 'severity' => 'error', 'message' => "Node \"{$id}\" needs a fallback (re-ask or handoff)."];
            }
        }

        if ($this->hasCycle($byId, $start)) {
            $issues[] = ['node' => null, 'severity' => 'warning', 'message' => 'Flow contains a loop — a runtime max-step guard will apply.'];
        }

        return $issues;
    }

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>}  $graph
     */
    public function canPublish(array $graph): bool
    {
        foreach ($this->validate($graph) as $issue) {
            if ($issue['severity'] === 'error') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, string>
     */
    private function outgoing(array $node): array
    {
        $out = [];
        if (isset($node['next'])) {
            $out[] = (string) $node['next'];
        }
        foreach ($node['transitions'] ?? [] as $t) {
            $out[] = (string) $t;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     */
    private function hasCycle(array $byId, string $start): bool
    {
        $state = []; // 0 = visiting, 1 = done

        $visit = function (string $id) use (&$visit, &$state, $byId): bool {
            if (($state[$id] ?? null) === 0) {
                return true; // back-edge → cycle
            }
            if (($state[$id] ?? null) === 1 || ! isset($byId[$id])) {
                return false;
            }
            $state[$id] = 0;
            foreach ($this->outgoing($byId[$id]) as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $state[$id] = 1;

            return false;
        };

        return $visit($start);
    }
}
