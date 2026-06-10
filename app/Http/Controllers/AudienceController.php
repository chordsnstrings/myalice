<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Services\AudienceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AudienceController extends Controller
{
    /** Create or update a saved segment (tag/lifecycle filters). */
    public function store(Request $request, AudienceBuilder $builder): RedirectResponse
    {
        $data = $this->validated($request);

        $audience = Audience::create([
            'name' => $data['name'],
            'type' => 'dynamic',
            'filters' => ['tags' => $data['tags'] ?? [], 'lifecycle' => $data['lifecycle'] ?? []],
        ]);
        $audience->update(['size' => $builder->count('whatsapp', $audience)]);

        return back()->with('success', 'Audience saved.');
    }

    /** Live size estimate for a set of filters (AJAX). */
    public function preview(Request $request, AudienceBuilder $builder): JsonResponse
    {
        $data = $this->validated($request, requireName: false);

        $audience = new Audience(['filters' => ['tags' => $data['tags'] ?? [], 'lifecycle' => $data['lifecycle'] ?? []]]);
        $channel = $request->input('channel', 'whatsapp');

        return response()->json(['size' => $builder->count(is_string($channel) ? $channel : 'whatsapp', $audience)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireName = true): array
    {
        return $request->validate([
            'name' => [$requireName ? 'required' : 'nullable', 'string', 'max:120'],
            'channel' => ['nullable', Rule::in(['whatsapp', 'messenger', 'instagram'])],
            'tags' => ['array'],
            'tags.*' => ['string'],
            'lifecycle' => ['array'],
            'lifecycle.*' => ['string'],
        ]);
    }
}
