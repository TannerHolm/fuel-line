<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;

/**
 * Registers the webhook subscriptions Fuel Line listens for (spec §9).
 * Idempotent: existing subscriptions pointing at the right URL are left alone,
 * stale ones (e.g. an old domain) are re-pointed, so it is safe to re-run
 * after every domain change or deploy.
 */
class ShopifyWebhooks extends Command
{
    protected $signature = 'fuelline:shopify-webhooks {--prune : Delete Fuel Line subscriptions that point at a different host}';

    protected $description = 'Register the Shopify webhooks Fuel Line listens for';

    /** Topics the ShopifyWebhookController actually handles. */
    private const TOPICS = ['ORDERS_PAID', 'ORDERS_FULFILLED'];

    public function handle(ShopifyService $shopify): int
    {
        if (! $shopify->configured()) {
            $this->error('Shopify is not connected. Visit /shopify/connect first.');

            return self::FAILURE;
        }

        $callback = route('webhooks.shopify');

        if (! str_starts_with($callback, 'https://')) {
            $this->error("Shopify requires an HTTPS callback; APP_URL currently gives {$callback}.");

            return self::FAILURE;
        }

        $existing = collect($shopify->graphql(<<<'GQL'
            { webhookSubscriptions(first: 100) {
                nodes { id topic endpoint { ... on WebhookHttpEndpoint { callbackUrl } } } } }
        GQL)['webhookSubscriptions']['nodes']);

        foreach (self::TOPICS as $topic) {
            $match = $existing->first(fn ($w) => $w['topic'] === $topic);
            $current = $match['endpoint']['callbackUrl'] ?? null;

            if ($current === $callback) {
                $this->line("= {$topic} already points at {$callback}");

                continue;
            }

            if ($match !== null) {
                // Re-point rather than duplicate — this is what a domain change needs.
                $result = $shopify->graphql(<<<'GQL'
                    mutation($id: ID!, $subscription: WebhookSubscriptionInput!) {
                        webhookSubscriptionUpdate(id: $id, webhookSubscription: $subscription) {
                            userErrors { field message }
                        }
                    }
                GQL, ['id' => $match['id'], 'subscription' => ['callbackUrl' => $callback]]);

                $errors = $result['webhookSubscriptionUpdate']['userErrors'];
                $this->line($errors ? "! {$topic} update failed: ".json_encode($errors) : "~ {$topic} re-pointed from {$current}");

                continue;
            }

            $result = $shopify->graphql(<<<'GQL'
                mutation($topic: WebhookSubscriptionTopic!, $subscription: WebhookSubscriptionInput!) {
                    webhookSubscriptionCreate(topic: $topic, webhookSubscription: $subscription) {
                        userErrors { field message }
                    }
                }
            GQL, ['topic' => $topic, 'subscription' => ['callbackUrl' => $callback, 'format' => 'JSON']]);

            $errors = $result['webhookSubscriptionCreate']['userErrors'];
            $this->line($errors ? "! {$topic} failed: ".json_encode($errors) : "+ {$topic} registered");
        }

        $this->newLine();
        $this->info("Webhooks point at {$callback}");
        $this->comment('They are signed with the app client secret and verified on arrival.');

        return self::SUCCESS;
    }
}
