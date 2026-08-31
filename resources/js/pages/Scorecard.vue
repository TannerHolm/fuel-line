<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface TrendPoint {
    week_of: string;
    qualified_conversations: number | null;
    opening_conversion_pct: number | null;
    avg_opening_order: number | null;
    units_per_store_week: number | null;
    avg_days_to_reorder: number | null;
    reorder_rate_pct: number | null;
}

const props = defineProps<{
    kpis: Record<string, any>;
    trend: TrendPoint[];
    generatedAt: string;
}>();

const fmt = (v: number | null | undefined, opts: { money?: boolean; pct?: boolean } = {}) => {
    if (v === null || v === undefined) return '—';
    if (opts.money) return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (opts.pct) return v + '%';
    return String(v);
};

const tiles = computed(() => [
    { label: 'Qualified retailer conversations', value: fmt(props.kpis.qualified_conversations), key: 'qualified_conversations' as const },
    { label: 'Opening-order conversion', value: fmt(props.kpis.opening_conversion_pct, { pct: true }), key: 'opening_conversion_pct' as const },
    { label: 'Average opening order', value: fmt(props.kpis.avg_opening_order, { money: true }), key: 'avg_opening_order' as const },
    { label: 'Units per store per week', value: fmt(props.kpis.units_per_store_week), key: 'units_per_store_week' as const },
    { label: 'Days to first reorder', value: fmt(props.kpis.avg_days_to_reorder), key: 'avg_days_to_reorder' as const },
    { label: 'Wholesale reorder rate', value: fmt(props.kpis.reorder_rate_pct, { pct: true }), key: 'reorder_rate_pct' as const },
]);

function sparkline(key: keyof TrendPoint): string | null {
    const values = props.trend.map((t) => t[key]).filter((v): v is number => v !== null && typeof v === 'number');
    if (values.length < 2) return null;
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    return values
        .map((v, i) => `${((i / (values.length - 1)) * 100).toFixed(1)},${(28 - ((v - min) / range) * 24 + 2).toFixed(1)}`)
        .join(' ');
}
</script>

<template>
    <Head title="Day-90 Scorecard" />
    <div class="min-h-screen bg-ink text-white">
        <header class="flex items-center justify-between gap-6 border-b border-white/[0.18] px-5 py-4 sm:px-10">
            <img src="/images/logo-horizontal-white.png" alt="Freedom Fuel" class="block h-6 w-auto" />
            <span class="ff-label-sm text-white/[0.55]">Read-only · Generated {{ generatedAt }}</span>
        </header>

        <main class="mx-auto max-w-4xl px-5 pb-20 pt-10 sm:px-10">
            <div class="ff-label text-ff-red-bright">Wholesale validation · Evidence, not vision</div>
            <h1 class="ff-display mt-3 text-5xl">Scorecard</h1>
            <p class="mt-4 max-w-xl text-[14px] leading-relaxed text-white/[0.72]">
                The six primary KPIs from the 90-day wholesale validation sprint, computed from dated events logged at
                the account level. Trend lines build weekly as snapshots accumulate.
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="tile in tiles" :key="tile.label" class="ff-card px-5 py-5">
                    <div class="ff-label-sm text-white/[0.55]">{{ tile.label }}</div>
                    <div class="ff-display mt-2.5 text-[34px]">{{ tile.value }}</div>
                    <svg v-if="sparkline(tile.key)" viewBox="0 0 100 32" preserveAspectRatio="none" class="mt-3 h-8 w-full">
                        <polyline :points="sparkline(tile.key)!" fill="none" stroke="#E63946" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                    </svg>
                    <div v-else class="mt-3 flex h-8 items-center"><div class="h-px w-full bg-white/[0.1]"></div></div>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="ff-card px-5 py-4">
                    <div class="ff-label-sm text-white/[0.55]">Accounts on record</div>
                    <div class="ff-display mt-2 text-[24px]">{{ kpis.meta.accounts_total }}</div>
                </div>
                <div class="ff-card px-5 py-4">
                    <div class="ff-label-sm text-white/[0.55]">Live &amp; selling</div>
                    <div class="ff-display mt-2 text-[24px]">{{ kpis.meta.accounts_live }}</div>
                </div>
                <div class="ff-card px-5 py-4">
                    <div class="ff-label-sm text-white/[0.55]">Reorder-eligible ({{ kpis.meta.maturity_days }}d+)</div>
                    <div class="ff-display mt-2 text-[24px]">{{ kpis.meta.accounts_mature }}</div>
                </div>
            </div>

            <div class="mt-8 flex items-center gap-6 bg-ff-tan px-5 py-2.5 text-ink">
                <span class="ff-label tracking-[0.22em]">Method</span>
                <span class="text-[13px] leading-snug">
                    Every number derives from dated events — stage changes, orders, check-ins — recorded with consistent
                    definitions. Nothing on this page is hand-entered.
                </span>
            </div>
        </main>
    </div>
</template>
