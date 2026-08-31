<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * City/state-level geocoding via OpenStreetMap's Nominatim. No API key; results
 * are cached forever per "city, state" so each place is looked up exactly once.
 * Nominatim's usage policy asks for an identifying User-Agent and ≤1 req/sec —
 * fine at Fuel Line's account volume.
 */
class GeocodingService
{
    /** @return array{lat: float, lng: float}|null */
    public function geocodeCity(?string $city, ?string $state): ?array
    {
        if (blank($city) || blank($state)) {
            return null;
        }

        $key = 'geocode:'.mb_strtolower(trim($city)).','.mb_strtolower(trim($state));

        return Cache::rememberForever($key, function () use ($city, $state) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'FuelLine/1.0 (Freedom Fuel wholesale ops)'])
                    ->timeout(10)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'city' => $city,
                        'state' => $state,
                        'country' => 'USA',
                        'format' => 'jsonv2',
                        'limit' => 1,
                    ]);

                $hit = $response->json()[0] ?? null;

                if ($hit === null) {
                    return null;
                }

                return ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon']];
            } catch (\Throwable $e) {
                Log::warning("Geocoding failed for {$city}, {$state}: {$e->getMessage()}");

                return null; // cached as null; re-run fuelline:geocode --retry to try again
            }
        });
    }
}
