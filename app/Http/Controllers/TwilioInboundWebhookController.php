<?php

namespace App\Http\Controllers;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Account;
use App\Models\Message;
use App\Services\TwilioService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Inbound SMS from the Messaging Service. Signature-verified, idempotent by
 * MessageSid, always answers empty TwiML so Twilio neither retries nor
 * auto-replies. STOP/START keywords are recorded on the account before the
 * message is stored — the thread still shows the keyword itself so it's
 * obvious why sends are blocked.
 */
class TwilioInboundWebhookController extends Controller
{
    private const STOP_WORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];

    private const START_WORDS = ['START', 'YES', 'UNSTOP'];

    public function __invoke(Request $request, TwilioService $twilio): Response
    {
        if (! $twilio->validateSignature($request)) {
            return response('invalid signature', 401);
        }

        $sid = $request->input('MessageSid');

        if (filled($sid) && Message::where('provider_message_id', $sid)->exists()) {
            return $this->twiml(); // duplicate delivery
        }

        $from = Phone::toE164($request->input('From'));
        $account = $this->matchAccount($from);
        $body = trim((string) $request->input('Body'));

        if ($account !== null) {
            $keyword = strtoupper($body);

            if (in_array($keyword, self::STOP_WORDS, true)) {
                $account->forceFill(['sms_opted_out_at' => now()])->saveQuietly();
            } elseif (in_array($keyword, self::START_WORDS, true)) {
                $account->forceFill(['sms_opted_out_at' => null])->saveQuietly();
            }
        } else {
            Log::info('Inbound SMS did not match an account', ['from' => $request->input('From')]);
        }

        $media = (int) $request->input('NumMedia', 0);

        try {
            Message::create([
                'account_id' => $account?->id,
                'channel' => MessageChannel::Sms,
                'direction' => MessageDirection::In,
                'status' => MessageStatus::Received,
                'from_address' => $from ?? (string) $request->input('From'),
                'to_address' => (string) $request->input('To'),
                'body' => $body.($media > 0 ? "\n[+{$media} media attachment(s) — view in Twilio]" : ''),
                'provider_message_id' => $sid ?: null,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Concurrent duplicate delivery lost the race — already stored.
        }

        return $this->twiml();
    }

    /**
     * accounts.phone is free text, so matching normalizes both sides in PHP.
     * Duplicate phones across accounts resolve to the most recently updated.
     */
    private function matchAccount(?string $e164): ?Account
    {
        if ($e164 === null) {
            return null;
        }

        return Account::whereNotNull('phone')
            ->orderByDesc('updated_at')
            ->get(['id', 'phone', 'sms_opted_out_at', 'updated_at'])
            ->first(fn (Account $a) => Phone::toE164($a->phone) === $e164);
    }

    private function twiml(): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response/>', 200)
            ->header('Content-Type', 'text/xml');
    }
}
