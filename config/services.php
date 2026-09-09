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

    /*
     * Two-way account messaging. Both services no-op safely (message rows go
     * to "failed" with a clear error) until their keys are present.
     */
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'from' => env('TWILIO_FROM', '+13853508287'), // recorded as messages.from_address
    ],

    'sendgrid' => [
        'key' => env('SENDGRID_API_KEY'),
        'from_address' => env('SENDGRID_FROM_ADDRESS', 'sales@wholesale.freedomfuel.us'),
        'from_name' => env('SENDGRID_FROM_NAME', 'Freedom Fuel'),
        'reply_domain' => env('SENDGRID_REPLY_DOMAIN'), // e.g. reply.freedomfuel.us (Inbound Parse MX)
        'inbound_token' => env('SENDGRID_INBOUND_TOKEN'), // random URL token; blank = inbound rejected
    ],

];
