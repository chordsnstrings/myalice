<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Validates and resolves channel credentials for both onboarding paths (B9):
 *  - manual()   — admin pastes a token + ids; we verify against the Graph API.
 *  - embedded() — Meta Embedded Signup returns a code/token; we exchange + verify.
 *
 * Both return a normalized [external_id, name, credentials] ready to persist on
 * the Channel. A failing Graph call throws (caught by the controller).
 */
class ChannelOnboarder
{
    private function version(): string
    {
        return (string) config('services.meta.graph_version', 'v21.0');
    }

    private function graph(): string
    {
        return 'https://graph.facebook.com/'.$this->version();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{external_id: string, name: string, credentials: array<string, mixed>}
     */
    public function manual(string $type, array $input): array
    {
        if ($type === 'whatsapp') {
            $token = (string) $input['access_token'];
            $phoneId = (string) $input['phone_number_id'];

            $res = Http::withToken($token)
                ->get("{$this->graph()}/{$phoneId}", ['fields' => 'display_phone_number,verified_name'])
                ->throw();

            return [
                'external_id' => $phoneId,
                'name' => $res->json('verified_name') ?? $res->json('display_phone_number') ?? 'WhatsApp',
                'credentials' => [
                    'access_token' => $token,
                    'phone_number_id' => $phoneId,
                    'waba_id' => $input['waba_id'] ?? null,
                ],
            ];
        }

        // Messenger / Instagram use a page (or IG-linked) access token.
        $token = (string) $input['page_token'];
        $fields = $type === 'instagram' ? 'id,username,name' : 'id,name';

        $res = Http::withToken($token)->get("{$this->graph()}/me", ['fields' => $fields])->throw();

        return [
            'external_id' => (string) $res->json('id'),
            'name' => $res->json('name') ?? $res->json('username') ?? ucfirst($type),
            'credentials' => ['page_token' => $token],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{external_id: string, name: string, credentials: array<string, mixed>}
     */
    public function embedded(string $type, array $input): array
    {
        $token = $input['access_token'] ?? null;

        // Embedded Signup may hand back a short-lived code to exchange for a token.
        if (! $token && ! empty($input['code'])) {
            $exchange = Http::get("{$this->graph()}/oauth/access_token", [
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'code' => $input['code'],
            ])->throw();

            $token = $exchange->json('access_token');
        }

        $token = (string) $token;

        if ($type === 'whatsapp') {
            return [
                'external_id' => (string) $input['phone_number_id'],
                'name' => 'WhatsApp',
                'credentials' => [
                    'access_token' => $token,
                    'phone_number_id' => (string) $input['phone_number_id'],
                    'waba_id' => $input['waba_id'] ?? null,
                ],
            ];
        }

        $res = Http::withToken($token)->get("{$this->graph()}/me", ['fields' => 'id,name'])->throw();

        return [
            'external_id' => (string) $res->json('id'),
            'name' => $res->json('name') ?? ucfirst($type),
            'credentials' => ['page_token' => $token],
        ];
    }
}
