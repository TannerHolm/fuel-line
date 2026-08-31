<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Shopify → Fuel Line (spec §9): orders/paid flips payment status; orders/fulfilled
 * sets the go-live date that starts the units/store/week and days-to-reorder clocks.
 * HMAC-verified, idempotent (a retried webhook just re-sets the same state), and
 * always fast-200s so Shopify's retry logic never piles up.
 */
class ShopifyWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->signatureValid($request)) {
            return response('invalid signature', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        $payload = $request->json()->all();

        // Draft orders become real orders on payment — match either id.
        $order = Order::where('shopify_order_id', (string) ($payload['id'] ?? ''))
            ->orWhere(function ($q) use ($payload) {
                $draftId = $payload['source_identifier'] ?? null;
                if ($draftId) {
                    $q->where('shopify_draft_order_id', (string) $draftId);
                }
            })
            ->first();

        if ($order === null) {
            Log::info("Shopify webhook {$topic}: no matching Fuel Line order for Shopify order ".($payload['id'] ?? '?'));

            return response('ok');
        }

        if ($order->shopify_order_id === null && isset($payload['id'])) {
            $order->forceFill(['shopify_order_id' => (string) $payload['id']])->saveQuietly();
        }

        match ($topic) {
            'orders/paid' => $order->forceFill(['payment_status' => 'paid'])->saveQuietly(),
            'orders/fulfilled' => $this->markFulfilled($order),
            default => Log::info("Shopify webhook ignored topic {$topic}"),
        };

        return response('ok');
    }

    private function markFulfilled(Order $order): void
    {
        $order->forceFill(['fulfilled_at' => $order->fulfilled_at ?? now()->toDateString()])->saveQuietly();

        $account = $order->account;
        if ($order->type->value === 'opening' && $account->pipeline_stage === PipelineStage::OpeningOrder) {
            $account->transitionNote = 'Opening order fulfilled (Shopify webhook)';
            $account->update(['pipeline_stage' => PipelineStage::Selling]);
        }
    }

    private function signatureValid(Request $request): bool
    {
        // Dev-dashboard apps sign webhooks with the client secret.
        $secret = config('services.shopify.webhook_secret') ?: config('services.shopify.client_secret');

        if (blank($secret)) {
            return false; // never accept unsigned webhooks
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, (string) $request->header('X-Shopify-Hmac-Sha256'));
    }
}
