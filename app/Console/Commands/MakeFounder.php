<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates a founder login interactively. The password is prompted for and
 * never passed as an argument, so it stays out of shell history and logs.
 */
class MakeFounder extends Command
{
    protected $signature = 'fuelline:make-founder';

    protected $description = 'Create (or re-password) a founder login';

    public function handle(): int
    {
        $name = text('Full name', required: true);
        $email = text('Email address', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');
        $secret = password('Password', required: true, validate: fn ($v) => strlen($v) >= 12 ? null : 'Use at least 12 characters.');

        $user = User::updateOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['name' => $name, 'password' => $secret, 'role' => 'founder']
        );

        $this->info("Founder login ready for {$user->email}.");

        return self::SUCCESS;
    }
}
