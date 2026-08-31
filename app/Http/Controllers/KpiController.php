<?php

namespace App\Http\Controllers;

use App\Models\KpiSnapshot;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KpiController extends Controller
{
    public function index(Request $request, KpiService $kpis): Response
    {
        $filters = $request->only(['retailer_type', 'engine', 'state']);

        $segmentKey = 'all';
        if ($filters['retailer_type'] ?? null) {
            $segmentKey = 'type:'.$filters['retailer_type'];
        } elseif ($filters['engine'] ?? null) {
            $segmentKey = 'engine:'.$filters['engine'];
        }

        return Inertia::render('Kpis/Index', [
            'kpis' => $kpis->compute($filters),
            'velocity' => $kpis->velocityLeaderboard($filters),
            'filters' => $filters,
            'trend' => KpiSnapshot::where('segment_key', $segmentKey)
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
        ]);
    }
}
