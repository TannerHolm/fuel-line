<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SendGrid v3 Mail Send over plain REST. Replies route back into the app
 * because every send sets reply-to on the Inbound Parse subdomain — no
 * subject threading needed. Runs afterResponse; never throws.
 */
class SendGridService
{
    public function configured(): bool
    {
        return filled(config('services.sendgrid.key'))
            && filled(config('services.sendgrid.from_address'));
    }

    public function sendEmail(Message $message): void
    {
        if (! $this->configured()) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'error' => 'SendGrid is not configured (SENDGRID_* env keys).',
            ])->saveQuietly();

            return;
        }

        try {
            $payload = [
                'personalizations' => [['to' => [['email' => $message->to_address]]]],
                'from' => [
                    'email' => config('services.sendgrid.from_address'),
                    'name' => config('services.sendgrid.from_name'),
                ],
                'subject' => $message->subject ?: 'Message from Freedom Fuel',
                'content' => [['type' => 'text/plain', 'value' => $message->body]],
            ];

            if (filled(config('services.sendgrid.reply_domain'))) {
                $payload['reply_to'] = ['email' => 'reply@'.config('services.sendgrid.reply_domain')];
            }

            $response = Http::withToken(config('services.sendgrid.key'))
                ->post('https://api.sendgrid.com/v3/mail/send', $payload);

            if ($response->successful()) {
                $message->forceFill([
                    'status' => MessageStatus::Sent,
                    'provider_message_id' => $response->header('X-Message-Id') ?: null,
                ])->saveQuietly();

                return;
            }

            $message->forceFill([
                'status' => MessageStatus::Failed,
                'error' => $response->json('errors.0.message') ?? ('HTTP '.$response->status()),
            ])->saveQuietly();
            Log::warning("SendGrid send failed for message {$message->id}: ".$response->body());
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'error' => $e->getMessage(),
            ])->saveQuietly();
            Log::warning("SendGrid send threw for message {$message->id}: {$e->getMessage()}");
        }
    }

    public function inboundTokenValid(string $token): bool
    {
        $configured = config('services.sendgrid.inbound_token');

        return filled($configured) && hash_equals($configured, $token);
    }
}
