<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Order;
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
