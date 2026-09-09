<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountImportTest extends TestCase
{
    use RefreshDatabase;

    private function founder(): User
    {
        return User::factory()->create(['role' => 'founder']);
    }

    public function test_import_is_founder_only()
    {
        $retailer = User::factory()->create(['role' => 'retailer']);

        $this->get('/accounts/import')->assertRedirect('/login');
        $this->actingAs($retailer)->get('/accounts/import')->assertForbidden();
        $this->actingAs($retailer)->post('/accounts/import', ['rows' => [['name' => 'X']]])->assertForbidden();
    }

    public function test_founder_can_view_the_import_page()
    {
        $this->actingAs($this->founder())->get('/accounts/import')->assertOk();
    }

    public function test_rows_import_as_qualified_prospects_with_stage_history()
    {
        $founder = $this->founder();

        $response = $this->actingAs($founder)->post('/accounts/import', [
            'owner_id' => $founder->id,
            'lead_source' => 'referral',
            'rows' => [
                [
                    'name' => 'Big Sky Sinclair',
                    'city' => 'Belgrade',
                    'state' => 'Montana',
                    'retailer_type' => 'C o n v e n i e n c e', // survives odd spacing
                    'decision_maker' => 'Dana Ross',
                    'phone' => '406-555-0141',
                    'email' => 'dana@bigsky.example',
                    'lead_source' => 'Cold call / visit',
                    'notes' => 'Near the truck route',
                ],
                ['name' => 'Hurricane Fast Stop', 'city' => 'Hurricane', 'state' => 'ut'],
            ],
        ]);

        $response->assertRedirect('/accounts/import')->assertSessionHas('import_summary');
        $summary = session('import_summary');
        $this->assertSame(2, $summary['created']);
        $this->assertSame([], $summary['skipped']);
        $this->assertSame([], $summary['failed']);

        $account = Account::where('name', 'Big Sky Sinclair')->firstOrFail();
        $this->assertSame('MT', $account->state);
        $this->assertSame('convenience', $account->retailer_type->value);
        $this->assertSame('cold_call', $account->lead_source->value);
        $this->assertSame('qualified_prospect', $account->pipeline_stage->value);
        $this->assertSame($founder->id, $account->owner_id);
        $this->assertSame(1, $account->stageTransitions()->count());

        // Row without its own source falls back to the batch default.
        $this->assertSame('referral', Account::where('name', 'Hurricane Fast Stop')->firstOrFail()->lead_source->value);
    }

    public function test_duplicates_are_skipped_not_reimported()
    {
        Account::create(['name' => 'Fasteddys', 'city' => 'Meridian', 'state' => 'ID']);

        $this->actingAs($this->founder())->post('/accounts/import', [
            'rows' => [
                ['name' => 'FASTEDDYS', 'city' => 'Meridian'],   // exists already
                ['name' => 'New Stop', 'city' => 'Boise'],
                ['name' => 'New Stop', 'city' => 'Boise'],       // duplicate inside the batch
            ],
        ]);

        $summary = session('import_summary');
        $this->assertSame(1, $summary['created']);
        $this->assertCount(2, $summary['skipped']);
        $this->assertSame(1, Account::whereRaw("LOWER(name) = 'fasteddys'")->count());
    }

    public function test_same_name_in_a_different_city_is_a_distinct_prospect()
    {
        Account::create(['name' => 'Sinclair', 'city' => 'Meridian', 'state' => 'ID']);

        $this->actingAs($this->founder())->post('/accounts/import', [
            'rows' => [['name' => 'Sinclair', 'city' => 'Nampa', 'state' => 'ID']],
        ]);

        $this->assertSame(1, session('import_summary')['created']);
    }

    public function test_bad_rows_fail_with_reasons_and_good_rows_still_import()
    {
        $this->actingAs($this->founder())->post('/accounts/import', [
            'rows' => [
                ['name' => 'Good Stop', 'city' => 'Boise', 'state' => 'ID'],
                ['city' => 'Nowhere'],                                        // no name
                ['name' => 'Bad State', 'state' => 'Confusion'],
                ['name' => 'Bad Type', 'retailer_type' => 'Gymnasium'],
                ['name' => 'Bad Email', 'email' => 'not-an-email'],
            ],
        ]);

        $summary = session('import_summary');
        $this->assertSame(1, $summary['created']);
        $this->assertCount(4, $summary['failed']);
        $this->assertTrue(Account::where('name', 'Good Stop')->exists());
        $this->assertFalse(Account::where('name', 'Bad State')->exists());
    }

    public function test_row_cap_is_enforced()
    {
        $rows = array_map(fn ($i) => ['name' => "Store {$i}"], range(1, 501));

        $this->actingAs($this->founder())
            ->post('/accounts/import', ['rows' => $rows])
            ->assertSessionHasErrors('rows');
    }
}
