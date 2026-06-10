<?php

namespace App\Ai;

/** A normalized tool/function call returned by a model. */
class ToolCall
{
    /** @param  array<string, mixed>  $arguments */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}
}
