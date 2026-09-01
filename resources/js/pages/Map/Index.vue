<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface MapAccount {
    id: number;
    name: string;
    city: string | null;
    state: string | null;
    lat: number;
    lng: number;
    stage: string;
    stage_label: string;
    retailer_type: string | null;
    engine: string | null;
    next_action: string | null;
    next_action_date: string | null;
    overdue: boolean;
}

const props = defineProps<{
    accounts: MapAccount[];
    unlocatedCount: number;
    filters: { retailer_type?: string; engine?: string };
}>();

const retailerType = ref(props.filters.retailer_type ?? '');
const engine = ref(props.filters.engine ?? '');

function applyFilters() {
    router.get(
        '/map',
        { retailer_type: retailerType.value || undefined, engine: engine.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
}

// Selling green, in-motion amber, prospecting white, lost gray. Overdue rings red.
const stageColor = (stage: string): string => {
    if (['selling', 'reordered', 'repeat_account'].includes(stage)) return '#3ED660';
    if (['opening_order', 'interested'].includes(stage)) return '#EE9441';
    if (stage === 'lost') return 'rgba(255,255,255,0.35)';
    return '#FFFFFF';
};

const legend = [
    { color: '#3ED660', label: 'Selling / reordered' },
    { color: '#EE9441', label: 'Interested / opening order' },
    { color: '#FFFFFF', label: 'Prospecting' },
    { color: 'rgba(255,255,255,0.35)', label: 'Lost' },
    { color: '#E63946', label: 'Ring = action overdue', ring: true },
];

// City-level pins stack; spread same-coordinate accounts in a small circle.
const spread = computed(() => {
    const groups = new Map<string, MapAccount[]>();
    for (const a of props.accounts) {
        const key = `${a.lat},${a.lng}`;
        groups.set(key, [...(groups.get(key) ?? []), a]);
    }
    const out: (MapAccount & { dlat: number; dlng: number })[] = [];
    for (const group of groups.values()) {
        group.forEach((a, i) => {
            if (group.length === 1) {
                out.push({ ...a, dlat: a.lat, dlng: a.lng });
            } else {
                const angle = (2 * Math.PI * i) / group.length;
                out.push({ ...a, dlat: a.lat + 0.012 * Math.sin(angle), dlng: a.lng + 0.015 * Math.cos(angle) });
            }
        });
    }
    return out;
});

const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;

function escapeHtml(s: string): string {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
}

function renderMarkers() {
    if (!map) return;
    markerLayer?.remove();
    markerLayer = L.layerGroup().addTo(map);

    for (const a of spread.value) {
        const marker = L.circleMarker([a.dlat, a.dlng], {
            radius: 8,
            fillColor: stageColor(a.stage),
            fillOpacity: 0.9,
            color: a.overdue ? '#E63946' : '#0D0F14',
            weight: a.overdue ? 3 : 1.5,
        });

        marker.bindPopup(
            `<div class="ff-map-popup">
                <a href="/accounts/${a.id}" class="ff-map-popup-name">${escapeHtml(a.name)}</a>
                <div class="ff-map-popup-meta">${escapeHtml([a.city, a.state].filter(Boolean).join(', '))} · ${escapeHtml(a.stage_label)}</div>
                ${a.next_action ? `<div class="ff-map-popup-action${a.overdue ? ' is-overdue' : ''}">${escapeHtml(a.next_action)}${a.next_action_date ? ` · ${a.next_action_date}` : ''}</div>` : ''}
            </div>`,
            { closeButton: false, offset: [0, -4] },
        );

        marker.addTo(markerLayer);
    }

    if (spread.value.length > 0) {
        map.fitBounds(
            L.latLngBounds(spread.value.map((a) => [a.dlat, a.dlng] as [number, number])),
            { padding: [50, 50], maxZoom: 11 },
        );
    }
}

onMounted(() => {
    map = L.map(mapEl.value!, { zoomControl: true, attributionControl: true });
    map.setView([37.1, -113.3], 8); // Freedom Fuel's home turf as the empty-state view

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Esri, HERE, Garmin, &copy; OpenStreetMap contributors',
        maxZoom: 16,
    }).addTo(map);

    renderMarkers();
});

watch(spread, renderMarkers);

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <Head title="Map" />
    <FFLayout>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="ff-display text-4xl">Territory</h1>
                <p class="mt-1.5 text-white/[0.55]">
                    {{ accounts.length }} accounts on the map<template v-if="unlocatedCount > 0">
                        · {{ unlocatedCount }} missing a city/state — run <span class="ff-mono text-[12px]">php artisan fuelline:geocode</span></template>.
                </p>
            </div>
            <div class="grid w-full grid-cols-2 gap-2.5 sm:flex sm:w-auto sm:gap-3">
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

        <div class="ff-card overflow-hidden">
            <div ref="mapEl" class="h-[60vh] min-h-[320px] w-full sm:h-[calc(100vh-280px)] sm:min-h-[420px]"></div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2">
            <span v-for="item in legend" :key="item.label" class="ff-status text-white/[0.72]">
                <span
                    class="block h-2.5 w-2.5 rounded-full"
                    :style="{
                        background: item.ring ? 'transparent' : item.color,
                        border: item.ring ? '2.5px solid #E63946' : '1px solid rgba(255,255,255,0.2)',
                    }"
                ></span>
                {{ item.label }}
            </span>
        </div>
    </FFLayout>
</template>

<style>
/* Leaflet chrome, restyled to the app's surfaces. Unscoped on purpose —
   Leaflet renders outside Vue's scope attribute. */
.leaflet-container {
    background: #07080b;
    font-family: 'Gotcha Gothic', 'Inter', sans-serif;
}
.leaflet-popup-content-wrapper {
    background: #12151c;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 0;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.45);
}
.leaflet-popup-tip {
    background: #12151c;
    border: 1px solid rgba(255, 255, 255, 0.18);
}
.leaflet-popup-content {
    margin: 12px 14px;
    line-height: 1.4;
}
.ff-map-popup-name {
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
}
.ff-map-popup-name:hover {
    color: #e63946;
}
.ff-map-popup-meta {
    margin-top: 3px;
    font-family: 'Thedus Condensed', 'Inter', sans-serif;
    font-weight: 700;
    font-size: 10px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.55);
}
.ff-map-popup-action {
    margin-top: 7px;
    padding-top: 7px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 12px;
    color: rgba(255, 255, 255, 0.72);
}
.ff-map-popup-action.is-overdue {
    color: #e63946;
}
.leaflet-bar a {
    background: #12151c;
    color: #fff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}
.leaflet-bar a:hover {
    background: #1a1f28;
    color: #fff;
}
.leaflet-bar {
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 2px 3px rgba(0, 0, 0, 0.2);
}
.leaflet-control-attribution {
    background: rgba(13, 15, 20, 0.8);
    color: rgba(255, 255, 255, 0.4);
    font-size: 10px;
}
.leaflet-control-attribution a {
    color: rgba(255, 255, 255, 0.55);
}
</style>
