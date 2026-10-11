<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    targetUser: { type: Object, required: true },
    statuses: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: props.targetUser.name ?? '',
    email: props.targetUser.email ?? '',
    phone: props.targetUser.phone ?? '',
    employee_no: props.targetUser.employee_no ?? '',
    notes: props.targetUser.notes ?? '',
    status: props.targetUser.status ?? 'active',
    must_change_password: props.targetUser.must_change_password ?? false,
});

function submit() {
    form.put(route('users.update', props.targetUser.id));
}
</script>

<template>
    <Head :title="`تعديل ${targetUser.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                    تعديل بيانات: {{ targetUser.name }}
                </h2>
                <Link :href="route('users.show', targetUser.id)" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    صفحة المستخدم
                </Link>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8" @submit.prevent="submit">
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
                                :disabled="!can.changeStatus"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            >
                                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <p v-if="!can.changeStatus" class="mt-1 text-xs text-slate-400">
                                لا يمكن تغيير حالة هذا الحساب (حسابك الشخصي أو آخر مدير نشِط).
                            </p>
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

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input
                            v-model="form.must_change_password"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-800"
                        />
                        إلزام المستخدم بتغيير كلمة المرور عند الدخول القادم
                    </label>
                </section>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    الأدوار والصلاحيات وكلمة المرور والجلسات تُدار من
                    <Link :href="route('users.show', targetUser.id)" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        صفحة المستخدم
                    </Link>.
                </p>

                <div class="flex items-center gap-3">
                    <PrimaryButton :disabled="form.processing">حفظ التعديلات</PrimaryButton>
                    <Link :href="route('users.index')" class="text-sm text-slate-500 hover:underline dark:text-slate-400">إلغاء</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
