<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PipelineStage;
use App\Models\Account;
use App\Models\PricingTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function store(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(OrderType::class)],
            'date' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'freight' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'fulfilled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $tier = PricingTier::forQuantity((int) $data['quantity']);
        $unitPrice = $data['unit_price'] ?? ($tier?->unit_price !== null ? (float) $tier->unit_price : null);

        $account->orders()->create([
            ...$data,
            'tier' => $tier?->key,
            'unit_price' => $unitPrice,
            'revenue' => $unitPrice !== null ? round($unitPrice * $data['quantity'], 2) : null,
        ]);

        // Orders advance the pipeline automatically.
        if ($data['type'] === 'opening'
            && in_array($account->pipeline_stage, [PipelineStage::QualifiedProspect, PipelineStage::Contacted, PipelineStage::Sampled, PipelineStage::Interested])) {
            $account->update(['pipeline_stage' => ! empty($data['fulfilled_at']) ? PipelineStage::Selling : PipelineStage::OpeningOrder]);
        } elseif ($data['type'] === 'reorder'
            && in_array($account->pipeline_stage, [PipelineStage::OpeningOrder, PipelineStage::Selling])) {
            $account->update(['pipeline_stage' => PipelineStage::Reordered]);
        } elseif ($data['type'] === 'reorder' && $account->pipeline_stage === PipelineStage::Reordered
            && $account->orders()->where('type', 'reorder')->count() >= 2) {
            $account->update(['pipeline_stage' => PipelineStage::RepeatAccount]);
        }

        return back()->with('success', 'Order logged.');
    }
}
