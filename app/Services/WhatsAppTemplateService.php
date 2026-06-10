<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages WhatsApp HSM templates against Meta's WhatsApp Business API: submit for
 * approval and sync statuses. Thin HTTP client; stub mode when the WABA isn't
 * connected (so the flow is testable without live credentials).
 */
class WhatsAppTemplateService
{
    /** Map Meta's UPPERCASE status to our lowercase approval_status. */
    private const STATUS_MAP = [
        'APPROVED' => 'approved',
        'PENDING' => 'pending',
        'IN_APPEAL' => 'pending',
        'PENDING_DELETION' => 'pending',
        'REJECTED' => 'rejected',
        'PAUSED' => 'paused',
        'DISABLED' => 'disabled',
        'FLAGGED' => 'paused',
    ];

    /** @return array{token: ?string, waba_id: ?string} */
    private function creds(): array
    {
        $c = optional(Channel::where('type', 'whatsapp')->first())->credentials ?? [];

        return [
            'token' => $c['access_token'] ?? config('services.whatsapp.token'),
            'waba_id' => $c['waba_id'] ?? config('services.whatsapp.waba_id'),
        ];
    }

    public function configured(): bool
    {
        $c = $this->creds();

        return filled($c['token']) && filled($c['waba_id']);
    }

    /**
     * Submit a template to Meta for approval. Sets meta_template_id + status.
     */
    public function submit(MessageTemplate $template): void
    {
        $creds = $this->creds();

        if (blank($creds['token']) || blank($creds['waba_id'])) {
            Log::info('[WhatsApp template stub] would submit', ['name' => $template->name]);
            $template->update(['meta_template_id' => 'stub_'.Str::uuid(), 'approval_status' => 'pending']);

            return;
        }

        $response = Http::withToken((string) $creds['token'])
            ->post("https://graph.facebook.com/v21.0/{$creds['waba_id']}/message_templates", [
                'name' => $template->name,
                'language' => $template->language,
                'category' => strtoupper($template->category),
                'components' => $template->components ?? [],
            ])
            ->throw();

        $template->update([
            'meta_template_id' => (string) $response->json('id', ''),
            'approval_status' => self::STATUS_MAP[strtoupper((string) $response->json('status', 'PENDING'))] ?? 'pending',
        ]);
    }

    /**
     * Pull template statuses from Meta and reconcile local rows by (name, language).
     *
     * @return int number of templates updated
     */
    public function sync(): int
    {
        $creds = $this->creds();

        if (blank($creds['token']) || blank($creds['waba_id'])) {
            return 0;
        }

        $response = Http::withToken((string) $creds['token'])
            ->get("https://graph.facebook.com/v21.0/{$creds['waba_id']}/message_templates", [
                'fields' => 'name,language,status,category,id,quality_score',
                'limit' => 200,
            ])
            ->throw();

        $updated = 0;
        foreach ((array) $response->json('data', []) as $remote) {
            $template = MessageTemplate::where('name', $remote['name'] ?? '')
                ->where('language', $remote['language'] ?? '')
                ->first();
            if (! $template) {
                continue;
            }

            $template->update([
                'meta_template_id' => (string) ($remote['id'] ?? $template->meta_template_id),
                'approval_status' => self::STATUS_MAP[strtoupper((string) ($remote['status'] ?? ''))] ?? $template->approval_status,
            ]);
            $updated++;
        }

        return $updated;
    }
}
