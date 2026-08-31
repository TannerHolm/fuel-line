<script setup lang="ts">
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ mustVerifyEmail: boolean; status?: string }>();

const page = usePage();
const user = (page.props.auth as any).user;

const form = useForm({ name: user.name, email: user.email });
const submit = () => form.patch('/settings/profile', { preserveScroll: true });

const confirmingDelete = ref(false);
const deleteForm = useForm({ password: '' });
const deleteUser = () =>
    deleteForm.delete('/settings/profile', {
        preserveScroll: true,
        onError: () => deleteForm.reset('password'),
    });
</script>

<template>
    <Head title="Profile settings" />
    <SettingsLayout>
        <div class="ff-card p-6">
            <div class="ff-label-sm text-ff-tan-light">Profile</div>
            <p class="mt-2 text-[13px] text-white/[0.55]">The name and email on your login.</p>

            <form class="mt-5 flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="name">Name</label>
                    <input id="name" v-model="form.name" type="text" class="ff-input" autocomplete="name" />
                    <div v-if="form.errors.name" class="ff-error">{{ form.errors.name }}</div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="email">Email address</label>
                    <input id="email" v-model="form.email" type="email" class="ff-input" autocomplete="username" />
                    <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
                </div>

                <div v-if="mustVerifyEmail && !user.email_verified_at" class="border-l-2 border-l-ff-tan bg-ink px-4 py-3">
                    <p class="text-[13px] text-white/[0.72]">
                        This email is unverified.
                        <Link href="/email/verification-notification" method="post" as="button" class="text-ff-red-bright underline">
                            Resend the verification email.
                        </Link>
                    </p>
                    <p v-if="status === 'verification-link-sent'" class="mt-2 text-[13px] text-ff-success">
                        A new verification link is on its way.
                    </p>
                </div>

                <div class="mt-2 flex items-center gap-4 border-t border-white/[0.18] pt-5">
                    <button type="submit" class="ff-btn ff-btn-primary" :disabled="form.processing">Save profile</button>
                    <span v-if="form.recentlySuccessful" class="ff-status text-ff-success"><span class="ff-dot"></span>Saved</span>
                </div>
            </form>
        </div>

        <div class="ff-card mt-4 border-l-2 border-l-ff-red-bright p-6">
            <div class="ff-label-sm text-ff-red-bright">Delete login</div>
            <p class="mt-2 max-w-lg text-[13px] leading-relaxed text-white/[0.72]">
                Deletes your login only. Account records, orders, and check-ins stay in Fuel Line — the history the KPIs are
                built on is never removed this way. This cannot be undone.
            </p>

            <button v-if="!confirmingDelete" type="button" class="ff-btn ff-btn-destructive mt-4" @click="confirmingDelete = true">
                Delete login
            </button>

            <form v-else class="mt-4 flex flex-col gap-3" @submit.prevent="deleteUser">
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="delete_password">Confirm your password</label>
                    <input id="delete_password" v-model="deleteForm.password" type="password" class="ff-input max-w-sm" autocomplete="current-password" />
                    <div v-if="deleteForm.errors.password" class="ff-error">{{ deleteForm.errors.password }}</div>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="ff-btn ff-btn-secondary" @click="confirmingDelete = false; deleteForm.reset()">Cancel</button>
                    <button type="submit" class="ff-btn ff-btn-cta" :disabled="deleteForm.processing">Delete login</button>
                </div>
            </form>
        </div>
    </SettingsLayout>
</template>
