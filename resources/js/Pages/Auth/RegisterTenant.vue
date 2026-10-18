<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import NavIcon from '@/Components/NavIcon.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    centralDomain: {
        type: String,
        default: 'exchange.damatech.sy',
    },
    createdTenant: {
        type: Object,
        default: null,
    },
    selectedPlanId: {
        type: Number,
        default: null,
    },
    plans: {
        type: Array,
        default: () => [],
    },
});

const showPassword = ref(false);

const form = useForm({
    company_name: '',
    subdomain: '',
    admin_name: '',
    admin_email: '',
    password: '',
    password_confirmation: '',
    plan_id: props.selectedPlanId || (props.plans?.length ? props.plans[0].id : null),
});

const submit = () => {
    form.post('/register-company', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل شركة صرافة جديدة" />

        <!-- Success screen after creation -->
        <div v-if="createdTenant" class="text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20 dark:bg-emerald-400/10 dark:text-emerald-400">
                <NavIcon name="check" class="h-8 w-8" />
            </div>

            <h2 class="mt-4 text-2xl font-bold text-slate-900 dark:text-white">تم إنشاء الشركة بنجاح!</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                تم تهيئة قاعدة بيانات مستقلة بالكامل لشركتك وتجهيز الجداول والصلاحيات.
            </p>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-start dark:border-slate-800 dark:bg-slate-800/60">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">اسم الشركة:</span>
                        <span class="font-semibold text-slate-800 dark:text-white">{{ createdTenant.company_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">النطاق الفرعي:</span>
                        <span class="font-mono font-medium text-indigo-600 dark:text-indigo-400">{{ createdTenant.subdomain }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">البريد الإلكتروني:</span>
                        <span class="font-mono text-slate-800 dark:text-white">{{ createdTenant.admin_email }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                <a
                    :href="createdTenant.login_url"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-700 dark:hover:bg-indigo-500"
                >
                    <span>الدخول إلى لوحة تحكم شركتك</span>
                    <NavIcon name="arrow-left" class="h-4 w-4" />
                </a>

                <Link
                    href="/login"
                    class="block text-center text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400"
                >
                    العودة لصفحة الدخول الرئيسية
                </Link>
            </div>
        </div>

        <!-- Registration Form -->
        <div v-else>
            <div class="mb-6">
                <div class="flex flex-col items-center gap-3 text-center lg:items-start lg:text-start">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 ring-1 ring-indigo-500/20 dark:bg-indigo-400/10 dark:text-indigo-400">
                        <NavIcon name="building" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">تسجيل شركة جديدة</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            احصل على نظام محاسبة متكامل بقاعدة بيانات سحابية معزولة ومستقلة لشركتك.
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Plan Selection if available -->
                <div v-if="plans && plans.length > 0">
                    <InputLabel for="plan_id" value="باقة الاشتراك المطلوبة" />
                    <select
                        id="plan_id"
                        v-model="form.plan_id"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs sm:text-sm font-semibold dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                        <option v-for="p in plans" :key="p.id" :value="p.id">
                            {{ p.name }} — ${{ Number(p.price).toFixed(2) }}/شهرياً
                        </option>
                    </select>
                </div>

                <!-- Company Name -->
                <div>
                    <InputLabel for="company_name" value="اسم شركة الصرافة" />
                    <TextInput
                        id="company_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.company_name"
                        placeholder="مثال: شركة البركة للصرافة"
                        required
                        autofocus
                    />
                    <InputError class="mt-1.5" :message="form.errors.company_name" />
                </div>

                <!-- Subdomain -->
                <div>
                    <InputLabel for="subdomain" value="معرّف الرابط (Subdomain)" />
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span class="inline-flex items-center rounded-s-md border border-e-0 border-slate-300 bg-slate-50 px-3 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400" dir="ltr">
                            .{{ centralDomain }}
                        </span>
                        <TextInput
                            id="subdomain"
                            type="text"
                            class="block w-full rounded-s-none text-start font-mono text-sm"
                            dir="ltr"
                            v-model="form.subdomain"
                            placeholder="albaraka"
                            required
                        />
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">أحرف إنجليزية وأرقام وشرطات فقط.</p>
                    <InputError class="mt-1" :message="form.errors.subdomain" />
                </div>

                <!-- Admin Name -->
                <div>
                    <InputLabel for="admin_name" value="اسم المدير المسؤول" />
                    <TextInput
                        id="admin_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.admin_name"
                        placeholder="الاسم الثلاثي"
                        required
                    />
                    <InputError class="mt-1.5" :message="form.errors.admin_name" />
                </div>

                <!-- Admin Email -->
                <div>
                    <InputLabel for="admin_email" value="البريد الإلكتروني للإدارة" />
                    <TextInput
                        id="admin_email"
                        type="email"
                        class="mt-1 block w-full"
                        dir="ltr"
                        v-model="form.admin_email"
                        placeholder="admin@company.com"
                        required
                    />
                    <InputError class="mt-1.5" :message="form.errors.admin_email" />
                </div>

                <!-- Password -->
                <div>
                    <InputLabel for="password" value="كلمة المرور" />
                    <div class="relative mt-1">
                        <TextInput
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="block w-full pl-10"
                            dir="ltr"
                            v-model="form.password"
                            required
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 end-0 flex items-center pe-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                            :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                            @click="showPassword = !showPassword"
                        >
                            <NavIcon :name="showPassword ? 'eye-off' : 'eye'" class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError class="mt-1.5" :message="form.errors.password" />
                </div>

                <!-- Confirm Password -->
                <div>
                    <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" />
                    <TextInput
                        id="password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        class="mt-1 block w-full"
                        dir="ltr"
                        v-model="form.password_confirmation"
                        required
                    />
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-indigo-500"
                        :disabled="form.processing"
                    >
                        <svg v-if="form.processing" class="h-4.5 w-4.5 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
                        </svg>
                        <span>{{ form.processing ? 'جاري إنشاء قاعدة البيانات...' : 'إنشاء الشركة والبدء الآن' }}</span>
                    </button>
                </div>

                <div class="pt-2 flex items-center justify-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <Link href="/" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        العودة للرئيسية
                    </Link>
                    <span>•</span>
                    <div>
                        لديك حساب بالفعل؟
                        <Link href="/login" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                            تسجيل الدخول
                        </Link>
                    </div>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>
