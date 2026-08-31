<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    account: Record<string, any> | null;
    options: {
        retailer_types: Option[];
        lead_sources: Option[];
        engines: Option[];
        owners: { id: number; name: string }[];
    };
}>();

const editing = props.account !== null;

const form = useForm({
    name: props.account?.name ?? '',
    city: props.account?.city ?? '',
    state: props.account?.state ?? '',
    retailer_type: props.account?.retailer_type ?? '',
    decision_maker: props.account?.decision_maker ?? '',
    phone: props.account?.phone ?? '',
    email: props.account?.email ?? '',
    service_community_link: props.account?.service_community_link ?? '',
    lead_source: props.account?.lead_source ?? '',
    acquisition_engine: props.account?.acquisition_engine ?? 'direct',
    owner_id: props.account?.owner_id ?? '',
    next_action: props.account?.next_action ?? '',
    next_action_date: props.account?.next_action_date ?? '',
    notes: props.account?.notes ?? '',
});

function submit() {
    const data = form.transform((d) => ({
        ...d,
        retailer_type: d.retailer_type || null,
        lead_source: d.lead_source || null,
        owner_id: d.owner_id || null,
        state: d.state ? String(d.state).toUpperCase() : null,
        next_action_date: d.next_action_date || null,
    }));

    if (editing) {
        data.put(`/accounts/${props.account!.id}`);
    } else {
        data.post('/accounts');
    }
}
</script>

<template>
    <Head :title="editing ? 'Edit account' : 'New account'" />
    <FFLayout>
        <div class="mx-auto max-w-2xl">
            <div class="mb-6">
                <Link :href="editing ? `/accounts/${account!.id}` : '/accounts'" class="ff-label text-white/[0.55] no-underline hover:text-white">
                    ← Back
                </Link>
                <h1 class="ff-display mt-3 text-4xl">{{ editing ? 'Edit account' : 'New account' }}</h1>
            </div>

            <form class="ff-card p-6" @submit.prevent="submit">
                <div class="ff-label-sm mb-4 text-ff-tan-light">Identity</div>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="name">Business name</label>
                        <input id="name" v-model="form.name" type="text" class="ff-input" placeholder="Ridgeline Supply Co." />
                        <div v-if="form.errors.name" class="ff-error">{{ form.errors.name }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2 flex flex-col gap-2">
                            <label class="ff-field-label" for="city">City</label>
                            <input id="city" v-model="form.city" type="text" class="ff-input" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="ff-field-label" for="state">State</label>
                            <input id="state" v-model="form.state" type="text" maxlength="2" class="ff-input uppercase" placeholder="UT" />
                            <div v-if="form.errors.state" class="ff-error">{{ form.errors.state }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <label class="ff-field-label" for="type">Retailer type</label>
                            <select id="type" v-model="form.retailer_type" class="ff-input">
                                <option value="">—</option>
                                <option v-for="o in options.retailer_types" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="ff-field-label" for="owner">Owner</label>
                            <select id="owner" v-model="form.owner_id" class="ff-input">
                                <option value="">—</option>
                                <option v-for="o in options.owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="ff-label-sm mb-4 mt-8 text-ff-tan-light">Contact</div>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="dm">Decision maker</label>
                        <input id="dm" v-model="form.decision_maker" type="text" class="ff-input" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <label class="ff-field-label" for="phone">Phone</label>
                            <input id="phone" v-model="form.phone" type="text" class="ff-input" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="ff-field-label" for="email">Email</label>
                            <input id="email" v-model="form.email" type="email" class="ff-input" />
                            <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="scl">Service-community link</label>
                        <input id="scl" v-model="form.service_community_link" type="text" class="ff-input" placeholder="Veteran-owned, sponsors first-responder league…" />
                    </div>
                </div>

                <div class="ff-label-sm mb-4 mt-8 text-ff-tan-light">Acquisition</div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="source">Lead source</label>
                        <select id="source" v-model="form.lead_source" class="ff-input">
                            <option value="">—</option>
                            <option v-for="o in options.lead_sources" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="engine">Engine</label>
                        <select id="engine" v-model="form.acquisition_engine" class="ff-input">
                            <option v-for="o in options.engines" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="ff-label-sm mb-4 mt-8 text-ff-tan-light">Next action</div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2 flex flex-col gap-2">
                        <label class="ff-field-label" for="na">What happens next</label>
                        <input id="na" v-model="form.next_action" type="text" class="ff-input" placeholder="Drop samples Thursday" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="ff-field-label" for="nad">By when</label>
                        <input id="nad" v-model="form.next_action_date" type="date" class="ff-input" />
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <label class="ff-field-label" for="notes">Notes</label>
                    <textarea id="notes" v-model="form.notes" rows="3" class="ff-input"></textarea>
                </div>

                <div class="mt-8 flex justify-end gap-3 border-t border-white/[0.18] pt-5">
                    <Link :href="editing ? `/accounts/${account!.id}` : '/accounts'" class="ff-btn ff-btn-secondary no-underline">Cancel</Link>
                    <button type="submit" class="ff-btn ff-btn-primary" :disabled="form.processing">
                        {{ editing ? 'Save account' : 'Create account' }}
                    </button>
                </div>
            </form>
        </div>
    </FFLayout>
</template>
