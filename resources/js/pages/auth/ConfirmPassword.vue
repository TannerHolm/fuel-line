<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post('/confirm-password', { onFinish: () => form.reset() });
</script>

<template>
    <AuthLayout title="Confirm password" description="Confirm your password to continue into a secured area.">
        <Head title="Confirm password" />

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="password">Password</label>
                <input id="password" v-model="form.password" type="password" required autofocus autocomplete="current-password" class="ff-input" />
                <div v-if="form.errors.password" class="ff-error">{{ form.errors.password }}</div>
            </div>

            <button type="submit" class="ff-btn ff-btn-primary mt-2 w-full" :disabled="form.processing">Confirm password</button>
        </form>
    </AuthLayout>
</template>
