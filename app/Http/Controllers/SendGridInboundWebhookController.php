<?php

namespace App\Http\Controllers;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Account;
use App\Models\Message;
use App\Services\SendGridService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SendGrid Inbound Parse for the reply subdomain. Parse has no native
 * signing, so authentication is the unguessable URL token — blank config
 * rejects everything (never accept unauthenticated webhooks). SendGrid
 * retries non-2xx for days, so dedupe by the RFC Message-ID.
 */
class SendGridInboundWebhookController extends Controller
{
    public function __invoke(Request $request, SendGridService $sendgrid, string $token): Response
    {
        if (! $sendgrid->inboundTokenValid($token)) {
            return response('forbidden', 403);
        }

        $from = mb_strtolower($this->extractAddress((string) $request->input('from')));
        $messageId = $this->extractMessageId((string) $request->input('headers'));

        if ($messageId !== null && Message::where('provider_message_id', $messageId)->exists()) {
            return response('ok'); // retry of something we already stored
        }

        $body = trim((string) ($request->input('text') ?: strip_tags((string) $request->input('html'))));

        $account = blank($from) ? null : Account::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($from)])
            ->orderByDesc('updated_at')
            ->first();

        if ($account === null) {
            Log::info('Inbound email did not match an account', ['from' => $from]);
        }

        try {
            Message::create([
                'account_id' => $account?->id,
                'channel' => MessageChannel::Email,
                'direction' => MessageDirection::In,
                'status' => MessageStatus::Received,
                'from_address' => $from ?: (string) $request->input('from'),
                'to_address' => $this->extractAddress((string) $request->input('to')),
                'subject' => Str::limit(trim((string) $request->input('subject')), 255, '') ?: null,
                'body' => Str::limit($body, 60000, "\n[truncated]"),
                'provider_message_id' => $messageId,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Concurrent retry lost the race — already stored.
        }

        return response('ok');
    }

    /** "Joe Buyer <joe@shop.com>" → "joe@shop.com"; bare addresses pass through. */
    private function extractAddress(string $raw): string
    {
        return preg_match('/<([^>]+)>/', $raw, $m) ? trim($m[1]) : trim($raw);
    }

    private function extractMessageId(string $headers): ?string
    {
        return preg_match('/^Message-ID:\s*(.+)$/mi', $headers, $m)
            ? Str::limit(trim($m[1]), 255, '')
            : null;
    }
}
