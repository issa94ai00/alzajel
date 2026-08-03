<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    shift: { type: Object, required: true },
    balances: { type: Array, required: true },
    profitLoss: { type: Object, required: true },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const form = useForm({});

function closeShift() {
    if (confirm('هل أنت متأكد من إغلاق هذه الوردية؟ لا يمكن التراجع عن هذا الإجراء.')) {
        form.post(route('shifts.close', props.shift.id));
    }
}

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}
</script>

<template>
    <Head title="تفاصيل الوردية" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    وردية {{ shift.business_date }}
                    <span :class="shift.status === 'open' ? 'text-emerald-700' : 'text-gray-500'" class="text-sm font-semibold">
                        ({{ shift.status === 'open' ? 'مفتوحة' : 'مغلقة' }})
                    </span>
                </h2>
                <button
                    v-if="shift.status === 'open' && isAdmin"
                    @click="closeShift"
                    class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                >
                    إغلاق الوردية
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-800">الأرصدة</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">العملة</th>
                                <th class="px-4 py-2 text-start">الرصيد الافتتاحي</th>
                                <th class="px-4 py-2 text-start">الرصيد الحالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="b in balances" :key="b.currency.id">
                                <td class="px-4 py-2">{{ b.currency.name }} ({{ b.currency.code }})</td>
                                <td class="px-4 py-2">{{ formatNumber(b.opening_balance) }}</td>
                                <td class="px-4 py-2 font-semibold">{{ formatNumber(b.current_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="isAdmin" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-800">الأرباح والخسائر</h3>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-lg">
                            صافي الربح:
                            <span class="font-bold" :class="Number(profitLoss.total_profit) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                {{ formatNumber(profitLoss.total_profit) }}
                            </span>
                            <span class="text-sm text-gray-500">({{ profitLoss.transaction_count }} عملية)</span>
                        </p>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">العملة</th>
                                <th class="px-4 py-2 text-start">عدد العمليات</th>
                                <th class="px-4 py-2 text-start">الربح</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="profitLoss.by_currency.length === 0">
                                <td colspan="3" class="px-4 py-6 text-center text-gray-400">لا توجد عمليات بعد.</td>
                            </tr>
                            <tr v-for="row in profitLoss.by_currency" :key="row.currency.id">
                                <td class="px-4 py-2">{{ row.currency.name }} ({{ row.currency.code }})</td>
                                <td class="px-4 py-2">{{ row.transaction_count }}</td>
                                <td class="px-4 py-2 font-semibold" :class="Number(row.profit) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ formatNumber(row.profit) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
