<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ChannelConnectionController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CommerceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\Reports\AgentPerformanceController;
use App\Http\Controllers\Reports\CsatReportController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Guest
Route::middleware(['guest', 'throttle:auth'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::get('/', fn () => redirect(auth()->check() ? '/inbox' : '/login'));

// Locale switch (A13) — stored in the session, applied by SetLocale middleware.
Route::post('/locale', function (Request $request) {
    $request->validate(['locale' => ['required', 'in:en,ar,es,pt']]);
    $request->session()->put('locale', $request->string('locale')->toString());

    return back();
})->name('locale');

// Authenticated workspace
Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/onboarding', fn () => Inertia::render('Onboarding/Wizard'))->name('onboarding');

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Manager reports (B10.2–B10.4)
    Route::middleware('can:manage-team')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/agents', [AgentPerformanceController::class, 'index'])->name('agents');
        Route::get('/agents/{agent}', [AgentPerformanceController::class, 'show'])->name('agents.show');
        Route::get('/sales', [SalesReportController::class, 'index'])->name('sales');
        Route::get('/csat', [CsatReportController::class, 'index'])->name('csat');
    });

    // CRM
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts');
    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');

    // Chatbots
    Route::get('/chatbots', [ChatbotController::class, 'index'])->name('chatbots');
    Route::get('/chatbots/{chatbot}/edit', [ChatbotController::class, 'edit'])->name('chatbots.edit');
    Route::post('/chatbots/{chatbot}/publish', [ChatbotController::class, 'publish'])->name('chatbots.publish');

    // Broadcasts & templates
    Route::get('/broadcasts', [BroadcastController::class, 'index'])->name('broadcasts');
    Route::get('/broadcasts/create', [BroadcastController::class, 'create'])->name('broadcasts.create');
    Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates');

    // Automations
    Route::get('/automations', [AutomationController::class, 'index'])->middleware('can:use-automation')->name('automations');

    // Commerce
    Route::get('/orders', [CommerceController::class, 'orders'])->name('orders');
    Route::get('/products', [CommerceController::class, 'products'])->name('products');

    // Settings cluster
    Route::get('/settings', [SettingsController::class, 'workspace'])->name('settings');
    Route::get('/settings/team', [SettingsController::class, 'team'])->name('settings.team');
    Route::get('/settings/content', [SettingsController::class, 'content'])->name('settings.content');
    Route::get('/settings/hours', [SettingsController::class, 'hours'])->name('settings.hours');
    Route::get('/settings/channels', [SettingsController::class, 'channels'])->name('settings.channels');
    Route::middleware('can:manage-channels')->group(function () {
        Route::post('/settings/channels/{type}/connect', [ChannelConnectionController::class, 'connect'])->name('channels.connect');
        Route::post('/settings/channels/{type}/embedded', [ChannelConnectionController::class, 'embedded'])->name('channels.embedded');
        Route::delete('/settings/channels/{type}', [ChannelConnectionController::class, 'disconnect'])->name('channels.disconnect');
    });
    Route::get('/settings/billing', [SettingsController::class, 'billing'])->middleware('can:manage-billing')->name('settings.billing');
    Route::get('/settings/wallet', [SettingsController::class, 'wallet'])->middleware('can:manage-billing')->name('settings.wallet');
    Route::get('/settings/developer', [SettingsController::class, 'developer'])->middleware('can:manage-api')->name('settings.developer');
    Route::get('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
    Route::get('/settings/widget', [SettingsController::class, 'widget'])->name('settings.widget');
    Route::get('/settings/qr', [SettingsController::class, 'qr'])->name('settings.qr');
});
