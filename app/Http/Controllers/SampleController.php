<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Enums\SampleRecipient;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SampleController extends Controller
{
    public function store(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'pucks_given' => ['required', 'integer', 'min:1'],
            'given_to' => ['required', Rule::enum(SampleRecipient::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $account->samples()->create($data + ['user_id' => $request->user()->id]);

        // A sample naturally advances early-stage accounts.
        if (in_array($account->pipeline_stage, [PipelineStage::QualifiedProspect, PipelineStage::Contacted])) {
            $account->update(['pipeline_stage' => PipelineStage::Sampled]);
        }

        return back()->with('success', 'Sample logged.');
    }
}
