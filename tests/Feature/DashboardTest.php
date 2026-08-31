<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_to_the_pipeline()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/pipeline');
    }

    public function test_guests_are_redirected_from_the_pipeline_to_login()
    {
        $response = $this->get('/pipeline');
        $response->assertRedirect('/login');
    }

    public function test_founders_can_visit_the_pipeline()
    {
        $user = User::factory()->create(['role' => 'founder']);
        $this->actingAs($user);

        $response = $this->get('/pipeline');
        $response->assertStatus(200);
    }
}
