<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    roles: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    employee_no: '',
    notes: '',
    status: 'active',
    password: '',
    password_confirmation: '',
    must_change_password: true,
    roles: [],
});

function submit() {
    form.post(route('users.store'));
}

function generatePassword() {
    // كلمة مرور مؤقتة تُسلَّم للموظف ويُجبَر على تغييرها عند أول دخول.
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$';
    const bytes = crypto.getRandomValues(new Uint32Array(14));
    const password = [...bytes].map((n) => alphabet[n % alphabet.length]).join('');

    form.password = password;
    form.password_confirmation = password;
    form.must_change_password = true;
}
</script>

<template>
    <Head title="مستخدم جديد" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">مستخدم جديد</h2>
                <Link :href="route('users.index')" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    رجوع للقائمة
                </Link>
            </div>
        </template>

        <div class="py-8">
            <form
                class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8"
                @submit.prevent="submit"
            >
                <section class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="border-b border-slate-100 pb-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">
                        البيانات الأساسية
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="الاسم" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="email" value="البريد الإلكتروني" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.email" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="رقم الهاتف" />
                            <TextInput id="phone" v-model="form.phone" class="mt-1 block w-full" />
                            <InputError :message="form.errors.phone" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="employee_no" value="الرقم الوظيفي" />
                            <TextInput id="employee_no" v-model="form.employee_no" class="mt-1 block w-full" />
                            <InputError :message="form.errors.employee_no" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="status" value="الحالة" />
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            >
                                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="notes" value="ملاحظات" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        ></textarea>
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>
                </section>

                <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="border-b border-slate-100 pb-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">
                        الأدوار
                    </h3>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        الأدوار تحدد صلاحيات المستخدم. يمكن ضبط صلاحيات إضافية خاصة به من صفحته بعد الإنشاء.
                    </p>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="role in roles"
                            :key="role.name"
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 p-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-900/40"
                        >
                            <input
                                v-model="form.roles"
                                type="checkbox"
                                :value="role.name"
                                class="rounded border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <span class="flex-1 text-slate-700 dark:text-slate-200">{{ role.label }}</span>
                            <span class="text-xs text-slate-400">{{ role.permissions_count }} صلاحية</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.roles" class="mt-1" />
                </section>

                <section class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">كلمة المرور</h3>
                        <button
                            type="button"
                            class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                            @click="generatePassword"
                        >
                            توليد كلمة مرور قوية
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="password" value="كلمة المرور" />
                            <TextInput id="password" v-model="form.password" type="text" class="mt-1 block w-full font-mono" required />
                            <InputError :message="form.errors.password" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="text"
                                class="mt-1 block w-full font-mono"
                                required
                            />
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input
                            v-model="form.must_change_password"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-800"
                        />
                        إلزام المستخدم بتغيير كلمة المرور عند أول تسجيل دخول
                    </label>
                </section>

                <div class="flex items-center gap-3">
                    <PrimaryButton :disabled="form.processing">إنشاء المستخدم</PrimaryButton>
                    <Link :href="route('users.index')" class="text-sm text-slate-500 hover:underline dark:text-slate-400">إلغاء</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
