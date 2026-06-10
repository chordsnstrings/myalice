<?php

namespace App\Ai;

/** Provider-agnostic result of one chat() call. */
class LlmResponse
{
    /**
     * @param  list<ToolCall>  $toolCalls
     * @param  array{in:int,out:int}  $usage
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
        public readonly array $usage = ['in' => 0, 'out' => 0],
        public readonly string $stopReason = 'stop',
        public string $provider = '',
        public string $model = '',
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
