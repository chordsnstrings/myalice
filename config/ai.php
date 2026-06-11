<?php

return [
    // HTTP timeout per LLM call (seconds). Kept well under the queue's --max-time=50.
    'timeout' => (int) env('AI_HTTP_TIMEOUT', 20),

    // Max tool-call round-trips per reply, and how much context to send.
    'max_tool_iterations' => 3,
    'history_limit' => 30,
    'catalog_limit' => 20,

    // Wall-clock budget for the whole agent run (seconds) — fits the cron worker.
    'run_budget' => 35,

    // Humanized "typing" pause before an auto-reply is sent (enabled per-agent via
    // the humanize_replies guardrail). Delay = base + chars*per_char, capped at max.
    // Kept small so it stays within the run budget / 50s worker.
    'typing' => [
        'base_ms' => (int) env('AI_TYPING_BASE_MS', 700),
        'per_char_ms' => (int) env('AI_TYPING_PER_CHAR_MS', 18),
        'max_ms' => (int) env('AI_TYPING_MAX_MS', 6000),
    ],

    /*
    | Provider presets surfaced as cards in the admin panel. `openai_compatible`
    | entries reuse the OpenAI driver via base_url (self-hosted + aggregators).
    | `custom` = requires the enterprise plan (self-hosted / custom endpoints).
    */
    'presets' => [
        'anthropic' => ['type' => 'anthropic', 'name' => 'Anthropic (Claude)', 'model' => 'claude-sonnet-4-5', 'custom' => false],
        'openai' => ['type' => 'openai', 'name' => 'OpenAI', 'model' => 'gpt-4.1-mini', 'custom' => false],
        'gemini' => ['type' => 'gemini', 'name' => 'Google Gemini', 'model' => 'gemini-2.0-flash', 'custom' => false],
        'deepseek' => ['type' => 'openai_compatible', 'name' => 'DeepSeek', 'model' => 'deepseek-chat', 'base_url' => 'https://api.deepseek.com', 'custom' => false],
        'groq' => ['type' => 'openai_compatible', 'name' => 'Groq', 'model' => 'llama-3.3-70b-versatile', 'base_url' => 'https://api.groq.com/openai/v1', 'custom' => true],
        'together' => ['type' => 'openai_compatible', 'name' => 'Together AI', 'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo', 'base_url' => 'https://api.together.xyz/v1', 'custom' => true],
        'openrouter' => ['type' => 'openai_compatible', 'name' => 'OpenRouter', 'model' => 'meta-llama/llama-3.3-70b-instruct', 'base_url' => 'https://openrouter.ai/api/v1', 'custom' => true],
        'ollama' => ['type' => 'openai_compatible', 'name' => 'Self-hosted (Ollama)', 'model' => 'llama3.1', 'base_url' => 'http://localhost:11434/v1', 'custom' => true],
    ],
];
