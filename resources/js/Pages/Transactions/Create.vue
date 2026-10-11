<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionForm from '@/Components/TransactionForm.vue';
import TransactionSummary from '@/Components/TransactionSummary.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shift: { type: Object, required: true },
    currencies: { type: Array, required: true },
    baseCurrency: { type: Object, default: null },
    balances: { type: Object, default: () => ({}) },
    // آخر عمليات الوردية، الأحدث أولاً.
    recentTransactions: { type: Array, default: () => [] },
    transactionsCount: { type: Number, default: 0 },
});

const page = usePage();

const { number, date, settings } = useFormat();

// يُغذّيه النموذج بكل ضغطة، ويُعرض في العمود الجانبي على الشاشات الكبيرة.
const summary = ref(null);

const receiptsEnabled = computed(() => Boolean(page.props.settings?.receipts_enabled));

const baseCode = computed(() => props.baseCurrency?.code ?? '');

function time(value) {
    if (!value) return '';

    return new Date(value).toLocaleTimeString(settings().locale ?? 'ar-SY', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="عملية جديدة" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">تسجيل عملية بيع/شراء</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                        وردية {{ date(shift.business_date) }}
                        <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                        {{ transactionsCount }} عملية مسجّلة
                    </p>
                </div>

                <Link
                    :href="route('transactions.index')"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    سجل العمليات
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-5 lg:items-start">
                    <div class="lg:col-span-3">
                        <TransactionForm
                            :currencies="currencies"
                            :base-currency="baseCurrency"
                            :balances="balances"
                            @update:summary="summary = $event"
                        />
                    </div>

                    <!-- العمود الجانبي: الملخص الحيّ وآخر العمليات — ثابتان أثناء التمرير. -->
                    <aside class="space-y-4 lg:sticky lg:top-6 lg:col-span-2">
                        <TransactionSummary v-if="summary" :summary="summary" class="hidden lg:block" />

                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-3 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">آخر عمليات الوردية</h3>
                                <Link
                                    v-if="recentTransactions.length"
                                    :href="route('transactions.index')"
                                    class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    عرض الكل
                                </Link>
                            </div>

                            <ul v-if="recentTransactions.length" class="divide-y divide-gray-100 px-5 dark:divide-slate-700">
                                <li
                                    v-for="t in recentTransactions"
                                    :key="t.id"
                                    class="flex items-center justify-between gap-3 py-2.5"
                                >
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded px-1.5 py-0.5 text-[11px] font-bold"
                                                :class="t.type === 'buy'
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                    : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                                            >
                                                {{ t.type === 'buy' ? 'شراء' : 'بيع' }}
                                            </span>
                                            <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">
                                                {{ number(t.amount) }} {{ t.currency?.code }}
                                            </span>
                                            <span
                                                v-if="t.currency_tag"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                            >
                                                {{ t.currency_tag }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-slate-400">
                                            <span class="tabular-nums">{{ time(t.created_at) }}</span>
                                            <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                                            <span class="tabular-nums">{{ number(t.total) }} {{ baseCode }}</span>
                                            <template v-if="t.customer_name">
                                                <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                                                {{ t.customer_name }}
                                            </template>
                                        </div>
                                    </div>

                                    <a
                                        v-if="receiptsEnabled"
                                        :href="route('transactions.receipt', t.id)"
                                        target="_blank"
                                        class="shrink-0 rounded-md border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-600 dark:text-slate-300 dark:hover:border-indigo-500 dark:hover:text-indigo-300"
                                        title="طباعة الإيصال"
                                    >
                                        طباعة
                                    </a>
                                </li>
                            </ul>

                            <p v-else class="px-5 py-6 text-center text-xs text-gray-400 dark:text-slate-500">
                                لم تُسجَّل عمليات في هذه الوردية بعد.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
