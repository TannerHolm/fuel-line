<?php

namespace App\Http\Controllers;

use App\Enums\AcquisitionEngine;
use App\Enums\LeadSource;
use App\Enums\PipelineStage;
use App\Enums\RetailerType;
use App\Models\Account;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $accounts = Account::query()
            ->with('owner:id,name')
            ->withCount(['orders', 'checkIns'])
            ->search($q)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'city' => $a->city,
                'state' => $a->state,
                'retailer_type' => $a->retailer_type?->label(),
                'stage' => $a->pipeline_stage->value,
                'stage_label' => $a->pipeline_stage->label(),
                'owner' => $a->owner?->name,
                'orders_count' => $a->orders_count,
                'check_ins_count' => $a->check_ins_count,
                'next_action_date' => $a->next_action_date?->toDateString(),
            ]);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'q' => $q,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Form', [
            'account' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = Account::create($this->validated($request));

        return redirect()->route('accounts.show', $account)->with('success', 'Account created.');
    }

    public function show(Account $account, KpiService $kpis): Response
    {
        $account->load([
            'owner:id,name',
            'samples' => fn ($q) => $q->with('user:id,name')->latest('date'),
            'orders' => fn ($q) => $q->latest('date'),
            'checkIns' => fn ($q) => $q->with('user:id,name')->latest('date'),
            'stageTransitions' => fn ($q) => $q->with('user:id,name')->latest('created_at'),
        ]);

        $timeline = collect()
            ->concat($account->stageTransitions->map(fn ($t) => [
                'kind' => 'stage',
                'date' => $t->created_at->toDateString(),
                'sort' => $t->created_at->timestamp,
                'title' => $t->from_stage
                    ? $t->from_stage->label().' → '.$t->to_stage->label()
                    : 'Entered pipeline as '.$t->to_stage->label(),
                'detail' => $t->note,
                'by' => $t->user?->name,
            ]))
            ->concat($account->samples->map(fn ($s) => [
                'kind' => 'sample',
                'date' => $s->date->toDateString(),
                'sort' => $s->date->timestamp + 1,
                'title' => $s->pucks_given.' pucks sampled to '.$s->given_to->label(),
                'detail' => $s->notes,
                'by' => $s->user?->name,
            ]))
            ->concat($account->orders->map(fn ($o) => [
                'kind' => 'order',
                'date' => $o->date->toDateString(),
                'sort' => $o->date->timestamp + 2,
                'title' => ($o->type->value === 'opening' ? 'Opening order' : 'Reorder').' — '.$o->quantity.' units'
                    .($o->revenue ? ' · $'.number_format((float) $o->revenue, 2) : ''),
                'detail' => 'Tier: '.($o->tier ?? '—').' · Payment: '.$o->payment_status->value
                    .($o->fulfilled_at ? ' · Fulfilled '.$o->fulfilled_at->toDateString() : ''),
                'by' => null,
            ]))
            ->concat($account->checkIns->map(fn ($c) => [
                'kind' => 'check_in',
                'date' => $c->date->toDateString(),
                'sort' => $c->date->timestamp + 3,
                'title' => 'Check-in ('.$c->cadence->label().')'
                    .($c->units_sold !== null ? ' — '.$c->units_sold.' sold' : '')
                    .($c->units_on_hand !== null ? ', '.$c->units_on_hand.' on hand' : ''),
                'detail' => collect([
                    $c->flavors_moving ? 'Moving: '.implode(', ', $c->flavors_moving) : null,
                    $c->staff_recommends === true ? 'Staff recommending' : null,
                    $c->objections ? 'Objection: '.$c->objections : null,
                    $c->ready_for_reorder ? 'READY FOR REORDER' : null,
                    $c->notes,
                ])->filter()->implode(' · '),
                'by' => ($c->source->value === 'retailer' ? 'Retailer' : $c->user?->name),
            ]))
            ->sortByDesc('sort')
            ->values();

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'city' => $account->city,
                'state' => $account->state,
                'retailer_type' => $account->retailer_type?->value,
                'retailer_type_label' => $account->retailer_type?->label(),
                'decision_maker' => $account->decision_maker,
                'phone' => $account->phone,
                'email' => $account->email,
                'service_community_link' => $account->service_community_link,
                'lead_source' => $account->lead_source?->label(),
                'engine' => $account->acquisition_engine?->label(),
                'stage' => $account->pipeline_stage->value,
                'stage_label' => $account->pipeline_stage->label(),
                'owner' => $account->owner?->name,
                'next_action' => $account->next_action,
                'next_action_date' => $account->next_action_date?->toDateString(),
                'lost_reason' => $account->lost_reason,
                'notes' => $account->notes,
                'signup_source' => $account->signup_source->value,
            ],
            'timeline' => $timeline,
            'stats' => [
                'total_units' => $account->orders->sum('quantity'),
                'total_revenue' => (float) $account->orders->sum(fn ($o) => (float) $o->revenue),
                'units_sold_reported' => $account->checkIns->sum('units_sold'),
                'last_check_in' => $account->checkIns->max('date')?->toDateString(),
            ],
            'velocity' => $kpis->accountVelocity($account),
            'stages' => collect(PipelineStage::ordered())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'options' => $this->options(),
        ]);
    }

    public function edit(Account $account): Response
    {
        return Inertia::render('Accounts/Form', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'city' => $account->city,
                'state' => $account->state,
                'retailer_type' => $account->retailer_type?->value,
                'decision_maker' => $account->decision_maker,
                'phone' => $account->phone,
                'email' => $account->email,
                'service_community_link' => $account->service_community_link,
                'lead_source' => $account->lead_source?->value,
                'acquisition_engine' => $account->acquisition_engine->value,
                'owner_id' => $account->owner_id,
                'next_action' => $account->next_action,
                'next_action_date' => $account->next_action_date?->toDateString(),
                'notes' => $account->notes,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($this->validated($request));

        return redirect()->route('accounts.show', $account)->with('success', 'Account updated.');
    }

    public function updateStage(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::enum(PipelineStage::class)],
            'note' => ['nullable', 'string', 'max:255'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $account->transitionNote = $data['note'] ?? null;
        $account->update([
            'pipeline_stage' => $data['stage'],
            'lost_reason' => $data['stage'] === 'lost' ? ($data['lost_reason'] ?? $account->lost_reason) : null,
        ]);

        return back()->with('success', 'Stage updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'retailer_type' => ['nullable', Rule::enum(RetailerType::class)],
            'decision_maker' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'service_community_link' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['nullable', Rule::enum(LeadSource::class)],
            'acquisition_engine' => ['required', Rule::enum(AcquisitionEngine::class)],
            'owner_id' => ['nullable', 'exists:users,id'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function options(): array
    {
        return [
            'retailer_types' => collect(RetailerType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
            'lead_sources' => collect(LeadSource::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
            'engines' => collect(AcquisitionEngine::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
            'owners' => User::where('role', 'founder')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
