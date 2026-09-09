<?php

namespace Tests\Feature\Settings;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function founder(): User
    {
        return User::factory()->create(['role' => 'founder']);
    }

    public function test_founder_can_view_the_users_page()
    {
        $this->actingAs($this->founder())
            ->get('/settings/users')
            ->assertOk();
    }

    public function test_retailer_cannot_access_user_management()
    {
        $retailer = User::factory()->create(['role' => 'retailer']);

        $this->actingAs($retailer)->get('/settings/users')->assertForbidden();
        $this->actingAs($retailer)->post('/settings/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'founder',
        ])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/settings/users')->assertRedirect('/login');
    }

    public function test_founder_can_create_a_founder_and_gets_a_one_time_link()
    {
        $response = $this->actingAs($this->founder())->post('/settings/users', [
            'name' => 'Chris Freedom',
            'email' => 'Chris.New@FreedomFuel.us',
            'role' => 'founder',
        ]);

        $response->assertRedirect('/settings/users')
            ->assertSessionHas('invite_link')
            ->assertSessionHas('success');

        $user = User::where('email', 'chris.new@freedomfuel.us')->firstOrFail();
        $this->assertSame('founder', $user->role->value);
        $this->assertNull($user->account_id);
        $this->assertStringContainsString('reset-password', session('invite_link'));
    }

    public function test_founder_can_create_a_retailer_scoped_to_an_account()
    {
        $account = Account::create([
            'name' => 'Test Depot', 'city' => 'Hurricane', 'state' => 'UT',
            'retailer_type' => 'convenience', 'lead_source' => 'online',
        ]);

        $this->actingAs($this->founder())->post('/settings/users', [
            'name' => 'Depot Owner',
            'email' => 'owner@testdepot.com',
            'role' => 'retailer',
            'account_id' => $account->id,
        ])->assertRedirect('/settings/users')->assertSessionHas('invite_link');

        $user = User::where('email', 'owner@testdepot.com')->firstOrFail();
        $this->assertSame('retailer', $user->role->value);
        $this->assertSame($account->id, $user->account_id);
    }

    public function test_a_retailer_requires_an_account()
    {
        $this->actingAs($this->founder())->post('/settings/users', [
            'name' => 'Orphan Retailer',
            'email' => 'orphan@example.com',
            'role' => 'retailer',
        ])->assertSessionHasErrors('account_id');

        $this->assertDatabaseMissing('users', ['email' => 'orphan@example.com']);
    }

    public function test_duplicate_email_is_rejected()
    {
        $existing = User::factory()->create(['role' => 'retailer']);

        $this->actingAs($this->founder())->post('/settings/users', [
            'name' => 'Duplicate',
            'email' => strtoupper($existing->email),
            'role' => 'founder',
        ])->assertSessionHasErrors('email');
    }

    public function test_investor_role_cannot_be_created()
    {
        $this->actingAs($this->founder())->post('/settings/users', [
            'name' => 'No Login Investor',
            'email' => 'investor@example.com',
            'role' => 'investor',
        ])->assertSessionHasErrors('role');
    }

    public function test_founder_can_issue_a_fresh_link_for_an_existing_user()
    {
        $user = User::factory()->create(['role' => 'retailer']);

        $this->actingAs($this->founder())
            ->post("/settings/users/{$user->id}/invite")
            ->assertRedirect('/settings/users')
            ->assertSessionHas('invite_link');
    }
}
