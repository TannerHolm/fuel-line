<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only demo data so the board, timeline, and KPI dashboard render with
 * something real-looking. Never runs outside APP_ENV=local.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Account::count() > 0) {
            return; // don't stack demo data on real data
        }

        $tanner = User::where('email', 'tanner@freedomfuel.us')->first();
        $chris = User::where('email', 'chris@freedomfuel.us')->first();

        // --- A validated account: opening order, selling, reordered ---
        $ridgeline = Account::create([
            'name' => 'Ridgeline Supply Co.', 'city' => 'St. George', 'state' => 'UT',
            'retailer_type' => 'service', 'decision_maker' => 'Mark Jensen',
            'phone' => '435-555-0142', 'email' => 'mark@ridgelinesupply.com',
            'lead_source' => 'referral', 'acquisition_engine' => 'direct',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $tanner?->id,
        ]);
        $this->advance($ridgeline, [
            'contacted' => 49, 'sampled' => 46, 'interested' => 42, 'opening_order' => 38,
            'selling' => 33, 'reordered' => 8,
        ]);
        $ridgeline->samples()->create(['date' => now()->subDays(46), 'pucks_given' => 6, 'given_to' => 'decision_maker', 'user_id' => $tanner?->id]);
        $ridgeline->orders()->create([
            'type' => 'opening', 'date' => now()->subDays(38), 'quantity' => 100, 'tier' => 'growth',
            'unit_price' => 8.00, 'revenue' => 800.00, 'payment_status' => 'paid', 'fulfilled_at' => now()->subDays(33),
        ]);
        $ridgeline->checkIns()->create(['date' => now()->subDays(26), 'cadence' => '7', 'units_sold' => 14, 'units_on_hand' => 86, 'flavors_moving' => ['Original', 'Citrus'], 'staff_recommends' => true, 'source' => 'founder', 'user_id' => $tanner?->id]);
        $ridgeline->checkIns()->create(['date' => now()->subDays(19), 'cadence' => '14', 'units_sold' => 18, 'units_on_hand' => 68, 'flavors_moving' => ['Original', 'Citrus'], 'staff_recommends' => true, 'source' => 'founder', 'user_id' => $tanner?->id]);
        $ridgeline->checkIns()->create(['date' => now()->subDays(9), 'cadence' => '30', 'units_sold' => 31, 'units_on_hand' => 37, 'flavors_moving' => ['Citrus'], 'ready_for_reorder' => true, 'source' => 'founder', 'user_id' => $tanner?->id]);
        $ridgeline->orders()->create([
            'type' => 'reorder', 'date' => now()->subDays(8), 'quantity' => 100, 'tier' => 'growth',
            'unit_price' => 8.00, 'revenue' => 800.00, 'payment_status' => 'invoiced',
        ]);
        $ridgeline->update(['next_action' => 'Confirm reorder invoice paid', 'next_action_date' => now()->addDays(2)]);

        // --- Selling, check-in due ---
        $ironside = Account::create([
            'name' => 'Ironside Outfitters', 'city' => 'Cedar City', 'state' => 'UT',
            'retailer_type' => 'performance', 'decision_maker' => 'Dana Whitmore',
            'phone' => '435-555-0177', 'email' => 'dana@ironsideoutfitters.com',
            'lead_source' => 'cold_call', 'acquisition_engine' => 'direct',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $chris?->id,
        ]);
        $this->advance($ironside, ['contacted' => 30, 'sampled' => 27, 'interested' => 24, 'opening_order' => 20, 'selling' => 16]);
        $ironside->samples()->create(['date' => now()->subDays(27), 'pucks_given' => 4, 'given_to' => 'staff', 'user_id' => $chris?->id]);
        $ironside->orders()->create([
            'type' => 'opening', 'date' => now()->subDays(20), 'quantity' => 50, 'tier' => 'core',
            'unit_price' => 8.50, 'revenue' => 425.00, 'payment_status' => 'paid', 'fulfilled_at' => now()->subDays(16),
        ]);
        $ironside->checkIns()->create(['date' => now()->subDays(9), 'cadence' => '7', 'units_sold' => 8, 'units_on_hand' => 42, 'flavors_moving' => ['Original'], 'staff_recommends' => false, 'objections' => 'Staff keeps forgetting to mention it at the counter', 'source' => 'founder', 'user_id' => $chris?->id]);
        $ironside->update(['next_action' => 'Day-14 check-in call', 'next_action_date' => now()->subDays(2)]);

        // --- Opening order placed, not yet shipped ---
        $station12 = Account::create([
            'name' => 'Station 12 Provisions', 'city' => 'Hurricane', 'state' => 'UT',
            'retailer_type' => 'convenience', 'decision_maker' => 'Ray Ortega',
            'phone' => '435-555-0110', 'email' => 'ray@station12.co',
            'lead_source' => 'walk_in', 'acquisition_engine' => 'direct',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $tanner?->id,
        ]);
        $this->advance($station12, ['contacted' => 12, 'sampled' => 10, 'interested' => 6, 'opening_order' => 2]);
        $station12->samples()->create(['date' => now()->subDays(10), 'pucks_given' => 3, 'given_to' => 'decision_maker', 'user_id' => $tanner?->id]);
        $station12->orders()->create([
            'type' => 'opening', 'date' => now()->subDays(2), 'quantity' => 25, 'tier' => 'starter',
            'unit_price' => 9.00, 'revenue' => 225.00, 'payment_status' => 'invoiced',
        ]);
        $station12->update(['next_action' => 'Chase invoice, schedule delivery', 'next_action_date' => now()->addDays(1)]);

        // --- Interested, needs a push ---
        $ninthWard = Account::create([
            'name' => 'Ninth Ward Market', 'city' => 'Washington', 'state' => 'UT',
            'retailer_type' => 'convenience', 'decision_maker' => 'Lena Pham',
            'phone' => '435-555-0163', 'email' => 'lena@ninthwardmarket.com',
            'lead_source' => 'activation', 'acquisition_engine' => 'seeded',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $chris?->id,
        ]);
        $this->advance($ninthWard, ['contacted' => 15, 'sampled' => 13, 'interested' => 7]);
        $ninthWard->samples()->create(['date' => now()->subDays(13), 'pucks_given' => 5, 'given_to' => 'staff', 'user_id' => $chris?->id]);
        $ninthWard->update(['next_action' => 'Follow up on 250-unit quote request', 'next_action_date' => now()]);

        // --- Sampled, waiting ---
        $desertPeak = Account::create([
            'name' => 'Desert Peak Nutrition', 'city' => 'St. George', 'state' => 'UT',
            'retailer_type' => 'performance', 'decision_maker' => 'Cole Barrett',
            'phone' => '435-555-0195', 'email' => 'cole@desertpeaknutrition.com',
            'lead_source' => 'referral', 'acquisition_engine' => 'direct',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $tanner?->id,
        ]);
        $this->advance($desertPeak, ['contacted' => 8, 'sampled' => 5]);
        $desertPeak->samples()->create(['date' => now()->subDays(5), 'pucks_given' => 4, 'given_to' => 'decision_maker', 'user_id' => $tanner?->id]);
        $desertPeak->update(['next_action' => 'Sample feedback call', 'next_action_date' => now()->addDays(2)]);

        // --- Contacted ---
        $vetsHardware = Account::create([
            'name' => 'Veterans Hardware & Feed', 'city' => 'Kanab', 'state' => 'UT',
            'retailer_type' => 'service', 'decision_maker' => 'Sam Delgado',
            'phone' => '435-555-0121', 'email' => 'sam@vetshardware.com',
            'lead_source' => 'event', 'acquisition_engine' => 'seeded',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $chris?->id,
        ]);
        $this->advance($vetsHardware, ['contacted' => 3]);
        $vetsHardware->update(['next_action' => 'Drop off samples on Kanab run', 'next_action_date' => now()->addDays(4)]);

        // --- Fresh prospects ---
        Account::create([
            'name' => 'Zion Gateway Fuel', 'city' => 'Springdale', 'state' => 'UT',
            'retailer_type' => 'convenience', 'decision_maker' => 'TBD',
            'lead_source' => 'cold_call', 'acquisition_engine' => 'direct',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $tanner?->id,
            'next_action' => 'First visit', 'next_action_date' => now()->addDays(3),
        ]);
        Account::create([
            'name' => 'Overwatch Coffee Co.', 'city' => 'Mesquite', 'state' => 'NV',
            'retailer_type' => 'service', 'decision_maker' => 'Jess Weaver',
            'lead_source' => 'online', 'acquisition_engine' => 'seeded',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $chris?->id,
            'next_action' => 'Reply to inbound email', 'next_action_date' => now()->subDays(1),
        ]);

        // --- One lost, so the board shows the truth ---
        $lost = Account::create([
            'name' => 'Canyon Corner Store', 'city' => 'Page', 'state' => 'AZ',
            'retailer_type' => 'convenience', 'decision_maker' => 'Hank Foster',
            'lead_source' => 'cold_call', 'acquisition_engine' => 'direct',
            'pipeline_stage' => 'qualified_prospect', 'owner_id' => $tanner?->id,
        ]);
        $this->advance($lost, ['contacted' => 21, 'sampled' => 18]);
        $lost->update(['pipeline_stage' => 'lost', 'lost_reason' => 'Counter space committed to competing energy brand']);

        // Pin demo towns without hitting the live geocoder.
        $coords = [
            'St. George,UT' => [37.0965, -113.5684],
            'Cedar City,UT' => [37.6775, -113.0619],
            'Hurricane,UT' => [37.1753, -113.2899],
            'Washington,UT' => [37.1306, -113.5083],
            'Kanab,UT' => [37.0475, -112.5263],
            'Springdale,UT' => [37.1889, -112.9980],
            'Mesquite,NV' => [36.8055, -114.0672],
            'Page,AZ' => [36.9147, -111.4558],
        ];
        foreach (Account::all() as $account) {
            $key = $account->city.','.$account->state;
            if (isset($coords[$key])) {
                $account->forceFill(['latitude' => $coords[$key][0], 'longitude' => $coords[$key][1]])->saveQuietly();
            }
        }
    }

    /** Walk an account through stages with backdated transition timestamps. */
    private function advance(Account $account, array $stageDaysAgo): void
    {
        // Backdate the initial "entered pipeline" row to before the first stage move.
        $earliest = max($stageDaysAgo) + 2;
        $account->stageTransitions()->oldest('id')->first()?->update(['created_at' => now()->subDays($earliest)]);
        $account->update(['created_at' => now()->subDays($earliest)]);

        foreach ($stageDaysAgo as $stage => $daysAgo) {
            $account->update(['pipeline_stage' => $stage]);
            $account->stageTransitions()->latest('id')->first()?->update(['created_at' => now()->subDays($daysAgo)]);
        }
    }
}
