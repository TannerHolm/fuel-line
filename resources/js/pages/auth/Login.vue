<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string; canResetPassword: boolean }>();

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <AuthBase title="Log in" description="Wholesale partners and Freedom Fuel staff.">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 border-l-2 border-l-ff-success bg-ink px-4 py-2.5 text-[13px] text-white/[0.82]">
            {{ status }}
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="email">Email address</label>
                <input id="email" v-model="form.email" type="email" required autofocus autocomplete="email" class="ff-input" placeholder="you@yourstore.com" />
                <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-baseline justify-between gap-3">
                    <label class="ff-field-label" for="password">Password</label>
                    <Link v-if="canResetPassword" href="/forgot-password" class="ff-label-sm text-white/[0.55] no-underline hover:text-white">
                        Forgot password
                    </Link>
                </div>
                <input id="password" v-model="form.password" type="password" required autocomplete="current-password" class="ff-input" />
                <div v-if="form.errors.password" class="ff-error">{{ form.errors.password }}</div>
            </div>

            <label class="flex cursor-pointer items-center gap-3 py-1">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 accent-[#C8102E]" />
                <span class="text-[14px] text-white/[0.82]">Keep me signed in</span>
            </label>

            <button type="submit" class="ff-btn ff-btn-primary mt-2 w-full" :disabled="form.processing">Log in</button>
        </form>

        <p class="mt-5 border-t border-white/[0.18] pt-5 text-center text-[13px] text-white/[0.55]">
            New wholesale partner?
            <Link href="/register" class="text-ff-red-bright no-underline hover:underline">Create an account</Link>
        </p>
    </AuthBase>
</template>
