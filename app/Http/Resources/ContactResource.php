<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'channel' => $this->channel,
            'lifecycle_stage' => $this->lifecycle_stage,
            'tags' => $this->tags ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
