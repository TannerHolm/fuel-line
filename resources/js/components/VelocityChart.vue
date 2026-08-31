<script setup lang="ts">
import { computed } from 'vue';

interface Point {
    date: string;
    units_sold: number | null;
    units_on_hand: number | null;
    source: string;
}

const props = defineProps<{ series: Point[]; height?: number }>();

const H = computed(() => props.height ?? 180);
const W = 100; // viewBox units; the SVG scales to its container
const PAD_T = 8;
const PAD_B = 18;

const plotH = computed(() => H.value - PAD_T - PAD_B);

const max = computed(() => {
    const values = props.series.flatMap((p) => [p.units_sold ?? 0, p.units_on_hand ?? 0]);
    return Math.max(...values, 1);
});

const y = (v: number) => PAD_T + plotH.value - (v / max.value) * plotH.value;

// One bar slot per check-in, evenly spaced.
const slotW = computed(() => W / Math.max(props.series.length, 1));

// Cap the bar width so two check-ins don't render as giant slabs.
const barW = computed(() => Math.min(slotW.value * 0.44, 7));

const bars = computed(() =>
    props.series.map((p, i) => {
        const sold = p.units_sold ?? 0;
        const top = y(sold);
        return {
            x: i * slotW.value + (slotW.value - barW.value) / 2,
            w: barW.value,
            y: top,
            h: Math.max(PAD_T + plotH.value - top, sold > 0 ? 1 : 0),
            sold,
            date: p.date,
            retailer: p.source === 'retailer',
        };
    }),
);

// On-hand line rides across the bar centres.
const onHandPath = computed(() => {
    const pts = props.series
        .map((p, i) => ({ v: p.units_on_hand, x: i * slotW.value + slotW.value / 2 }))
        .filter((p): p is { v: number; x: number } => p.v !== null);

    if (pts.length === 0) return null;
    if (pts.length === 1) return { line: null, dot: { cx: pts[0].x, cy: y(pts[0].v) } };

    return {
        line: pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(2)},${y(p.v).toFixed(2)}`).join(' '),
        dot: null,
    };
});

const gridLines = computed(() => [0, 0.5, 1].map((f) => ({ y: y(max.value * f), label: Math.round(max.value * f) })));

const short = (d: string) => d.slice(5).replace('-', '/');
</script>

<template>
    <div>
        <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="w-full" :style="{ height: H + 'px' }">
            <!-- hairline grid -->
            <g>
                <line
                    v-for="g in gridLines"
                    :key="g.label"
                    x1="0"
                    :y1="g.y"
                    :x2="W"
                    :y2="g.y"
                    stroke="rgba(255,255,255,0.10)"
                    stroke-width="0.5"
                    vector-effect="non-scaling-stroke"
                />
            </g>

            <!-- units sold per check-in -->
            <rect
                v-for="(b, i) in bars"
                :key="'b' + i"
                :x="b.x"
                :y="b.y"
                :width="b.w"
                :height="b.h"
                :fill="b.retailer ? '#A89B7C' : '#C8102E'"
            />

            <!-- units on hand -->
            <path
                v-if="onHandPath?.line"
                :d="onHandPath.line"
                fill="none"
                stroke="#FFFFFF"
                stroke-width="1.5"
                stroke-opacity="0.85"
                vector-effect="non-scaling-stroke"
            />
            <circle v-if="onHandPath?.dot" :cx="onHandPath.dot.cx" :cy="onHandPath.dot.cy" r="1.5" fill="#fff" />
        </svg>

        <!-- axis labels sit outside the stretched SVG so type isn't distorted -->
        <div class="mt-1 flex justify-between">
            <span v-for="(p, i) in series" :key="i" class="ff-mono text-[10px] text-white/[0.4]">{{ short(p.date) }}</span>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1">
            <span class="ff-status text-white/[0.55]"><span class="ff-dot" style="background: #c8102e"></span>Units sold</span>
            <span class="ff-status text-white/[0.55]"><span class="ff-dot" style="background: #a89b7c"></span>Retailer-reported</span>
            <span class="ff-status text-white/[0.55]"><span class="ff-dot" style="background: #fff"></span>On hand</span>
            <span class="ff-mono ml-auto text-[10px] text-white/[0.35]">peak {{ max }} units</span>
        </div>
    </div>
</template>
