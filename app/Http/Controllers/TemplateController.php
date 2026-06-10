<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Services\WhatsAppTemplateService;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /** Template manager (B6.3 / C-09). */
    public function index(): Response
    {
        $templates = MessageTemplate::latest()->get()->map(fn (MessageTemplate $t) => $this->payload($t));

        return Inertia::render('Broadcasts/Templates', [
            'templates' => $templates,
            'waba_connected' => app(WhatsAppTemplateService::class)->configured(),
        ]);
    }

    /** Persist a draft template (built from structured components). */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        $template = MessageTemplate::create($this->attributes($data) + ['approval_status' => 'draft']);

        if ($request->boolean('submit')) {
            app(WhatsAppTemplateService::class)->submit($template);
        }

        return redirect('/templates')->with('success', 'Template saved.');
    }

    /** Update a draft/rejected template, optionally resubmitting. */
    public function update(Request $request, MessageTemplate $template): RedirectResponse
    {
        abort_if(in_array($template->approval_status, ['approved', 'pending'], true), 422, 'Approved or pending templates cannot be edited.');

        $data = $this->validateTemplate($request, $template->id);
        $template->update($this->attributes($data));

        if ($request->boolean('submit')) {
            app(WhatsAppTemplateService::class)->submit($template);
        }

        return back()->with('success', 'Template updated.');
    }

    /** Submit a draft/rejected template to Meta for approval. */
    public function submit(MessageTemplate $template): RedirectResponse
    {
        app(WhatsAppTemplateService::class)->submit($template);

        return back()->with('success', 'Template submitted for approval.');
    }

    /** Pull the latest statuses from Meta. */
    public function sync(): RedirectResponse
    {
        $count = app(WhatsAppTemplateService::class)->sync();

        return back()->with('success', "Synced {$count} template(s).");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('message_templates', 'name')
                    ->where(fn ($q) => $q->where('workspace_id', Tenancy::id())->where('language', $request->input('language')))
                    ->ignore($ignoreId)],
            'category' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'language' => ['required', 'string', 'max:8'],
            'body' => ['required', 'string', 'max:1024'],
            'header_format' => ['nullable', Rule::in(['none', 'text', 'image', 'video', 'document'])],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_media_url' => ['nullable', 'url'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['array', 'max:3'],
            'buttons.*.type' => ['required', Rule::in(['quick_reply', 'url', 'phone_number'])],
            'buttons.*.text' => ['required', 'string', 'max:25'],
            'buttons.*.value' => ['nullable', 'string', 'max:2000'],
            'variable_samples' => ['array'],
            'variable_samples.*' => ['string'],
        ]);
    }

    /**
     * Build the persisted attributes (incl. normalized Meta components) from input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $variableCount = MessageTemplate::countVariables($data['body']);
        $components = $this->buildComponents($data, $variableCount);

        return [
            'name' => $data['name'],
            'category' => $data['category'],
            'language' => $data['language'],
            'body' => $data['body'],
            'components' => $components,
            'variable_count' => $variableCount,
            'variable_samples' => array_values($data['variable_samples'] ?? []),
            'header_format' => ($data['header_format'] ?? 'none') === 'none' ? null : $data['header_format'],
            'header_media_url' => $data['header_media_url'] ?? null,
            'quality' => 'unknown',
        ];
    }

    /**
     * Normalize the builder fields into Meta's `components` array.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function buildComponents(array $data, int $variableCount): array
    {
        $components = [];

        $headerFormat = $data['header_format'] ?? 'none';
        if ($headerFormat === 'text' && filled($data['header_text'] ?? null)) {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $data['header_text']];
        } elseif (in_array($headerFormat, ['image', 'video', 'document'], true)) {
            $components[] = ['type' => 'HEADER', 'format' => strtoupper($headerFormat)];
        }

        $body = ['type' => 'BODY', 'text' => $data['body']];
        if ($variableCount > 0) {
            $body['example'] = ['body_text' => [array_values($data['variable_samples'] ?? [])]];
        }
        $components[] = $body;

        if (filled($data['footer'] ?? null)) {
            $components[] = ['type' => 'FOOTER', 'text' => $data['footer']];
        }

        $buttons = [];
        foreach ($data['buttons'] ?? [] as $btn) {
            $buttons[] = match ($btn['type']) {
                'url' => ['type' => 'URL', 'text' => $btn['text'], 'url' => $btn['value'] ?? ''],
                'phone_number' => ['type' => 'PHONE_NUMBER', 'text' => $btn['text'], 'phone_number' => $btn['value'] ?? ''],
                default => ['type' => 'QUICK_REPLY', 'text' => $btn['text']],
            };
        }
        if ($buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MessageTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'language' => $t->language,
            'approval_status' => $t->approval_status,
            'quality' => $t->quality,
            'rejection_reason' => $t->rejection_reason,
            'body' => $t->body,
            'components' => $t->components,
            'variable_count' => $t->variable_count,
            'header_format' => $t->header_format,
        ];
    }
}
