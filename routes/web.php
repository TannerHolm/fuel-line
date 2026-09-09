<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\SampleController;
use Illuminate\Support\Facades\Route;

Route::get('/', \App\Http\Controllers\LandingController::class)->name('home');

// Public legal pages — also the verifiable call-to-action for the A2P SMS campaign.
Route::get('privacy', fn () => \Inertia\Inertia::render('Legal/Privacy'))->name('privacy');
Route::get('terms', fn () => \Inertia\Inertia::render('Legal/Terms'))->name('terms');
Route::get('dashboard', fn () => redirect()->route('pipeline'))->name('dashboard');

// Investor scorecard — signed, expiring, read-only. No auth.
Route::get('scorecard', [\App\Http\Controllers\InvestorController::class, 'scorecard'])->name('scorecard');

// Shopify webhooks — HMAC-verified in the controller, CSRF-exempt in bootstrap/app.php.
Route::post('webhooks/shopify', \App\Http\Controllers\ShopifyWebhookController::class)->name('webhooks.shopify');

// Messaging webhooks — Twilio signature / URL token verified in the controllers, CSRF-exempt.
Route::post('webhooks/twilio/inbound', \App\Http\Controllers\TwilioInboundWebhookController::class)->name('webhooks.twilio.inbound');
Route::post('webhooks/twilio/status', \App\Http\Controllers\TwilioStatusWebhookController::class)->name('webhooks.twilio.status');
Route::post('webhooks/sendgrid/inbound/{token}', \App\Http\Controllers\SendGridInboundWebhookController::class)->name('webhooks.sendgrid.inbound');

// Wholesale-partner portal — scoped to the signed-in retailer's own account.
Route::middleware(['auth', 'retailer'])->group(function () {
    Route::get('portal', [\App\Http\Controllers\PortalController::class, 'index'])->name('portal');
    Route::get('portal/order', [\App\Http\Controllers\PortalController::class, 'orderForm'])->name('portal.order');
    Route::post('portal/order', [\App\Http\Controllers\PortalController::class, 'placeOrder'])->name('portal.order.store');
    Route::post('portal/report', [\App\Http\Controllers\PortalController::class, 'report'])->name('portal.report');
});

Route::middleware(['auth', 'founder'])->group(function () {
    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::get('accounts/import', [\App\Http\Controllers\AccountImportController::class, 'show'])->name('accounts.import');
    Route::post('accounts/import', [\App\Http\Controllers\AccountImportController::class, 'store'])->name('accounts.import.store');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::patch('accounts/{account}/stage', [AccountController::class, 'updateStage'])->name('accounts.stage');

    Route::post('accounts/{account}/check-ins', [CheckInController::class, 'store'])->name('accounts.check-ins.store');
    Route::post('accounts/{account}/samples', [SampleController::class, 'store'])->name('accounts.samples.store');
    Route::post('accounts/{account}/orders', [OrderController::class, 'store'])->name('accounts.orders.store');

    Route::get('messages', [\App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
    Route::post('accounts/{account}/messages', [\App\Http\Controllers\MessageController::class, 'store'])->name('accounts.messages.store');
    Route::post('accounts/{account}/messages/read', [\App\Http\Controllers\MessageController::class, 'markRead'])->name('accounts.messages.read');

    Route::get('kpis', [KpiController::class, 'index'])->name('kpis');
    Route::post('kpis/investor-link', [\App\Http\Controllers\InvestorController::class, 'generateLink'])->name('kpis.investor-link');

    Route::get('field', [\App\Http\Controllers\FieldController::class, 'index'])->name('field');
    Route::get('map', [\App\Http\Controllers\MapController::class, 'index'])->name('map');

    // One-time Shopify OAuth install (dev-dashboard app).
    Route::get('shopify/connect', [\App\Http\Controllers\ShopifyOAuthController::class, 'connect'])->name('shopify.connect');
    Route::get('shopify/callback', [\App\Http\Controllers\ShopifyOAuthController::class, 'callback'])->name('shopify.callback');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
