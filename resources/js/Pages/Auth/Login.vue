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

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">تسجيل الدخول</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أدخل بيانات حسابك للمتابعة إلى نظام إدارة الصرافة.</p>
        </div>

        <div v-if="status" class="mb-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            {{ status }}
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
                        class="block w-full ps-10"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
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
                        class="block w-full ps-10 pe-10"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 end-3 flex items-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
                        @click="showPassword = !showPassword"
                    >
                        <NavIcon :name="showPassword ? 'eye-off' : 'eye'" class="h-4.5 w-4.5" />
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-slate-600 dark:text-slate-400">تذكرني</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-slate-500 underline underline-offset-2 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                >
                    نسيت كلمة المرور؟
                </Link>
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center rounded-lg bg-[#141b4d] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0d1338] disabled:opacity-50"
                :disabled="form.processing"
            >
                تسجيل الدخول
            </button>
        </form>
    </GuestLayout>
</template>
