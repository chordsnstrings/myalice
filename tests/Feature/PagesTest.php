<?php

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $ws = Workspace::create(['name' => 'Test WS', 'wallet_balance' => 128.50, 'currency' => 'USD']);
    $this->user = User::create([
        'workspace_id' => $ws->id,
        'name' => 'Tester',
        'email' => 'tester@test.test',
        'password' => bcrypt('x'),
    ]);
});

it('renders every authenticated page', function (string $url, string $component) {
    $this->actingAs($this->user)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/inbox', 'Inbox/Index'],
    ['/dashboard', 'Dashboard'],
    ['/contacts', 'Contacts/Index'],
    ['/chatbots', 'Chatbots/Index'],
    ['/broadcasts', 'Broadcasts/Index'],
    ['/broadcasts/create', 'Broadcasts/Create'],
    ['/templates', 'Broadcasts/Templates'],
    ['/automations', 'Automations/Index'],
    ['/orders', 'Commerce/Orders'],
    ['/products', 'Commerce/Products'],
    ['/settings', 'Settings/Workspace'],
    ['/settings/team', 'Settings/Team'],
    ['/settings/channels', 'Settings/Channels'],
    ['/settings/hours', 'Settings/Hours'],
    ['/settings/content', 'Settings/Content'],
    ['/settings/billing', 'Settings/Billing'],
    ['/settings/wallet', 'Settings/Wallet'],
    ['/settings/developer', 'Settings/Developer'],
    ['/settings/profile', 'Settings/Profile'],
    ['/onboarding', 'Onboarding/Wizard'],
]);

it('shows the forgot-password screen and accepts a request without enumerating', function () {
    $this->get('/forgot-password')->assertOk()->assertInertia(fn ($p) => $p->component('Auth/ForgotPassword'));

    $this->post('/forgot-password', ['email' => 'nobody@nowhere.test'])
        ->assertRedirect()
        ->assertSessionHas('status');
});

it('exposes the broadcast wallet pre-flight figures', function () {
    $this->actingAs($this->user)
        ->get('/broadcasts/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Broadcasts/Create')
            ->where('wallet', 128.5)
            ->has('price_per_message')
        );
});
