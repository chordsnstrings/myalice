<?php

namespace App\Ai;

/**
 * Provider-agnostic chat contract. Drivers translate the normalized message +
 * tool shapes to/from their wire format so the agent never special-cases a vendor.
 *
 * Normalized message: { role: system|user|assistant|tool, content: string,
 *   tool_calls?: list<ToolCall>, tool_call_id?: string, name?: string }
 * Normalized tool: { name, description, parameters: JSON-Schema object }
 */
interface LlmClient
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $opts
     */
    public function chat(array $messages, array $tools = [], array $opts = []): LlmResponse;
}
