<?php

namespace App\Models;

use App\Enums\AcquisitionEngine;
use App\Enums\LeadSource;
use App\Enums\PipelineStage;
use App\Enums\RetailerType;
use App\Enums\SignupSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Account extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'pipeline_stage' => 'qualified_prospect',
        'acquisition_engine' => 'direct',
        'signup_source' => 'founder',
    ];

    /** Optional note attached to the next stage-transition log row. */
    public ?string $transitionNote = null;

    protected function casts(): array
    {
        return [
            'retailer_type' => RetailerType::class,
            'lead_source' => LeadSource::class,
            'acquisition_engine' => AcquisitionEngine::class,
            'pipeline_stage' => PipelineStage::class,
            'signup_source' => SignupSource::class,
            'next_action_date' => 'date',
            'sms_opted_out_at' => 'datetime',
            'sms_consent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Stage history is the KPI raw material (days-to-reorder, conversion %),
        // so every stage the account ever occupies gets a dated row — automatically.
        static::created(function (Account $account) {
            $account->stageTransitions()->create([
                'from_stage' => null,
                'to_stage' => $account->pipeline_stage->value,
                'user_id' => Auth::id(),
                'note' => $account->transitionNote,
            ]);
        });

        static::updated(function (Account $account) {
            if ($account->wasChanged('pipeline_stage')) {
                $account->stageTransitions()->create([
                    'from_stage' => $account->getOriginal('pipeline_stage')?->value,
                    'to_stage' => $account->pipeline_stage->value,
                    'user_id' => Auth::id(),
                    'note' => $account->transitionNote,
                ]);
            }
        });

        // Pin the account on the map once it has a city/state. Runs after the
        // response so a slow geocoder never delays a save from the field.
        static::saved(function (Account $account) {
            if (! config('fuelline.geocoding')) {
                return;
            }

            $needsPin = $account->latitude === null || $account->wasChanged(['city', 'state']);

            if ($needsPin && filled($account->city) && filled($account->state)) {
                dispatch(function () use ($account) {
                    $result = app(\App\Services\GeocodingService::class)
                        ->geocodeCity($account->city, $account->state);

                    if ($result !== null) {
                        $account->forceFill([
                            'latitude' => $result['lat'],
                            'longitude' => $result['lng'],
                        ])->saveQuietly();
                    }
                })->afterResponse();
            }
        });
    }

    /**
     * Case-insensitive search that behaves the same on MySQL and Postgres
     * (ILIKE is Postgres-only; MySQL's LIKE is collation-dependent).
     */
    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, ?string $term, array $columns = ['name', 'city', 'decision_maker']): void
    {
        if (blank($term)) {
            return;
        }

        $needle = '%'.mb_strtolower(trim($term)).'%';

        $query->where(function ($query) use ($columns, $needle) {
            foreach ($columns as $column) {
                $query->orWhereRaw("LOWER({$column}) LIKE ?", [$needle]);
            }
        });
    }

    /** Soonest next action first, accounts with no date last — portable ordering. */
    public function scopeByNextAction(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->orderByRaw('next_action_date IS NULL')->orderBy('next_action_date');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function stageTransitions(): HasMany
    {
        return $this->hasMany(StageTransition::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function smsOptedOut(): bool
    {
        return $this->sms_opted_out_at !== null;
    }

    public function buybackAgreements(): HasMany
    {
        return $this->hasMany(BuybackAgreement::class);
    }

    public function caseStudies(): HasMany
    {
        return $this->hasMany(CaseStudy::class);
    }

    public function activations(): BelongsToMany
    {
        return $this->belongsToMany(Activation::class);
    }

    public function openingOrder(): ?Order
    {
        return $this->orders->where('type', \App\Enums\OrderType::Opening)->sortBy('date')->first();
    }

    /** Date the sell-through clock started: first fulfilled opening order. */
    public function goLiveDate(): ?\Carbon\CarbonInterface
    {
        $opening = $this->openingOrder();

        return $opening?->fulfilled_at ?? null;
    }
}
