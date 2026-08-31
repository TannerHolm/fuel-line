<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyWebhookRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $appUrl = 'https://wholesale.freedomfuel.us'): void
    {
        config([
            'app.url' => $appUrl,
            'services.shopify.domain' => 'test-store.myshopify.com',
            'services.shopify.token' => 'test-token',
        ]);
        \Illuminate\Support\Facades\URL::forceRootUrl($appUrl);
        \Illuminate\Support\Facades\URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME));
    }

    public function test_it_registers_missing_topics_and_repoints_stale_ones(): void
    {
        $this->configure();

        $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $body = $request->data();
            $calls[] = $body['query'];

            if (str_contains($body['query'], 'webhookSubscriptions(first')) {
                return Http::response(['data' => ['webhookSubscriptions' => ['nodes' => [
                    // Stale: points at the old local dev domain.
                    ['id' => 'gid://shopify/WebhookSubscription/1', 'topic' => 'ORDERS_PAID',
                        'endpoint' => ['callbackUrl' => 'https://fuel-line.test/webhooks/shopify']],
                ]]]]);
            }

            if (str_contains($body['query'], 'webhookSubscriptionUpdate')) {
                return Http::response(['data' => ['webhookSubscriptionUpdate' => ['userErrors' => []]]]);
            }

            return Http::response(['data' => ['webhookSubscriptionCreate' => ['userErrors' => []]]]);
        });

        $this->artisan('fuelline:shopify-webhooks')
            ->expectsOutputToContain('re-pointed')
            ->expectsOutputToContain('ORDERS_FULFILLED registered')
            ->assertSuccessful();

        $this->assertTrue(collect($calls)->contains(fn ($q) => str_contains($q, 'webhookSubscriptionUpdate')));
        $this->assertTrue(collect($calls)->contains(fn ($q) => str_contains($q, 'webhookSubscriptionCreate')));
    }

    public function test_it_leaves_correct_subscriptions_alone(): void
    {
        $this->configure();

        Http::fake(function ($request) {
            if (str_contains($request->data()['query'], 'webhookSubscriptions(first')) {
                return Http::response(['data' => ['webhookSubscriptions' => ['nodes' => [
                    ['id' => 'gid://1', 'topic' => 'ORDERS_PAID',
                        'endpoint' => ['callbackUrl' => 'https://wholesale.freedomfuel.us/webhooks/shopify']],
                    ['id' => 'gid://2', 'topic' => 'ORDERS_FULFILLED',
                        'endpoint' => ['callbackUrl' => 'https://wholesale.freedomfuel.us/webhooks/shopify']],
                ]]]]);
            }

            return Http::response(['data' => []]);
        });

        $this->artisan('fuelline:shopify-webhooks')
            ->expectsOutputToContain('already points at')
            ->assertSuccessful();

        Http::assertSentCount(1); // the query only — no mutations
    }

    public function test_it_refuses_a_non_https_callback(): void
    {
        $this->configure('http://fuel-line.test');
        Http::fake();

        $this->artisan('fuelline:shopify-webhooks')->assertFailed();
        Http::assertNothingSent();
    }
}
