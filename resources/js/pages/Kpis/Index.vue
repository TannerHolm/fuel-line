<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface TrendPoint {
    week_of: string;
    qualified_conversations: number | null;
    opening_conversion_pct: number | null;
    avg_opening_order: number | null;
    units_per_store_week: number | null;
    avg_days_to_reorder: number | null;
    reorder_rate_pct: number | null;
}

interface VelocityRow {
    id: number;
    name: string;
    city: string | null;
    state: string | null;
    stage: string;
    weekly: number | null;
    on_hand: number | null;
    days_of_cover: number | null;
    total_sold: number;
    days_live: number | null;
}

const props = defineProps<{
    velocity: VelocityRow[];
    kpis: Record<string, any>;
    filters: { retailer_type?: string; engine?: string; state?: string };
    trend: TrendPoint[];
}>();

const retailerType = ref(props.filters.retailer_type ?? '');
const engine = ref(props.filters.engine ?? '');

function applyFilters() {
    router.get(
        '/kpis',
        { retailer_type: retailerType.value || undefined, engine: engine.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
}

const maxWeekly = computed(() => Math.max(...props.velocity.map((r) => r.weekly ?? 0), 0));

const fmt = (v: number | null | undefined, opts: { money?: boolean; pct?: boolean; suffix?: string } = {}) => {
    if (v === null || v === undefined) return '—';
    if (opts.money) return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (opts.pct) return v + '%';
    return String(v) + (opts.suffix ?? '');
};

const tiles = computed(() => [
    {
        label: 'Qualified conversations',
        value: fmt(props.kpis.qualified_conversations),
        sub: 'Reached "Contacted" or beyond',
        key: 'qualified_conversations' as const,
    },
    {
        label: 'Opening-order conversion',
        value: fmt(props.kpis.opening_conversion_pct, { pct: true }),
        sub: `${props.kpis.meta.opening_orders} opening orders`,
        key: 'opening_conversion_pct' as const,
    },
    {
        label: 'Avg. opening order',
        value: fmt(props.kpis.avg_opening_order, { money: true }),
        sub: 'Revenue per first order',
        key: 'avg_opening_order' as const,
    },
    {
        label: 'Units / store / week',
        value: fmt(props.kpis.units_per_store_week),
        sub: `${props.kpis.meta.accounts_live} live accounts`,
        key: 'units_per_store_week' as const,
    },
    {
        label: 'Days to first reorder',
        value: fmt(props.kpis.avg_days_to_reorder),
        sub: 'Opening order → first reorder',
        key: 'avg_days_to_reorder' as const,
    },
    {
        label: 'Reorder rate',
        value: fmt(props.kpis.reorder_rate_pct, { pct: true }),
        sub: `${props.kpis.meta.accounts_mature} mature (≥${props.kpis.meta.maturity_days}d live)`,
        key: 'reorder_rate_pct' as const,
    },
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
    <Head title="KPIs" />
    <FFLayout>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="ff-display text-4xl">Scorecard</h1>
                <p class="mt-1.5 max-w-xl text-white/[0.55]">
                    The six primary KPIs from the 90-Day Plan, computed from logged events — not entered by hand. Weekly trend fills in as
                    nightly snapshots accumulate.
                </p>
            </div>
            <div class="flex gap-3">
                <select v-model="retailerType" class="ff-input ff-input-sm w-full sm:w-auto" @change="applyFilters">
                    <option value="">All types</option>
                    <option value="service">Service</option>
                    <option value="performance">Performance</option>
                    <option value="convenience">Convenience</option>
                </select>
                <select v-model="engine" class="ff-input ff-input-sm w-full sm:w-auto" @change="applyFilters">
                    <option value="">Both engines</option>
                    <option value="direct">Direct outbound</option>
                    <option value="seeded">Demand-seeded</option>
                </select>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="tile in tiles" :key="tile.label" class="ff-card px-5 py-5">
                <div class="ff-label-sm text-white/[0.55]">{{ tile.label }}</div>
                <div class="ff-display mt-2.5 text-[34px]">{{ tile.value }}</div>
                <div class="mt-1.5 text-[12px] text-white/[0.55]">{{ tile.sub }}</div>
                <svg v-if="sparkline(tile.key)" viewBox="0 0 100 32" preserveAspectRatio="none" class="mt-3 h-8 w-full">
                    <polyline
                        :points="sparkline(tile.key)!"
                        fill="none"
                        stroke="#E63946"
                        stroke-width="1.5"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>
                <div v-else class="mt-3 flex h-8 items-center">
                    <div class="h-px w-full bg-white/[0.1]"></div>
                </div>
            </div>
        </div>

        <div class="mt-5 flex items-center gap-6 bg-ff-tan px-5 py-2.5 text-ink">
            <span class="ff-label tracking-[0.22em]">Consistency rule</span>
            <span class="text-[13px] leading-snug">
                Reorder-eligible means live ≥ {{ kpis.meta.maturity_days }} days. Set once in config — never a judgment call made fresh each
                week.
            </span>
        </div>

        <!-- Velocity: which shelves actually move product -->
        <div class="ff-card mt-5">
            <div class="flex items-center justify-between border-b border-white/[0.18] px-5 py-4">
                <span class="ff-display text-xl">Velocity by store</span>
                <span class="ff-label-sm text-white/[0.55]">{{ velocity.length }} live</span>
            </div>

            <div v-if="velocity.length === 0" class="px-5 py-8 text-center text-[13px] text-white/[0.55]">
                No live accounts yet. Velocity starts when an opening order is fulfilled.
            </div>

            <div
                v-for="row in velocity"
                :key="row.id"
                class="flex items-center gap-4 border-b border-white/[0.1] px-5 py-3.5 last:border-b-0"
            >
                <Link :href="`/accounts/${row.id}`" class="w-52 flex-none no-underline">
                    <div class="text-sm text-white hover:text-ff-red-bright">{{ row.name }}</div>
                    <div class="ff-label-sm mt-0.5 text-white/[0.4]">{{ [row.city, row.state].filter(Boolean).join(', ') || '—' }}</div>
                </Link>

                <!-- bar is proportional to the fastest-moving store -->
                <div class="h-2.5 min-w-0 flex-1 bg-white/[0.06]">
                    <div
                        class="h-2.5 bg-ff-red"
                        :style="{ width: (row.weekly && maxWeekly ? Math.max((row.weekly / maxWeekly) * 100, 2) : 0) + '%' }"
                    ></div>
                </div>

                <div class="w-24 flex-none text-right">
                    <span class="ff-display text-lg">{{ row.weekly ?? '—' }}</span>
                    <span class="ff-label-sm ml-1 text-white/[0.4]">/wk</span>
                </div>
                <div class="w-28 flex-none text-right">
                    <span
                        class="ff-status justify-end"
                        :class="row.days_of_cover !== null && row.days_of_cover <= 14 ? 'text-ff-warning' : 'text-white/[0.55]'"
                    >
                        <span class="ff-dot"></span>
                        {{ row.days_of_cover !== null ? row.days_of_cover + 'd cover' : 'no check-ins' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
            <p class="max-w-lg text-[13px] text-white/[0.55]">
                Need a Darrell-ready readout? Generate a read-only link — it expires in 14 days and shows no account
                names, contacts, or per-account economics.
            </p>
            <button type="button" class="ff-btn ff-btn-secondary" @click="router.post('/kpis/investor-link', {}, { preserveScroll: true })">
                Generate investor link
            </button>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="ff-card px-5 py-4">
                <div class="ff-label-sm text-white/[0.55]">Accounts in segment</div>
                <div class="ff-display mt-2 text-[24px]">{{ kpis.meta.accounts_total }}</div>
            </div>
            <div class="ff-card px-5 py-4">
                <div class="ff-label-sm text-white/[0.55]">Live (opening order fulfilled)</div>
                <div class="ff-display mt-2 text-[24px]">{{ kpis.meta.accounts_live }}</div>
            </div>
            <div class="ff-card px-5 py-4">
                <div class="ff-label-sm text-white/[0.55]">Mature (reorder-eligible)</div>
                <div class="ff-display mt-2 text-[24px]">{{ kpis.meta.accounts_mature }}</div>
            </div>
        </div>
    </FFLayout>
</template>
