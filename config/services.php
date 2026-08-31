<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Shopify custom-app credentials (spec §9). Fuel Line owns the relationship;
     * Shopify owns money & fulfillment. Everything here no-ops until the token
     * is present, so the app runs fine without it.
     */
    'shopify' => [
        'domain' => env('SHOPIFY_SHOP_DOMAIN'),            // e.g. freedom-fuel-21612.myshopify.com
        // Dev-dashboard app credentials: one-time OAuth at /shopify/connect
        // stores the access token encrypted in app_settings.
        'client_id' => env('SHOPIFY_CLIENT_ID'),
        'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
        // Legacy path: a direct Admin token (old store-admin custom apps).
        // If present it wins over the OAuth-obtained token.
        'token' => env('SHOPIFY_ADMIN_TOKEN'),
        'version' => env('SHOPIFY_API_VERSION', '2026-01'),
        // Webhooks from a dev-dashboard app are signed with the client secret,
        // so this only needs setting when that differs.
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'wholesale_tag' => env('SHOPIFY_WHOLESALE_TAG', 'wholesale'),
        'send_invoices' => env('SHOPIFY_SEND_INVOICES', false), // email Shopify invoice on draft-order push
        'scopes' => 'read_customers,write_customers,read_orders,write_draft_orders',
    ],

];
