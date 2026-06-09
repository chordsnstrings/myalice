<?php

use App\Services\FlowRuntime;

it('emits messages then stops at a terminal node', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'm1'],
        ['id' => 'm1', 'type' => 'message', 'next' => 'm2'],
        ['id' => 'm2', 'type' => 'message', 'next' => 'h'],
        ['id' => 'h', 'type' => 'handoff'],
    ]];

    $result = (new FlowRuntime)->walk($graph);

    expect($result['emitted'])->toBe(['m1', 'm2']);
    expect($result['stopped'])->toBe('terminal');
    expect($result['at'])->toBe('h');
});

it('stops and waits at a branching node', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'm'],
        ['id' => 'm', 'type' => 'message', 'next' => 'ask'],
        ['id' => 'ask', 'type' => 'buttons', 'fallback' => 'h', 'next' => 'h'],
        ['id' => 'h', 'type' => 'handoff'],
    ]];

    $result = (new FlowRuntime)->walk($graph);

    expect($result['stopped'])->toBe('awaiting_input');
    expect($result['at'])->toBe('ask');
});

it('halts a runaway loop with the max-step guard (C-10 runtime)', function () {
    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'a'],
        ['id' => 'a', 'type' => 'message', 'next' => 'a'], // infinite self-loop
    ]];

    $result = (new FlowRuntime(maxSteps: 10))->walk($graph);

    expect($result['stopped'])->toBe('max_steps');
});
