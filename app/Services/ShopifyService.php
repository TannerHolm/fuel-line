<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin client for the Shopify GraphQL Admin API (spec §9). Custom app, one
 * store, token auth — no OAuth dance. Every public method is safe to call
 * when Shopify isn't configured yet: pushes silently no-op, pulls throw.
 */
class ShopifyService
{
    public function configured(): bool
    {
        return filled($this->domain()) && filled($this->token());
    }

    /** Direct .env token wins; otherwise the OAuth token stored at /shopify/connect. */
    public function token(): ?string
    {
        return config('services.shopify.token') ?: \App\Models\AppSetting::get('shopify_admin_token');
    }

    /** The store that actually completed the install wins over the .env default. */
    public function domain(): ?string
    {
        return \App\Models\AppSetting::get('shopify_shop_domain') ?: config('services.shopify.domain');
    }

    /**
     * Pull wholesale customers and their orders, one page at a time.
     * $query uses Shopify search syntax, e.g. "tag:wholesale".
     *
     * @return array{customers: array, hasNextPage: bool, endCursor: ?string}
     */
    public function fetchCustomersPage(?string $query = null, ?string $cursor = null): array
    {
        $data = $this->graphql(<<<'GQL'
            query($cursor: String, $query: String) {
                customers(first: 25, after: $cursor, query: $query) {
                    pageInfo { hasNextPage endCursor }
                    nodes {
                        legacyResourceId
                        email
                        firstName
                        lastName
                        phone
                        tags
                        defaultAddress { company city provinceCode }
                        orders(first: 100) {
                            nodes {
                                legacyResourceId
                                name
                                createdAt
                                displayFinancialStatus
                                displayFulfillmentStatus
                                currentSubtotalPriceSet { shopMoney { amount } }
                                subtotalLineItemsQuantity
                                fulfillments { createdAt }
                                shippingAddress { company city provinceCode phone }
                            }
                        }
                    }
                }
            }
        GQL, ['cursor' => $cursor, 'query' => $query]);

        $connection = $data['customers'];

        return [
            'customers' => $connection['nodes'],
            'hasNextPage' => $connection['pageInfo']['hasNextPage'],
            'endCursor' => $connection['pageInfo']['endCursor'],
        ];
    }

    /**
     * Pull B2B Companies and their order history. When the store uses
     * Companies, this is the authoritative wholesale list — a company exists
     * the moment a partner is set up, before they have ordered anything.
     *
     * @return array{companies: array, hasNextPage: bool, endCursor: ?string}
     */
    public function fetchCompaniesPage(?string $cursor = null): array
    {
        $data = $this->graphql(<<<'GQL'
            query($cursor: String) {
                companies(first: 25, after: $cursor) {
                    pageInfo { hasNextPage endCursor }
                    nodes {
                        id
                        name
                        createdAt
                        note
                        mainContact { customer { legacyResourceId email firstName lastName phone } }
                        locations(first: 1) {
                            nodes {
                                phone
                                shippingAddress { city zoneCode phone }
                                billingAddress { city zoneCode phone }
                            }
                        }
                        orders(first: 100) {
                            nodes {
                                legacyResourceId
                                name
                                createdAt
                                displayFinancialStatus
                                displayFulfillmentStatus
                                currentSubtotalPriceSet { shopMoney { amount } }
                                subtotalLineItemsQuantity
                                fulfillments { createdAt }
                            }
                        }
                    }
                }
            }
        GQL, ['cursor' => $cursor]);

        return [
            'companies' => $data['companies']['nodes'],
            'hasNextPage' => $data['companies']['pageInfo']['hasNextPage'],
            'endCursor' => $data['companies']['pageInfo']['endCursor'],
        ];
    }

