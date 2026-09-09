<script setup lang="ts">
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    account: string | null;
    created_at: string | null;
}

defineProps<{ users: UserRow[]; accounts: { id: number; name: string }[] }>();

const page = usePage();
const inviteLink = computed(() => (page.props as any).flash?.invite_link as string | undefined);

const form = useForm({ name: '', email: '', role: 'founder', account_id: '' as number | '' });

const addUser = () =>
    form
        .transform((data) => ({ ...data, account_id: data.role === 'retailer' ? data.account_id : null }))
        .post('/settings/users', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });

const inviting = ref<number | null>(null);
const newLink = (user: UserRow) => {
    inviting.value = user.id;
    useForm({}).post(`/settings/users/${user.id}/invite`, {
        preserveScroll: true,
        onFinish: () => (inviting.value = null),
    });
};

const copied = ref(false);
const copyLink = async () => {
    if (!inviteLink.value) return;
    await navigator.clipboard.writeText(inviteLink.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const roleColor = (role: string) =>
    role === 'founder' ? 'text-ff-tan-light' : role === 'retailer' ? 'text-ff-success' : 'text-white/[0.55]';
</script>

<template>
    <Head title="User settings" />
    <SettingsLayout>
        <div class="flex flex-col gap-6">
            <div v-if="inviteLink" class="ff-card border-l-2 border-l-ff-tan-light p-5">
                <div class="ff-label-sm text-ff-tan-light">One-time set-password link</div>
                <p class="mt-2 text-[13px] text-white/[0.55]">
                    Send this to the new user. It expires in 60 minutes and works once — after that, use New link on their row.
                </p>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <code class="ff-mono block min-w-0 flex-1 overflow-x-auto whitespace-nowrap border border-white/[0.18] bg-ink px-3 py-2.5 text-xs text-white/[0.72]">{{ inviteLink }}</code>
                    <button type="button" class="ff-btn ff-btn-secondary flex-none" @click="copyLink">
                        {{ copied ? 'Copied' : 'Copy link' }}
                    </button>
                </div>
            </div>

            <div class="ff-card p-6">
                <div class="ff-label-sm text-ff-tan-light">Add a user</div>
                <p class="mt-2 text-[13px] text-white/[0.55]">
                    No password is set here. You get a one-time link the person uses to choose their own.
                </p>

                <form class="mt-5 flex flex-col gap-4" @submit.prevent="addUser">
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <div class="flex flex-1 flex-col gap-2">
                            <label class="ff-field-label" for="name">Full name</label>
                            <input id="name" v-model="form.name" type="text" class="ff-input" autocomplete="off" />
                            <div v-if="form.errors.name" class="ff-error">{{ form.errors.name }}</div>
                        </div>
                        <div class="flex flex-1 flex-col gap-2">
                            <label class="ff-field-label" for="email">Email address</label>
                            <input id="email" v-model="form.email" type="email" class="ff-input" autocomplete="off" />
                            <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row">
                        <div class="flex flex-1 flex-col gap-2">
                            <label class="ff-field-label" for="role">Role</label>
                            <select id="role" v-model="form.role" class="ff-input">
                                <option value="founder">Founder</option>
                                <option value="retailer">Retailer</option>
                            </select>
                            <div v-if="form.errors.role" class="ff-error">{{ form.errors.role }}</div>
                        </div>
                        <div v-if="form.role === 'retailer'" class="flex flex-1 flex-col gap-2">
                            <label class="ff-field-label" for="account_id">Account</label>
                            <select id="account_id" v-model="form.account_id" class="ff-input">
                                <option value="" disabled>Pick the retailer's account</option>
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <div v-if="form.errors.account_id" class="ff-error">{{ form.errors.account_id }}</div>
                        </div>
                        <div v-else class="hidden flex-1 sm:block"></div>
                    </div>

                    <div class="mt-1 flex items-center gap-4 border-t border-white/[0.18] pt-5">
                        <button type="submit" class="ff-btn ff-btn-primary" :disabled="form.processing">Create login</button>
                    </div>
                </form>
            </div>

            <div class="ff-card overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.18]">
                            <th class="ff-label-sm px-5 py-2.5 text-left text-white/[0.55]">Name</th>
                            <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] sm:table-cell">Email</th>
                            <th class="ff-label-sm px-5 py-2.5 text-left text-white/[0.55]">Role</th>
                            <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] md:table-cell">Account</th>
                            <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] lg:table-cell">Added</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id" class="border-b border-white/[0.1] last:border-b-0">
                            <td class="px-5 py-3 font-medium text-white">
                                {{ u.name }}
                                <span class="ff-label-sm mt-1 block text-white/[0.55] sm:hidden">{{ u.email }}</span>
                            </td>
                            <td class="hidden px-5 py-3 text-white/[0.72] sm:table-cell">{{ u.email }}</td>
                            <td class="px-5 py-3">
                                <span class="ff-status" :class="roleColor(u.role)"><span class="ff-dot"></span>{{ u.role }}</span>
                            </td>
                            <td class="hidden px-5 py-3 text-white/[0.72] md:table-cell">{{ u.account ?? '—' }}</td>
                            <td class="hidden px-5 py-3 text-white/[0.72] lg:table-cell">{{ u.created_at ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <button
                                    type="button"
                                    class="ff-btn ff-btn-ghost whitespace-nowrap"
                                    :disabled="inviting === u.id"
                                    @click="newLink(u)"
                                >
                                    New link
                                </button>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-white/[0.55]">No users yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SettingsLayout>
</template>
