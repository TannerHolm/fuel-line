<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Enums\PipelineStage;
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Computes the six primary KPIs from Section 11 of the 90-Day Plan.
 * Everything derives from dated events (stage_transitions, orders, check_ins) —
 * nothing here is hand-entered.
 */
class KpiService
{
    /** Stages at or past "a real conversation happened". */
    private const CONVERSATION_STAGES = [
        'contacted', 'sampled', 'interested', 'opening_order',
        'selling', 'reordered', 'repeat_account',
    ];

    /**
     * @param  array{retailer_type?: ?string, engine?: ?string, state?: ?string}  $filters
     */
    public function compute(array $filters = [], ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        $accounts = $this->filteredAccounts($filters)
            ->with(['orders', 'checkIns', 'stageTransitions'])
            ->get();

        // 1 — Qualified retailer conversations (ever reached Contacted or beyond).
        $conversed = $accounts->filter(
            fn (Account $a) => $a->stageTransitions
                ->contains(fn ($t) => in_array($t->to_stage->value, self::CONVERSATION_STAGES))
        );

        // Opening orders.
        $withOpening = $accounts->filter(
            fn (Account $a) => $a->orders->contains(fn ($o) => $o->type === OrderType::Opening)
        );
        $openingOrders = $withOpening->map(
            fn (Account $a) => $a->orders->where('type', OrderType::Opening)->sortBy('date')->first()
        );

        // 4 — Units / store / week across live accounts.
        $liveAccounts = $accounts->filter(fn (Account $a) => $this->goLive($a) !== null);
        $velocities = $liveAccounts->map(function (Account $a) use ($asOf) {
            // Whole days live, so the number doesn't drift with time of day.
            $weeks = max((int) $this->goLive($a)->diffInDays($asOf), 1) / 7;
            return $a->checkIns->sum('units_sold') / $weeks;
        });

        // 5 — Days to first reorder.
        $reorderDays = $accounts->map(function (Account $a) {
            $opening = $a->orders->where('type', OrderType::Opening)->sortBy('date')->first();
            $reorder = $a->orders->where('type', OrderType::Reorder)->sortBy('date')->first();
            return ($opening && $reorder) ? $opening->date->diffInDays($reorder->date) : null;
        })->filter(fn ($d) => $d !== null);

        // 6 — Reorder rate among mature accounts.
        $maturityDays = config('fuelline.maturity_days');
        $mature = $accounts->filter(
            fn (Account $a) => ($g = $this->goLive($a)) && $g->diffInDays($asOf) >= $maturityDays
        );
        $matureReordered = $mature->filter(
            fn (Account $a) => $a->orders->contains(fn ($o) => $o->type === OrderType::Reorder)
        );

        return [
            'qualified_conversations' => $conversed->count(),
            'opening_conversion_pct' => $conversed->count() > 0
                ? round($withOpening->count() / $conversed->count() * 100, 1)
                : null,
            'avg_opening_order' => $openingOrders->isNotEmpty()
                ? round($openingOrders->avg(fn ($o) => (float) $o->revenue), 2)
                : null,
            'units_per_store_week' => $velocities->isNotEmpty()
                ? round($velocities->avg(), 1)
                : null,
            'avg_days_to_reorder' => $reorderDays->isNotEmpty()
                ? round($reorderDays->avg(), 1)
                : null,
            'reorder_rate_pct' => $mature->count() > 0
                ? round($matureReordered->count() / $mature->count() * 100, 1)
                : null,
            'meta' => [
                'accounts_total' => $accounts->count(),
                'accounts_live' => $liveAccounts->count(),
                'accounts_mature' => $mature->count(),
                'opening_orders' => $withOpening->count(),
                'maturity_days' => $maturityDays,
            ],
        ];
    }

    /**
     * Per-account sell-through velocity, built from the check-in history.
     *
     * "Days of cover" is the number the founders actually act on: at the
     * current rate, how long until this store is empty — which is when the
     * reorder conversation has to have already happened.
     */
    public function accountVelocity(Account $account): array
    {
        $checkIns = $account->checkIns()->orderBy('date')->get();
        $goLive = $this->goLive($account->loadMissing('orders'));

        $series = $checkIns->map(fn ($c) => [
            'date' => $c->date->toDateString(),
            'units_sold' => $c->units_sold,
            'units_on_hand' => $c->units_on_hand,
            'source' => $c->source->value,
        ])->values();

        $totalSold = (int) $checkIns->sum('units_sold');
        $onHand = $checkIns->last()?->units_on_hand;

        $weeksLive = $goLive !== null
            ? max((int) $goLive->diffInDays(now()), 1) / 7
            : null;

        $weekly = ($weeksLive !== null && $totalSold > 0) ? round($totalSold / $weeksLive, 1) : null;

        $daysOfCover = ($weekly !== null && $weekly > 0 && $onHand !== null)
            ? (int) floor($onHand / ($weekly / 7))
            : null;

        return [
            'series' => $series,
            'weekly' => $weekly,
            'total_sold' => $totalSold,
            'on_hand' => $onHand,
            'days_of_cover' => $daysOfCover,
            'sell_out_date' => $daysOfCover !== null ? now()->addDays($daysOfCover)->toDateString() : null,
            'go_live' => $goLive?->toDateString(),
            'days_live' => $goLive !== null ? (int) $goLive->diffInDays(now()) : null,
            'units_ordered' => (int) $account->orders->sum('quantity'),
        ];
    }

    /** Velocity for every live account, best sellers first. */
    public function velocityLeaderboard(array $filters = []): array
    {
        return $this->filteredAccounts($filters)
            ->with(['orders', 'checkIns'])
            ->get()
            ->filter(fn (Account $a) => $this->goLive($a) !== null)
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'city' => $a->city,
                'state' => $a->state,
                'stage' => $a->pipeline_stage->value,
            ] + $this->accountVelocity($a))
            ->sortByDesc(fn ($row) => $row['weekly'] ?? -1)
            ->values()
            ->all();
    }

    private function filteredAccounts(array $filters): Builder
    {
        return Account::query()
            ->when($filters['retailer_type'] ?? null, fn ($q, $v) => $q->where('retailer_type', $v))
            ->when($filters['engine'] ?? null, fn ($q, $v) => $q->where('acquisition_engine', $v))
            ->when($filters['state'] ?? null, fn ($q, $v) => $q->where('state', $v));
    }

    private function goLive(Account $a): ?Carbon
    {
        $opening = $a->orders->where('type', OrderType::Opening)->sortBy('date')->first();

        return $opening?->fulfilled_at ? Carbon::parse($opening->fulfilled_at) : null;
    }
}
