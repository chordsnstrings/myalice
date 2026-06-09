<?php

use App\Actions\ImportContacts;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;

afterEach(fn () => Tenancy::clear());

it('imports valid rows, dedupes, and flags invalids (C-19)', function () {
    $ws = Workspace::create(['name' => 'Import WS']);
    Tenancy::set($ws);

    Contact::create(['name' => 'Existing', 'phone' => '+15551112222']);

    $summary = app(ImportContacts::class)->handle([
        ['name' => 'New Person', 'phone' => '+15553334444', 'email' => 'new@x.test'],
        ['name' => 'Existing Updated', 'phone' => '+15551112222'],   // dup → merge
        ['name' => '', 'phone' => 'not-a-number'],                    // invalid
    ]);

    expect($summary['added'])->toBe(1);
    expect($summary['merged'])->toBe(1);
    expect($summary['invalid'])->toBe(1);
    expect(Contact::count())->toBe(2);
    expect(Contact::where('phone', '+15551112222')->first()->name)->toBe('Existing Updated');
});

it('accepts a CSV upload over HTTP and reports a summary', function () {
    $ws = Workspace::create(['name' => 'Upload WS']);
    $user = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o@u.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);

    $csv = "name,phone,email\nAda Lovelace,+15557778888,ada@x.test\nBad Row,xx,\n";
    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $this->actingAs($user)
        ->post('/contacts/import', ['file' => $file])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Contact::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
});
