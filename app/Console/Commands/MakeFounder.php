<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates a founder login.
 *
 * Interactive (default): prompts for the password so it never lands in shell
 * history or a command log.
 *
 * --link: for non-interactive consoles (e.g. Forge's Commands box). Sets a
 * random password nobody ever sees and prints a one-time, expiring link for
 * the founder to choose their own. No password is ever transmitted or logged.
 */
class MakeFounder extends Command
{
    protected $signature = 'fuelline:make-founder
        {--name= : Full name (required with --link)}
        {--email= : Email address (required with --link)}
        {--link : Skip the prompts; output a one-time set-password link instead}';

    protected $description = 'Create (or re-password) a founder login';

    public function handle(): int
    {
        return $this->option('link') ? $this->viaLink() : $this->interactive();
    }

    private function interactive(): int
    {
        $name = text('Full name', required: true);
        $email = text('Email address', required: true, validate: fn ($v) => $this->validEmail($v));
        $secret = password('Password', required: true, validate: fn ($v) => strlen($v) >= 12 ? null : 'Use at least 12 characters.');

        $user = $this->upsert($name, $email, $secret);
        $this->info("Founder login ready for {$user->email}.");

        return self::SUCCESS;
    }

    private function viaLink(): int
    {
        $name = (string) $this->option('name');
        $email = (string) $this->option('email');

        if (blank($name) || blank($email) || $this->validEmail($email) !== null) {
            $this->error('--link requires --name and a valid --email.');

            return self::FAILURE;
        }

        // A password nobody knows, replaced the moment the link is used.
        $user = $this->upsert($name, $email, Str::random(64));

        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token]).'?email='.urlencode($user->email);

        $this->info("Founder created: {$user->email}");
        $this->newLine();
        $this->line('Set the password with this one-time link (expires in 60 minutes):');
        $this->line($url);
        $this->newLine();
        $this->comment('Anyone with this link can set the password until it is used or expires.');

        return self::SUCCESS;
    }

    private function upsert(string $name, string $email, string $secret): User
    {
        return User::updateOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['name' => $name, 'password' => $secret, 'role' => 'founder']
        );
    }

    private function validEmail(string $v): ?string
    {
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.';
    }
}
