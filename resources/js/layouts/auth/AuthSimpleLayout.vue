<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center bg-ink p-6 text-white">
        <div class="w-full max-w-md">
            <div class="flex flex-col items-center gap-4">
                <Link href="/" class="flex flex-col items-center gap-3 no-underline">
                    <img src="/images/logo-eagle-white.png" alt="Freedom Fuel" class="h-12 w-auto" />
                    <span class="sr-only">Fuel Line</span>
                </Link>
                <div class="mt-1 space-y-2 text-center">
                    <h1 class="ff-display text-3xl">{{ title }}</h1>
                    <p class="mx-auto max-w-sm text-[14px] leading-relaxed text-white/[0.55]">{{ description }}</p>
                </div>
            </div>

            <div class="ff-card mt-8 p-6">
                <slot />
            </div>
        </div>
    </div>
</template>
