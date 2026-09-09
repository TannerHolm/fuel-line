<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Terms & SMS Terms" />
    <div class="min-h-screen bg-ink text-white">
        <header class="border-b border-white/[0.18] px-5 py-4 sm:px-8">
            <Link href="/" class="ff-display inline-flex items-center gap-3 text-[17px] tracking-[0.06em] text-white no-underline">
                <img src="/images/logo-eagle-white.png" alt="" class="h-7 w-auto" />
                Freedom Fuel
            </Link>
        </header>

        <main class="mx-auto max-w-2xl px-5 py-10 sm:px-8">
            <h1 class="ff-display text-4xl">Terms &amp; SMS Terms</h1>
            <p class="mt-2 text-white/[0.55]">Freedom Fuel LLC · Effective September 9, 2026</p>

            <div class="mt-8 flex flex-col gap-6 leading-relaxed text-white/[0.85]">
                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">Wholesale program</h2>
                    <p>
                        Freedom Fuel LLC sells fire-starting products to retail businesses through its wholesale program
                        at wholesale.freedomfuel.us. Wholesale pricing, minimums, and the pilot buyback guarantee are
                        presented during signup and ordering. Orders are invoiced per the terms agreed at order time.
                    </p>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">SMS program — Freedom Fuel Wholesale Updates</h2>
                    <p>
                        Wholesale partners can opt in to receive text messages from Freedom Fuel about their account —
                        order confirmations, delivery updates, restock check-ins, and replies to questions they text us —
                        along with occasional promotional offers such as new products or reorder specials. You opt in by
                        checking the SMS consent box when creating a wholesale account (or by asking us in writing to
                        text you). Consent is not a condition of purchase.
                    </p>
                    <ul class="mt-3 flex list-disc flex-col gap-1.5 pl-5">
                        <li>Message frequency varies with your account activity.</li>
                        <li>Message and data rates may apply.</li>
                        <li>Reply <strong>STOP</strong> at any time to cancel. You'll get one final confirmation text.</li>
                        <li>Reply <strong>HELP</strong> for help, or contact us at the address below.</li>
                        <li>Carriers are not liable for delayed or undelivered messages.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">Privacy</h2>
                    <p>
                        How we handle your information — including that no mobile information is shared with third
                        parties or affiliates for marketing purposes — is covered by our
                        <Link href="/privacy" class="text-white underline">Privacy Policy</Link>.
                    </p>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">Contact</h2>
                    <p>
                        Freedom Fuel LLC ·
                        <a href="mailto:sales@wholesale.freedomfuel.us" class="text-white underline">sales@wholesale.freedomfuel.us</a>
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
