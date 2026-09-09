<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AccountRow {
    id: number;
    name: string;
    city: string | null;
    state: string | null;
    retailer_type: string | null;
    stage: string;
    stage_label: string;
    owner: string | null;
    orders_count: number;
    check_ins_count: number;
    next_action_date: string | null;
}

const props = defineProps<{ accounts: AccountRow[]; q: string; owner: string; owners: { id: number; name: string }[] }>();

const q = ref(props.q ?? '');
const owner = ref(props.owner ?? '');
const search = () =>
    router.get('/accounts', { q: q.value || undefined, owner: owner.value || undefined }, { preserveState: true });

const stageColor = (stage: string) =>
    stage === 'lost'
        ? 'text-white/[0.4]'
        : ['selling', 'reordered', 'repeat_account'].includes(stage)
          ? 'text-ff-success'
          : ['opening_order'].includes(stage)
            ? 'text-ff-warning'
            : 'text-white/[0.72]';
</script>

<template>
    <Head title="Accounts" />
    <FFLayout>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="ff-display text-4xl">Accounts</h1>
                <p class="mt-1.5 text-white/[0.55]">{{ accounts.length }} retailers on record.</p>
            </div>
            <div class="flex items-center gap-3">
                <Link href="/accounts/import" class="ff-btn ff-btn-ghost no-underline">Import</Link>
                <Link href="/accounts/create" class="ff-btn ff-btn-primary no-underline">New account</Link>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2.5 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
            <input v-model="q" type="text" placeholder="Search name, city, contact" class="ff-input ff-input-sm w-full sm:w-72" @keyup.enter="search" />
            <select v-model="owner" class="ff-input ff-input-sm w-full sm:w-auto" @change="search">
                <option value="">All owners</option>
                <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                <option value="none">Unassigned</option>
            </select>
            <button v-if="q || owner" type="button" class="ff-btn ff-btn-ghost" @click="q = ''; owner = ''; search()">Clear</button>
        </div>

        <div class="ff-card overflow-x-auto">
            <table class="w-full border-collapse text-sm sm:min-w-[720px]">
                <thead>
                    <tr class="border-b border-white/[0.18]">
                        <th class="ff-label-sm px-5 py-2.5 text-left text-white/[0.55]">Account</th>
                        <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] sm:table-cell">Location</th>
                        <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] lg:table-cell">Type</th>
                        <th class="ff-label-sm px-5 py-2.5 text-left text-white/[0.55]">Stage</th>
                        <th class="ff-label-sm hidden px-5 py-2.5 text-right text-white/[0.55] md:table-cell">Orders</th>
                        <th class="ff-label-sm hidden px-5 py-2.5 text-right text-white/[0.55] lg:table-cell">Check-ins</th>
                        <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] lg:table-cell">Owner</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="a in accounts"
                        :key="a.id"
                        class="cursor-pointer border-b border-white/[0.1] transition-colors duration-150 last:border-b-0 hover:bg-charcoal"
                        @click="router.visit(`/accounts/${a.id}`)"
                    >
                        <td class="px-5 py-3 font-medium text-white">
                            {{ a.name }}
                            <span class="ff-label-sm mt-1 block text-white/[0.55] sm:hidden">
                                {{ [a.city, a.state].filter(Boolean).join(', ') || '—' }}
                            </span>
                        </td>
                        <td class="hidden px-5 py-3 text-white/[0.72] sm:table-cell">{{ [a.city, a.state].filter(Boolean).join(', ') || '—' }}</td>
                        <td class="ff-label hidden px-5 py-3 text-white/[0.72] lg:table-cell">{{ a.retailer_type ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="ff-status" :class="stageColor(a.stage)">
                                <span class="ff-dot"></span>{{ a.stage_label }}
                            </span>
                        </td>
                        <td class="ff-display hidden px-5 py-3 text-right text-base md:table-cell">{{ a.orders_count }}</td>
                        <td class="ff-display hidden px-5 py-3 text-right text-base lg:table-cell">{{ a.check_ins_count }}</td>
                        <td class="hidden px-5 py-3 text-white/[0.72] lg:table-cell">{{ a.owner?.split(' ')[0] ?? '—' }}</td>
                    </tr>
                    <tr v-if="accounts.length === 0">
                        <td colspan="7" class="px-5 py-10 text-center text-white/[0.55]">
                            No accounts yet. Start with the first prospect.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </FFLayout>
</template>
