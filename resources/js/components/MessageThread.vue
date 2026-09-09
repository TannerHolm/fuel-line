<script setup lang="ts">
import { router, useForm, usePoll } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

export interface ThreadMessage {
    id: number;
    channel: string;
    channel_label: string;
    direction: 'in' | 'out';
    status: string;
    status_label: string;
    subject: string | null;
    body: string;
    error: string | null;
    by: string;
    unread: boolean;
    created_at: string;
}

const props = defineProps<{
    account: { id: number; phone: string | null; email: string | null; sms_opted_out: boolean };
    thread: ThreadMessage[];
}>();

usePoll(10000, { only: ['thread', 'unreadMessages'] });

const smsAvailable = computed(() => !!props.account.phone && !props.account.sms_opted_out);
const emailAvailable = computed(() => !!props.account.email);

const smsHint = computed(() =>
    !props.account.phone ? 'No phone on file' : props.account.sms_opted_out ? 'Opted out via STOP' : null,
);

const form = useForm({
    channel: smsAvailable.value ? 'sms' : 'email',
    subject: '',
    body: '',
});

const segments = computed(() => (form.body.length <= 160 ? 1 : Math.ceil(form.body.length / 153)));

const send = () =>
    form.post(`/accounts/${props.account.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => form.reset('subject', 'body'),
    });

// Mark inbound as read once the thread is on screen; never on GET so polling
// stays side-effect-free.
let marking = false;
const markRead = () => {
    if (marking || !props.thread.some((m) => m.unread)) return;
    marking = true;
    router.post(
        `/accounts/${props.account.id}/messages/read`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['thread', 'unreadMessages'],
            onFinish: () => (marking = false),
        },
    );
};

const scroller = ref<HTMLElement | null>(null);
const scrollToEnd = () => nextTick(() => scroller.value && (scroller.value.scrollTop = scroller.value.scrollHeight));

onMounted(() => {
    scrollToEnd();
    markRead();
});
watch(
    () => props.thread.length,
    () => {
        scrollToEnd();
        markRead();
    },
);

const timeOf = (iso: string) =>
    new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

const staleQueued = (m: ThreadMessage) => m.status === 'queued' && Date.now() - new Date(m.created_at).getTime() > 120000;

const statusClass = (m: ThreadMessage) =>
    m.status === 'failed' || staleQueued(m)
        ? 'text-ff-warning'
        : m.status === 'queued'
          ? 'text-white/[0.4]'
          : 'text-white/[0.55]';
</script>

<template>
    <div class="ff-card p-5">
        <div class="flex items-baseline justify-between gap-3">
            <div class="ff-label-sm text-ff-tan-light">Messages</div>
            <span v-if="account.sms_opted_out" class="ff-status text-ff-warning"><span class="ff-dot"></span>SMS opted out</span>
        </div>

        <div ref="scroller" class="mt-4 flex max-h-[480px] flex-col gap-3 overflow-y-auto pr-1">
            <p v-if="thread.length === 0" class="py-8 text-center text-[13px] text-white/[0.55]">
                No messages yet. Start the conversation below.
            </p>

            <div
                v-for="m in thread"
                :key="m.id"
                class="max-w-[85%] sm:max-w-[70%]"
                :class="m.direction === 'out' ? 'self-end' : 'self-start'"
            >
                <div
                    class="border px-3.5 py-2.5 text-[13px] leading-relaxed"
                    :class="m.direction === 'out' ? 'border-white/[0.1] bg-charcoal' : 'border-white/[0.18] bg-elevated'"
                >
                    <div v-if="m.subject" class="mb-1 font-medium text-white">{{ m.subject }}</div>
                    <div class="whitespace-pre-wrap text-white/[0.85]">{{ m.body }}</div>
                </div>
                <div class="ff-label-sm mt-1 flex flex-wrap gap-x-2 text-white/[0.4]" :class="m.direction === 'out' ? 'justify-end' : ''">
                    <span>{{ m.channel_label }}</span>
                    <span>·</span>
                    <span>{{ m.by }}</span>
                    <span>·</span>
                    <span>{{ timeOf(m.created_at) }}</span>
                    <template v-if="m.direction === 'out'">
                        <span>·</span>
                        <span :class="statusClass(m)">{{ staleQueued(m) ? 'May not have sent' : m.status_label }}</span>
                    </template>
                </div>
                <div v-if="m.error" class="ff-label-sm mt-0.5 text-ff-warning" :class="m.direction === 'out' ? 'text-right' : ''">
                    {{ m.error }}
                </div>
            </div>
        </div>

        <form class="mt-5 border-t border-white/[0.18] pt-4" @submit.prevent="send">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="ff-btn ff-btn-ghost"
                    :class="form.channel === 'sms' ? 'border-white text-white' : ''"
                    :disabled="!smsAvailable"
                    :title="smsHint ?? undefined"
                    @click="form.channel = 'sms'"
                >
                    SMS
                </button>
                <button
                    type="button"
                    class="ff-btn ff-btn-ghost"
                    :class="form.channel === 'email' ? 'border-white text-white' : ''"
                    :disabled="!emailAvailable"
                    :title="!emailAvailable ? 'No email on file' : undefined"
                    @click="form.channel = 'email'"
                >
                    Email
                </button>
                <span v-if="smsHint" class="ff-label-sm text-white/[0.4]">{{ smsHint }}</span>
            </div>

            <p v-if="!smsAvailable && !emailAvailable" class="mt-3 text-[13px] text-white/[0.55]">
                No reachable contact info.
                <a :href="`/accounts/${account.id}/edit`" class="text-white underline">Add a phone or email</a> to message this account.
            </p>

            <template v-else>
                <input
                    v-if="form.channel === 'email'"
                    v-model="form.subject"
                    type="text"
                    placeholder="Subject (optional)"
                    class="ff-input ff-input-sm mt-3 w-full"
                />
                <textarea
                    v-model="form.body"
                    rows="3"
                    class="ff-input mt-3 w-full"
                    :placeholder="form.channel === 'sms' ? 'Text this account' : 'Email this account'"
                ></textarea>
                <div v-if="form.errors.body" class="ff-error mt-1">{{ form.errors.body }}</div>

                <div class="mt-2 flex items-center justify-between gap-3">
                    <span v-if="form.channel === 'sms'" class="ff-mono text-[11px]" :class="form.body.length > 160 ? 'text-ff-warning' : 'text-white/[0.4]'">
                        {{ form.body.length }} chars · {{ segments }} segment{{ segments === 1 ? '' : 's' }}
                    </span>
                    <span v-else></span>
                    <button type="submit" class="ff-btn ff-btn-primary" :disabled="form.processing || !form.body.trim()">
                        Send {{ form.channel === 'sms' ? 'SMS' : 'email' }}
                    </button>
                </div>
            </template>
        </form>
    </div>
</template>
