<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['retailer_type', 'engine', 'stage_group']);

        $accounts = Account::query()
            ->when($filters['retailer_type'] ?? null, fn ($q, $v) => $q->where('retailer_type', $v))
            ->when($filters['engine'] ?? null, fn ($q, $v) => $q->where('acquisition_engine', $v))
            ->orderBy('name')
            ->get();

        $located = $accounts->filter(fn (Account $a) => $a->latitude !== null && $a->longitude !== null);

        return Inertia::render('Map/Index', [
            'accounts' => $located->values()->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'city' => $a->city,
                'state' => $a->state,
                'lat' => (float) $a->latitude,
                'lng' => (float) $a->longitude,
                'stage' => $a->pipeline_stage->value,
                'stage_label' => $a->pipeline_stage->label(),
                'retailer_type' => $a->retailer_type?->label(),
                'engine' => $a->acquisition_engine?->value,
                'next_action' => $a->next_action,
                'next_action_date' => $a->next_action_date?->toDateString(),
                'overdue' => $a->next_action_date !== null && $a->next_action_date->isPast() && ! $a->next_action_date->isToday(),
            ]),
            'unlocatedCount' => $accounts->count() - $located->count(),
            'filters' => $filters,
        ]);
    }
}
