<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Privacy Policy" />
    <div class="min-h-screen bg-ink text-white">
        <header class="border-b border-white/[0.18] px-5 py-4 sm:px-8">
            <Link href="/" class="ff-display inline-flex items-center gap-3 text-[17px] tracking-[0.06em] text-white no-underline">
                <img src="/images/logo-eagle-white.png" alt="" class="h-7 w-auto" />
                Freedom Fuel
            </Link>
        </header>

        <main class="mx-auto max-w-2xl px-5 py-10 sm:px-8">
            <h1 class="ff-display text-4xl">Privacy Policy</h1>
            <p class="mt-2 text-white/[0.55]">Freedom Fuel LLC · Effective September 9, 2026</p>

            <div class="mt-8 flex flex-col gap-6 leading-relaxed text-white/[0.85]">
                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">What we collect</h2>
                    <p>
                        When you apply for or manage a wholesale account, we collect the business contact information you
                        give us: your name, business name, business address, phone number, and email address, along with
                        your order history with us. We collect this to run your wholesale account — quoting, order
                        processing, delivery coordination, and support.
                    </p>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">Text messaging</h2>
                    <p>
                        If you opt in to text messages, we use your phone number to send order and account updates and to
                        answer questions you text us. Message frequency varies. Message and data rates may apply. Reply
                        STOP at any time to stop receiving texts, or HELP for help. Opting out of texts does not affect
                        your wholesale account.
                    </p>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">What we never do</h2>
                    <p>
                        No mobile information will be shared with third parties or affiliates for marketing or
                        promotional purposes. Text messaging originator opt-in data and consent will not be shared with
                        any third party. We do not sell your information. We share it only with the service providers who
                        operate our systems (message delivery, email delivery, order processing), and only so they can
                        provide those services to us.
                    </p>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">Retention and access</h2>
                    <p>
                        We keep account records for as long as you have a wholesale relationship with us and as required
                        for business records afterward. To review, correct, or delete your information, contact us and
                        we'll take care of it.
                    </p>
                </section>

                <section>
                    <h2 class="ff-label mb-2 text-ff-tan-light">Contact</h2>
                    <p>
                        Freedom Fuel LLC ·
                        <a href="mailto:sales@wholesale.freedomfuel.us" class="text-white underline">sales@wholesale.freedomfuel.us</a>
                    </p>
                </section>

                <p class="text-white/[0.55]">
                    See also our <Link href="/terms" class="text-white underline">Terms &amp; SMS Terms</Link>.
                </p>
            </div>
        </main>
    </div>
</template>
