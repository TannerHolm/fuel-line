<?php

namespace App\Console\Commands;

use App\Services\TwilioService;
use Illuminate\Console\Command;

/**
 * Points the Twilio Messaging Service's inbound-SMS URL (and fallback) at
 * this app. Nothing arrives until this has run — same trap as the Shopify
 * webhooks. Idempotent; safe to re-run after any domain change.
 */
class TwilioWebhooks extends Command
{
    protected $signature = 'fuelline:twilio-webhooks';

    protected $description = 'Point the Twilio messaging service inbound webhook at this app';

    public function handle(TwilioService $twilio): int
    {
        if (! $twilio->configured()) {
            $this->error('Twilio is not configured — set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN and TWILIO_MESSAGING_SERVICE_SID.');

            return self::FAILURE;
        }

        $url = route('webhooks.twilio.inbound');

        if (! str_starts_with($url, 'https://')) {
            $this->error("Twilio requires an HTTPS callback; APP_URL currently gives {$url}.");

            return self::FAILURE;
        }

        try {
            $current = $twilio->getService()['inbound_request_url'] ?? null;

            if ($current === $url) {
                $this->line("= inbound already points at {$url}");
            } else {
                // Fallback = same endpoint: narrows the lost-SMS window if the
                // primary attempt lands mid-deploy (Twilio does not retry).
                $twilio->setInboundWebhook($url, $url);
                $this->line($current ? "~ inbound re-pointed from {$current}" : '+ inbound registered');
            }
        } catch (\Throwable $e) {
            $this->error("Twilio API call failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Inbound SMS posts to {$url}");
        $this->comment('Requests are verified against X-Twilio-Signature on arrival.');

        return self::SUCCESS;
    }
}
