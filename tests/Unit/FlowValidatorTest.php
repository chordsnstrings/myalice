<?php

use App\Services\FlowValidator;

function severities(array $issues): array
{
    return collect($issues)->pluck('severity')->all();
}

it('passes a well-formed flow', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'msg'],
        ['id' => 'msg', 'type' => 'message', 'next' => 'ask'],
        ['id' => 'ask', 'type' => 'question', 'fallback' => 'handoff', 'next' => 'handoff'],
        ['id' => 'handoff', 'type' => 'handoff'],
    ]];

    $v = new FlowValidator;
    expect($v->canPublish($graph))->toBeTrue();
    expect($v->validate($graph))->toBe([]);
});

it('blocks publish on a dead end', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'msg'],
        ['id' => 'msg', 'type' => 'message'], // no next, not terminal → dead end
    ]];

    $v = new FlowValidator;
    expect($v->canPublish($graph))->toBeFalse();
    expect(severities($v->validate($graph)))->toContain('error');
});

it('flags a branching node missing a fallback (C-10)', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'ask'],
        ['id' => 'ask', 'type' => 'buttons', 'next' => 'handoff'], // no fallback
        ['id' => 'handoff', 'type' => 'handoff'],
    ]];

    $messages = collect((new FlowValidator)->validate($graph))->pluck('message')->implode(' ');
    expect($messages)->toContain('fallback');
});

it('warns on unreachable nodes and loops', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'a'],
        ['id' => 'a', 'type' => 'message', 'next' => 'a'], // self-loop
        ['id' => 'orphan', 'type' => 'handoff'],            // unreachable
    ]];

    $messages = collect((new FlowValidator)->validate($graph))->pluck('message')->implode(' ');
    expect($messages)->toContain('unreachable');
    expect($messages)->toContain('loop');
});
