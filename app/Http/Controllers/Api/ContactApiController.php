<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactApiController extends Controller
{
    /** Paginated, workspace-scoped contacts (M19). */
    public function index(): AnonymousResourceCollection
    {
        return ContactResource::collection(Contact::orderBy('name')->paginate(50));
    }

    public function show(Contact $contact): ContactResource
    {
        return new ContactResource($contact);
    }
}
