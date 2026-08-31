<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['retailer_type', 'engine', 'state', 'q']);

        $accounts = Account::query()
            ->with(['owner:id,name', 'stageTransitions' => fn ($q) => $q->latest('id')->limit(1)])
            ->when($filters['retailer_type'] ?? null, fn ($q, $v) => $q->where('retailer_type', $v))
            ->when($filters['engine'] ?? null, fn ($q, $v) => $q->where('acquisition_engine', $v))
            ->when($filters['state'] ?? null, fn ($q, $v) => $q->where('state', $v))
            ->search($filters['q'] ?? null)
            ->byNextAction()
            ->get();

        $board = collect(PipelineStage::ordered())->map(fn (PipelineStage $stage) => [
            'value' => $stage->value,
            'label' => $stage->label(),
            'accounts' => $accounts
                ->where('pipeline_stage', $stage)
                ->values()
                ->map(fn (Account $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'city' => $a->city,
                    'state' => $a->state,
                    'retailer_type' => $a->retailer_type?->value,
                    'engine' => $a->acquisition_engine?->value,
                    'owner' => $a->owner?->name,
                    'next_action' => $a->next_action,
                    'next_action_date' => $a->next_action_date?->toDateString(),
                    'overdue' => $a->next_action_date !== null && $a->next_action_date->isPast() && ! $a->next_action_date->isToday(),
                    'due_today' => $a->next_action_date?->isToday() ?? false,
                    'days_in_stage' => (int) round(abs(($a->stageTransitions->first()?->created_at ?? $a->created_at)->diffInDays(now()))),
                ]),
        ]);

        return Inertia::render('Pipeline/Index', [
            'board' => $board,
            'filters' => $filters,
            'states' => Account::query()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
        ]);
    }
}
