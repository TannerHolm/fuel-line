<script setup lang="ts">
import FFModal from '@/components/FFModal.vue';
import MessageThread, { type ThreadMessage } from '@/components/MessageThread.vue';
import VelocityChart from '@/components/VelocityChart.vue';
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface TimelineItem {
    kind: 'stage' | 'sample' | 'order' | 'check_in' | 'message';
    date: string;
    title: string;
    detail: string | null;
    by: string | null;
}

interface Velocity {
    series: { date: string; units_sold: number | null; units_on_hand: number | null; source: string }[];
    weekly: number | null;
    total_sold: number;
    on_hand: number | null;
    days_of_cover: number | null;
    sell_out_date: string | null;
    go_live: string | null;
    days_live: number | null;
    units_ordered: number;
}

const props = defineProps<{
    account: Record<string, any>;
    velocity: Velocity;
    thread: ThreadMessage[];
    timeline: TimelineItem[];
    stats: { total_units: number; total_revenue: number; units_sold_reported: number; last_check_in: string | null };
    stages: { value: string; label: string }[];
    options: Record<string, any>;
}>();

const today = new Date().toISOString().slice(0, 10);

// --- stage move ---
const stageOpen = ref(false);
const stageForm = useForm({ stage: props.account.stage, note: '', lost_reason: '' });
const submitStage = () =>
    stageForm.patch(`/accounts/${props.account.id}/stage`, {
        preserveScroll: true,
        onSuccess: () => {
            stageOpen.value = false;
            stageForm.reset('note', 'lost_reason');
        },
    });

// --- sample ---
const sampleOpen = ref(false);
const sampleForm = useForm({ date: today, pucks_given: 4, given_to: 'decision_maker', notes: '' });
const submitSample = () =>
    sampleForm.post(`/accounts/${props.account.id}/samples`, {
        preserveScroll: true,
        onSuccess: () => {
            sampleOpen.value = false;
            sampleForm.reset();
        },
    });

// --- check-in ---
const checkInOpen = ref(false);
const checkInForm = useForm({
    date: today,
    cadence: 'adhoc',
    units_sold: null as number | null,
    units_on_hand: null as number | null,
    flavors_moving: '',
    staff_recommends: null as boolean | null,
    buyer_profile: '',
    objections: '',
    ready_for_reorder: false,
    notes: '',
});
const submitCheckIn = () =>
    checkInForm.post(`/accounts/${props.account.id}/check-ins`, {
        preserveScroll: true,
        onSuccess: () => {
            checkInOpen.value = false;
            checkInForm.reset();
        },
    });

// --- order ---
const orderOpen = ref(false);
const orderForm = useForm({
    type: 'opening',
    date: today,
    quantity: 25,
    unit_price: null as number | null,
    payment_status: 'pending',
    fulfilled_at: '',
    notes: '',
});
const submitOrder = () =>
    orderForm
        .transform((d) => ({ ...d, fulfilled_at: d.fulfilled_at || null, unit_price: d.unit_price || null }))
        .post(`/accounts/${props.account.id}/orders`, {
            preserveScroll: true,
            onSuccess: () => {
                orderOpen.value = false;
                orderForm.reset();
            },
        });

const kindColor: Record<string, string> = {
    stage: 'text-ff-tan-light',
    sample: 'text-white/[0.72]',
    order: 'text-ff-success',
    check_in: 'text-ff-warning',
    message: 'text-white/[0.55]',
};
const kindLabel: Record<string, string> = {
    stage: 'Stage',
    sample: 'Sample',
    order: 'Order',
    check_in: 'Check-in',
    message: 'Message',
};

