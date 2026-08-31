<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Field mode: the phone-first quick-log screen for store visits.
 * Accounts sorted by what's overdue, then due today, then everything else.
 */
class FieldController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $accounts = Account::query()
            ->where('pipeline_stage', '!=', 'lost')
            ->search($q, ['name', 'city'])
            ->byNextAction()
            ->limit(60)
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'city' => $a->city,
                'state' => $a->state,
                'stage' => $a->pipeline_stage->value,
                'stage_label' => $a->pipeline_stage->label(),
                'next_action' => $a->next_action,
                'next_action_date' => $a->next_action_date?->toDateString(),
                'overdue' => $a->next_action_date !== null && $a->next_action_date->isPast() && ! $a->next_action_date->isToday(),
                'due_today' => $a->next_action_date?->isToday() ?? false,
            ]);

        return Inertia::render('Field/Index', ['accounts' => $accounts, 'q' => $q]);
    }
}
