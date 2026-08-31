<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MakeFounderTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_mode_creates_a_founder_and_prints_a_working_reset_link(): void
    {
        $this->artisan('fuelline:make-founder --link --name="Tanner Holm" --email=tanner@freedomfuel.us')
            ->assertSuccessful();

        $user = User::where('email', 'tanner@freedomfuel.us')->firstOrFail();
        $this->assertSame('founder', $user->role->value);
        $this->assertSame('Tanner Holm', $user->name);

        // The generated link actually lets the founder set a password.
        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-real-password-1',
            'password_confirmation' => 'a-real-password-1',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('a-real-password-1', $user->fresh()->password));
    }

    public function test_link_mode_requires_name_and_valid_email(): void
    {
        $this->artisan('fuelline:make-founder --link --email=tanner@freedomfuel.us')->assertFailed();
        $this->artisan('fuelline:make-founder --link --name=X --email=not-an-email')->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_rerunning_repasswords_rather_than_duplicating(): void
    {
        $this->artisan('fuelline:make-founder --link --name="Tanner Holm" --email=tanner@freedomfuel.us');
        $this->artisan('fuelline:make-founder --link --name="Tanner Holm" --email=TANNER@freedomfuel.us');

        $this->assertSame(1, User::where('email', 'tanner@freedomfuel.us')->count());
    }
}
