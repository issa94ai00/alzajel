<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    currencies: { type: Array, required: true },
});
</script>

<template>
    <Head title="العملات" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">العملات</h2>
                <Link :href="route('currencies.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    + عملة جديدة
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                        <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2 text-start">الرمز</th>
                                <th class="px-4 py-2 text-start">الاسم</th>
                                <th class="px-4 py-2 text-start">العلامة</th>
                                <th class="px-4 py-2 text-start">النوع</th>
                                <th class="px-4 py-2 text-start">الحالة</th>
                                <th class="px-4 py-2 text-start"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                            <tr v-for="currency in currencies" :key="currency.id">
                                <td class="px-4 py-2 font-medium">{{ currency.code }}</td>
                                <td class="px-4 py-2">{{ currency.name }}</td>
                                <td class="px-4 py-2">{{ currency.symbol ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span v-if="currency.is_base" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        عملة أساس
                                    </span>
                                    <span v-else class="text-gray-500 dark:text-slate-400">عملة أجنبية</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="currency.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400'"
                                    >
                                        {{ currency.is_active ? 'مفعّلة' : 'معطّلة' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <Link :href="route('currencies.edit', currency.id)" class="text-indigo-600 hover:underline dark:text-indigo-400">تعديل</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
