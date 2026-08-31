<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();

const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password');
</script>

<template>
    <AuthLayout title="Forgot password" description="We'll email you a link to set a new one.">
        <Head title="Forgot password" />

        <div v-if="status" class="mb-4 border-l-2 border-l-ff-success bg-ink px-4 py-2.5 text-[13px] text-white/[0.82]">
            {{ status }}
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="email">Email address</label>
                <input id="email" v-model="form.email" type="email" autofocus autocomplete="email" class="ff-input" placeholder="you@yourstore.com" />
                <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
            </div>

            <button type="submit" class="ff-btn ff-btn-primary mt-2 w-full" :disabled="form.processing">Email reset link</button>
        </form>

        <p class="mt-5 border-t border-white/[0.18] pt-5 text-center text-[13px] text-white/[0.55]">
            <Link href="/login" class="text-ff-red-bright no-underline hover:underline">Back to log in</Link>
        </p>
    </AuthLayout>
</template>
