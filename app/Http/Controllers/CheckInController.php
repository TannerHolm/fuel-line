<?php

namespace App\Http\Controllers;

use App\Enums\CheckInWindow;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckInController extends Controller
{
    public function store(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'cadence' => ['required', Rule::enum(CheckInWindow::class)],
            'units_sold' => ['nullable', 'integer', 'min:0'],
            'units_on_hand' => ['nullable', 'integer', 'min:0'],
            'flavors_moving' => ['nullable', 'string', 'max:255'],
            'staff_recommends' => ['nullable', 'boolean'],
            'buyer_profile' => ['nullable', 'string'],
            'objections' => ['nullable', 'string'],
            'ready_for_reorder' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['flavors_moving'] = isset($data['flavors_moving']) && $data['flavors_moving'] !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $data['flavors_moving']))))
            : null;

        $account->checkIns()->create($data + [
            'source' => 'founder',
            'user_id' => $request->user()->id,
        ]);

        if ($data['ready_for_reorder'] ?? false) {
            $account->update([
                'next_action' => 'Retailer ready to reorder — send invoice',
                'next_action_date' => now(),
            ]);
        }

        return back()->with('success', 'Check-in logged.');
    }
}
