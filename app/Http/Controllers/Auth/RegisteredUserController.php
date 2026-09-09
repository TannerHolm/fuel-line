<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RetailerType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public registration is the wholesale-partner front door (Section 07 of the
 * architecture doc). It creates a retailer login plus the same Account record
 * the founders' pipeline board tracks, tagged signup_source=self_service.
 * Founder logins are seeded/invited, never self-registered.
 */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register', [
            'retailerTypes' => collect(RetailerType::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'business_name' => 'required|string|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|size:2',
            'retailer_type' => ['nullable', Rule::enum(RetailerType::class)],
            'phone' => 'nullable|string|max:30',
            'sms_consent' => 'nullable|boolean',
        ]);

        $user = DB::transaction(function () use ($request) {
            $account = Account::create([
                'name' => $request->business_name,
                'city' => $request->city,
                'state' => $request->state ? strtoupper($request->state) : null,
                'retailer_type' => $request->retailer_type,
                'decision_maker' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'sms_consent_at' => $request->boolean('sms_consent') && filled($request->phone) ? now() : null,
                'lead_source' => 'online',
                'acquisition_engine' => 'seeded',
                'pipeline_stage' => 'qualified_prospect',
                'signup_source' => 'self_service',
            ]);

            return User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'retailer',
                'phone' => $request->phone,
                'account_id' => $account->id,
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        // Mirror the new partner into Shopify (no-op until credentials exist).
        $account = $user->account;
        dispatch(fn () => app(\App\Services\ShopifyService::class)->pushCustomer($account))->afterResponse();

        return to_route('portal');
    }
}
