<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\ChannelOnboarder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Onboards messaging channels from the admin panel (B9). Two paths, admin's
 * choice: manual credential entry and Meta Embedded Signup. Both verify against
 * the Graph API, then persist the Channel with encrypted credentials.
 */
class ChannelConnectionController extends Controller
{
    private const META = ['whatsapp', 'messenger', 'instagram'];

    /** Manual credential entry. */
    public function connect(Request $request, string $type, ChannelOnboarder $onboarder): RedirectResponse
    {
        $this->assertMeta($type);

        $rules = match ($type) {
            'whatsapp' => [
                'access_token' => ['required', 'string'],
                'phone_number_id' => ['required', 'string'],
                'waba_id' => ['nullable', 'string'],
            ],
            default => ['page_token' => ['required', 'string']],
        };

        $input = $request->validate($rules);

        return $this->persist($type, fn () => $onboarder->manual($type, $input));
    }

    /** Meta Embedded Signup ("Connect with Facebook"). */
    public function embedded(Request $request, string $type, ChannelOnboarder $onboarder): RedirectResponse
    {
        $this->assertMeta($type);

        if (blank(config('services.meta.app_id'))) {
            throw ValidationException::withMessages([
                'embedded' => 'Embedded Signup is not configured. Set META_APP_ID or use manual connection.',
            ]);
        }

        $input = $request->validate([
            'code' => ['required_without:access_token', 'string'],
            'access_token' => ['required_without:code', 'string'],
            'phone_number_id' => [Rule::requiredIf($type === 'whatsapp'), 'string'],
            'waba_id' => ['nullable', 'string'],
        ]);

        return $this->persist($type, fn () => $onboarder->embedded($type, $input));
    }

    public function disconnect(string $type): RedirectResponse
    {
        $this->assertMeta($type);

        Channel::where('type', $type)->delete();

        return back()->with('success', ucfirst($type).' disconnected.');
    }

    /**
     * @param  callable(): array{external_id: string, name: string, credentials: array<string, mixed>}  $resolve
     */
    private function persist(string $type, callable $resolve): RedirectResponse
    {
        try {
            $resolved = $resolve();
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'connection' => "Couldn't verify these credentials with Meta. Check the token and try again.",
            ]);
        }

        Channel::updateOrCreate(
            ['type' => $type],
            [
                'name' => $resolved['name'],
                'external_id' => $resolved['external_id'],
                'credentials' => $resolved['credentials'],
                'status' => 'connected',
            ],
        );

        return back()->with('success', ucfirst($type).' connected.');
    }

    private function assertMeta(string $type): void
    {
        abort_unless(in_array($type, self::META, true), 404);
    }
}
