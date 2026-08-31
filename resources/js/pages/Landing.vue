<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Tier {
    key: string;
    name: string;
    min_qty: number;
    max_qty: number | null;
    unit_price: number | null;
    margin_pct: number | null;
}

defineProps<{ tiers: Tier[]; msrp: number }>();

const qty = (t: Tier) => (t.max_qty ? `${t.min_qty}–${t.max_qty}` : `${t.min_qty}+`);
</script>

<template>
    <Head title="Wholesale & Retail Partner Program" />
    <div class="min-h-screen bg-ink text-white">
        <!-- Top bar -->
        <header class="flex items-center justify-between gap-6 border-b border-white/[0.18] px-5 py-4 sm:px-10">
            <div class="flex items-center gap-4">
                <img src="/images/logo-horizontal-white.png" alt="Freedom Fuel" class="block h-7 w-auto" />
            </div>
            <div class="flex items-center gap-3">
                <Link href="/login" class="ff-btn ff-btn-ghost no-underline">Partner sign in</Link>
                <Link href="/register" class="ff-btn ff-btn-primary no-underline">Place an order</Link>
            </div>
        </header>

        <!-- Hero -->
        <section class="mx-auto max-w-5xl px-5 pb-16 pt-16 sm:px-10 sm:pt-24">
            <div class="ff-label text-ff-red-bright">Freedom Fuel · Wholesale & Retail Partner Program</div>
            <h1 class="ff-display mt-4 max-w-3xl text-5xl leading-[0.96] sm:text-7xl">
                Stock the energy pouch built for the counter.
            </h1>
            <p class="mt-6 max-w-xl text-base leading-relaxed text-white/[0.72]">
                Nicotine-free caffeine + L-theanine pouches at a ${{ msrp.toFixed(2) }} suggested retail. Zero-risk pilot,
                display support included, and a reorder cadence that follows what actually sells.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <Link href="/register" class="ff-btn ff-btn-cta no-underline">Place an order</Link>
                <a href="#pricing" class="ff-btn ff-btn-secondary no-underline">See wholesale pricing</a>
            </div>
        </section>

        <!-- Tan strip — the give-back is the brand's reason to exist, so it
             gets its own centered beat rather than a one-line footnote. -->
        <div class="bg-ff-tan px-5 py-10 text-center text-ink sm:px-10 sm:py-14">
            <div class="ff-label tracking-[0.24em]">The give-back</div>
            <p class="mx-auto mt-4 max-w-3xl text-[20px] leading-snug sm:text-[26px]">
                A portion of every purchase supports American veterans, first responders, and military families.
            </p>
        </div>

        <!-- Pricing -->
        <section id="pricing" class="mx-auto max-w-5xl px-5 py-16 sm:px-10">
            <div class="flex items-baseline gap-4">
                <h2 class="ff-display text-3xl">Volume pricing</h2>
                <div class="h-px flex-1 bg-white/[0.18]"></div>
                <span class="ff-label-sm text-white/[0.55]">${{ msrp.toFixed(2) }} MSRP</span>
            </div>
            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="t in tiers"
                    :key="t.key"
                    class="ff-card flex flex-col p-5"
                    :class="t.key === 'core' ? 'border-2 border-ff-red' : ''"
                >
                    <div v-if="t.key === 'core'" class="ff-label-sm -mx-5 -mt-5 mb-4 bg-ff-red py-1.5 text-center text-white">
                        Most common start
                    </div>
                    <div class="ff-label-sm text-ff-tan-light">{{ t.name }}</div>
                    <div class="ff-display mt-3 text-[34px]">
                        <template v-if="t.unit_price !== null">${{ t.unit_price.toFixed(2) }}</template>
                        <template v-else>Quote</template>
                    </div>
                    <div class="mt-1 text-[13px] text-white/[0.55]">
                        <template v-if="t.unit_price !== null">per puck</template>
                        <template v-else>priced individually</template>
                    </div>
                    <div class="mt-4 border-t border-white/[0.1] pt-4 text-[13px] leading-relaxed text-white/[0.72]">
                        {{ qty(t) }} units / order
                        <template v-if="t.margin_pct !== null"><br />{{ t.margin_pct }}% retail margin at MSRP</template>
                        <template v-else><br />250+ units — talk to a founder</template>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-[13px] text-white/[0.55]">
                20 pouches per puck. Freight billed on the invoice. Pilot orders are prepaid and covered by the 90-day buyback below.
            </p>
        </section>

        <!-- Why stock it -->
        <section class="border-y border-white/[0.18] bg-elevated">
            <div class="mx-auto grid max-w-5xl gap-8 px-5 py-14 sm:grid-cols-3 sm:px-10">
                <div>
                    <div class="ff-display text-lg text-ff-red-bright">01</div>
                    <h3 class="ff-display mt-2 text-xl">Zero-risk trial</h3>
                    <p class="mt-2.5 text-[13px] leading-relaxed text-white/[0.72]">
                        Start at 25 units. If your pilot doesn't sell in 90 days, we buy back unsold, unopened inventory at
                        what you paid. Signed electronically at checkout — not a handshake promise.
                    </p>
                </div>
                <div>
                    <div class="ff-display text-lg text-ff-red-bright">02</div>
                    <h3 class="ff-display mt-2 text-xl">Display support</h3>
                    <p class="mt-2.5 text-[13px] leading-relaxed text-white/[0.72]">
                        Counter-top dispensers and point-of-sale materials are included with your opening order, sized for
                        the space next to the register.
                    </p>
                </div>
                <div>
                    <div class="ff-display text-lg text-ff-red-bright">03</div>
                    <h3 class="ff-display mt-2 text-xl">Built for the counter</h3>
                    <p class="mt-2.5 text-[13px] leading-relaxed text-white/[0.72]">
                        100mg caffeine + 100mg L-theanine, zero nicotine, zero sugar. An easy add-on sale for morning
                        regulars, crews, and anyone quitting harder habits.
                    </p>
                </div>
            </div>
        </section>

        <!-- Product facts -->
        <section class="mx-auto max-w-5xl px-5 py-16 sm:px-10">
            <div class="flex items-baseline gap-4">
                <h2 class="ff-display text-3xl">Product facts</h2>
                <div class="h-px flex-1 bg-white/[0.18]"></div>
            </div>
            <div class="mt-8 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="fact in ['100mg caffeine', '100mg L-theanine', 'Zero nicotine', 'Zero sugar', '20 pouches / puck', 'Made in the USA']" :key="fact" class="ff-card px-4 py-4 text-center">
                    <div class="ff-label leading-relaxed text-white/[0.82]">{{ fact }}</div>
                </div>
            </div>
        </section>

        <!-- Closing CTA — the one notched plate on the page -->
        <section class="mx-auto max-w-5xl px-5 pb-20 sm:px-10">
            <div class="ff-card ff-notch flex flex-wrap items-center justify-between gap-6 p-8">
                <div>
                    <h2 class="ff-display text-3xl">Ready to stock Freedom Fuel?</h2>
                    <p class="mt-2 max-w-lg text-[14px] text-white/[0.72]">
                        Create your wholesale account, pick a tier, and place your pilot order in about three minutes. No
                        phone call required.
                    </p>
                </div>
                <div class="flex flex-none items-center gap-4">
                    <Link href="/register" class="ff-btn ff-btn-cta no-underline">Place an order</Link>
                    <img src="/images/logo-eagle-white.png" alt="" class="hidden h-11 w-auto opacity-90 sm:block" />
                </div>
            </div>
        </section>

        <footer class="border-t border-white/[0.18] px-5 py-6 sm:px-10">
            <div class="ff-label-sm text-white/[0.4]">Freedom Fuel · Powered by purpose</div>
        </footer>
    </div>
</template>
