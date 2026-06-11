<?php

namespace App\Http\Controllers;

use App\Ai\LlmManager;
use App\Ai\Prompts;
use App\Ai\SalesAgent;
use App\Channels\ChannelManager;
use App\Events\MessageCreated;
use App\Jobs\SendOutboundMessage;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Message;
use App\Support\Plans;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Admin panel for the AI sales agent (M13): connect/switch LLM providers, tune the
 * agent's mode/goal/tone/methodology/guardrails, and try it in a live playground.
 * All credentials are stored encrypted per-workspace; nothing here ever leaks keys.
 */
class AiAgentController extends Controller
{
    /** The single agent profile we expose in the panel is the 'all'-channel row. */
    private const SCOPE = 'all';

    public function index(): Response
    {
        $ws = Tenancy::currentOrFail();

        $presets = [];
        foreach ((array) config('ai.presets') as $key => $p) {
            if (! is_array($p)) {
                continue;
            }
            $presets[] = [
                'key' => $key,
                'type' => $p['type'],
                'name' => $p['name'],
                'model' => $p['model'],
                'base_url' => $p['base_url'] ?? null,
                'custom' => $p['custom'],
            ];
        }

        return Inertia::render('Settings/AiAgents', [
            'providers' => AiProvider::orderBy('fallback_order')->get()->map(fn (AiProvider $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'name' => $p->name,
                'model' => $p->credentials['model'] ?? null,
                'base_url' => $p->credentials['base_url'] ?? null,
                'status' => $p->status,
                'is_default' => $p->is_default,
            ])->all(),
            'presets' => $presets,
            'agent' => $this->agentPayload(),
            'meta' => Prompts::presets(),
            'llm_unlocked' => Plans::includes($ws->plan, 'llm'),
        ]);
    }

    public function connectProvider(Request $request, LlmManager $llm): RedirectResponse
    {
        $ws = Tenancy::currentOrFail();

        $data = $request->validate([
            'preset' => ['required', 'string'],
            'api_key' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'base_url' => ['nullable', 'string', 'url'],
        ]);

        $preset = config("ai.presets.{$data['preset']}");
        if (! is_array($preset)) {
            abort(404);
        }

        // Self-hosted / custom endpoints are the enterprise differentiator.
        if ($preset['custom'] && ! Plans::includes($ws->plan, 'llm')) {
            throw ValidationException::withMessages([
                'preset' => 'Self-hosted and custom endpoints require the Enterprise plan.',
            ]);
        }

        // A caller-supplied base_url that differs from the preset's fixed endpoint
        // is itself a custom endpoint — gate it behind Enterprise too, otherwise a
        // lower tier could point the server at an arbitrary host (SSRF) by pairing
        // a non-custom preset with an override URL.
        $providedBase = trim((string) ($data['base_url'] ?? ''));
        $presetBase = $preset['base_url'] ?? null;
        if ($providedBase !== '' && $providedBase !== $presetBase && ! Plans::includes($ws->plan, 'llm')) {
            throw ValidationException::withMessages([
                'base_url' => 'A custom endpoint URL requires the Enterprise plan.',
            ]);
        }

        $base = $providedBase !== '' ? $providedBase : $presetBase;
        if ($base !== null && $this->isBlockedEndpoint($base)) {
            throw ValidationException::withMessages([
                'base_url' => 'That endpoint URL is not allowed.',
            ]);
        }

        $credentials = [
            'api_key' => $data['api_key'],
            'model' => ($data['model'] ?? '') ?: $preset['model'],
        ];
        if ($base) {
            $credentials['base_url'] = $base;
        }

        // Verify with a real one-message call before persisting (ChannelOnboarder pattern).
        $probe = new AiProvider(['type' => $preset['type'], 'credentials' => $credentials]);
        try {
            $llm->clientFor($probe)->chat([['role' => 'user', 'content' => 'Reply with the single word: ok']]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'api_key' => "Couldn't reach this provider with those settings. Check the key, model and URL.",
            ]);
        }

        $isFirst = AiProvider::count() === 0;
        AiProvider::updateOrCreate(
            ['type' => $preset['type'], 'name' => $preset['name']],
            [
                'credentials' => $credentials,
                'status' => 'connected',
                'is_default' => $isFirst,
                'fallback_order' => AiProvider::max('fallback_order') + 1,
            ],
        );

