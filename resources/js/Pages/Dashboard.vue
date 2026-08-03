<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CurrencyBalanceCard from '@/Components/CurrencyBalanceCard.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    shift: { type: Object, default: null },
    balances: { type: Array, default: () => [] },
    recentTransactions: { type: Array, default: () => [] },
});

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}
</script>

<template>
    <Head title="الرئيسية" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">الرئيسية</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="!shift" class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-center">
                    <p class="text-amber-800">لا توجد وردية مفتوحة حالياً. يجب فتح وردية جديدة لبدء تسجيل العمليات.</p>
                    <Link :href="route('shifts.create')" class="mt-3 inline-block rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        فتح وردية جديدة
                    </Link>
                </div>

                <template v-else>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <CurrencyBalanceCard
                            v-for="balance in balances"
                            :key="balance.currency.id"
                            :code="balance.currency.code"
                            :name="balance.currency.name"
                            :symbol="balance.currency.symbol"
                            :balance="balance.current_balance"
                        />
                    </div>

                    <div class="flex gap-3">
                        <Link :href="route('transactions.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            + عملية بيع/شراء جديدة
                        </Link>
                        <Link :href="route('shifts.show', shift.id)" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            تفاصيل الوردية
                        </Link>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-4 py-3">
                            <h3 class="font-semibold text-gray-800">آخر العمليات</h3>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 text-start">الوقت</th>
                                    <th class="px-4 py-2 text-start">النوع</th>
                                    <th class="px-4 py-2 text-start">العملة</th>
                                    <th class="px-4 py-2 text-start">المبلغ</th>
                                    <th class="px-4 py-2 text-start">السعر</th>
                                    <th class="px-4 py-2 text-start">الإجمالي</th>
                                    <th class="px-4 py-2 text-start">الموظف</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="recentTransactions.length === 0">
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">لا توجد عمليات بعد في هذه الوردية.</td>
                                </tr>
                                <tr v-for="t in recentTransactions" :key="t.id">
                                    <td class="px-4 py-2">{{ new Date(t.created_at).toLocaleTimeString('ar-SY') }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="t.type === 'buy' ? 'text-emerald-700' : 'text-rose-700'" class="font-semibold">
                                            {{ t.type === 'buy' ? 'شراء' : 'بيع' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">{{ t.currency.code }}</td>
                                    <td class="px-4 py-2">{{ formatNumber(t.amount) }}</td>
                                    <td class="px-4 py-2">{{ formatNumber(t.rate) }}</td>
                                    <td class="px-4 py-2">{{ formatNumber(t.total) }}</td>
                                    <td class="px-4 py-2">{{ t.user.name }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
