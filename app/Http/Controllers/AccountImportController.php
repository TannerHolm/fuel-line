<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\RetailerType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk import of prospective locations. Rows arrive pre-parsed from the
 * paste/CSV preview screen; every valid row becomes a Qualified Prospect
 * through Account::create so stage history logs like any other account.
 * Duplicates (same name + city as an existing account) are skipped, so
 * re-pasting a corrected sheet is safe.
 */
class AccountImportController extends Controller
{
    private const MAX_ROWS = 500;

    private const STATES = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
        'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
        'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
        'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
        'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
        'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
        'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
        'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
        'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
        'wisconsin' => 'WI', 'wyoming' => 'WY', 'district of columbia' => 'DC',
    ];

    public function show(): Response
    {
        return Inertia::render('Accounts/Import', [
            'options' => [
                'retailer_types' => collect(RetailerType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
                'lead_sources' => collect(LeadSource::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
                'owners' => User::where('role', 'founder')->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*' => ['array'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'lead_source' => ['nullable', \Illuminate\Validation\Rule::enum(LeadSource::class)],
        ]);

        $created = 0;
        $skipped = [];
        $failed = [];
        $seen = []; // name+city keys already handled in this batch

        foreach ($request->input('rows') as $i => $raw) {
            $line = $i + 1;
            $name = trim((string) ($raw['name'] ?? ''));

            if ($name === '') {
                $failed[] = ['line' => $line, 'name' => '(blank)', 'reason' => 'Missing a business name.'];

                continue;
            }

            $city = trim((string) ($raw['city'] ?? '')) ?: null;
            $state = $this->resolveState($raw['state'] ?? null);

            if ($state === false) {
                $failed[] = ['line' => $line, 'name' => $name, 'reason' => "Unknown state '".trim((string) $raw['state'])."'. Use the two-letter code."];

                continue;
            }

            $type = $this->resolveEnum(RetailerType::cases(), $raw['retailer_type'] ?? null);

            if ($type === false) {
                $failed[] = ['line' => $line, 'name' => $name, 'reason' => "Unknown retailer type '".trim((string) $raw['retailer_type'])."'. Use Service, Performance, or Convenience."];

                continue;
            }

            $source = $this->resolveEnum(LeadSource::cases(), $raw['lead_source'] ?? null);

            if ($source === false) {
                $failed[] = ['line' => $line, 'name' => $name, 'reason' => "Unknown lead source '".trim((string) $raw['lead_source'])."'."];

                continue;
            }

            $email = trim((string) ($raw['email'] ?? '')) ?: null;

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed[] = ['line' => $line, 'name' => $name, 'reason' => "'{$email}' is not a valid email."];

                continue;
            }

            $key = mb_strtolower($name).'|'.mb_strtolower((string) $city);

            if (isset($seen[$key])) {
                $skipped[] = ['line' => $line, 'name' => $name, 'reason' => 'Duplicate of an earlier row in this import.'];

                continue;
            }
            $seen[$key] = true;

            if ($this->existingAccount($name, $city)) {
                $skipped[] = ['line' => $line, 'name' => $name, 'reason' => 'Already in the pipeline.'];

                continue;
            }

            Account::create([
                'name' => mb_substr($name, 0, 255),
                'city' => $city ? mb_substr($city, 0, 120) : null,
                'state' => $state,
                'retailer_type' => $type,
                'lead_source' => $source ?? $request->input('lead_source'),
                'decision_maker' => mb_substr(trim((string) ($raw['decision_maker'] ?? '')), 0, 120) ?: null,
                'phone' => mb_substr(trim((string) ($raw['phone'] ?? '')), 0, 30) ?: null,
                'email' => $email,
                'notes' => trim((string) ($raw['notes'] ?? '')) ?: null,
                'owner_id' => $request->input('owner_id'),
            ]);
            $created++;
        }

        return to_route('accounts.import')->with('import_summary', [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    /** Two-letter code, full state name, or blank. False means unresolvable. */
    private function resolveState(mixed $value): string|null|false
    {
        $state = trim((string) $value);

        if ($state === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $state)) {
            return strtoupper($state);
        }

        return self::STATES[mb_strtolower($state)] ?? false;
    }

    /**
     * Matches a pasted value against an enum's values and labels, ignoring
     * case and punctuation ("Cold call / visit", "cold_call" and "Cold Call"
     * all resolve). False means the value matched nothing.
     */
    private function resolveEnum(array $cases, mixed $value): \BackedEnum|null|false
    {
        $needle = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value)));

        if ($needle === '') {
            return null;
        }

        foreach ($cases as $case) {
            $candidates = [$case->value, $case->label()];

            foreach ($candidates as $candidate) {
                if (preg_replace('/[^a-z0-9]/', '', mb_strtolower($candidate)) === $needle) {
                    return $case;
                }
            }
        }

        return false;
    }

    private function existingAccount(string $name, ?string $city): bool
    {
        // Same name is a duplicate unless BOTH sides name a city and they differ
        // (two franchises of one chain in different towns are distinct prospects).
        return Account::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->get(['city'])
            ->contains(function ($account) use ($city) {
                return blank($city)
                    || blank($account->city)
                    || mb_strtolower($account->city) === mb_strtolower($city);
            });
    }
}
