<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Founder-only user administration. No password is ever typed or seen here:
 * new logins get a random password and the founder is handed a one-time
 * set-password link to pass along (same pattern as fuelline:make-founder
 * --link). Investors have no login at all — the scorecard is signed-URL only.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Users', [
            'users' => User::with('account:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role?->value,
                    'account' => $u->account?->name,
                    'created_at' => $u->created_at?->toDateString(),
                ]),
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::in([UserRole::Founder->value, UserRole::Retailer->value])],
            'account_id' => [
                Rule::requiredIf($request->input('role') === UserRole::Retailer->value),
                'nullable',
                'exists:accounts,id',
            ],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'account_id' => $data['role'] === UserRole::Retailer->value ? $data['account_id'] : null,
            // A password nobody knows, replaced the moment the link is used.
            'password' => Str::random(64),
        ]);

        return $this->withInviteLink($user, "Login created for {$user->email}. Send them the link below.");
    }

    public function invite(User $user): RedirectResponse
    {
        return $this->withInviteLink($user, "New set-password link for {$user->email}. The old one no longer works.");
    }

    private function withInviteLink(User $user, string $message): RedirectResponse
    {
        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token]).'?email='.urlencode($user->email);

        return to_route('users.index')->with('success', $message)->with('invite_link', $url);
    }
}
