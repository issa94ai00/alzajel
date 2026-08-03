<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    shifts: { type: Array, required: true },
    range: { type: Object, default: () => ({}) },
});

const form = reactive({
    date_from: props.range.date_from ?? '',
    date_to: props.range.date_to ?? '',
});

function applyRange() {
    router.get(route('reports.profit-loss'), form, { preserveState: true, replace: true });
}

const totalProfit = computed(() =>
    props.shifts.reduce((sum, s) => sum + Number(s.profit), 0)
);

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}
</script>

<template>
    <Head title="تقرير الأرباح والخسائر" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">تقرير الأرباح والخسائر</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div class="flex items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-xs text-gray-500">من تاريخ</label>
                        <input v-model="form.date_from" type="date" class="mt-1 rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">إلى تاريخ</label>
                        <input v-model="form.date_to" type="date" class="mt-1 rounded-md border-gray-300 text-sm" />
                    </div>
                    <button @click="applyRange" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        عرض
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <span class="text-sm text-gray-500">إجمالي الربح للفترة المحددة</span>
                    <div class="text-2xl font-bold" :class="totalProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                        {{ formatNumber(totalProfit) }}
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">الوردية</th>
                                <th class="px-4 py-2 text-start">الحالة</th>
                                <th class="px-4 py-2 text-start">عدد العمليات</th>
                                <th class="px-4 py-2 text-start">صافي الربح</th>
                                <th class="px-4 py-2 text-start"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="shifts.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400">لا توجد بيانات في هذا النطاق.</td>
                            </tr>
                            <tr v-for="row in shifts" :key="row.shift.id">
                                <td class="px-4 py-2">{{ row.shift.business_date }}</td>
                                <td class="px-4 py-2">{{ row.shift.status === 'open' ? 'مفتوحة' : 'مغلقة' }}</td>
                                <td class="px-4 py-2">{{ row.transaction_count }}</td>
                                <td class="px-4 py-2 font-semibold" :class="Number(row.profit) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ formatNumber(row.profit) }}
                                </td>
                                <td class="px-4 py-2">
                                    <Link :href="route('shifts.show', row.shift.id)" class="text-indigo-600 hover:underline">تفاصيل</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
