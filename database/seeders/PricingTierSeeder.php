<?php

namespace Database\Seeders;

use App\Models\PricingTier;
use Illuminate\Database\Seeder;

/**
 * Pricing tiers from the 90-Day Plan (Section 6). Safe to run anywhere —
 * it's reference data the landing page, order flow, and Shopify sync all read.
 */
class PricingTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['key' => 'starter', 'name' => 'Starter', 'min_qty' => 25, 'max_qty' => 49, 'unit_price' => 9.00, 'self_serve' => true, 'sort' => 1],
            ['key' => 'core', 'name' => 'Core', 'min_qty' => 50, 'max_qty' => 99, 'unit_price' => 8.50, 'self_serve' => true, 'sort' => 2],
            ['key' => 'growth', 'name' => 'Growth', 'min_qty' => 100, 'max_qty' => 249, 'unit_price' => 8.00, 'self_serve' => true, 'sort' => 3],
            ['key' => 'volume', 'name' => 'Volume / Strategic', 'min_qty' => 250, 'max_qty' => null, 'unit_price' => null, 'self_serve' => false, 'sort' => 4],
        ];

        foreach ($tiers as $tier) {
            PricingTier::updateOrCreate(['key' => $tier['key']], $tier);
        }
    }
}
