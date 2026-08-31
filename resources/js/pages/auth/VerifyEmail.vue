<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();

const form = useForm({});
const submit = () => form.post('/email/verification-notification');
</script>

<template>
    <AuthLayout title="Verify email" description="Click the link we just emailed you to finish setting up your login.">
        <Head title="Email verification" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 border-l-2 border-l-ff-success bg-ink px-4 py-2.5 text-[13px] text-white/[0.82]">
            A new verification link is on its way.
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <button type="submit" class="ff-btn ff-btn-secondary w-full" :disabled="form.processing">Resend verification email</button>
        </form>

        <p class="mt-5 border-t border-white/[0.18] pt-5 text-center text-[13px] text-white/[0.55]">
            <Link href="/logout" method="post" as="button" class="text-ff-red-bright no-underline hover:underline">Sign out</Link>
        </p>
    </AuthLayout>
</template>
