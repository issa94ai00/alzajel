<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionForm from '@/Components/TransactionForm.vue';
import EasyTransactionForm from '@/Components/EasyTransactionForm.vue';
import TransactionSummary from '@/Components/TransactionSummary.vue';
import KeyboardShortcutsModal from '@/Components/KeyboardShortcutsModal.vue';
import DepositPromptModal from '@/Components/DepositPromptModal.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    shift: { type: Object, required: true },
    currencies: { type: Array, required: true },
    baseCurrency: { type: Object, default: null },
    balances: { type: Object, default: () => ({}) },
    // آخر عمليات الوردية، الأحدث أولاً.
    recentTransactions: { type: Array, default: () => [] },
    transactionsCount: { type: Number, default: 0 },
    canDeposit: { type: Boolean, default: true },
});

const page = usePage();
const { number, date, settings } = useFormat();

const formRef = ref(null);
const summary = ref(null);
const showShortcutsModal = ref(false);
const isEasyMode = ref(true);

const showDepositModal = ref(false);
const depositModalData = ref({
    currencyId: null,
    currencyCode: '',
    available: 0,
    required: 0,
    deficit: 0,
});

function handleRequestDeposit(data) {
    if (data) {
        depositModalData.value = { ...data };
    }
    showDepositModal.value = true;
}

function handleSummaryRequestDeposit() {
    if (!summary.value) return;
    const f = summary.value.funding;
    const def = Math.max(0, (f?.required ?? 0) - (f?.available ?? 0));
    depositModalData.value = {
        currencyId: f?.currency_id,
        currencyCode: f?.code ?? '',
        available: f?.available ?? 0,
        required: f?.required ?? 0,
        deficit: def,
    };
    showDepositModal.value = true;
}

function setMode(mode) {
    isEasyMode.value = mode === 'easy';
    if (typeof window !== 'undefined') {
        localStorage.setItem('transaction_mode', mode);
    }
}

function toggleMode() {
    setMode(isEasyMode.value ? 'detailed' : 'easy');
}

const receiptsEnabled = computed(() => Boolean(page.props.settings?.receipts_enabled));
const baseCode = computed(() => props.baseCurrency?.code ?? '');

const baseBalance = computed(() => {
    if (!props.baseCurrency?.id) return 0;
    const val = Number(props.balances?.[props.baseCurrency.id]);
    return Number.isFinite(val) ? val : 0;
});

