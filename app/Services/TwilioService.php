<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio Messaging over plain REST — no SDK, matching ShopifyService.
 * sendSms() runs afterResponse (no queue worker in production), so it must
 * never throw: every outcome lands on the message row as sent or failed.
 */
class TwilioService
{
    public function configured(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.messaging_service_sid'));
    }

    public function sendSms(Message $message): void
    {
        if (! $this->configured()) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'error' => 'Twilio is not configured (TWILIO_* env keys).',
            ])->saveQuietly();

            return;
        }

        try {
            $sid = config('services.twilio.sid');

            $response = Http::withBasicAuth($sid, config('services.twilio.token'))
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $message->to_address,
                    'MessagingServiceSid' => config('services.twilio.messaging_service_sid'),
                    'Body' => $message->body,
                    'StatusCallback' => route('webhooks.twilio.status'),
                ]);

            if ($response->successful()) {
                $message->forceFill([
                    'status' => MessageStatus::Sent,
                    'provider_message_id' => $response->json('sid'),
                ])->saveQuietly();

                return;
            }

            $message->forceFill([
                'status' => MessageStatus::Failed,
                'error' => $response->json('message') ?? ('HTTP '.$response->status()),
            ])->saveQuietly();
            Log::warning("Twilio send failed for message {$message->id}: ".$response->body());
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'error' => $e->getMessage(),
            ])->saveQuietly();
            Log::warning("Twilio send threw for message {$message->id}: {$e->getMessage()}");
        }
    }

    /**
     * Twilio signs the exact URL it called plus the ksorted POST params with
     * HMAC-SHA1 of the auth token. Unsigned/unconfigured is always rejected.
     */
    public function validateSignature(Request $request): bool
    {
        $token = config('services.twilio.token');

        if (blank($token)) {
            return false;
        }

        $data = $request->fullUrl();
        $params = $request->post();
        ksort($params);

        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, (string) $request->header('X-Twilio-Signature'));
    }

    /**
     * Points the Messaging Service's inbound (and fallback) URL at this app.
     * Returns the service resource on success.
     */
    public function setInboundWebhook(string $url, ?string $fallbackUrl = null): array
    {
        $serviceSid = config('services.twilio.messaging_service_sid');

        $response = Http::withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
            ->asForm()
            ->post("https://messaging.twilio.com/v1/Services/{$serviceSid}", array_filter([
                'InboundRequestUrl' => $url,
                'FallbackUrl' => $fallbackUrl,
            ]))
            ->throw();

        return $response->json();
    }

    public function getService(): array
    {
        $serviceSid = config('services.twilio.messaging_service_sid');

        return Http::withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
            ->get("https://messaging.twilio.com/v1/Services/{$serviceSid}")
            ->throw()
            ->json();
    }
}
