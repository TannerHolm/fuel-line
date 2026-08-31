<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthLayout title="Reset password" description="Choose a new password for your login.">
        <Head title="Reset password" />

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" class="ff-input opacity-60" readonly />
                <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="password">New password</label>
                <input id="password" v-model="form.password" type="password" autofocus autocomplete="new-password" class="ff-input" />
                <div v-if="form.errors.password" class="ff-error">{{ form.errors.password }}</div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="ff-input" />
                <div v-if="form.errors.password_confirmation" class="ff-error">{{ form.errors.password_confirmation }}</div>
            </div>

            <button type="submit" class="ff-btn ff-btn-primary mt-2 w-full" :disabled="form.processing">Reset password</button>
        </form>
    </AuthLayout>
</template>
