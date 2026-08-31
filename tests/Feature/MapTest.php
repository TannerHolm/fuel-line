<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_shows_only_located_accounts_and_counts_the_rest(): void
    {
        $founder = User::factory()->create(['role' => 'founder']);

        Account::create(['name' => 'Pinned', 'city' => 'Hurricane', 'state' => 'UT', 'latitude' => 37.1753, 'longitude' => -113.2899]);
        Account::create(['name' => 'Unpinned', 'city' => 'Moab', 'state' => 'UT']);

        $response = $this->actingAs($founder)->get('/map');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Map/Index')
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Pinned')
                ->where('accounts.0.lat', 37.1753)
                ->where('unlocatedCount', 1)
            );
    }

    public function test_map_is_founder_only(): void
    {
        $account = Account::create(['name' => 'Depot']);
        $retailer = User::factory()->create(['role' => 'retailer', 'account_id' => $account->id]);

        $this->get('/map')->assertRedirect('/login');
        $this->actingAs($retailer)->get('/map')->assertForbidden();
    }

    public function test_geocoding_service_resolves_and_caches_city_lookups(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '37.1041944', 'lon' => '-113.5841313'],
            ]),
        ]);

        $service = app(GeocodingService::class);

        $first = $service->geocodeCity('St. George', 'UT');
        $second = $service->geocodeCity('St. George', 'UT');

        $this->assertSame(['lat' => 37.1041944, 'lng' => -113.5841313], $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1); // second call served from cache

        $this->assertNull($service->geocodeCity(null, 'UT'));
    }
}
