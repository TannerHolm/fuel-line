<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PricingTier;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FuelLineFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['key' => 'starter', 'name' => 'Starter', 'min_qty' => 25, 'max_qty' => 49, 'unit_price' => 9.00, 'self_serve' => true, 'sort' => 1],
            ['key' => 'core', 'name' => 'Core', 'min_qty' => 50, 'max_qty' => 99, 'unit_price' => 8.50, 'self_serve' => true, 'sort' => 2],
            ['key' => 'growth', 'name' => 'Growth', 'min_qty' => 100, 'max_qty' => 249, 'unit_price' => 8.00, 'self_serve' => true, 'sort' => 3],
            ['key' => 'volume', 'name' => 'Volume / Strategic', 'min_qty' => 250, 'max_qty' => null, 'unit_price' => null, 'self_serve' => false, 'sort' => 4],
        ] as $tier) {
            PricingTier::create($tier);
        }
    }

    private function founder(): User
    {
        return User::factory()->create(['role' => 'founder']);
    }

    private function retailerWithAccount(): User
    {
        $account = Account::create([
            'name' => 'Test Depot', 'city' => 'Hurricane', 'state' => 'UT',
            'retailer_type' => 'convenience', 'lead_source' => 'online',
            'acquisition_engine' => 'seeded', 'signup_source' => 'self_service',
        ]);

        return User::factory()->create(['role' => 'retailer', 'account_id' => $account->id]);
    }

    public function test_self_service_registration_creates_retailer_login_and_pipeline_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Riley Vance',
            'email' => 'riley@example.com',
            'password' => 'pilot-order-2026',
            'password_confirmation' => 'pilot-order-2026',
            'business_name' => 'Bluff Street Nutrition',
            'city' => 'St. George',
            'state' => 'ut',
            'retailer_type' => 'performance',
            'phone' => '(435) 555-0188',
            'sms_consent' => true,
        ]);

        $response->assertRedirect('/portal');

        $account = Account::where('name', 'Bluff Street Nutrition')->firstOrFail();
        $this->assertSame('self_service', $account->signup_source->value);
        $this->assertSame('UT', $account->state);
        $this->assertNotNull($account->sms_consent_at);
        $this->assertSame('qualified_prospect', $account->pipeline_stage->value);
        $this->assertSame(1, $account->stageTransitions()->count());

        $user = User::where('email', 'riley@example.com')->firstOrFail();
        $this->assertSame('retailer', $user->role->value);
        $this->assertSame($account->id, $user->account_id);
    }

    public function test_pilot_order_cannot_submit_without_buyback_signature(): void
    {
        $user = $this->retailerWithAccount();

        $response = $this->actingAs($user)->post('/portal/order', ['quantity' => 50]);

        $response->assertSessionHasErrors(['signer_name', 'agree']);
        $this->assertSame(0, $user->account->orders()->count());
    }

    public function test_signed_pilot_order_creates_order_agreement_and_advances_pipeline(): void
    {
        $user = $this->retailerWithAccount();

        $response = $this->actingAs($user)->post('/portal/order', [
            'quantity' => 50,
            'signer_name' => 'Riley Vance',
            'signer_title' => 'Owner',
            'agree' => true,
        ]);

        $response->assertRedirect('/portal');

        $account = $user->account->fresh();
        $order = $account->orders()->firstOrFail();

        $this->assertSame('opening', $order->type->value);
        $this->assertSame('core', $order->tier);
        $this->assertSame(8.50, (float) $order->unit_price);
        $this->assertSame(425.00, (float) $order->revenue);
        $this->assertSame('opening_order', $account->pipeline_stage->value);
        $this->assertSame(1, $account->buybackAgreements()->count());

        $agreement = $account->buybackAgreements()->first();
        $this->assertSame('Riley Vance', $agreement->signer_name);
        $this->assertSame($order->id, $agreement->order_id);
        $this->assertStringStartsWith('v1-', $agreement->agreement_version);
    }

    public function test_reorder_skips_buyback_gate_once_agreement_on_file(): void
    {
        $user = $this->retailerWithAccount();

        $this->actingAs($user)->post('/portal/order', [
            'quantity' => 50, 'signer_name' => 'Riley Vance', 'agree' => true,
        ]);

        $response = $this->actingAs($user)->post('/portal/order', ['quantity' => 100]);
        $response->assertRedirect('/portal');

        $account = $user->account->fresh();
        $this->assertSame(2, $account->orders()->count());
        $this->assertSame(1, $account->orders()->where('type', 'reorder')->count());
        $this->assertSame(1, $account->buybackAgreements()->count());
        $this->assertSame('reordered', $account->pipeline_stage->value);
    }

    public function test_order_quantity_guardrails_are_enforced(): void
    {
        $user = $this->retailerWithAccount();

        $this->actingAs($user)->post('/portal/order', [
            'quantity' => 20, 'signer_name' => 'R', 'agree' => true,
        ])->assertSessionHasErrors('quantity');

        $this->actingAs($user)->post('/portal/order', [
            'quantity' => 27, 'signer_name' => 'R', 'agree' => true,
        ])->assertSessionHasErrors('quantity');

        $this->actingAs($user)->post('/portal/order', [
            'quantity' => 250, 'signer_name' => 'R', 'agree' => true,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, $user->account->orders()->count());
    }

    public function test_retailer_self_report_lands_in_shared_timeline_tagged_by_source(): void
    {
        $user = $this->retailerWithAccount();

        $response = $this->actingAs($user)->post('/portal/report', [
            'units_sold' => 12,
            'units_on_hand' => 38,
            'flavors_moving' => 'Original, Citrus',
            'ready_for_reorder' => true,
        ]);

        $response->assertRedirect();

        $checkIn = $user->account->checkIns()->firstOrFail();
        $this->assertSame('retailer', $checkIn->source->value);
        $this->assertSame(12, $checkIn->units_sold);
        $this->assertSame(['Original', 'Citrus'], $checkIn->flavors_moving);

        $this->assertSame('Retailer requested reorder', $user->account->fresh()->next_action);
    }

    public function test_retailers_cannot_reach_founder_screens(): void
    {
        $user = $this->retailerWithAccount();

        $this->actingAs($user)->get('/pipeline')->assertForbidden();
        $this->actingAs($user)->get('/accounts')->assertForbidden();
        $this->actingAs($user)->get('/kpis')->assertForbidden();
    }

    public function test_founders_can_reach_all_screens(): void
    {
        $user = $this->founder();

        $this->actingAs($user)->get('/pipeline')->assertOk();
        $this->actingAs($user)->get('/accounts')->assertOk();
        $this->actingAs($user)->get('/kpis')->assertOk();
        $this->actingAs($user)->get('/field')->assertOk();
    }

    public function test_stage_changes_are_logged_automatically_with_history(): void
    {
        $account = Account::create(['name' => 'History Test']);

        $account->update(['pipeline_stage' => 'contacted']);
        $account->update(['pipeline_stage' => 'sampled']);

        $stages = $account->stageTransitions()->orderBy('id')->pluck('to_stage')
            ->map(fn ($s) => $s->value)->all();
        $this->assertSame(['qualified_prospect', 'contacted', 'sampled'], $stages);
    }

    public function test_investor_scorecard_requires_valid_signature(): void
    {
        $this->get('/scorecard')->assertForbidden();

        $url = URL::temporarySignedRoute('scorecard', now()->addDays(7));
        $this->get($url)->assertOk();
    }

    public function test_founders_can_generate_investor_link(): void
    {
        $response = $this->actingAs($this->founder())->post('/kpis/investor-link');

        $response->assertRedirect();
        $this->assertStringContainsString('/scorecard?', session('success'));
    }

    public function test_kpi_engine_computes_metrics_from_events(): void
    {
        $account = Account::create(['name' => 'KPI Test', 'retailer_type' => 'service']);
        $account->update(['pipeline_stage' => 'contacted']);
        $account->orders()->create([
            'type' => 'opening', 'date' => now()->subDays(40), 'quantity' => 100,
            'tier' => 'growth', 'unit_price' => 8.00, 'revenue' => 800.00,
            'payment_status' => 'paid', 'fulfilled_at' => now()->subDays(35),
        ]);
        $account->checkIns()->create(['date' => now()->subDays(10), 'cadence' => '30', 'units_sold' => 70]);
        $account->orders()->create([
            'type' => 'reorder', 'date' => now()->subDays(5), 'quantity' => 100,
            'tier' => 'growth', 'unit_price' => 8.00, 'revenue' => 800.00,
        ]);

        $kpis = app(KpiService::class)->compute();

        $this->assertSame(1, $kpis['qualified_conversations']);
        $this->assertSame(100.0, $kpis['opening_conversion_pct']);
        $this->assertSame(800.0, $kpis['avg_opening_order']);
        $this->assertSame(35.0, $kpis['avg_days_to_reorder']);
        $this->assertSame(100.0, $kpis['reorder_rate_pct']);
        $this->assertSame(14.0, $kpis['units_per_store_week']);
    }
}
