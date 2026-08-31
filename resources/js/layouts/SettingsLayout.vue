<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const items = [
    { title: 'Profile', href: '/settings/profile' },
    { title: 'Password', href: '/settings/password' },
];

const isActive = (href: string) => page.url.startsWith(href);
</script>

<template>
    <FFLayout>
        <div class="mx-auto max-w-3xl">
            <h1 class="ff-display text-4xl">Settings</h1>
            <p class="mt-1.5 text-white/[0.55]">Your login and password. Account data lives on the account record.</p>

            <div class="mt-6 flex gap-6 border-b border-white/[0.18]">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="ff-label border-b-2 pb-2.5 no-underline transition-colors duration-150"
                    :class="isActive(item.href) ? 'border-ff-red text-white' : 'border-transparent text-white/[0.55] hover:text-white'"
                >
                    {{ item.title }}
                </Link>
            </div>

            <div class="mt-7">
                <slot />
            </div>
        </div>
    </FFLayout>
</template>
