<?php

namespace App\Http\Controllers;

use App\Models\PricingTier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user?->isFounder()) {
            return redirect()->route('pipeline');
        }
        if ($user) {
            return redirect()->route('portal');
        }

        return Inertia::render('Landing', [
            'tiers' => PricingTier::orderBy('sort')->get()->map(fn ($t) => [
                'key' => $t->key,
                'name' => $t->name,
                'min_qty' => $t->min_qty,
                'max_qty' => $t->max_qty,
                'unit_price' => $t->unit_price !== null ? (float) $t->unit_price : null,
                'margin_pct' => $t->unit_price !== null
                    ? round((config('fuelline.msrp') - (float) $t->unit_price) / config('fuelline.msrp') * 100, 1)
                    : null,
            ]),
            'msrp' => config('fuelline.msrp'),
        ]);
    }
}
