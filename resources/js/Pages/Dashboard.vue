<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CurrencyBalanceCard from '@/Components/CurrencyBalanceCard.vue';
import NavIcon from '@/Components/NavIcon.vue';
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
            <h2 class="text-lg font-bold leading-tight text-slate-800">الرئيسية</h2>
        </template>

        <div class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <div v-if="!shift" class="rounded-xl border border-amber-200 bg-amber-50 p-8 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                        <NavIcon name="clock" class="h-6 w-6 text-amber-600" />
                    </div>
                    <p class="text-amber-800">لا توجد وردية مفتوحة حالياً. يجب فتح وردية جديدة لبدء تسجيل العمليات.</p>
                    <Link
                        :href="route('shifts.create')"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700"
                    >
                        <NavIcon name="plus-circle" class="h-4 w-4" />
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

                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('transactions.create')"
                            class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                        >
                            <NavIcon name="plus-circle" class="h-4 w-4" />
                            عملية بيع/شراء جديدة
                        </Link>
                        <Link
                            :href="route('shifts.show', shift.id)"
                            class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            <NavIcon name="clock" class="h-4 w-4" />
                            تفاصيل الوردية
                        </Link>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h3 class="font-bold text-slate-800">آخر العمليات</h3>
                            <Link :href="route('transactions.index')" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                                عرض الكل
                            </Link>
                        </div>

                        <div v-if="recentTransactions.length === 0" class="px-5 py-10 text-center text-slate-400">
                            لا توجد عمليات بعد في هذه الوردية.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3 text-start font-semibold">الوقت</th>
                                        <th class="px-5 py-3 text-start font-semibold">النوع</th>
                                        <th class="px-5 py-3 text-start font-semibold">العملة</th>
                                        <th class="px-5 py-3 text-start font-semibold">المبلغ</th>
                                        <th class="px-5 py-3 text-start font-semibold">السعر</th>
                                        <th class="px-5 py-3 text-start font-semibold">الإجمالي</th>
                                        <th class="px-5 py-3 text-start font-semibold">الموظف</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="t in recentTransactions" :key="t.id" class="transition hover:bg-slate-50">
                                        <td class="px-5 py-3 text-slate-500">{{ new Date(t.created_at).toLocaleTimeString('ar-SY') }}</td>
                                        <td class="px-5 py-3">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="t.type === 'buy' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                            >
                                                {{ t.type === 'buy' ? 'شراء' : 'بيع' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 font-medium text-slate-700">{{ t.currency.code }}</td>
                                        <td class="px-5 py-3 text-slate-700">{{ formatNumber(t.amount) }}</td>
                                        <td class="px-5 py-3 text-slate-500">{{ formatNumber(t.rate) }}</td>
                                        <td class="px-5 py-3 font-semibold text-slate-800">{{ formatNumber(t.total) }}</td>
                                        <td class="px-5 py-3 text-slate-500">{{ t.user.name }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
