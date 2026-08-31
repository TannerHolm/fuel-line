<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Enums\PipelineStage;
use App\Models\Account;
use App\Models\PricingTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The wholesale-partner portal: scoped to exactly one account. Retailers see
 * their own orders, invoices, buyback status, and the self-report form —
 * never other accounts, other retailers' pricing, or internal CRM notes.
 */
class PortalController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $this->accountFor($request);

        abort_unless($account !== null, 404);

        $account->load(['orders' => fn ($q) => $q->latest('date'), 'buybackAgreements', 'checkIns' => fn ($q) => $q->latest('date')->limit(5)]);

        $hasOpening = $account->orders->contains(fn ($o) => $o->type === OrderType::Opening);

        return Inertia::render('Portal/Index', [
            'account' => [
                'name' => $account->name,
                'city' => $account->city,
                'state' => $account->state,
            ],
            'orders' => $account->orders->map(fn ($o) => [
                'id' => $o->id,
                'type' => $o->type->value,
                'date' => $o->date->toDateString(),
                'quantity' => $o->quantity,
                'tier' => $o->tier,
                'unit_price' => $o->unit_price !== null ? (float) $o->unit_price : null,
                'revenue' => $o->revenue !== null ? (float) $o->revenue : null,
                'payment_status' => $o->payment_status->value,
                'fulfilled_at' => $o->fulfilled_at?->toDateString(),
            ]),
            'buybackSigned' => $account->buybackAgreements->isNotEmpty(),
            'hasOpening' => $hasOpening,
            'recentReports' => $account->checkIns->map(fn ($c) => [
                'date' => $c->date->toDateString(),
                'units_sold' => $c->units_sold,
                'units_on_hand' => $c->units_on_hand,
            ]),
            'isFounderPreview' => $request->user()->isFounder(),
        ]);
    }

    public function orderForm(Request $request): Response
    {
        $account = $this->accountFor($request);
        abort_unless($account !== null, 404);

        $needsBuyback = $account->buybackAgreements()->count() === 0
            && ! $account->orders()->where('type', 'opening')->exists();

        return Inertia::render('Portal/Order', [
            'account' => ['name' => $account->name],
            'tiers' => PricingTier::orderBy('sort')->get()->map(fn ($t) => [
                'key' => $t->key,
                'name' => $t->name,
                'min_qty' => $t->min_qty,
                'max_qty' => $t->max_qty,
                'unit_price' => $t->unit_price !== null ? (float) $t->unit_price : null,
                'self_serve' => $t->self_serve,
            ]),
            'guardrails' => [
                'minimum' => config('fuelline.minimum_order_units'),
                'increment' => config('fuelline.order_increment'),
                'msrp' => config('fuelline.msrp'),
            ],
            'needsBuyback' => $needsBuyback,
            'buybackTerms' => $needsBuyback ? config('fuelline.buyback_terms') : null,
            'buybackVersion' => $needsBuyback ? $this->buybackVersion() : null,
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $account = $this->accountFor($request);
        abort_unless($account !== null, 404);

        $min = config('fuelline.minimum_order_units');
        $inc = config('fuelline.order_increment');

        $data = $request->validate([
            'quantity' => ['required', 'integer', "min:{$min}", 'max:249', fn ($attr, $value, $fail) => $value % $inc === 0 ?: $fail("Orders must be in increments of {$inc}.")],
            'signer_name' => ['nullable', 'string', 'max:120'],
            'signer_title' => ['nullable', 'string', 'max:120'],
            'agree' => ['nullable', 'boolean'],
        ]);

        $isFirstOrder = ! $account->orders()->where('type', 'opening')->exists();
        $needsBuyback = $isFirstOrder && $account->buybackAgreements()->count() === 0;

        if ($needsBuyback) {
            $request->validate([
                'signer_name' => ['required', 'string', 'max:120'],
                'agree' => ['required', 'accepted'],
            ], [
                'signer_name.required' => 'Type your full name to sign the buyback agreement.',
                'agree.accepted' => 'The buyback agreement must be accepted before a pilot order can submit.',
            ]);
        }

        $tier = PricingTier::forQuantity((int) $data['quantity']);
        abort_unless($tier !== null && $tier->self_serve && $tier->unit_price !== null, 422);

        DB::transaction(function () use ($request, $account, $data, $tier, $isFirstOrder, $needsBuyback) {
            $order = $account->orders()->create([
                'type' => $isFirstOrder ? 'opening' : 'reorder',
                'date' => now()->toDateString(),
                'quantity' => $data['quantity'],
                'tier' => $tier->key,
                'unit_price' => $tier->unit_price,
                'revenue' => round((float) $tier->unit_price * $data['quantity'], 2),
                'payment_status' => 'pending',
            ]);

            if ($needsBuyback) {
                $account->buybackAgreements()->create([
                    'order_id' => $order->id,
                    'agreement_version' => $this->buybackVersion(),
                    'signer_name' => $data['signer_name'],
                    'signer_title' => $data['signer_title'] ?? null,
                    'signed_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
            }

            if ($isFirstOrder && in_array($account->pipeline_stage, [
                PipelineStage::QualifiedProspect, PipelineStage::Contacted,
                PipelineStage::Sampled, PipelineStage::Interested,
            ])) {
                $account->update(['pipeline_stage' => PipelineStage::OpeningOrder]);
            } elseif (! $isFirstOrder && in_array($account->pipeline_stage, [PipelineStage::OpeningOrder, PipelineStage::Selling])) {
                $account->update(['pipeline_stage' => PipelineStage::Reordered]);
            }

            $account->update([
                'next_action' => 'Self-service order placed — send invoice',
                'next_action_date' => now(),
            ]);

            // Shopify owns the money: mirror this as a Draft Order + invoice
            // (no-op until credentials exist; a founder invoices manually meanwhile).
            dispatch(function () use ($account, $order) {
                $shopify = app(\App\Services\ShopifyService::class);
                $shopify->pushCustomer($account->fresh());
                $shopify->pushDraftOrder($order->fresh());
            })->afterResponse();
        });

        return redirect()->route('portal')->with('success', 'Order submitted. Your invoice will follow by email.');
    }

    public function report(Request $request): RedirectResponse
    {
        $account = $this->accountFor($request);
        abort_unless($account !== null, 404);

        $data = $request->validate([
            'units_sold' => ['required', 'integer', 'min:0'],
            'units_on_hand' => ['required', 'integer', 'min:0'],
            'flavors_moving' => ['nullable', 'string', 'max:255'],
            'ready_for_reorder' => ['boolean'],
        ]);

        $account->checkIns()->create([
            'date' => now()->toDateString(),
            'cadence' => 'adhoc',
            'units_sold' => $data['units_sold'],
            'units_on_hand' => $data['units_on_hand'],
            'flavors_moving' => filled($data['flavors_moving'] ?? null)
                ? array_values(array_filter(array_map('trim', explode(',', $data['flavors_moving']))))
                : null,
            'ready_for_reorder' => $data['ready_for_reorder'] ?? false,
            'source' => 'retailer',
            'user_id' => $request->user()->id,
        ]);

        if ($data['ready_for_reorder'] ?? false) {
            $account->update([
                'next_action' => 'Retailer requested reorder',
                'next_action_date' => now(),
            ]);
        }

        return back()->with('success', 'Report received. Thanks — this keeps your account priced right.');
    }

    private function accountFor(Request $request): ?Account
    {
        $user = $request->user();

        if ($user->isFounder()) {
            // Founders can preview any account's portal via ?account=ID.
            $id = $request->integer('account');

            return $id ? Account::find($id) : Account::first();
        }

        return $user->account;
    }

    private function buybackVersion(): string
    {
        return 'v1-'.substr(sha1(config('fuelline.buyback_terms')), 0, 8);
    }
}
