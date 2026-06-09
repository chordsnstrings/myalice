<?php

namespace App\Http\Controllers;

use App\Actions\ImportContacts;
use App\Models\Contact;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /** Contacts list (B4.1). */
    public function index(): Response
    {
        $contacts = Contact::orderBy('name')
            ->get()
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'channel' => $c->channel,
                'lifecycle' => $c->lifecycle_stage,
                'tags' => $c->tags ?? [],
                'orders' => Order::where('contact_id', $c->id)->count(),
            ])->values();

        return Inertia::render('Contacts/Index', ['contacts' => $contacts]);
    }

    /**
     * Import contacts from an uploaded CSV (B4.1 / C-19): mapping + validation
     * preview, dedupe, async-style summary. Small files parse inline; large ones
     * would be queued in production.
     */
    public function import(Request $request, ImportContacts $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $rows = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $header = fgetcsv($handle);
            $header = is_array($header) ? array_map(fn ($h) => strtolower(trim((string) $h)), $header) : [];

            while (($line = fgetcsv($handle)) !== false) {
                /** @var array<string, string|null> $assoc */
                $assoc = [];
                foreach ($header as $idx => $key) {
                    $assoc[$key] = $line[$idx] ?? null;
                }
                $rows[] = $assoc;
            }
            fclose($handle);
        }

        $summary = $importer->handle($rows);

        return back()->with('success', "Import complete: {$summary['added']} added · {$summary['merged']} merged · {$summary['invalid']} invalid.");
    }

    /** Contact profile (B4.2). */
    public function show(Contact $contact): Response
    {
        $orders = Order::where('contact_id', $contact->id)->latest()->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'total' => (float) $o->total,
                'currency' => $o->currency,
                'status' => $o->status,
            ]);

        return Inertia::render('Contacts/Show', [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'channel' => $contact->channel,
                'lifecycle' => $contact->lifecycle_stage,
                'tags' => $contact->tags ?? [],
            ],
            'orders' => $orders,
        ]);
    }
}
