<?php

namespace App\Actions;

use App\Models\Contact;
use Illuminate\Support\Facades\Validator;

/**
 * Import contacts from parsed CSV rows (M8 / C-19): validate, flag invalids,
 * dedupe on phone within the workspace, and return a results summary.
 */
class ImportContacts
{
    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{added: int, merged: int, invalid: int, errors: array<int, string>}
     */
    public function handle(array $rows): array
    {
        $added = 0;
        $merged = 0;
        $invalid = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $data = [
                'name' => trim((string) ($row['name'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')) ?: null,
                'channel' => trim((string) ($row['channel'] ?? 'whatsapp')) ?: 'whatsapp',
            ];

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
                'email' => ['nullable', 'email'],
            ]);

            if ($validator->fails()) {
                $invalid++;
                $errors[] = 'Row '.($i + 1).': '.$validator->errors()->first();

                continue;
            }

            $existing = Contact::where('phone', $data['phone'])->first();

            if ($existing) {
                $existing->update(['name' => $data['name'], 'email' => $data['email']]);
                $merged++;

                continue;
            }

            Contact::create([...$data, 'lifecycle_stage' => 'lead']);
            $added++;
        }

        return ['added' => $added, 'merged' => $merged, 'invalid' => $invalid, 'errors' => $errors];
    }
}
