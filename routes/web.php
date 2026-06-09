<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/', fn () => redirect(auth()->check() ? '/inbox' : '/login'));

// Authenticated workspace
Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Polished placeholders for sections delivered in later phases.
    $placeholder = fn (array $p) => fn () => Inertia::render('Placeholder', $p);

    Route::get('/contacts', $placeholder([
        'title' => 'Contacts', 'icon' => 'Users', 'spec' => 'M8 · B4',
        'description' => 'A commerce-aware CRM: profiles, cross-channel identity, segments and CSV import.',
        'cta' => 'Import contacts',
    ]))->name('contacts');

    Route::get('/chatbots', $placeholder([
        'title' => 'Chatbots', 'icon' => 'Bot', 'spec' => 'M12 · B5',
        'description' => 'A no-code visual flow builder to automate FAQs, lead capture and guided selling.',
        'cta' => 'New chatbot',
    ]))->name('chatbots');

    Route::get('/broadcasts', $placeholder([
        'title' => 'Broadcasts', 'icon' => 'Megaphone', 'spec' => 'M14 · B6',
        'description' => 'Compliant, costed WhatsApp campaigns with a wallet pre-flight before every send.',
        'cta' => 'New broadcast',
    ]))->name('broadcasts');

    Route::get('/broadcasts/create', $placeholder([
        'title' => 'New broadcast', 'icon' => 'Megaphone', 'spec' => 'B6.2',
        'description' => 'Template → audience → schedule → review & cost. Money is shown before you spend.',
        'cta' => 'Start',
    ]));

    Route::get('/automations', $placeholder([
        'title' => 'Automations', 'icon' => 'Workflow', 'spec' => 'M15 · B7',
        'description' => 'Lifecycle triggers: abandoned cart, order confirmation, shipping, upsell and re-engagement.',
        'cta' => 'New automation',
    ]))->name('automations');

    Route::get('/orders', $placeholder([
        'title' => 'Commerce', 'icon' => 'ShoppingBag', 'spec' => 'M9–M11 · B8',
        'description' => 'Sync your store catalog and orders; turn conversations into paid orders.',
        'cta' => 'Connect a store',
    ]))->name('orders');

    Route::get('/settings', $placeholder([
        'title' => 'Settings', 'icon' => 'Settings', 'spec' => 'B11',
        'description' => 'Workspace, team & roles, business hours, billing, wallet, developer API and more.',
        'cta' => 'Open workspace settings',
    ]))->name('settings');

    Route::get('/settings/wallet', $placeholder([
        'title' => 'Wallet', 'icon' => 'Wallet', 'spec' => 'B11.6',
        'description' => 'Prepaid credits, top-up, auto-recharge and an auditable ledger.',
        'cta' => 'Top up',
    ]));

    Route::get('/settings/profile', $placeholder([
        'title' => 'Profile', 'icon' => 'User', 'spec' => 'B11.8',
        'description' => 'Your name, avatar, password, 2FA, language, density and theme.',
        'cta' => 'Edit profile',
    ]));
});
