<script setup lang="ts">
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

const updatePassword = () =>
    form.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Password settings" />
    <SettingsLayout>
        <div class="ff-card p-6">
            <div class="ff-label-sm text-ff-tan-light">Password</div>
            <p class="mt-2 text-[13px] text-white/[0.55]">Use a long, random password you don't use anywhere else.</p>

            <form class="mt-5 flex max-w-md flex-col gap-4" @submit.prevent="updatePassword">
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="current_password">Current password</label>
                    <input id="current_password" v-model="form.current_password" type="password" class="ff-input" autocomplete="current-password" />
                    <div v-if="form.errors.current_password" class="ff-error">{{ form.errors.current_password }}</div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="password">New password</label>
                    <input id="password" v-model="form.password" type="password" class="ff-input" autocomplete="new-password" />
                    <div v-if="form.errors.password" class="ff-error">{{ form.errors.password }}</div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="ff-input" autocomplete="new-password" />
                    <div v-if="form.errors.password_confirmation" class="ff-error">{{ form.errors.password_confirmation }}</div>
                </div>

                <div class="mt-2 flex items-center gap-4 border-t border-white/[0.18] pt-5">
                    <button type="submit" class="ff-btn ff-btn-primary" :disabled="form.processing">Save password</button>
                    <span v-if="form.recentlySuccessful" class="ff-status text-ff-success"><span class="ff-dot"></span>Saved</span>
                </div>
            </form>
        </div>
    </SettingsLayout>
</template>
