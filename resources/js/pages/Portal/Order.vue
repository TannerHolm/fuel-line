<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Tier {
    key: string;
    name: string;
    min_qty: number;
    max_qty: number | null;
    unit_price: number | null;
    self_serve: boolean;
}

const props = defineProps<{
    account: { name: string };
    tiers: Tier[];
    guardrails: { minimum: number; increment: number; msrp: number };
    needsBuyback: boolean;
    buybackTerms: string | null;
    buybackVersion: string | null;
}>();

const form = useForm({
    quantity: props.guardrails.minimum,
    signer_name: '',
    signer_title: '',
    agree: false,
});

const activeTier = computed(
    () =>
        props.tiers.find(
            (t) => form.quantity >= t.min_qty && (t.max_qty === null || form.quantity <= t.max_qty),
        ) ?? null,
);

const total = computed(() =>
    activeTier.value?.unit_price != null ? activeTier.value.unit_price * form.quantity : null,
);

const isVolume = computed(() => activeTier.value !== null && !activeTier.value.self_serve);

const step = (dir: 1 | -1) => {
    const next = form.quantity + dir * props.guardrails.increment;
    if (next >= props.guardrails.minimum) form.quantity = next;
};

const termsOpen = ref(false);
const submit = () => form.post('/portal/order');
</script>

<template>
    <Head title="Place order" />
    <div class="min-h-screen bg-ink text-white">
        <header class="flex items-center justify-between gap-6 border-b border-white/[0.18] px-5 py-3.5 sm:px-8">
            <div class="flex items-center gap-4">
                <img src="/images/logo-eagle-white.png" alt="" class="block h-6 w-auto" />
                <span class="ff-display text-[15px] tracking-[0.06em]">Wholesale Portal</span>
            </div>
            <Link href="/portal" class="ff-btn ff-btn-ghost no-underline">Back to portal</Link>
        </header>

        <main class="mx-auto max-w-2xl px-5 pb-20 pt-8 sm:px-8">
            <div class="ff-label-sm text-ff-tan-light">{{ account.name }}</div>
            <h1 class="ff-display mt-2 text-4xl">{{ needsBuyback ? 'Pilot order' : 'Reorder' }}</h1>

            <!-- Tier picker -->
            <div class="mt-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <button
                    v-for="t in tiers"
                    :key="t.key"
                    type="button"
                    class="ff-card cursor-pointer p-4 text-left transition-colors duration-150"
                    :class="activeTier?.key === t.key ? 'border-2 border-ff-red' : 'hover:bg-charcoal'"
                    @click="form.quantity = t.min_qty"
                >
                    <div class="ff-label-sm" :class="activeTier?.key === t.key ? 'text-ff-red-bright' : 'text-white/[0.55]'">
                        {{ t.name }}
                    </div>
                    <div class="ff-display mt-2 text-xl">
                        <template v-if="t.unit_price !== null">${{ t.unit_price.toFixed(2) }}</template>
                        <template v-else>Quote</template>
                    </div>
                    <div class="mt-1 text-[11px] text-white/[0.55]">{{ t.max_qty ? `${t.min_qty}–${t.max_qty}` : `${t.min_qty}+` }} units</div>
                </button>
            </div>

            <!-- Quantity -->
            <div class="ff-card mt-4 p-5">
                <label class="ff-field-label" for="qty">Quantity ({{ guardrails.increment }}-unit increments, {{ guardrails.minimum }} minimum)</label>
                <div class="mt-3 flex items-center gap-3">
                    <button type="button" class="ff-btn ff-btn-secondary h-11 w-11 flex-none !px-0" @click="step(-1)">−</button>
                    <input
                        id="qty"
                        v-model.number="form.quantity"
                        type="number"
                        :min="guardrails.minimum"
                        :step="guardrails.increment"
                        class="ff-input ff-input-numeric w-32 text-center"
                    />
                    <button type="button" class="ff-btn ff-btn-secondary h-11 w-11 flex-none !px-0" @click="step(1)">+</button>
                    <div class="ml-auto text-right">
                        <div v-if="total !== null" class="ff-display text-[28px]">${{ total.toFixed(2) }}</div>
                        <div v-else class="ff-display text-[22px] text-ff-tan-light">Quoted individually</div>
                        <div class="text-[11px] text-white/[0.55]">freight added to invoice</div>
                    </div>
                </div>
                <div v-if="form.errors.quantity" class="ff-error mt-2">{{ form.errors.quantity }}</div>
            </div>

            <div v-if="isVolume" class="ff-card mt-4 border-l-2 border-l-ff-tan p-4">
                <span class="ff-label-sm text-ff-tan-light">Volume orders above 249 units are individually priced.</span>
                <p class="mt-1.5 text-[13px] text-white/[0.72]">Submit at 249 or below to order now, or email us and a founder will quote your volume directly.</p>
            </div>

            <!-- Buyback agreement — hard gate on the pilot order -->
            <div v-if="needsBuyback" class="ff-card mt-4 p-5">
                <div class="ff-label-sm text-ff-tan-light">90-day guaranteed buyback</div>
                <p class="mt-2.5 text-[13px] leading-relaxed text-white/[0.72]">
                    Your pilot order is covered: if it doesn't sell within 90 days, we buy back unsold, unopened,
                    resalable inventory at what you paid. Signing below is required before the order can submit.
                </p>
                <button type="button" class="ff-btn ff-btn-ghost mt-3" @click="termsOpen = !termsOpen">
                    {{ termsOpen ? 'Hide full terms' : 'Read full terms' }}
                </button>
                <pre v-if="termsOpen" class="ff-mono mt-3 max-h-64 overflow-y-auto whitespace-pre-wrap border border-white/[0.1] bg-ink p-4 text-[11px] leading-relaxed text-white/[0.72]">{{ buybackTerms }}</pre>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="signer">Full name (signature)</label>
                        <input id="signer" v-model="form.signer_name" type="text" class="ff-input" placeholder="Type your legal name" />
                        <div v-if="form.errors.signer_name" class="ff-error">{{ form.errors.signer_name }}</div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="title">Title</label>
                        <input id="title" v-model="form.signer_title" type="text" class="ff-input" placeholder="Owner, Manager…" />
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-3">
                    <input v-model="form.agree" type="checkbox" class="mt-0.5 h-4 w-4 flex-none accent-[#C8102E]" />
                    <span class="text-[13px] leading-relaxed text-white/[0.82]">
                        I have read and agree to the 90-Day Guaranteed Buyback Agreement ({{ buybackVersion }}), and I am
                        authorized to sign for this business.
                    </span>
                </label>
                <div v-if="form.errors.agree" class="ff-error mt-2">{{ form.errors.agree }}</div>
            </div>

            <!-- Submit -->
            <div class="mt-6 flex items-center justify-between gap-4 border-t border-white/[0.18] pt-5">
                <p class="max-w-xs text-[12px] leading-relaxed text-white/[0.55]">
                    Pilot orders are prepaid. Your invoice arrives by email after submission.
                </p>
                <button type="button" class="ff-btn ff-btn-cta" :disabled="form.processing || isVolume" @click="submit">
                    Submit order
                </button>
            </div>
        </main>
    </div>
</template>
