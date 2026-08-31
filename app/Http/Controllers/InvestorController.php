<?php

namespace App\Http\Controllers;

use App\Models\KpiSnapshot;
use App\Services\KpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Day-90 scorecard behind a signed, expiring link. No account list,
 * no contact info, no raw economics, no edit access. Regenerate per readout,
 * revoke by rotating APP_KEY if ever needed.
 */
class InvestorController extends Controller
{
    public function generateLink(Request $request): RedirectResponse
    {
        $days = (int) $request->input('days', 14);

        $url = URL::temporarySignedRoute('scorecard', now()->addDays($days));

        return back()->with('success', "Investor link (valid {$days} days): {$url}");
    }

    public function scorecard(Request $request, KpiService $kpis): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        return Inertia::render('Scorecard', [
            'kpis' => $kpis->compute(),
            'trend' => KpiSnapshot::where('segment_key', 'all')
                ->orderBy('week_of')
                ->get()
                ->map(fn ($s) => [
                    'week_of' => $s->week_of->toDateString(),
                    'qualified_conversations' => $s->qualified_conversations,
                    'opening_conversion_pct' => $s->opening_conversion_pct !== null ? (float) $s->opening_conversion_pct : null,
                    'avg_opening_order' => $s->avg_opening_order !== null ? (float) $s->avg_opening_order : null,
                    'units_per_store_week' => $s->units_per_store_week !== null ? (float) $s->units_per_store_week : null,
                    'avg_days_to_reorder' => $s->avg_days_to_reorder !== null ? (float) $s->avg_days_to_reorder : null,
                    'reorder_rate_pct' => $s->reorder_rate_pct !== null ? (float) $s->reorder_rate_pct : null,
                ]),
            'generatedAt' => now()->toDateString(),
        ]);
    }
}
