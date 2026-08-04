<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    selectedCurrency: { type: Object, default: null },
    transactions: { type: Array, default: () => [] },
    range: { type: Object, default: () => ({}) },
});

const form = reactive({
    currency_id: props.selectedCurrency?.id ?? '',
    date_from: props.range.date_from ?? '',
    date_to: props.range.date_to ?? '',
});

function applyFilters() {
    router.get(route('reports.currency-movement'), form, { preserveState: true, replace: true });
}

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}
</script>

<template>
    <Head title="تقرير حركة العملة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">تقرير حركة العملة</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-slate-400">العملة</label>
                        <select v-model="form.currency_id" class="mt-1 rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                            <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-slate-400">من تاريخ</label>
                        <input v-model="form.date_from" type="date" class="mt-1 rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-slate-400">إلى تاريخ</label>
                        <input v-model="form.date_to" type="date" class="mt-1 rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                    </div>
                    <button @click="applyFilters" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        عرض
                    </button>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                        <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2 text-start">التاريخ</th>
                                <th class="px-4 py-2 text-start">النوع</th>
                                <th class="px-4 py-2 text-start">المبلغ</th>
                                <th class="px-4 py-2 text-start">السعر</th>
                                <th class="px-4 py-2 text-start">الإجمالي</th>
                                <th class="px-4 py-2 text-start">الموظف</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                            <tr v-if="transactions.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400 dark:text-slate-500">لا توجد حركات لهذه العملة في هذا النطاق.</td>
                            </tr>
                            <tr v-for="t in transactions" :key="t.id">
                                <td class="px-4 py-2">{{ new Date(t.created_at).toLocaleString('ar-SY') }}</td>
                                <td class="px-4 py-2">
                                    <span :class="t.type === 'buy' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" class="font-semibold">
                                        {{ t.type === 'buy' ? 'شراء' : 'بيع' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ formatNumber(t.amount) }}</td>
                                <td class="px-4 py-2">{{ formatNumber(t.rate) }}</td>
                                <td class="px-4 py-2">{{ formatNumber(t.total) }}</td>
                                <td class="px-4 py-2">{{ t.user.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
