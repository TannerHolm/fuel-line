<?php

namespace Database\Seeders;

use App\Models\PricingTier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Pricing tiers — the 90-Day Plan (Section 6) is the source of truth.
        $tiers = [
            ['key' => 'starter', 'name' => 'Starter', 'min_qty' => 25, 'max_qty' => 49, 'unit_price' => 9.00, 'self_serve' => true, 'sort' => 1],
            ['key' => 'core', 'name' => 'Core', 'min_qty' => 50, 'max_qty' => 99, 'unit_price' => 8.50, 'self_serve' => true, 'sort' => 2],
            ['key' => 'growth', 'name' => 'Growth', 'min_qty' => 100, 'max_qty' => 249, 'unit_price' => 8.00, 'self_serve' => true, 'sort' => 3],
            ['key' => 'volume', 'name' => 'Volume / Strategic', 'min_qty' => 250, 'max_qty' => null, 'unit_price' => null, 'self_serve' => false, 'sort' => 4],
        ];

        foreach ($tiers as $tier) {
            PricingTier::updateOrCreate(['key' => $tier['key']], $tier);
        }

        // Founders.
        User::updateOrCreate(
            ['email' => 'tanner@freedomfuel.us'],
            ['name' => 'Tanner Holm', 'password' => 'password', 'role' => 'founder']
        );
        User::updateOrCreate(
            ['email' => 'chris@freedomfuel.us'],
            ['name' => 'Chris Miller', 'password' => 'password', 'role' => 'founder']
        );

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
