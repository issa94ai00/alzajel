<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionForm from '@/Components/TransactionForm.vue';
import TransactionSummary from '@/Components/TransactionSummary.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

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

/*
 * رسالة النجاح العامة لا تتغيّر بين عمليتين متتاليتين، فلا يُرى فرق بعد الحفظ
 * الثاني. نرصد ظهور عملية جديدة في رأس القائمة ونُبرزها لحظات مع تنبيه
 * عابر يلخّصها — فيعرف الصرّاف أن ما كتبه قد سُجّل، وما الذي سُجّل.
 */
const SAVED_HIGHLIGHT_MS = 6000;

const latestId = ref(props.recentTransactions[0]?.id ?? null);
const justSaved = ref(null);
let savedTimer = null;

watch(
    () => props.recentTransactions[0]?.id,
    (id) => {
        if (!id || id === latestId.value) return;

        latestId.value = id;
        justSaved.value = props.recentTransactions[0];

        clearTimeout(savedTimer);
        savedTimer = setTimeout(() => (justSaved.value = null), SAVED_HIGHLIGHT_MS);
    },
);

onUnmounted(() => clearTimeout(savedTimer));

function describe(t) {
    const verb = t.type === 'buy' ? 'شراء' : 'بيع';
    const tag = t.currency_tag && t.currency_tag !== t.currency?.code ? ` (${t.currency_tag})` : '';

    return `${verb} ${number(t.amount)} ${t.currency?.code ?? ''}${tag}`;
}

function time(value) {
    if (!value) return '';

    return new Date(value).toLocaleTimeString(settings().locale ?? 'ar-SY', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="عملية جديدة" />

    <!-- الرأس يذكر الوردية وعدد عملياتها، فشريط الوردية العام تكرارٌ يأكل ارتفاع شاشة الهاتف. -->
    <AuthenticatedLayout :show-shift-banner="false">
        <template #header>
            <!-- الرأس ارتفاعه ثابت؛ على الهاتف يُطوى الرابط كي لا يلتفّ تحت العنوان ويُقصّ. -->
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-bold leading-tight text-slate-800 dark:text-white">تسجيل عملية بيع/شراء</h2>
                    <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                        وردية {{ date(shift.business_date) }}
                        <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                        {{ transactionsCount }} عملية مسجّلة
                    </p>
                </div>

                <Link
                    :href="route('transactions.index')"
                    class="hidden shrink-0 items-center gap-1.5 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 sm:inline-flex"
                >
                    سجل العمليات
                </Link>
            </div>
        </template>

        <!-- تنبيه عابر بعد كل حفظ: يظهر فوق كل شيء مهما كان موضع التمرير. -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-3 opacity-0"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="-translate-y-3 opacity-0"
        >
            <div
                v-if="justSaved"
                class="pointer-events-none fixed inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-40 flex justify-center px-4"
                role="status"
                aria-live="polite"
            >
                <div class="pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-xl border border-emerald-200 bg-white p-3 shadow-lg dark:border-emerald-800/60 dark:bg-slate-800">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                        <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">تم تسجيل العملية</p>
                        <p class="truncate text-xs text-gray-500 dark:text-slate-400">
                            {{ describe(justSaved) }}
                            <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                            <span class="tabular-nums">{{ number(justSaved.total) }} {{ baseCode }}</span>
                        </p>
                    </div>
                    <a
                        v-if="receiptsEnabled"
                        :href="route('transactions.receipt', justSaved.id)"
                        target="_blank"
                        class="shrink-0 rounded-md border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-600 dark:text-slate-300 dark:hover:border-indigo-500 dark:hover:text-indigo-300"
                    >
                        طباعة
                    </a>
                    <button
                        type="button"
                        class="shrink-0 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700"
                        aria-label="إغلاق"
                        @click="justSaved = null"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <div class="py-4 sm:py-6 lg:py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:gap-6 lg:grid-cols-5 lg:items-start">
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
                            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-slate-700 sm:px-5">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">آخر عمليات الوردية</h3>
                                <Link
                                    v-if="recentTransactions.length"
                                    :href="route('transactions.index')"
                                    class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    عرض الكل
                                </Link>
                            </div>

                            <ul v-if="recentTransactions.length" class="divide-y divide-gray-100 px-4 dark:divide-slate-700 sm:px-5">
                                <li
                                    v-for="t in recentTransactions"
                                    :key="t.id"
                                    class="-mx-4 flex items-center justify-between gap-3 px-4 py-2.5 transition-colors duration-700 sm:-mx-5 sm:px-5"
                                    :class="t.id === justSaved?.id ? 'bg-emerald-50 dark:bg-emerald-900/20' : ''"
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
                                            <span
                                                v-if="t.id === justSaved?.id"
                                                class="rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white"
                                            >
                                                الآن
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

                            <p v-else class="px-4 py-6 text-center text-xs text-gray-400 dark:text-slate-500 sm:px-5">
                                لم تُسجَّل عمليات في هذه الوردية بعد.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
