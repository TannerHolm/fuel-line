<?php

namespace App\Console\Commands;

use App\Enums\AcquisitionEngine;
use App\Enums\RetailerType;
use App\Models\KpiSnapshot;
use App\Services\KpiService;
use Illuminate\Console\Command;

class ComputeKpiSnapshots extends Command
{
    protected $signature = 'fuelline:compute-kpis {--week=}';

    protected $description = 'Compute and store weekly KPI snapshots (overall + per segment)';

    public function handle(KpiService $kpis): int
    {
        $weekOf = $this->option('week')
            ? now()->parse($this->option('week'))->startOfWeek()
            : now()->startOfWeek();

        $segments = ['all' => []];

        foreach (RetailerType::cases() as $type) {
            $segments['type:'.$type->value] = ['retailer_type' => $type->value];
        }
        foreach (AcquisitionEngine::cases() as $engine) {
            $segments['engine:'.$engine->value] = ['engine' => $engine->value];
        }

        foreach ($segments as $key => $filters) {
            $result = $kpis->compute($filters);

            KpiSnapshot::updateOrCreate(
                ['week_of' => $weekOf->toDateString(), 'segment_key' => $key],
                [
                    'qualified_conversations' => $result['qualified_conversations'],
                    'opening_conversion_pct' => $result['opening_conversion_pct'],
                    'avg_opening_order' => $result['avg_opening_order'],
                    'units_per_store_week' => $result['units_per_store_week'],
                    'avg_days_to_reorder' => $result['avg_days_to_reorder'],
                    'reorder_rate_pct' => $result['reorder_rate_pct'],
                    'computed_at' => now(),
                ]
            );

            $this->info("Snapshot [$key] for {$weekOf->toDateString()} stored.");
        }

        return self::SUCCESS;
    }
}
