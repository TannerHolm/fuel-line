<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PricingTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyTest extends TestCase
{
    use RefreshDatabase;

    private function configureShopify(): void
    {
        config([
            'services.shopify.domain' => 'test-store.myshopify.com',
            'services.shopify.token' => 'test-token',
            'services.shopify.webhook_secret' => 'test-secret',
        ]);
    }

    private function signedWebhook(array $payload, string $topic, ?string $secret = 'test-secret')
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/shopify', [], [], [], [
            'HTTP_X-Shopify-Topic' => $topic,
            'HTTP_X-Shopify-Hmac-Sha256' => base64_encode(hash_hmac('sha256', $body, $secret ?? '', true)),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_webhooks_reject_bad_or_unsigned_requests(): void
    {
        $this->configureShopify();

        $this->call('POST', '/webhooks/shopify', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')
            ->assertStatus(401);

        $this->signedWebhook(['id' => 1], 'orders/paid', 'wrong-secret')->assertStatus(401);

        // No secret configured at all → nothing is accepted.
        config(['services.shopify.webhook_secret' => null]);
        $this->signedWebhook(['id' => 1], 'orders/paid', 'test-secret')->assertStatus(401);
    }

    public function test_paid_and_fulfilled_webhooks_update_the_order_and_pipeline(): void
    {
        $this->configureShopify();

        $account = Account::create(['name' => 'Webhook Depot', 'pipeline_stage' => 'opening_order']);
        $order = $account->orders()->create([
            'type' => 'opening', 'date' => now()->toDateString(), 'quantity' => 50,
            'unit_price' => 8.50, 'revenue' => 425.00, 'payment_status' => 'invoiced',
            'shopify_order_id' => '9001',
        ]);

        $this->signedWebhook(['id' => 9001], 'orders/paid')->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status->value);

        $this->signedWebhook(['id' => 9001], 'orders/fulfilled')->assertOk();

        $order = $order->fresh();
        $this->assertNotNull($order->fulfilled_at);
        $this->assertSame('selling', $account->fresh()->pipeline_stage->value);

        // Unknown order: acknowledged (so Shopify stops retrying), nothing changed.
        $this->signedWebhook(['id' => 424242], 'orders/paid')->assertOk();
    }

    public function test_customers_fallback_import_creates_accounts_and_orders_and_infers_stage(): void
    {
        $this->configureShopify();

        PricingTier::create(['key' => 'core', 'name' => 'Core', 'min_qty' => 50, 'max_qty' => 99, 'unit_price' => 8.50, 'self_serve' => true, 'sort' => 2]);

        // An existing account that should link by email, not duplicate.
        Account::create(['name' => 'Ridgeline Supply Co.', 'email' => 'mark@ridgelinesupply.com']);

        Http::fake([
            'test-store.myshopify.com/*' => Http::response(['data' => ['customers' => [
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                'nodes' => [
                    [
                        'legacyResourceId' => '111',
                        'email' => 'MARK@ridgelinesupply.com',
                        'firstName' => 'Mark', 'lastName' => 'Jensen', 'phone' => null,
                        'tags' => ['wholesale'],
                        'defaultAddress' => ['company' => 'Ridgeline Supply Co.', 'city' => 'St. George', 'provinceCode' => 'UT'],
                        'orders' => ['nodes' => [
                            [
                                'legacyResourceId' => '5001', 'name' => '#1042',
                                'createdAt' => '2026-07-01T10:00:00Z',
                                'displayFinancialStatus' => 'PAID', 'displayFulfillmentStatus' => 'FULFILLED',
                                'currentSubtotalPriceSet' => ['shopMoney' => ['amount' => '425.00']],
                                'subtotalLineItemsQuantity' => 50,
                                'fulfillments' => [['createdAt' => '2026-07-03T10:00:00Z']],
                            ],
                            [
                                'legacyResourceId' => '5002', 'name' => '#1088',
                                'createdAt' => '2026-08-01T10:00:00Z',
                                'displayFinancialStatus' => 'PAID', 'displayFulfillmentStatus' => 'FULFILLED',
                                'currentSubtotalPriceSet' => ['shopMoney' => ['amount' => '425.00']],
                                'subtotalLineItemsQuantity' => 50,
                                'fulfillments' => [['createdAt' => '2026-08-02T10:00:00Z']],
                            ],
                        ]],
                    ],
                    [
                        'legacyResourceId' => '222',
                        'email' => 'new@trailhead.example',
                        'firstName' => 'Jo', 'lastName' => 'Kim', 'phone' => null,
                        'tags' => ['wholesale'],
                        'defaultAddress' => ['company' => 'Trailhead General', 'city' => 'Moab', 'provinceCode' => 'UT'],
                        'orders' => ['nodes' => []],
                    ],
                ],
            ]]]),
        ]);

        $this->artisan('fuelline:shopify-import --customers --all')->assertSuccessful();

        // Existing account linked, not duplicated; history imported; stage inferred.
        $ridgeline = Account::where('email', 'mark@ridgelinesupply.com')->firstOrFail();
        $this->assertSame(1, Account::whereRaw("lower(name) = 'ridgeline supply co.'")->count());
        $this->assertSame('111', $ridgeline->shopify_customer_id);
        $this->assertSame(2, $ridgeline->orders()->count());
        $this->assertSame('opening', $ridgeline->orders()->orderBy('date')->first()->type->value);
        $this->assertSame('reorder', $ridgeline->orders()->orderByDesc('date')->first()->type->value);
        $this->assertSame('core', $ridgeline->orders()->first()->tier);
        $this->assertSame('reordered', $ridgeline->pipeline_stage->value);

        // New customer became a new account with no orders, stage untouched.
        $trailhead = Account::where('name', 'Trailhead General')->firstOrFail();
        $this->assertSame('222', $trailhead->shopify_customer_id);
        $this->assertSame('qualified_prospect', $trailhead->pipeline_stage->value);

        // Re-running changes nothing (idempotent).
        $this->artisan('fuelline:shopify-import --customers --all')->assertSuccessful();
        $this->assertSame(2, $ridgeline->orders()->count());
        $this->assertSame(2, Account::whereNotNull('shopify_customer_id')->count());
    }

    public function test_company_without_an_address_falls_back_to_its_order_address(): void
    {
        $this->configureShopify();

        Http::fake([
            'test-store.myshopify.com/*' => Http::response(['data' => ['companies' => [
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                'nodes' => [[
                    'id' => 'gid://shopify/Company/900',
                    'name' => 'MVP Distributing',
                    'createdAt' => '2026-07-18T00:00:00Z',
                    'note' => null,
                    'mainContact' => ['customer' => ['legacyResourceId' => '77', 'email' => 'conner@mvp.example',
                        'firstName' => 'Connor', 'lastName' => 'Bates', 'phone' => null]],
                    // Company profile left blank, as wholesale buyers often do.
                    'locations' => ['nodes' => [['phone' => null, 'shippingAddress' => null, 'billingAddress' => null]]],
                    'orders' => ['nodes' => [[
                        'legacyResourceId' => '5555', 'name' => '#1012',
                        'createdAt' => '2026-07-22T18:37:31Z',
                        'displayFinancialStatus' => 'PAID', 'displayFulfillmentStatus' => 'FULFILLED',
                        'currentSubtotalPriceSet' => ['shopMoney' => ['amount' => '250.00']],
                        'subtotalLineItemsQuantity' => 35,
                        'fulfillments' => [['createdAt' => '2026-07-23T00:00:00Z']],
                        'shippingAddress' => ['city' => 'Meridian', 'provinceCode' => 'ID', 'phone' => '208-555-0100'],
                        'billingAddress' => null,
                    ]]],
                ]],
            ]]]),
        ]);

        $this->artisan('fuelline:shopify-import')->assertSuccessful();

        $account = Account::where('shopify_company_id', '900')->firstOrFail();
        $this->assertSame('Meridian', $account->city);
        $this->assertSame('ID', $account->state);
        $this->assertSame('208-555-0100', $account->phone);
    }

    public function test_oauth_callback_verifies_state_and_hmac_then_stores_the_token(): void
    {
        config([
            'services.shopify.domain' => 'test-store.myshopify.com',
            'services.shopify.client_id' => 'client-id',
            'services.shopify.client_secret' => 'client-secret',
            'services.shopify.token' => null,
        ]);

        $founder = \App\Models\User::factory()->create(['role' => 'founder']);

        // Connect redirects to Shopify's authorize screen and seeds state.
        $this->actingAs($founder)->get('/shopify/connect')
            ->assertRedirectContains('test-store.myshopify.com/admin/oauth/authorize');
        $state = session('shopify_oauth_state');
        $this->assertNotEmpty($state);

        // Wrong state is refused.
        $this->actingAs($founder)->withSession(['shopify_oauth_state' => $state, 'shopify_oauth_shop' => 'test-store.myshopify.com'])
            ->get('/shopify/callback?'.http_build_query(['state' => 'wrong', 'shop' => 'test-store.myshopify.com', 'code' => 'c']))
            ->assertForbidden();

        Http::fake(['test-store.myshopify.com/admin/oauth/access_token' => Http::response(['access_token' => 'shpat_realtoken'])]);

        $params = ['code' => 'auth-code', 'shop' => 'test-store.myshopify.com', 'state' => $state, 'timestamp' => '123'];
        ksort($params);
        $params['hmac'] = hash_hmac('sha256', http_build_query($params), 'client-secret');

        $this->actingAs($founder)->withSession(['shopify_oauth_state' => $state, 'shopify_oauth_shop' => 'test-store.myshopify.com'])
            ->get('/shopify/callback?'.http_build_query($params))
            ->assertRedirect('/kpis');

        $this->assertSame('shpat_realtoken', \App\Models\AppSetting::get('shopify_admin_token'));
        $this->assertTrue(app(\App\Services\ShopifyService::class)->configured());
    }

    public function test_shopify_initiated_install_starts_oauth_for_the_verified_shop(): void
    {
        config([
            'services.shopify.domain' => null,          // no .env default at all
            'services.shopify.client_id' => 'client-id',
            'services.shopify.client_secret' => 'client-secret',
            'services.shopify.token' => null,
        ]);

        $founder = \App\Models\User::factory()->create(['role' => 'founder']);

        $params = ['shop' => 'real-store.myshopify.com', 'timestamp' => '123'];
        ksort($params);
        $params['hmac'] = hash_hmac('sha256', http_build_query($params), 'client-secret');

        // Shopify-initiated install (no prior state) is accepted on a valid HMAC.
        $this->actingAs($founder)->get('/shopify/connect?'.http_build_query($params))
            ->assertRedirectContains('real-store.myshopify.com/admin/oauth/authorize');

        // A forged Shopify-initiated request (bad HMAC) is refused.
        $this->actingAs($founder)
            ->get('/shopify/connect?'.http_build_query(['shop' => 'attacker.myshopify.com', 'hmac' => 'bogus']))
            ->assertForbidden();

        // An authenticated founder may pin the shop explicitly (no HMAC needed).
        $this->actingAs($founder)->get('/shopify/connect?shop=freedom-fuel-21612.myshopify.com')
            ->assertRedirectContains('freedom-fuel-21612.myshopify.com/admin/oauth/authorize');

        // …but not to a non-Shopify host.
        $this->actingAs($founder)->get('/shopify/connect?shop=evil.example.com')->assertForbidden();
    }

    public function test_connected_shop_domain_overrides_the_env_default(): void
    {
        config([
            'services.shopify.domain' => 'stale-guess.myshopify.com',
            'services.shopify.token' => null,
        ]);

        \App\Models\AppSetting::put('shopify_admin_token', 'shpat_x');
        \App\Models\AppSetting::put('shopify_shop_domain', 'real-store.myshopify.com');

        $service = app(\App\Services\ShopifyService::class);
        $this->assertSame('real-store.myshopify.com', $service->domain());
        $this->assertTrue($service->configured());
    }

    public function test_shopify_connect_is_founder_only(): void
    {
        $account = Account::create(['name' => 'Depot']);
        $retailer = \App\Models\User::factory()->create(['role' => 'retailer', 'account_id' => $account->id]);

        $this->actingAs($retailer)->get('/shopify/connect')->assertForbidden();
    }

    public function test_company_import_creates_partners_links_existing_and_honours_exclusions(): void
    {
        $this->configureShopify();

        // An account previously imported via the customers path, under a
        // domain-derived name — the company name should win when relinked.
        Account::create(['name' => 'Fasteddys', 'email' => 'zach@fasteddys.com', 'shopify_customer_id' => '555']);

        Http::fake(['test-store.myshopify.com/*' => Http::response(['data' => ['companies' => [
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            'nodes' => [
                [
                    'id' => 'gid://shopify/Company/17586094245',
                    'name' => "Fast Eddy's",
                    'createdAt' => '2026-08-12T00:00:00Z',
                    'note' => null,
                    'mainContact' => ['customer' => ['legacyResourceId' => '555', 'email' => 'zach@fasteddys.com', 'firstName' => 'Zach', 'lastName' => '', 'phone' => null]],
                    'locations' => ['nodes' => [['phone' => '+12088660467', 'shippingAddress' => ['city' => 'Meridian', 'zoneCode' => 'ID', 'phone' => null], 'billingAddress' => null]]],
                    'orders' => ['nodes' => [[
                        'legacyResourceId' => '7015', 'name' => '#1015', 'createdAt' => '2026-08-17T00:00:00Z',
                        'displayFinancialStatus' => 'PAID', 'displayFulfillmentStatus' => 'FULFILLED',
                        'currentSubtotalPriceSet' => ['shopMoney' => ['amount' => '500.00']],
                        'subtotalLineItemsQuantity' => 50, 'fulfillments' => [['createdAt' => '2026-08-21T00:00:00Z']],
                    ]]],
                ],
                [
                    // A partner set up today with no orders yet — still belongs on the board.
                    'id' => 'gid://shopify/Company/18721833125',
                    'name' => 'Foothill Market',
                    'createdAt' => '2026-08-31T00:00:00Z',
                    'note' => null,
                    'mainContact' => ['customer' => ['legacyResourceId' => '777', 'email' => 'sam@foothillmarket.com', 'firstName' => 'Sam', 'lastName' => 'B', 'phone' => null]],
                    'locations' => ['nodes' => [['phone' => null, 'shippingAddress' => ['city' => 'Logan', 'zoneCode' => 'UT', 'phone' => null], 'billingAddress' => null]]],
                    'orders' => ['nodes' => []],
                ],
                [
                    'id' => 'gid://shopify/Company/999',
                    'name' => 'Testing',
                    'createdAt' => '2026-07-18T00:00:00Z',
                    'note' => null,
                    'mainContact' => ['customer' => ['legacyResourceId' => '888', 'email' => 'holm.tanner@gmail.com', 'firstName' => 'T', 'lastName' => 'H', 'phone' => null]],
                    'locations' => ['nodes' => []],
                    'orders' => ['nodes' => []],
                ],
            ],
        ]]])]);

        $this->artisan('fuelline:shopify-import --exclude=Testing')->assertSuccessful();

        // Relinked, not duplicated — and Shopify's company name wins.
        $this->assertNull(Account::where('name', 'Fasteddys')->first());
        $eddys = Account::where('shopify_company_id', '17586094245')->firstOrFail();
        $this->assertSame("Fast Eddy's", $eddys->name);
        $this->assertSame('Meridian', $eddys->city);
        $this->assertSame('ID', $eddys->state);
        $this->assertSame('selling', $eddys->pipeline_stage->value);
        $this->assertSame(1, $eddys->orders()->count());

        // Order-less company still lands as a prospect.
        $foothill = Account::where('name', 'Foothill Market')->firstOrFail();
        $this->assertSame('Logan', $foothill->city);
        $this->assertSame('qualified_prospect', $foothill->pipeline_stage->value);

        $this->assertNull(Account::where('name', 'Testing')->first());
        $this->assertSame(2, Account::count());

        // Re-running changes nothing.
        $this->artisan('fuelline:shopify-import --exclude=Testing')->assertSuccessful();
        $this->assertSame(2, Account::count());
        $this->assertSame(1, $eddys->fresh()->orders()->count());
    }

    public function test_pushes_are_silent_noops_when_shopify_is_not_configured(): void
    {
        Http::fake();

        $account = Account::create(['name' => 'Unconfigured Depot', 'email' => 'x@example.com']);
        app(\App\Services\ShopifyService::class)->pushCustomer($account);

        Http::assertNothingSent();
        $this->assertNull($account->fresh()->shopify_customer_id);
    }
}
