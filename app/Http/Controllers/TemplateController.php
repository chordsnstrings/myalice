<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /** Template manager (B6.3 / C-09). */
    public function index(): Response
    {
        $templates = MessageTemplate::latest()->get()->map(fn (MessageTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'language' => $t->language,
            'approval_status' => $t->approval_status,
            'quality' => $t->quality,
            'rejection_reason' => $t->rejection_reason,
            'body' => $t->body,
        ]);

        return Inertia::render('Broadcasts/Templates', ['templates' => $templates]);
    }
}
