<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'founder' => \App\Http\Middleware\EnsureUserIsFounder::class,
            'retailer' => \App\Http\Middleware\EnsureUserIsRetailer::class,
        ]);

        // Provider webhooks authenticate with signatures/URL tokens, not CSRF.
        $middleware->validateCsrfTokens(except: [
            'webhooks/shopify',
            'webhooks/twilio/*',
            'webhooks/sendgrid/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
