<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data — safe in every environment.
        $this->call(PricingTierSeeder::class);

        // Development logins only. Production founders are created with
        // `php artisan fuelline:make-founder`, which prompts for a real
        // password — a known default password must never reach a public site.
        if (app()->environment('local')) {
            User::updateOrCreate(
                ['email' => 'tanner@freedomfuel.us'],
                ['name' => 'Tanner Holm', 'password' => 'password', 'role' => 'founder']
            );
            User::updateOrCreate(
                ['email' => 'chris@freedomfuel.us'],
                ['name' => 'Chris Miller', 'password' => 'password', 'role' => 'founder']
            );

            $this->call(DemoDataSeeder::class);
        }
    }
}
