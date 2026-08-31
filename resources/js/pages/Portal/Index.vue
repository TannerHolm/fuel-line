<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface OrderRow {
    id: number;
    type: string;
    date: string;
    quantity: number;
    tier: string | null;
    unit_price: number | null;
    revenue: number | null;
    payment_status: string;
    fulfilled_at: string | null;
}

const props = defineProps<{
    account: { name: string; city: string | null; state: string | null };
    orders: OrderRow[];
    buybackSigned: boolean;
    hasOpening: boolean;
    recentReports: { date: string; units_sold: number | null; units_on_hand: number | null }[];
    isFounderPreview: boolean;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.success);

const reportOpen = ref(false);
const reportForm = useForm({ units_sold: null as number | null, units_on_hand: null as number | null, flavors_moving: '', ready_for_reorder: false });
const submitReport = () =>
    reportForm.post('/portal/report', {
        preserveScroll: true,
        onSuccess: () => {
            reportOpen.value = false;
            reportForm.reset();
        },
    });

const statusColor: Record<string, string> = {
    paid: 'text-ff-success',
    invoiced: 'text-ff-warning',
    pending: 'text-white/[0.55]',
};
const statusLabel: Record<string, string> = {
    paid: 'Paid',
    invoiced: 'Awaiting pay',
    pending: 'Received',
};
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Partner portal" />
    <div class="min-h-screen bg-ink text-white">
        <header class="flex items-center justify-between gap-6 border-b border-white/[0.18] px-5 py-3.5 sm:px-8">
            <div class="flex items-center gap-4">
                <img src="/images/logo-eagle-white.png" alt="" class="block h-6 w-auto" />
                <span class="ff-display text-[15px] tracking-[0.06em]">Wholesale Portal</span>
            </div>
            <button type="button" class="ff-btn ff-btn-ghost" @click="logout">Sign out</button>
        </header>

        <div v-if="isFounderPreview" class="bg-ff-tan px-5 py-2 text-ink sm:px-8">
            <span class="ff-label-sm tracking-[0.2em]">Founder preview — this is what the partner sees</span>
        </div>

        <div v-if="flash" class="mx-5 mt-4 border border-white/[0.18] border-l-2 border-l-ff-success bg-elevated px-4 py-3 sm:mx-8">
            <span class="text-[13px] text-white/[0.88]">{{ flash }}</span>
        </div>

        <main class="mx-auto max-w-3xl px-5 pb-20 pt-8 sm:px-8">
            <div class="ff-label-sm text-ff-tan-light">{{ [account.city, account.state].filter(Boolean).join(', ') }}</div>
            <h1 class="ff-display mt-2 text-4xl">{{ account.name }}</h1>

            <div class="mt-6 flex flex-wrap gap-3">
                <Link href="/portal/order" class="ff-btn ff-btn-cta no-underline">
                    {{ hasOpening ? 'Place a reorder' : 'Place your pilot order' }}
                </Link>
                <button v-if="hasOpening" type="button" class="ff-btn ff-btn-primary" @click="reportOpen = !reportOpen">
                    Report sales
                </button>
            </div>

            <!-- Self-report -->
            <div v-if="reportOpen" class="ff-card mt-5 p-5">
                <div class="ff-label-sm text-ff-tan-light">Ninety-second report</div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Units sold since last report</label>
                        <input v-model.number="reportForm.units_sold" type="number" min="0" class="ff-input ff-input-numeric" />
                        <div v-if="reportForm.errors.units_sold" class="ff-error">{{ reportForm.errors.units_sold }}</div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Units on hand now</label>
                        <input v-model.number="reportForm.units_on_hand" type="number" min="0" class="ff-input ff-input-numeric" />
                        <div v-if="reportForm.errors.units_on_hand" class="ff-error">{{ reportForm.errors.units_on_hand }}</div>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-2">
                    <label class="ff-field-label">Which flavors are moving?</label>
                    <input v-model="reportForm.flavors_moving" type="text" class="ff-input" placeholder="Original, Citrus" />
                </div>
                <label class="mt-4 flex cursor-pointer items-center gap-3">
                    <input v-model="reportForm.ready_for_reorder" type="checkbox" class="h-4 w-4 accent-[#C8102E]" />
                    <span class="text-sm text-white/[0.82]">I'd like to reorder</span>
                </label>
                <div class="mt-5 flex justify-end gap-3 border-t border-white/[0.18] pt-4">
                    <button type="button" class="ff-btn ff-btn-secondary" @click="reportOpen = false">Cancel</button>
                    <button type="button" class="ff-btn ff-btn-primary" :disabled="reportForm.processing" @click="submitReport">Send report</button>
                </div>
            </div>

            <!-- Buyback status -->
            <div class="ff-card mt-6 border-l-2 p-4" :class="buybackSigned ? 'border-l-ff-success' : 'border-l-ff-tan'">
                <span class="ff-label-sm" :class="buybackSigned ? 'text-ff-success' : 'text-ff-tan-light'">
                    {{ buybackSigned ? '90-day buyback agreement on file' : 'Buyback agreement signs with your pilot order' }}
                </span>
            </div>

            <!-- Orders -->
            <div class="ff-card mt-6">
                <div class="flex items-center justify-between border-b border-white/[0.18] px-5 py-3.5">
                    <span class="ff-display text-xl">Your orders</span>
                    <span class="ff-label-sm text-white/[0.55]">{{ orders.length }}</span>
                </div>
                <div v-if="orders.length === 0" class="px-5 py-8 text-center text-white/[0.55]">
                    No orders yet. Start with 25 units.
                </div>
                <div v-for="o in orders" :key="o.id" class="flex items-center justify-between gap-4 border-b border-white/[0.1] px-5 py-3.5 last:border-b-0">
                    <div>
                        <div class="text-sm text-white">
                            {{ o.type === 'opening' ? 'Pilot order' : 'Reorder' }} · {{ o.quantity }} units
                        </div>
                        <div class="ff-mono mt-0.5 text-[11px] text-white/[0.55]">
                            {{ o.date }}<template v-if="o.unit_price !== null"> · ${{ o.unit_price.toFixed(2) }}/puck</template>
                        </div>
                    </div>
                    <div class="flex items-center gap-5">
                        <span v-if="o.revenue !== null" class="ff-display text-lg">${{ o.revenue.toFixed(2) }}</span>
                        <span class="ff-status" :class="statusColor[o.payment_status]">
                            <span class="ff-dot"></span>{{ o.fulfilled_at ? 'Shipped' : statusLabel[o.payment_status] }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Recent reports -->
            <div v-if="recentReports.length" class="ff-card mt-6">
                <div class="border-b border-white/[0.18] px-5 py-3.5">
                    <span class="ff-display text-xl">Recent reports</span>
                </div>
                <div v-for="(r, i) in recentReports" :key="i" class="flex items-center justify-between border-b border-white/[0.1] px-5 py-3 last:border-b-0">
                    <span class="ff-mono text-[12px] text-white/[0.55]">{{ r.date }}</span>
                    <span class="text-sm text-white/[0.82]">{{ r.units_sold ?? '—' }} sold · {{ r.units_on_hand ?? '—' }} on hand</span>
                </div>
            </div>
        </main>
    </div>
</template>