    /**
     * Create the Shopify customer for a new wholesale partner and store its id
     * on the account. Silent no-op when unconfigured; logs (never throws) on
     * failure so a Shopify hiccup can't break signup.
     */
    public function pushCustomer(Account $account): void
    {
        if (! $this->configured() || $account->shopify_customer_id !== null) {
            return;
        }

        try {
            [$first, $last] = $this->splitName($account->decision_maker ?? $account->name);

            $data = $this->graphql(<<<'GQL'
                mutation($input: CustomerInput!) {
                    customerCreate(input: $input) {
                        customer { legacyResourceId }
                        userErrors { field message }
                    }
                }
            GQL, ['input' => [
                'email' => $account->email,
                'firstName' => $first,
                'lastName' => $last,
                'phone' => $account->phone,
                'tags' => [config('services.shopify.wholesale_tag')],
                'note' => 'Created by Fuel Line (account #'.$account->id.')',
                'addresses' => [[
                    'company' => $account->name,
                    'city' => $account->city,
                    'provinceCode' => $account->state,
                    'countryCode' => 'US',
                ]],
            ]]);

            if (! empty($data['customerCreate']['userErrors'])) {
                Log::warning('Shopify customerCreate rejected', $data['customerCreate']['userErrors']);

                return;
            }

            $account->forceFill([
                'shopify_customer_id' => $data['customerCreate']['customer']['legacyResourceId'],
            ])->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning("Shopify customer push failed for account {$account->id}: {$e->getMessage()}");
        }
    }

    /**
     * Create the Draft Order behind a Fuel Line order (custom line item at the
     * tier price — wholesale pricing doesn't fit storefront checkout). Stores
     * the draft id; optionally emails Shopify's payable invoice.
     */
    public function pushDraftOrder(Order $order): void
    {
        if (! $this->configured() || $order->shopify_draft_order_id !== null) {
            return;
        }

        try {
            $account = $order->account;

            $input = [
                'lineItems' => [[
                    'title' => 'Freedom Fuel Puck — wholesale ('.($order->tier ?? 'custom').' tier)',
                    'originalUnitPrice' => (string) $order->unit_price,
                    'quantity' => $order->quantity,
                ]],
                'note' => 'Fuel Line order #'.$order->id.' ('.$order->type->value.') — '.$account->name,
                'tags' => [config('services.shopify.wholesale_tag'), 'fuel-line'],
            ];

            if ($account->shopify_customer_id !== null) {
                $input['purchasingEntity'] = ['customerId' => 'gid://shopify/Customer/'.$account->shopify_customer_id];
            } elseif ($account->email !== null) {
                $input['email'] = $account->email;
            }

            $data = $this->graphql(<<<'GQL'
                mutation($input: DraftOrderInput!) {
                    draftOrderCreate(input: $input) {
                        draftOrder { id legacyResourceId }
                        userErrors { field message }
                    }
                }
            GQL, ['input' => $input]);

            if (! empty($data['draftOrderCreate']['userErrors'])) {
                Log::warning('Shopify draftOrderCreate rejected', $data['draftOrderCreate']['userErrors']);

                return;
            }

            $draft = $data['draftOrderCreate']['draftOrder'];
            $order->forceFill(['shopify_draft_order_id' => $draft['legacyResourceId']])->saveQuietly();

            if (config('services.shopify.send_invoices')) {
                $this->graphql(<<<'GQL'
                    mutation($id: ID!) {
                        draftOrderInvoiceSend(id: $id) {
                            userErrors { field message }
                        }
                    }
                GQL, ['id' => $draft['id']]);

                $order->forceFill(['payment_status' => 'invoiced'])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning("Shopify draft-order push failed for order {$order->id}: {$e->getMessage()}");
        }
    }

    /** @return array decoded `data` payload */
    public function graphql(string $query, array $variables = []): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Shopify is not configured — set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN in .env.');
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token(),
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            sprintf('https://%s/admin/api/%s/graphql.json', $this->domain(), config('services.shopify.version')),
            // Cast to object: an empty PHP array encodes as [], which Shopify
            // rejects with "Invalid variables parameter".
            ['query' => $query, 'variables' => (object) $variables],
        );

        $json = $response->throw()->json();

        if (! empty($json['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: '.json_encode($json['errors']));
        }

        return $json['data'];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function splitName(?string $name): array
    {
        if (blank($name)) {
            return [null, null];
        }

        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
