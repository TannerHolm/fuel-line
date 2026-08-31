<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const flash = computed(() => (page.props as any).flash?.success);

const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .map((p: string) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const founderTabs = [
    { label: 'Pipeline', href: '/pipeline', match: '/pipeline' },
    { label: 'Accounts', href: '/accounts', match: '/accounts' },
    { label: 'Map', href: '/map', match: '/map' },
    { label: 'KPIs', href: '/kpis', match: '/kpis' },
    { label: 'Field', href: '/field', match: '/field' },
];

const retailerTabs = [{ label: 'Portal', href: '/portal', match: '/portal' }];

const tabs = computed(() => (user.value?.role === 'founder' ? founderTabs : retailerTabs));

const isActive = (match: string) => page.url === match || page.url.startsWith(match + '/') || page.url.startsWith(match + '?');

const menuOpen = ref(false);
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-ink text-white">
        <header class="sticky top-0 z-30 border-b border-white/[0.18] bg-ink/[0.82] backdrop-blur-md">
            <div class="flex items-center justify-between gap-6 px-5 pt-3 sm:px-8">
                <div class="flex flex-none items-center gap-4">
                    <img src="/images/logo-eagle-white.png" alt="" class="block h-7 w-auto" />
                    <div class="h-5 w-px bg-white/[0.18]"></div>
                    <Link href="/pipeline" class="ff-display text-[17px] tracking-[0.06em] text-white no-underline">
                        Fuel Line
                    </Link>
                    <span class="ff-label-sm mt-px hidden text-white/40 md:block">Wholesale OS</span>
                </div>

                <div class="relative flex flex-none items-center gap-3">
                    <span class="ff-label hidden text-white/[0.55] sm:block">{{ user?.email }}</span>
                    <button
                        type="button"
                        class="ff-display flex h-[30px] w-[30px] cursor-pointer items-center justify-center rounded-full border border-white/[0.35] bg-transparent text-xs text-white"
                        @click="menuOpen = !menuOpen"
                    >
                        {{ initials }}
                    </button>
                    <div v-if="menuOpen" class="fixed inset-0 z-40" @click="menuOpen = false"></div>
                    <div
                        v-if="menuOpen"
                        class="ff-card absolute right-0 top-11 z-50 w-48 py-1 shadow-[0_6px_24px_rgba(0,0,0,0.45)]"
                    >
                        <Link href="/settings/profile" class="ff-label block px-4 py-2.5 text-white/[0.72] no-underline hover:bg-charcoal hover:text-white" @click="menuOpen = false">
                            Settings
                        </Link>
                        <button type="button" class="ff-label block w-full cursor-pointer border-0 bg-transparent px-4 py-2.5 text-left text-white/[0.72] hover:bg-charcoal hover:text-white" @click="logout">
                            Sign out
                        </button>
                    </div>
                </div>
            </div>

            <nav class="flex gap-6 px-5 sm:px-8">
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="ff-label border-b-2 py-3 no-underline transition-colors duration-150"
                    :class="isActive(tab.match) ? 'border-ff-red text-white' : 'border-transparent text-white/60 hover:text-white'"
                >
                    {{ tab.label }}
                </Link>
            </nav>
        </header>

        <div
            v-if="flash"
            class="mx-5 mt-4 border border-white/[0.18] border-l-2 border-l-ff-success bg-elevated px-4 py-3 sm:mx-8"
        >
            <span class="ff-label-sm text-ff-success">{{ flash }}</span>
        </div>

        <main class="px-5 pb-20 pt-6 sm:px-8">
            <slot />
        </main>
    </div>
</template>
