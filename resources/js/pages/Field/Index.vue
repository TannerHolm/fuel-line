<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface FieldAccount {
    id: number;
    name: string;
    city: string | null;
    state: string | null;
    stage: string;
    stage_label: string;
    next_action: string | null;
    next_action_date: string | null;
    overdue: boolean;
    due_today: boolean;
}

const props = defineProps<{ accounts: FieldAccount[]; q: string }>();

const q = ref(props.q ?? '');
const search = () => router.get('/field', { q: q.value || undefined }, { preserveState: true });
</script>

<template>
    <Head title="Field" />
    <FFLayout>
        <div class="mx-auto max-w-lg">
            <h1 class="ff-display text-4xl">Field</h1>
            <p class="mt-1.5 text-white/[0.55]">Standing in a store? Find the account, log it in two taps.</p>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <Link href="/accounts/create" class="ff-btn ff-btn-primary min-h-16 no-underline">New prospect</Link>
                <button type="button" class="ff-btn ff-btn-secondary min-h-16" @click="($refs.searchInput as HTMLInputElement)?.focus()">
                    Find account
                </button>
            </div>

            <input
                ref="searchInput"
                v-model="q"
                type="search"
                placeholder="Search name or city"
                class="ff-input mt-4"
                @keyup.enter="search"
            />

            <div class="mt-5 flex flex-col gap-2.5">
                <Link
                    v-for="a in accounts"
                    :key="a.id"
                    :href="`/accounts/${a.id}`"
                    class="ff-card block p-4 no-underline transition-colors duration-150 hover:bg-charcoal"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[15px] font-medium text-white">{{ a.name }}</div>
                            <div class="ff-label-sm mt-1 text-white/[0.55]">
                                {{ [a.city, a.state].filter(Boolean).join(', ') }} · {{ a.stage_label }}
                            </div>
                        </div>
                        <span
                            v-if="a.next_action_date"
                            class="ff-status mt-0.5 flex-none"
                            :class="a.overdue ? 'text-ff-red-bright' : a.due_today ? 'text-ff-warning' : 'text-white/[0.55]'"
                        >
                            <span class="ff-dot"></span>
                            {{ a.overdue ? 'Overdue' : a.due_today ? 'Today' : a.next_action_date }}
                        </span>
                    </div>
                    <div v-if="a.next_action" class="mt-2 border-t border-white/[0.1] pt-2 text-[13px] text-white/[0.72]">
                        {{ a.next_action }}
                    </div>
                </Link>
            </div>
        </div>
    </FFLayout>
</template>
