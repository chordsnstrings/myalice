<?php

use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\AudienceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ChannelConnectionController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CommerceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\KnowledgeController;
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
    // Human inbox actions (B3): reply, resolve/reopen, assign.
    Route::post('/conversations/{conversation}/messages', [InboxController::class, 'reply'])->name('conversations.reply');
    Route::put('/conversations/{conversation}/resolve', [InboxController::class, 'resolve'])->name('conversations.resolve');
    Route::put('/conversations/{conversation}/assign', [InboxController::class, 'assign'])->name('conversations.assign');
    Route::put('/conversations/{conversation}/resume-ai', [InboxController::class, 'resumeAi'])->name('conversations.resume-ai');
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

    // Chatbots — building/publishing flows are bot management (owner/manager).
    Route::get('/chatbots', [ChatbotController::class, 'index'])->name('chatbots');
    Route::middleware('can:manage-bots')->group(function () {
        Route::get('/chatbots/{chatbot}/edit', [ChatbotController::class, 'edit'])->name('chatbots.edit');
        Route::post('/chatbots/{chatbot}/publish', [ChatbotController::class, 'publish'])->name('chatbots.publish');
    });

    // Broadcasts & templates — creating a broadcast spends wallet credits.
    Route::get('/broadcasts', [BroadcastController::class, 'index'])->name('broadcasts');
    Route::middleware('can:create-broadcasts')->group(function () {
        Route::get('/broadcasts/create', [BroadcastController::class, 'create'])->name('broadcasts.create');
        Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
        Route::post('/broadcasts/preview', [BroadcastController::class, 'preview'])->name('broadcasts.preview');
        Route::post('/broadcasts/test', [BroadcastController::class, 'testSend'])->name('broadcasts.test');
        Route::put('/broadcasts/{broadcast}/pause', [BroadcastController::class, 'pause'])->name('broadcasts.pause');
        Route::put('/broadcasts/{broadcast}/resume', [BroadcastController::class, 'resume'])->name('broadcasts.resume');
        Route::delete('/broadcasts/{broadcast}', [BroadcastController::class, 'cancel'])->name('broadcasts.cancel');
        Route::post('/audiences', [AudienceController::class, 'store'])->name('audiences.store');
        Route::post('/audiences/preview', [AudienceController::class, 'preview'])->name('audiences.preview');
    });
    Route::get('/broadcasts/{broadcast}', [BroadcastController::class, 'show'])->name('broadcasts.show');
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates');
    Route::middleware('can:manage-bots')->group(function () {
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::post('/templates/{template}/submit', [TemplateController::class, 'submit'])->name('templates.submit');
        Route::post('/templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
    });

    // Automations
    Route::get('/automations', [AutomationController::class, 'index'])->middleware('can:use-automation')->name('automations');

    // Commerce
    Route::get('/orders', [CommerceController::class, 'orders'])->name('orders');
    Route::get('/products', [CommerceController::class, 'products'])->name('products');
    Route::patch('/products/{product}/type', [CommerceController::class, 'updateProductType'])->middleware('can:manage-bots')->name('products.type');

    // Settings cluster
    Route::get('/settings', [SettingsController::class, 'workspace'])->name('settings');
    Route::get('/settings/team', [SettingsController::class, 'team'])->middleware('can:manage-team')->name('settings.team');
    Route::get('/settings/content', [SettingsController::class, 'content'])->name('settings.content');
    Route::get('/settings/hours', [SettingsController::class, 'hours'])->name('settings.hours');
    // Channel config exposes webhook verify tokens — restrict to channel managers.
    Route::get('/settings/channels', [SettingsController::class, 'channels'])->middleware('can:manage-channels')->name('settings.channels');
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

    // AI sales agent (M13) — admin only + plan-gated.
    Route::middleware(['can:manage-bots', 'can:use-ai-agents'])->group(function () {
        Route::get('/settings/ai-agents', [AiAgentController::class, 'index'])->name('settings.ai-agents');
        Route::post('/settings/ai-agents/providers', [AiAgentController::class, 'connectProvider'])->name('ai.providers.connect');
        Route::put('/settings/ai-agents/providers/{provider}/default', [AiAgentController::class, 'setDefault'])->name('ai.providers.default');
        Route::delete('/settings/ai-agents/providers/{provider}', [AiAgentController::class, 'disconnectProvider'])->name('ai.providers.disconnect');
        Route::put('/settings/ai-agents/agent', [AiAgentController::class, 'updateAgent'])->name('ai.agent.update');
        Route::post('/settings/ai-agents/playground', [AiAgentController::class, 'playground'])->name('ai.playground');
        Route::post('/settings/ai-agents/knowledge', [KnowledgeController::class, 'addSource'])->name('ai.knowledge.add');
        Route::post('/settings/ai-agents/knowledge/{source}/refresh', [KnowledgeController::class, 'refreshSource'])->name('ai.knowledge.refresh');
        Route::delete('/settings/ai-agents/knowledge/{source}', [KnowledgeController::class, 'deleteSource'])->name('ai.knowledge.delete');
        Route::post('/inbox/ai-drafts/{message}/send', [AiAgentController::class, 'sendDraft'])->name('ai.drafts.send');
        Route::delete('/inbox/ai-drafts/{message}', [AiAgentController::class, 'dismissDraft'])->name('ai.drafts.dismiss');
    });
});