/*
 * رصد العمليات المحفوظة حديثاً لإبرازها
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

onUnmounted(() => {
    clearTimeout(savedTimer);
    window.removeEventListener('keydown', handleGlobalKeydown);
});

function describe(t) {
    const verb = t.type === 'buy' ? 'شراء' : 'بيع';
    const tag = t.currency_tag && t.currency_tag !== t.currency?.code ? ` (${t.currency_tag})` : '';

    return `${verb} ${number(t.amount)} ${t.currency?.code ?? ''}${tag}`;
}

function time(value) {
    if (!value) return '';

    return new Date(value).toLocaleTimeString(settings().locale ?? 'ar-SY', { hour: '2-digit', minute: '2-digit' });
}

function handleClone(transaction) {
    formRef.value?.populateFrom(transaction);
}

function handleGlobalKeydown(e) {
    // فتح نافذة الاختصارات عند الضغط على ? خارج حقول الإدخال
    if (e.key === '?' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
        e.preventDefault();
        showShortcutsModal.value = true;
        return;
    }

    // Alt + E للتبديل السريع بين الوضع السهل والوضع المفصل
    if (e.altKey && (e.key === 'e' || e.key === 'E' || e.code === 'KeyE')) {
        e.preventDefault();
        toggleMode();
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('transaction_mode');
        if (saved !== null) {
            isEasyMode.value = saved === 'easy';
        }
    }
    window.addEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <Head title="عملية صرافة جديدة" />

    <AuthenticatedLayout :show-shift-banner="false">
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <!-- عنوان الصفحة وبيانات الوردية السريعة -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-sm shadow-indigo-600/30">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base sm:text-lg font-black leading-tight text-slate-900 dark:text-white">
                                تسجيل عملية بيع / شراء
                            </h2>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100/80 px-2.5 py-0.5 text-[10px] font-black text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                وردية مفتوحة
                            </span>
                        </div>

                        <!-- شرائح معلومات الوردية المدمجة -->
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <span>📅</span>
                                <span>{{ date(shift.business_date) }}</span>
                            </span>

                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                <span>⚡</span>
                                <span>{{ transactionsCount }} عملية مسجّلة</span>
                            </span>

                            <span v-if="baseCode" class="inline-flex items-center gap-1 rounded-md border border-indigo-100 bg-indigo-50/90 px-2 py-0.5 text-[11px] font-bold text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-950/50 dark:text-indigo-300">
                                <span class="text-indigo-500 dark:text-indigo-400">💵 رصيد الصندوق:</span>
                                <span class="font-black tabular-nums">{{ number(baseBalance) }} {{ baseCode }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- أدوات التحكم السريعة والتبديل -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- مفتاح تبديل الوضع (الوضع السهل / الوضع المفصل) -->
                    <div class="inline-flex rounded-xl bg-slate-100/90 p-1 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 shadow-xs">
                        <button
                            type="button"
                            @click="setMode('easy')"
                            class="relative inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all duration-200"
                            :class="isEasyMode
                                ? 'bg-white text-indigo-700 shadow-sm font-black dark:bg-slate-700 dark:text-indigo-300'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            title="الوضع السهل: إدخال سريع ومبسط بدون تمرير (Alt+E)"
                        >
                            <span class="text-amber-500 font-black">⚡</span>
                            <span>الوضع السهل</span>
                            <span
                                v-if="isEasyMode"
                                class="hidden sm:inline-block rounded bg-indigo-50 px-1 py-0.2 text-[9px] font-mono text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300"
                            >
                                سريع
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="setMode('detailed')"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all duration-200"
                            :class="!isEasyMode
                                ? 'bg-white text-indigo-700 shadow-sm font-black dark:bg-slate-700 dark:text-indigo-300'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            title="الوضع المفصل: النموذج الشامل مع كافة الخيارات المتقدمة (Alt+E)"
                        >
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <span>الوضع المفصل</span>
                        </button>
                    </div>

                    <!-- زر دليل اختصارات لوحة المفاتيح -->
                    <button
                        type="button"
                        @click="showShortcutsModal = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/90 bg-white px-2.5 sm:px-3 py-1.5 text-xs font-bold text-slate-700 shadow-xs transition hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-500 dark:hover:bg-slate-700"
                        title="اختصارات لوحة المفاتيح (?)"
                    >
                        <span class="text-xs">⌨️</span>
                        <span class="hidden sm:inline">الاختصارات</span>
                        <kbd class="hidden md:inline rounded bg-slate-100 px-1 py-0.2 text-[10px] font-mono text-slate-500 dark:bg-slate-700 dark:text-slate-400">?</kbd>
                    </button>

                    <!-- رابط سجل العمليات -->
                    <Link
                        :href="route('transactions.index')"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/90 bg-white px-2.5 sm:px-3 py-1.5 text-xs font-bold text-slate-700 shadow-xs transition hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-500 dark:hover:bg-slate-700"
                        title="الانتقال إلى سجل العمليات"
                    >
                        <svg class="h-3.5 w-3.5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>سجل العمليات</span>
                    </Link>
                </div>
            </div>
        </template>

        <!-- تنبيه عابر بارز بعد كل حفظ -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-3 opacity-0"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="-translate-y-3 opacity-0"
        >
            <div
                v-if="justSaved"
                class="pointer-events-none fixed inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-50 flex justify-center px-4"
                role="status"
                aria-live="polite"
            >
                <div class="pointer-events-auto flex w-full max-w-lg items-center gap-3 rounded-2xl border border-emerald-300 bg-white p-3.5 shadow-xl ring-1 ring-emerald-500/20 dark:border-emerald-700 dark:bg-slate-800">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-gray-900 dark:text-white">تم حفظ العملية بنجاح</p>
                        <p class="truncate text-xs text-gray-600 dark:text-slate-300">
                            {{ describe(justSaved) }}
                            <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                            <span class="font-bold tabular-nums">{{ number(justSaved.total) }} {{ baseCode }}</span>
                        </p>
                    </div>

                    <a
                        v-if="receiptsEnabled"
                        :href="route('transactions.receipt', justSaved.id)"
                        target="_blank"
                        class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-950/50 dark:text-indigo-300 dark:hover:bg-indigo-900/60"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        طباعة الإيصال
                    </a>

                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        aria-label="إغلاق"
                        @click="justSaved = null"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <!-- المحتوى الرئيسي: الوضع السهل (بدون أي تمرير) -->
        <div v-if="isEasyMode" class="py-3 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
                <!-- نموذج الوضع السهل المدمج -->
                <div class="lg:col-span-8">
                    <EasyTransactionForm
                        ref="formRef"
                        :currencies="currencies"
                        :base-currency="baseCurrency"
                        :balances="balances"
                        @request-deposit="handleRequestDeposit"
                    />
                </div>

                <!-- الشريط الجانبي لآخر العمليات (محدد الارتفاع لتفادي أي تمرير للصفحة) -->
                <aside class="lg:col-span-4">
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-center justify-between border-b border-gray-100 px-3.5 py-2.5 dark:border-slate-700">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">آخر عمليات الوردية</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ recentTransactions.length }}
                                </span>
                            </div>

                            <Link
                                v-if="recentTransactions.length"
                                :href="route('transactions.index')"
                                class="text-[11px] font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                سجل العمليات
                            </Link>
                        </div>

                        <ul v-if="recentTransactions.length" class="divide-y divide-gray-100 max-h-[350px] overflow-y-auto dark:divide-slate-700">
                            <li
                                v-for="t in recentTransactions"
                                :key="t.id"
                                class="flex items-center justify-between gap-2 p-2.5 transition-colors duration-300 hover:bg-slate-50 dark:hover:bg-slate-700/40"
                                :class="t.id === justSaved?.id ? 'bg-emerald-50/80 dark:bg-emerald-950/30' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="inline-flex items-center rounded px-1.5 py-0.2 text-[10px] font-black"
                                            :class="t.type === 'buy'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300'
                                                : 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300'"
                                        >
                                            {{ t.type === 'buy' ? '↙ شراء' : '↗ بيع' }}
                                        </span>
                                        <span class="text-xs font-black tabular-nums text-gray-900 dark:text-white">
                                            {{ number(t.amount) }} {{ t.currency?.code }}
                                        </span>
                                        <span v-if="t.currency_tag" class="text-[9px] text-slate-400">
                                            ({{ t.currency_tag }})
                                        </span>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-slate-400">
                                        <span class="font-bold tabular-nums text-gray-700 dark:text-slate-300">
                                            {{ number(t.total) }} {{ baseCode }}
                                        </span>
                                        <span>·</span>
                                        <span class="tabular-nums text-[10px]">{{ time(t.created_at) }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        type="button"
                                        @click="handleClone(t)"
                                        class="rounded-lg border border-gray-200 bg-white p-1 text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-400"
                                        title="نسخ هذه العملية إلى النموذج"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>

                                    <a
                                        v-if="receiptsEnabled"
                                        :href="route('transactions.receipt', t.id)"
                                        target="_blank"
                                        class="rounded-lg border border-gray-200 bg-white p-1 text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-400"
                                        title="طباعة إيصال العملية"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </li>
                        </ul>

                        <div v-else class="p-4 text-center text-xs text-gray-400 dark:text-slate-500">
                            لم يتم تسجيل أي عمليات صرافة في هذه الوردية بعد.
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- المحتوى الرئيسي: الوضع المفصل الكامل -->
        <div v-else class="py-4 sm:py-6 lg:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-5 lg:grid-cols-5 lg:items-start">
                    <!-- نموذج تسجيل العملية -->
                    <div class="lg:col-span-3">
                        <TransactionForm
                            ref="formRef"
                            :currencies="currencies"
                            :base-currency="baseCurrency"
                            :balances="balances"
                            @update:summary="summary = $event"
                            @request-deposit="handleRequestDeposit"
                        />
                    </div>

                    <!-- العمود الجانبي: الملخص الحي وسجل العمليات الأخيرة -->
                    <aside class="space-y-4 lg:sticky lg:top-6 lg:col-span-2">
                        <!-- ملخص العملية الحية للشاشات الكبيرة -->
                        <TransactionSummary
                            v-if="summary"
                            :summary="summary"
                            @request-deposit="handleSummaryRequestDeposit"
                            class="hidden lg:block"
                        />

                        <!-- آخر عمليات الوردية -->
                        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-slate-700 sm:px-5">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">آخر عمليات الوردية</h3>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ recentTransactions.length }}
                                    </span>
                                </div>

                                <Link
                                    v-if="recentTransactions.length"
                                    :href="route('transactions.index')"
                                    class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    عرض السجل الكامل
                                </Link>
                            </div>

                            <ul v-if="recentTransactions.length" class="divide-y divide-gray-100 dark:divide-slate-700">
                                <li
                                    v-for="t in recentTransactions"
                                    :key="t.id"
                                    class="flex items-center justify-between gap-3 p-3.5 transition-colors duration-500 hover:bg-slate-50/80 sm:px-5 dark:hover:bg-slate-700/40"
                                    :class="t.id === justSaved?.id ? 'bg-emerald-50/80 dark:bg-emerald-950/30' : ''"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <!-- شارة البيع أو الشراء مع أيقونة -->
                                            <span
                                                class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-black"
                                                :class="t.type === 'buy'
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300'
                                                    : 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300'"
                                            >
                                                <span>{{ t.type === 'buy' ? '↙ شراء' : '↗ بيع' }}</span>
                                            </span>

                                            <span class="text-sm font-black tabular-nums text-gray-900 dark:text-white">
                                                {{ number(t.amount) }} {{ t.currency?.code }}
                                            </span>

                                            <span
                                                v-if="t.currency_tag"
                                                class="rounded bg-slate-100 px-1 py-0.5 text-[9px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                            >
                                                {{ t.currency_tag }}
                                            </span>

                                            <span
                                                v-if="t.id === justSaved?.id"
                                                class="rounded-full bg-emerald-600 px-1.5 py-0.2 text-[9px] font-bold text-white"
                                            >
                                                جديد
                                            </span>
                                        </div>

                                        <div class="mt-1 flex flex-wrap items-center text-xs text-gray-500 dark:text-slate-400">
                                            <span class="font-bold tabular-nums text-gray-700 dark:text-slate-300">
                                                {{ number(t.total) }} {{ baseCode }}
                                            </span>
                                            <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                                            <span class="tabular-nums text-[11px]">{{ time(t.created_at) }}</span>
                                            <template v-if="t.customer_name">
                                                <span class="mx-1 text-gray-300 dark:text-slate-600">·</span>
                                                <span class="truncate max-w-[100px]">{{ t.customer_name }}</span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- أزرار الإجراء السريع للعملية -->
                                    <div class="flex items-center gap-1 shrink-0">
                                        <!-- زر تكرار العملية في النموذج -->
                                        <button
                                            type="button"
                                            @click="handleClone(t)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-400"
                                            title="نسخ بيانات هذه العملية إلى النموذج لتسجيل عملية مشابهة"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            <span class="hidden sm:inline">نسخ</span>
                                        </button>

                                        <!-- زر طباعة الإيصال -->
                                        <a
                                            v-if="receiptsEnabled"
                                            :href="route('transactions.receipt', t.id)"
                                            target="_blank"
                                            class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-1 text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-400"
                                            title="طباعة إيصال العملية"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </a>
                                    </div>
                                </li>
                            </ul>

                            <div v-else class="p-6 text-center text-xs text-gray-400 dark:text-slate-500">
                                <p class="font-medium">لم يتم تسجيل أي عمليات صرافة في هذه الوردية بعد.</p>
                                <p class="mt-1 text-[11px] text-gray-400">ستظهر هنا أحدث العمليات لتسهيل مراجعتها وطباعة إيصالاتها.</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <!-- نافذة اختصارات لوحة المفاتيح -->
        <KeyboardShortcutsModal
            :show="showShortcutsModal"
            @close="showShortcutsModal = false"
        />

        <!-- نافذة السؤال عن الرغبة بالإيداع وتأكيده عند عدم توفر رصيد كافٍ -->
        <DepositPromptModal
            :show="showDepositModal"
            :shift-id="shift.id"
            :currency-id="depositModalData.currencyId"
            :currency-code="depositModalData.currencyCode"
            :available-balance="depositModalData.available"
            :required-amount="depositModalData.required"
            :deficit="depositModalData.deficit"
            :can-deposit="canDeposit"
            @close="showDepositModal = false"
            @deposited="showDepositModal = false"
        />
    </AuthenticatedLayout>
</template>