const stageIsHealthy = computed(() => ['selling', 'reordered', 'repeat_account'].includes(props.account.stage));
const money = (n: number) => '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head :title="account.name" />
    <FFLayout>
        <div class="mb-4">
            <Link href="/pipeline" class="ff-label text-white/[0.55] no-underline hover:text-white">← Pipeline</Link>
        </div>

        <!-- Hero plate — the one notched panel on this screen -->
        <div class="ff-card ff-notch mb-5 p-6">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <div class="ff-label-sm text-ff-tan-light">
                        {{ account.retailer_type_label ?? 'Retailer' }} · {{ account.engine }}
                        <span v-if="account.signup_source === 'self_service'"> · Self-service signup</span>
                    </div>
                    <h1 class="ff-display mt-2 text-[42px] leading-[0.96]">{{ account.name }}</h1>
                    <div class="mt-2 text-white/[0.72]">
                        {{ [account.city, account.state].filter(Boolean).join(', ') }}
                        <span v-if="account.decision_maker"> · {{ account.decision_maker }}</span>
                        <span v-if="account.phone" class="ff-mono text-[12px]"> · {{ account.phone }}</span>
                    </div>
                </div>
                <div class="flex flex-none flex-col items-end gap-3">
                    <button
                        type="button"
                        class="ff-status cursor-pointer border bg-transparent px-4 py-2.5"
                        :class="
                            account.stage === 'lost'
                                ? 'border-white/[0.18] text-white/[0.4]'
                                : stageIsHealthy
                                  ? 'border-ff-success/40 text-ff-success'
                                  : 'border-white/[0.35] text-white'
                        "
                        @click="stageOpen = true"
                    >
                        <span class="ff-dot"></span>
                        {{ account.stage_label }}
                        <span class="ml-1 text-white/[0.4]">Change</span>
                    </button>
                    <Link :href="`/accounts/${account.id}/edit`" class="ff-btn ff-btn-ghost no-underline">Edit details</Link>
                </div>
            </div>

            <div v-if="account.lost_reason" class="mt-4 border-l-2 border-l-ff-red-bright bg-ink px-4 py-2.5 text-[13px] text-white/[0.82]">
                Lost: {{ account.lost_reason }}
            </div>
        </div>

        <!-- Quick-log row -->
        <div class="mb-5 flex flex-wrap gap-3">
            <button type="button" class="ff-btn ff-btn-primary" @click="checkInOpen = true">Log check-in</button>
            <button type="button" class="ff-btn ff-btn-secondary" @click="sampleOpen = true">Log sample</button>
            <button type="button" class="ff-btn ff-btn-secondary" @click="orderOpen = true">Log order</button>
        </div>

        <!-- Conversation thread -->
        <div id="messages" class="mb-5">
            <MessageThread
                :account="{ id: account.id, phone: account.phone, email: account.email, sms_opted_out: account.sms_opted_out }"
                :thread="thread"
            />
        </div>

        <!-- Sell-through velocity -->
        <div class="ff-card mb-5 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="ff-label-sm text-ff-tan-light">Sell-through velocity</div>
                    <p class="mt-1.5 text-[13px] text-white/[0.55]">
                        <template v-if="velocity.go_live">Live {{ velocity.days_live }} days · {{ velocity.units_ordered }} units shipped</template>
                        <template v-else>Not live yet — velocity starts when an opening order is fulfilled.</template>
                    </p>
                </div>
                <div class="flex flex-wrap gap-8">
                    <div>
                        <div class="ff-label-sm text-white/[0.55]">Units / week</div>
                        <div class="ff-display mt-1.5 text-[30px]">{{ velocity.weekly ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="ff-label-sm text-white/[0.55]">On hand</div>
                        <div class="ff-display mt-1.5 text-[30px]">{{ velocity.on_hand ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="ff-label-sm text-white/[0.55]">Days of cover</div>
                        <div
                            class="ff-display mt-1.5 text-[30px]"
                            :class="velocity.days_of_cover !== null && velocity.days_of_cover <= 14 ? 'text-ff-warning' : ''"
                        >
                            {{ velocity.days_of_cover ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="velocity.series.length > 0" class="mt-5">
                <VelocityChart :series="velocity.series" />
                <div
                    v-if="velocity.sell_out_date"
                    class="mt-4 border-t border-white/[0.1] pt-3 text-[13px]"
                    :class="velocity.days_of_cover !== null && velocity.days_of_cover <= 14 ? 'text-ff-warning' : 'text-white/[0.72]'"
                >
                    At this rate the shelf is empty around {{ velocity.sell_out_date }}. Reorder before then.
                </div>
            </div>

            <div v-else class="mt-5 border-t border-white/[0.1] pt-5 text-[13px] text-white/[0.55]">
                No check-ins yet, so there is no velocity to show. Log the first one and this fills in.
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
            <!-- Timeline -->
            <div class="ff-card">
                <div class="flex items-center justify-between border-b border-white/[0.18] px-5 py-4">
                    <span class="ff-display text-xl">Timeline</span>
                    <span class="ff-label-sm text-white/[0.55]">{{ timeline.length }} events</span>
                </div>
                <div v-if="timeline.length === 0" class="px-5 py-10 text-center text-white/[0.55]">
                    Nothing logged yet. Start with a sample or a conversation.
                </div>
                <div v-for="(item, i) in timeline" :key="i" class="flex gap-4 border-b border-white/[0.1] px-5 py-3.5 last:border-b-0">
                    <div class="ff-mono w-[86px] flex-none pt-0.5 text-[11px] text-white/[0.55]">{{ item.date }}</div>
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2.5">
                            <span class="ff-status flex-none" :class="kindColor[item.kind]">
                                <span class="ff-dot"></span>{{ kindLabel[item.kind] }}
                            </span>
                            <span class="text-sm text-white">{{ item.title }}</span>
                        </div>
                        <div v-if="item.detail" class="mt-1 text-[13px] leading-relaxed text-white/[0.55]">{{ item.detail }}</div>
                        <div v-if="item.by" class="ff-label-sm mt-1 text-white/[0.35]">{{ item.by }}</div>
                    </div>
                </div>
            </div>

            <!-- Side rail -->
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="ff-card px-4 py-4">
                        <div class="ff-label-sm text-white/[0.55]">Units ordered</div>
                        <div class="ff-display mt-2 text-[28px]">{{ stats.total_units }}</div>
                    </div>
                    <div class="ff-card px-4 py-4">
                        <div class="ff-label-sm text-white/[0.55]">Revenue</div>
                        <div class="ff-display mt-2 text-[28px]">{{ money(stats.total_revenue) }}</div>
                    </div>
                    <div class="ff-card px-4 py-4">
                        <div class="ff-label-sm text-white/[0.55]">Units sold (reported)</div>
                        <div class="ff-display mt-2 text-[28px]">{{ stats.units_sold_reported }}</div>
                    </div>
                    <div class="ff-card px-4 py-4">
                        <div class="ff-label-sm text-white/[0.55]">Last check-in</div>
                        <div class="ff-display mt-2 text-[17px] leading-tight">{{ stats.last_check_in ?? '—' }}</div>
                    </div>
                </div>

                <div class="ff-card p-5">
                    <div class="ff-label-sm text-ff-tan-light">Next action</div>
                    <div v-if="account.next_action" class="mt-2.5 text-sm text-white">{{ account.next_action }}</div>
                    <div v-if="account.next_action_date" class="ff-mono mt-1.5 text-[12px] text-white/[0.55]">{{ account.next_action_date }}</div>
                    <div v-if="!account.next_action" class="mt-2.5 text-[13px] text-white/[0.55]">
                        No next action set. An account without a next action disappears from the data.
                    </div>
                </div>

                <div class="ff-card p-5">
                    <div class="ff-label-sm text-ff-tan-light">Profile</div>
                    <dl class="mt-3 flex flex-col gap-2.5 text-[13px]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-white/[0.55]">Lead source</dt>
                            <dd class="text-white/[0.82]">{{ account.lead_source ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-white/[0.55]">Owner</dt>
                            <dd class="text-white/[0.82]">{{ account.owner ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-white/[0.55]">Email</dt>
                            <dd class="truncate text-white/[0.82]">{{ account.email ?? '—' }}</dd>
                        </div>
                        <div v-if="account.service_community_link" class="flex flex-col gap-1">
                            <dt class="text-white/[0.55]">Service community</dt>
                            <dd class="text-white/[0.82]">{{ account.service_community_link }}</dd>
                        </div>
                        <div v-if="account.notes" class="flex flex-col gap-1 border-t border-white/[0.1] pt-2.5">
                            <dt class="text-white/[0.55]">Notes</dt>
                            <dd class="whitespace-pre-line text-white/[0.82]">{{ account.notes }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- ============ Modals ============ -->
        <FFModal :open="stageOpen" eyebrow="Pipeline" :title="`Move ${account.name}?`" @close="stageOpen = false">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Stage</label>
                    <select v-model="stageForm.stage" class="ff-input">
                        <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div v-if="stageForm.stage === 'lost'" class="flex flex-col gap-2">
                    <label class="ff-field-label">Lost reason</label>
                    <input v-model="stageForm.lost_reason" type="text" class="ff-input" placeholder="Counter space, price, timing…" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Note (optional)</label>
                    <input v-model="stageForm.note" type="text" class="ff-input" />
                </div>
            </div>
            <template #actions>
                <button type="button" class="ff-btn ff-btn-secondary" @click="stageOpen = false">Cancel</button>
                <button type="button" class="ff-btn ff-btn-primary" :disabled="stageForm.processing" @click="submitStage">Move stage</button>
            </template>
        </FFModal>

        <FFModal :open="sampleOpen" eyebrow="Demand seeding" title="Log sample" @close="sampleOpen = false">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Date</label>
                        <input v-model="sampleForm.date" type="date" class="ff-input" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Pucks given</label>
                        <input v-model.number="sampleForm.pucks_given" type="number" min="1" class="ff-input ff-input-numeric" />
                        <div v-if="sampleForm.errors.pucks_given" class="ff-error">{{ sampleForm.errors.pucks_given }}</div>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Given to</label>
                    <select v-model="sampleForm.given_to" class="ff-input">
                        <option value="decision_maker">Decision maker</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Notes</label>
                    <input v-model="sampleForm.notes" type="text" class="ff-input" placeholder="Reaction, flavors handed out…" />
                </div>
            </div>
            <template #actions>
                <button type="button" class="ff-btn ff-btn-secondary" @click="sampleOpen = false">Cancel</button>
                <button type="button" class="ff-btn ff-btn-primary" :disabled="sampleForm.processing" @click="submitSample">Log sample</button>
            </template>
        </FFModal>

        <FFModal :open="checkInOpen" eyebrow="Sell-through" title="Log check-in" @close="checkInOpen = false">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Date</label>
                        <input v-model="checkInForm.date" type="date" class="ff-input" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Window</label>
                        <select v-model="checkInForm.cadence" class="ff-input">
                            <option value="7">Day 7</option>
                            <option value="14">Day 14</option>
                            <option value="30">Day 30</option>
                            <option value="adhoc">Ad hoc</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Units sold since last</label>
                        <input v-model.number="checkInForm.units_sold" type="number" min="0" class="ff-input ff-input-numeric" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Units on hand</label>
                        <input v-model.number="checkInForm.units_on_hand" type="number" min="0" class="ff-input ff-input-numeric" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Flavors moving (comma-separated)</label>
                    <input v-model="checkInForm.flavors_moving" type="text" class="ff-input" placeholder="Original, Citrus" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Staff recommending it?</label>
                    <select v-model="checkInForm.staff_recommends" class="ff-input">
                        <option :value="null">Unknown</option>
                        <option :value="true">Yes</option>
                        <option :value="false">No</option>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Who's buying</label>
                    <input v-model="checkInForm.buyer_profile" type="text" class="ff-input" placeholder="Morning regulars, contractors…" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Objections / friction</label>
                    <input v-model="checkInForm.objections" type="text" class="ff-input" />
                </div>
                <label class="flex cursor-pointer items-center gap-3 py-1">
                    <input v-model="checkInForm.ready_for_reorder" type="checkbox" class="h-4 w-4 accent-[#C8102E]" />
                    <span class="text-sm text-white/[0.82]">Ready for reorder</span>
                </label>
            </div>
            <template #actions>
                <button type="button" class="ff-btn ff-btn-secondary" @click="checkInOpen = false">Cancel</button>
                <button type="button" class="ff-btn ff-btn-primary" :disabled="checkInForm.processing" @click="submitCheckIn">Log check-in</button>
            </template>
        </FFModal>

        <FFModal :open="orderOpen" eyebrow="Orders" title="Log order" @close="orderOpen = false">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Type</label>
                        <select v-model="orderForm.type" class="ff-input">
                            <option value="opening">Opening order</option>
                            <option value="reorder">Reorder</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Date</label>
                        <input v-model="orderForm.date" type="date" class="ff-input" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Units</label>
                        <input v-model.number="orderForm.quantity" type="number" min="1" step="5" class="ff-input ff-input-numeric" />
                        <div v-if="orderForm.errors.quantity" class="ff-error">{{ orderForm.errors.quantity }}</div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Unit price (blank = tier)</label>
                        <input v-model.number="orderForm.unit_price" type="number" min="0" step="0.01" class="ff-input ff-input-numeric" placeholder="Auto" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Payment</label>
                        <select v-model="orderForm.payment_status" class="ff-input">
                            <option value="pending">Pending</option>
                            <option value="invoiced">Invoiced</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label">Fulfilled / delivered</label>
                        <input v-model="orderForm.fulfilled_at" type="date" class="ff-input" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label">Notes</label>
                    <input v-model="orderForm.notes" type="text" class="ff-input" />
                </div>
            </div>
            <template #actions>
                <button type="button" class="ff-btn ff-btn-secondary" @click="orderOpen = false">Cancel</button>
                <button type="button" class="ff-btn ff-btn-cta" :disabled="orderForm.processing" @click="submitOrder">Log order</button>
            </template>
        </FFModal>
    </FFLayout>
</template>
