<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, usePoll } from '@inertiajs/vue3';

interface Conversation {
    account_id: number;
    name: string;
    location: string;
    stage_label: string;
    channel: string | null;
    outbound: boolean;
    preview: string;
    last_message_at: string;
    unread_count: number;
}

interface Unmatched {
    id: number;
    channel: string;
    from: string;
    preview: string;
    created_at: string;
}

defineProps<{ conversations: Conversation[]; unmatched: Unmatched[] }>();

usePoll(15000);

const timeOf = (iso: string) => {
    const date = new Date(iso);
    const sameDay = date.toDateString() === new Date().toDateString();
    return sameDay
        ? date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
        : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Messages" />
    <FFLayout>
        <div class="mx-auto max-w-4xl">
            <div class="mb-5">
                <h1 class="ff-display text-4xl">Messages</h1>
                <p class="mt-1.5 text-white/[0.55]">Every SMS and email conversation with your accounts, newest first.</p>
            </div>

            <div class="ff-card">
                <div v-if="conversations.length === 0" class="px-5 py-12 text-center text-white/[0.55]">
                    No conversations yet. Open an account and send the first message.
                </div>

                <Link
                    v-for="c in conversations"
                    :key="c.account_id"
                    :href="`/accounts/${c.account_id}`"
                    class="flex items-center gap-4 border-b border-white/[0.1] px-5 py-4 no-underline transition-colors duration-150 last:border-b-0 hover:bg-charcoal"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-3">
                            <span class="font-medium text-white">{{ c.name }}</span>
                            <span class="ff-label-sm hidden text-white/[0.4] sm:inline">{{ c.location || '—' }}</span>
                            <span class="ff-label-sm hidden text-white/[0.4] md:inline">{{ c.stage_label }}</span>
                        </div>
                        <div class="mt-1 truncate text-[13px]" :class="c.unread_count > 0 ? 'text-white' : 'text-white/[0.55]'">
                            <span v-if="c.channel" class="ff-label-sm mr-1.5 text-white/[0.4]">{{ c.outbound ? '→' : '←' }} {{ c.channel }}</span>
                            {{ c.preview }}
                        </div>
                    </div>
                    <div class="flex flex-none flex-col items-end gap-1.5">
                        <span class="ff-mono text-[11px] text-white/[0.4]">{{ timeOf(c.last_message_at) }}</span>
                        <span
                            v-if="c.unread_count > 0"
                            class="ff-mono rounded-full bg-ff-red px-1.5 py-0.5 text-[10px] leading-none text-white"
                        >
                            {{ c.unread_count }}
                        </span>
                    </div>
                </Link>
            </div>

            <div v-if="unmatched.length > 0" class="mt-6">
                <div class="ff-label-sm mb-2 text-white/[0.55]">Unmatched replies</div>
                <div class="ff-card">
                    <p class="border-b border-white/[0.1] px-5 py-3 text-[13px] text-white/[0.55]">
                        These came from a number or address not on any account. Update the account's contact info to route
                        future replies into its thread.
                    </p>
                    <div v-for="m in unmatched" :key="m.id" class="border-b border-white/[0.1] px-5 py-3 last:border-b-0">
                        <div class="flex items-baseline gap-3">
                            <span class="ff-mono text-[12px] text-white">{{ m.from }}</span>
                            <span class="ff-label-sm text-white/[0.4]">{{ m.channel }}</span>
                            <span class="ff-mono ml-auto text-[11px] text-white/[0.4]">{{ timeOf(m.created_at) }}</span>
                        </div>
                        <p class="mt-1 text-[13px] text-white/[0.55]">{{ m.preview }}</p>
                    </div>
                </div>
            </div>
        </div>
    </FFLayout>
</template>
