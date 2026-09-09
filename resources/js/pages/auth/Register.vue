<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ retailerTypes: { value: string; label: string }[] }>();

const form = useForm({
    business_name: '',
    city: '',
    state: '',
    retailer_type: '',
    name: '',
    phone: '',
    sms_consent: false,
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.transform((d) => ({
        ...d,
        retailer_type: d.retailer_type || null,
        state: d.state ? d.state.toUpperCase() : null,
    })).post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create your wholesale account" description="Three minutes from here to a submitted pilot order.">
        <Head title="Become a partner" />

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="ff-label-sm text-ff-tan-light">Your business</div>

            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="business_name">Business name</label>
                <input id="business_name" v-model="form.business_name" type="text" required autofocus class="ff-input" placeholder="Ridgeline Supply Co." />
                <div v-if="form.errors.business_name" class="ff-error">{{ form.errors.business_name }}</div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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

            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="retailer_type">What kind of store</label>
                <select id="retailer_type" v-model="form.retailer_type" class="ff-input">
                    <option value="">Choose one</option>
                    <option v-for="t in retailerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <div class="ff-label-sm mt-3 text-ff-tan-light">Your login</div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="name">Your name</label>
                    <input id="name" v-model="form.name" type="text" required autocomplete="name" class="ff-input" />
                    <div v-if="form.errors.name" class="ff-error">{{ form.errors.name }}</div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="phone">Phone</label>
                    <input id="phone" v-model="form.phone" type="text" class="ff-input" />
                </div>
            </div>

            <label class="flex cursor-pointer items-start gap-3">
                <input v-model="form.sms_consent" type="checkbox" class="mt-1 h-4 w-4 flex-none accent-white" />
                <span class="text-[13px] leading-relaxed text-white/[0.55]">
                    Text me order updates, account service, and occasional offers from Freedom Fuel at the number
                    above. Message frequency varies, message and data rates may apply. Reply STOP to opt out, HELP for
                    help. Consent is not a condition of purchase. See our
                    <Link href="/privacy" class="text-white/[0.72] underline">Privacy Policy</Link> and
                    <Link href="/terms" class="text-white/[0.72] underline">SMS Terms</Link>.
                </span>
            </label>

            <div class="flex flex-col gap-2">
                <label class="ff-field-label" for="email">Email address</label>
                <input id="email" v-model="form.email" type="email" required autocomplete="email" class="ff-input" placeholder="you@yourstore.com" />
                <div v-if="form.errors.email" class="ff-error">{{ form.errors.email }}</div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="password">Password</label>
                    <input id="password" v-model="form.password" type="password" required autocomplete="new-password" class="ff-input" />
                    <div v-if="form.errors.password" class="ff-error">{{ form.errors.password }}</div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="ff-field-label" for="password_confirmation">Confirm</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="ff-input" />
                </div>
            </div>

            <button type="submit" class="ff-btn ff-btn-cta mt-3 w-full" :disabled="form.processing">Create wholesale account</button>
        </form>

        <p class="mt-5 border-t border-white/[0.18] pt-5 text-center text-[13px] text-white/[0.55]">
            Already a partner?
            <Link href="/login" class="text-ff-red-bright no-underline hover:underline">Sign in</Link>
        </p>
    </AuthBase>
</template>
