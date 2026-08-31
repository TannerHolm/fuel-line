<script setup lang="ts">
defineProps<{ open: boolean; eyebrow: string; title: string }>();
const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="ff-scrim fixed inset-0 z-50 flex items-center justify-center p-6" @click.self="emit('close')">
            <div class="ff-card w-full max-w-[520px] shadow-[0_6px_24px_rgba(0,0,0,0.45)]">
                <div class="px-6 pt-6">
                    <div class="ff-label-sm text-ff-tan-light">{{ eyebrow }}</div>
                    <div class="ff-display mt-3 text-[26px] leading-[1.05]">{{ title }}</div>
                </div>
                <div class="max-h-[65vh] overflow-y-auto px-6 pb-2 pt-4">
                    <slot />
                </div>
                <div class="flex justify-end gap-2.5 border-t border-white/[0.18] px-6 py-4">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
