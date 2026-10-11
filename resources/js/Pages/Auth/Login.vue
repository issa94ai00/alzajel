<script setup>
import { ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import NavIcon from '@/Components/NavIcon.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل الدخول" />

        <div class="mb-7">
            <div class="flex flex-col items-center gap-4 text-center lg:items-start lg:text-start">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 ring-1 ring-indigo-500/20 dark:bg-indigo-400/10 dark:text-indigo-400">
                    <NavIcon name="lock" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">تسجيل الدخول</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        أهلاً بك، أدخل بيانات حسابك للمتابعة إلى نظام إدارة الصرافة.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="status" class="mb-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            {{ status }}
        </div>

        <div
            v-if="form.hasErrors"
            class="mb-4 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-950/40 dark:text-red-300"
        >
            <NavIcon name="close" class="mt-0.5 h-4 w-4 shrink-0" />
            <span class="leading-relaxed">تعذر تسجيل الدخول. يرجى التحقق من بيانات الاعتماد والمحاولة مرة أخرى.</span>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="البريد الإلكتروني" />

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400 dark:text-slate-500">
                        <NavIcon name="mail" class="h-4.5 w-4.5" />
                    </span>
                    <TextInput
                        id="email"
                        type="email"
                        dir="ltr"
                        class="block w-full ps-10 pe-10 lg:text-sm"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name@example.com"
                        :error="!!form.errors.email"
                    />
                </div>

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="كلمة المرور" />

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400 dark:text-slate-500">
                        <NavIcon name="lock" class="h-4.5 w-4.5" />
                    </span>
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full ps-10 pe-10 lg:text-sm"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        :error="!!form.errors.password"
                    />
                    <button
                        type="button"
                        :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                        class="absolute inset-y-0 end-3 flex items-center rounded-md text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 dark:text-slate-500 dark:hover:text-slate-300"
                        @click="showPassword = !showPassword"
                    >
                        <NavIcon :name="showPassword ? 'eye-off' : 'eye'" class="h-4.5 w-4.5" />
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex cursor-pointer select-none items-center gap-2">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-slate-600 dark:text-slate-400">تذكرني</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-slate-500 underline underline-offset-4 transition hover:text-[#141b4d] dark:text-slate-400 dark:hover:text-slate-200"
                >
                    نسيت كلمة المرور؟
                </Link>
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2.5 rounded-lg bg-[#141b4d] px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-950/20 transition hover:bg-[#0d1338] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:cursor-not-allowed disabled:opacity-60 dark:bg-indigo-600 dark:text-white dark:hover:bg-indigo-500 dark:focus-visible:ring-offset-slate-900"
                :disabled="form.processing"
            >
                <svg v-if="form.processing" class="h-4.5 w-4.5 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
                </svg>
                <span>{{ form.processing ? 'جاري تسجيل الدخول...' : 'تسجيل الدخول' }}</span>
            </button>
        </form>
    </GuestLayout>
</template>