        return back()->with('success', $preset['name'].' connected.');
    }

    public function setDefault(AiProvider $provider): RedirectResponse
    {
        AiProvider::query()->update(['is_default' => false]);
        $provider->update(['is_default' => true]);

        return back()->with('success', $provider->name.' is now the default model.');
    }

    public function disconnectProvider(AiProvider $provider): RedirectResponse
    {
        $wasDefault = $provider->is_default;
        $provider->delete();

        if ($wasDefault) {
            $next = AiProvider::orderBy('fallback_order')->first();
            $next?->update(['is_default' => true]);
        }

        return back()->with('success', 'Provider disconnected.');
    }

    public function updateAgent(Request $request): RedirectResponse
    {
        $ws = Tenancy::currentOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'enabled' => ['boolean'],
            'mode' => ['required', 'in:off,suggest,auto,autopilot'],
            'goal' => ['required', 'in:sale,lead,support'],
            'tone' => ['required', 'string'],
            'methodology' => ['required', 'string'],
            'business_profile' => ['nullable', 'string', 'max:4000'],
            'custom_instructions' => ['nullable', 'string', 'max:4000'],
            'ai_provider_id' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'guardrails' => ['array'],
            'guardrails.max_messages_per_conversation' => ['nullable', 'integer', 'min:1', 'max:100'],
            'guardrails.order_total_cap' => ['nullable', 'numeric', 'min:0'],
            'guardrails.engage_new_conversations' => ['boolean'],
            'guardrails.humanize_replies' => ['boolean'],
            'guardrails.handoff_keywords' => ['array'],
            'guardrails.handoff_keywords.*' => ['string'],
            // High-closure techniques
            'guardrails.closure_techniques' => ['array'],
            'guardrails.closure_techniques.*' => ['string', Rule::in(AiAgent::CLOSURE_TECHNIQUES)],
            // Layered pre-approved discounts
            'guardrails.discount' => ['array'],
            'guardrails.discount.enabled' => ['boolean'],
            'guardrails.discount.layers' => ['array', 'max:6'],
            'guardrails.discount.layers.*.type' => ['required', Rule::in(AiAgent::DISCOUNT_TYPES)],
            'guardrails.discount.layers.*.value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'guardrails.discount.service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'guardrails.discount.shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'guardrails.discount.max_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'guardrails.discount.min_order_value' => ['nullable', 'numeric', 'min:0'],
            'guardrails.discount.once_per_contact' => ['boolean'],
            'guardrails.discount.offer_ttl_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            // ~23h re-engagement
            'guardrails.reengage' => ['array'],
            'guardrails.reengage.enabled' => ['boolean'],
            'guardrails.reengage.min_customer_messages' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        AiAgent::updateOrCreate(
            ['workspace_id' => $ws->id, 'channel_scope' => self::SCOPE],
            [
                'name' => $data['name'],
                'enabled' => $data['enabled'] ?? true,
                'mode' => $data['mode'],
                'goal' => $data['goal'],
                'tone' => $data['tone'],
                'methodology' => $data['methodology'],
                'business_profile' => $data['business_profile'] ?? null,
                'custom_instructions' => $data['custom_instructions'] ?? null,
                'ai_provider_id' => $data['ai_provider_id'] ?? null,
                'guardrails' => $data['guardrails'] ?? [],
            ],
        );

        return back()->with('success', 'AI agent updated.');
    }

    /** Synchronous dry run — nothing is persisted or sent to the customer. */
    public function playground(Request $request, SalesAgent $agent): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string'],
        ]);

        $config = AiAgent::resolveFor(self::SCOPE) ?? new AiAgent($this->defaults());

        try {
            $result = $agent->dryRun($config, $data['messages']);
        } catch (Throwable $e) {
            return response()->json(['error' => 'No model is connected yet, or the provider call failed.'], 422);
        }

        return response()->json($result);
    }

    public function sendDraft(Message $message, ChannelManager $channels): RedirectResponse
    {
        abort_unless($message->status === 'draft' && $message->author === 'bot', 404);

        $conversation = $message->conversation;
        $contact = $conversation->contact;
        $to = $contact->phone ?? $contact->email;

        if ($to && $channels->supports($conversation->channel)) {
            $message->update(['status' => 'queued']);
            SendOutboundMessage::dispatch($message->id, $conversation->channel, $to);
        } else {
            // Web widget (and other broadcast-only channels) deliver in-app.
            $message->update(['status' => 'sent']);
        }

        $conversation->update([
            'last_message' => $message->body,
            'last_message_at' => now(),
            'ai_status' => 'active',
        ]);
        MessageCreated::dispatch($message);

        return back()->with('success', 'Draft sent.');
    }

    public function dismissDraft(Message $message): RedirectResponse
    {
        abort_unless($message->status === 'draft' && $message->author === 'bot', 404);

        $message->delete();

        return back()->with('success', 'Draft dismissed.');
    }

    /**
     * Reject endpoints that should never be a legitimate LLM target. Private LAN
     * and loopback are intentionally allowed (Enterprise self-hosted Ollama/vLLM),
     * but the cloud-metadata link-local range never is.
     */
    private function isBlockedEndpoint(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return true; // malformed / unparseable
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && str_starts_with($host, '169.254.');
    }

    /** @return array<string, mixed> */
    private function agentPayload(): array
    {
        $agent = AiAgent::resolveFor(self::SCOPE);
        if (! $agent) {
            return $this->defaults() + ['guardrails' => AiAgent::DEFAULT_GUARDRAILS];
        }

        return [
            'name' => $agent->name,
            'enabled' => $agent->enabled,
            'mode' => $agent->mode,
            'goal' => $agent->goal,
            'tone' => $agent->tone,
            'methodology' => $agent->methodology,
            'business_profile' => $agent->business_profile,
            'custom_instructions' => $agent->custom_instructions,
            'ai_provider_id' => $agent->ai_provider_id,
            'guardrails' => $agent->guardConfig(),
        ];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'name' => 'Sales Assistant',
            'enabled' => true,
            'mode' => 'auto',
            'goal' => 'sale',
            'channel_scope' => self::SCOPE,
            'tone' => 'friendly',
            'methodology' => 'consultative_spin',
            'business_profile' => null,
            'custom_instructions' => null,
            'ai_provider_id' => null,
        ];
    }
}
