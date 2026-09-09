<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OwnerFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_and_accounts_filter_by_owner_and_unassigned(): void
    {
        $tanner = User::factory()->create(['role' => 'founder']);
        $chris = User::factory()->create(['role' => 'founder']);

        Account::create(['name' => 'Tanner Stop', 'owner_id' => $tanner->id]);
        Account::create(['name' => 'Chris Stop', 'owner_id' => $chris->id]);
        Account::create(['name' => 'Orphan Stop']);

        $cardNames = fn (array $board) => collect($board)->flatMap(fn ($col) => collect($col['accounts'])->pluck('name'))->all();

        $this->actingAs($tanner)->get('/pipeline?owner='.$chris->id)->assertInertia(
            fn (AssertableInertia $page) => $page->component('Pipeline/Index')
                ->where('board', fn ($board) => $cardNames($board->toArray()) === ['Chris Stop'])
                ->has('owners', 2)
        );

        $this->actingAs($tanner)->get('/pipeline?owner=none')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('board', fn ($board) => $cardNames($board->toArray()) === ['Orphan Stop'])
        );

        $this->actingAs($tanner)->get('/accounts?owner='.$tanner->id)->assertInertia(
            fn (AssertableInertia $page) => $page->component('Accounts/Index')
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Tanner Stop')
        );

        $this->actingAs($tanner)->get('/accounts?owner=none')->assertInertia(
            fn (AssertableInertia $page) => $page->has('accounts', 1)->where('accounts.0.name', 'Orphan Stop')
        );
    }
}
