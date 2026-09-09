<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Delivery-status callbacks for outbound SMS. Callbacks can arrive out of
 * order, so a status never regresses (delivered stays delivered).
 */
class TwilioStatusWebhookController extends Controller
{
    public function __invoke(Request $request, TwilioService $twilio): Response
    {
        if (! $twilio->validateSignature($request)) {
            return response('invalid signature', 401);
        }

        $message = Message::where('provider_message_id', $request->input('MessageSid'))->first();

        if ($message === null) {
            Log::info('Twilio status callback for unknown message', ['sid' => $request->input('MessageSid')]);

            return response('ok');
        }

        $status = match ($request->input('MessageStatus')) {
            'delivered' => MessageStatus::Delivered,
            'failed', 'undelivered' => MessageStatus::Failed,
            'sent' => $message->status === MessageStatus::Queued ? MessageStatus::Sent : null,
            default => null,
        };

        if ($status !== null && $message->status !== MessageStatus::Delivered) {
            $message->forceFill(array_filter([
                'status' => $status,
                'error' => $status === MessageStatus::Failed
                    ? trim('Twilio error '.$request->input('ErrorCode'))
                    : null,
            ]))->saveQuietly();
        }

        return response('ok');
    }
}
