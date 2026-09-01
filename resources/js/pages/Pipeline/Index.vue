<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface BoardCard {
    id: number;
    name: string;
    city: string | null;
    state: string | null;
    retailer_type: string | null;
    engine: string | null;
    owner: string | null;
    next_action: string | null;
    next_action_date: string | null;
    overdue: boolean;
    due_today: boolean;
    days_in_stage: number;
}

interface BoardColumn {
    value: string;
    label: string;
    accounts: BoardCard[];
}

const props = defineProps<{
    board: BoardColumn[];
    filters: { retailer_type?: string; engine?: string; state?: string; q?: string };
    states: string[];
}>();

const q = ref(props.filters.q ?? '');
const retailerType = ref(props.filters.retailer_type ?? '');
const engine = ref(props.filters.engine ?? '');
const state = ref(props.filters.state ?? '');

function applyFilters() {
    router.get(
        '/pipeline',
        {
            q: q.value || undefined,
            retailer_type: retailerType.value || undefined,
            engine: engine.value || undefined,
            state: state.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}

const totalAccounts = computed(() => props.board.reduce((n, col) => n + col.accounts.length, 0));

// --- drag & drop ---
const dragging = ref<number | null>(null);
const dragOver = ref<string | null>(null);

function onDrop(stage: string) {
    const id = dragging.value;
    dragging.value = null;
    dragOver.value = null;
    if (id == null) return;
    const from = props.board.find((c) => c.accounts.some((a) => a.id === id));
    if (from?.value === stage) return;
    router.patch(`/accounts/${id}/stage`, { stage }, { preserveScroll: true });
}

const typeShort: Record<string, string> = { service: 'SVC', performance: 'PERF', convenience: 'CONV' };
</script>

<template>
    <Head title="Pipeline" />
    <FFLayout>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="ff-display text-4xl">Pipeline</h1>
                <p class="mt-1.5 text-white/[0.55]">{{ totalAccounts }} accounts · drag a card to move its stage.</p>
            </div>
            <Link href="/accounts/create" class="ff-btn ff-btn-primary no-underline">New account</Link>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-2.5 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
            <input
                v-model="q"
                type="text"
                placeholder="Search name, city, contact"
                class="ff-input ff-input-sm w-full sm:w-60"
                @keyup.enter="applyFilters"
            />
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
            <select v-model="state" class="ff-input ff-input-sm w-full sm:w-auto" @change="applyFilters">
                <option value="">All states</option>
                <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
            </select>
            <button v-if="q || retailerType || engine || state" type="button" class="ff-btn ff-btn-ghost" @click="q = ''; retailerType = ''; engine = ''; state = ''; applyFilters()">
                Clear
            </button>
        </div>

        <div class="ff-rail -mx-5 snap-x snap-mandatory scroll-pl-5 overflow-x-auto px-5 pb-4 sm:-mx-8 sm:snap-none sm:scroll-pl-0 sm:px-8">
            <div class="flex min-w-max gap-3">
                <div
                    v-for="col in board"
                    :key="col.value"
                    class="w-[82vw] max-w-[248px] flex-none snap-start sm:w-[248px]"
                    @dragover.prevent="dragOver = col.value"
                    @dragleave="dragOver === col.value && (dragOver = null)"
                    @drop.prevent="onDrop(col.value)"
                >
                    <div
                        class="flex items-baseline justify-between border-b-2 pb-2"
                        :class="col.value === 'lost' ? 'border-white/[0.18]' : 'border-ff-red'"
                    >
                        <span class="ff-label" :class="col.value === 'lost' ? 'text-white/40' : 'text-white'">{{ col.label }}</span>
                        <span class="ff-mono text-[11px] text-white/[0.55]">{{ col.accounts.length }}</span>
                    </div>

                    <div
                        class="mt-2.5 flex min-h-32 flex-col gap-2.5 transition-colors duration-150"
                        :class="dragOver === col.value && dragging !== null ? 'bg-white/[0.03]' : ''"
                    >
                        <Link
                            v-for="card in col.accounts"
                            :key="card.id"
                            :href="`/accounts/${card.id}`"
                            class="ff-card block cursor-pointer p-3.5 no-underline transition-colors duration-150 hover:bg-charcoal"
                            draggable="true"
                            @dragstart="dragging = card.id"
                            @dragend="dragging = null; dragOver = null"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-medium leading-snug text-white">{{ card.name }}</span>
                                <span v-if="card.retailer_type" class="ff-label-sm mt-0.5 flex-none text-white/40">
                                    {{ typeShort[card.retailer_type] }}
                                </span>
                            </div>
                            <div class="ff-label-sm mt-1 text-white/[0.55]">
                                {{ [card.city, card.state].filter(Boolean).join(', ') || '—' }}
                                <span v-if="card.engine === 'seeded'" class="text-ff-tan-light"> · Seeded</span>
                            </div>
                            <div v-if="card.next_action" class="mt-2.5 border-t border-white/[0.1] pt-2">
                                <div class="text-[13px] leading-snug text-white/[0.72]">{{ card.next_action }}</div>
                                <div
                                    class="ff-status mt-1.5"
                                    :class="card.overdue ? 'text-ff-red-bright' : card.due_today ? 'text-ff-warning' : 'text-white/[0.55]'"
                                >
                                    <span class="ff-dot"></span>
                                    {{ card.overdue ? 'Overdue' : card.due_today ? 'Due today' : card.next_action_date }}
                                </div>
                            </div>
                            <div class="ff-mono mt-2 text-[10px] text-white/[0.35]">
                                {{ card.days_in_stage }}d in stage<span v-if="card.owner"> · {{ card.owner.split(' ')[0] }}</span>
                            </div>
                        </Link>

                        <div
                            v-if="col.accounts.length === 0"
                            class="border border-dashed border-white/[0.1] px-3 py-6 text-center text-[13px] text-white/[0.35]"
                        >
                            No accounts
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FFLayout>
</template>
