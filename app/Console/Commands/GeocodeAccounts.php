<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeAccounts extends Command
{
    protected $signature = 'fuelline:geocode {--all : Re-geocode every account, not just the unlocated ones}';

    protected $description = 'Fill in coordinates for accounts from their city/state';

    public function handle(GeocodingService $geocoder): int
    {
        $accounts = Account::query()
            ->whereNotNull('city')
            ->whereNotNull('state')
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('latitude'))
            ->get();

        foreach ($accounts as $account) {
            $result = $geocoder->geocodeCity($account->city, $account->state);

            if ($result !== null) {
                // saveQuietly: coordinates are derived data, not a stage event.
                $account->forceFill(['latitude' => $result['lat'], 'longitude' => $result['lng']])->saveQuietly();
                $this->info("{$account->name} → {$result['lat']}, {$result['lng']}");
            } else {
                $this->warn("{$account->name}: no result for {$account->city}, {$account->state}");
            }

            usleep(1100000); // Nominatim courtesy: ≤1 request/second
        }

        return self::SUCCESS;
    }
}
