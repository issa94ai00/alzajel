<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import NavIcon from '@/Components/NavIcon.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import SyncAllRatesDialog from './SyncAllRatesDialog.vue';
import FillSuggestionsDialog from './FillSuggestionsDialog.vue';
import RateMarginDialog from './RateMarginDialog.vue';
import LargeDeviationDialog from './LargeDeviationDialog.vue';
import MarketInfoDialog from './MarketInfoDialog.vue';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    spToday: { type: Object, default: () => ({ success: false, rates: {} }) },
});

const { settings, number } = useFormat();
const { can } = usePermissions();
const canUpdateRates = computed(() => can('rates.update'));

const searchQuery = ref('');
const isRefreshing = ref(false);
const recentlyApplied = ref({});

// Dialog States
const showSyncAllDialog = ref(false);
const isSyncingAll = ref(false);

const showFillSuggestionsDialog = ref(false);

const showMarginDialog = ref(false);
const marginTargetCurrency = ref(null);

const showDeviationDialog = ref(false);
const deviationData = ref(null);

const showMarketInfoDialog = ref(false);

function editable(value) {
    if (value === null || value === undefined || value === '') return '';

    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '';

    const decimals = settings().decimals ?? 2;
    if (parsed !== 0 && Math.abs(parsed) < Math.pow(10, 1 - decimals)) {
        return String(parsed);
    }

    return parsed.toFixed(decimals);
}

const forms = Object.fromEntries(
    props.currencies.map((c) => [
        c.id,
        useForm({
            buy_rate: editable(c.exchange_rate?.buy_rate),
            sell_rate: editable(c.exchange_rate?.sell_rate),
        }),
    ])
);

const filteredCurrencies = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.currencies;
    return props.currencies.filter(
        (c) =>
            c.code.toLowerCase().includes(q) ||
            c.name.toLowerCase().includes(q),
    );
});

// استخراج اقتراح الليرة اليوم لعملة معينة
function getSpSuggestion(currencyCode) {
    if (!props.spToday?.rates) return null;
    return props.spToday.rates[currencyCode.toUpperCase()] ?? null;
}

// تطبيق السعر المقترح من الليرة اليوم في حقول العملة
function applySuggestion(currency, spreadPercent = 0) {
    if (!canUpdateRates.value) return;

    const sugg = getSpSuggestion(currency.code);
    if (!sugg) return;

    const buy = spreadPercent > 0 ? sugg.buy_new * (1 - spreadPercent / 100) : sugg.buy_new;
    const sell = spreadPercent > 0 ? sugg.sell_new * (1 + spreadPercent / 100) : sugg.sell_new;

    const decimals = settings().decimals ?? 2;
    forms[currency.id].buy_rate = buy.toFixed(decimals);
    forms[currency.id].sell_rate = sell.toFixed(decimals);

    recentlyApplied.value[currency.id] = true;
    setTimeout(() => {
        recentlyApplied.value[currency.id] = false;
    }, 2500);
}

// حوار اعتماد جميع الأسعار فوراً
function openSyncAllDialog() {
    if (!canUpdateRates.value) return;
    showSyncAllDialog.value = true;
}

function handleSyncAllConfirm({ rates, spread }) {
    isSyncingAll.value = true;
    router.post(
        route('rates.sync-all'),
        { rates, spread_percent: spread },
        {
            preserveScroll: true,
            onFinish: () => {
                isSyncingAll.value = false;
                showSyncAllDialog.value = false;
            },
        }
    );
}

// حوار تعبئة المقترحات للحقول
function openFillSuggestionsDialog() {
    if (!canUpdateRates.value) return;
    showFillSuggestionsDialog.value = true;
}

function handleFillSuggestionsApply({ spread }) {
    showFillSuggestionsDialog.value = false;
    props.currencies.forEach((c) => {
        applySuggestion(c, spread);
    });
}

// حوار هامش العملة المفردة
function openMarginDialog(currency) {
    if (!canUpdateRates.value) return;
    marginTargetCurrency.value = currency;
    showMarginDialog.value = true;
}

function handleMarginApply({ currency, buy, sell }) {
    forms[currency.id].buy_rate = buy;
    forms[currency.id].sell_rate = sell;
    recentlyApplied.value[currency.id] = true;
    setTimeout(() => {
        recentlyApplied.value[currency.id] = false;
    }, 2500);
    showMarginDialog.value = false;
}

// تحديث بيانات الليرة اليوم من المصدر
function refreshSpToday() {
    isRefreshing.value = true;
    router.post(route('rates.sptoday.refresh'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

function submit(currencyId, force = false) {
    if (!canUpdateRates.value) return;

    const currency = props.currencies.find((c) => c.id === currencyId);
    if (!currency) return;

    const buy = Number(forms[currencyId].buy_rate);
    const sell = Number(forms[currencyId].sell_rate);

    if (!force) {
        // فحص الانعكاس (الشراء أكبر من البيع)
        if (buy > 0 && sell > 0 && buy > sell) {
            deviationData.value = {
                currency,
                currencyId,
                newBuy: forms[currencyId].buy_rate,
                newSell: forms[currencyId].sell_rate,
                currentBuy: currency.exchange_rate?.buy_rate,
                currentSell: currency.exchange_rate?.sell_rate,
                marketBuy: getSpSuggestion(currency.code)?.buy_new,
                marketSell: getSpSuggestion(currency.code)?.sell_new,
                deviationPercent: 0,
                isInverted: true,
            };
            showDeviationDialog.value = true;
            return;
        }

        // فحص الفارق الكبير عن السعر الحالي المسجل أو سعر السوق
        const currentBuy = Number(currency.exchange_rate?.buy_rate) || 0;
        const sugg = getSpSuggestion(currency.code);
        const refBuy = currentBuy > 0 ? currentBuy : (sugg ? sugg.buy_new : 0);

        if (refBuy > 0 && buy > 0) {
            const diffRatio = Math.abs(buy - refBuy) / refBuy;
            if (diffRatio >= 0.15) { // فارق 15% أو أكثر
                deviationData.value = {
                    currency,
                    currencyId,
                    newBuy: forms[currencyId].buy_rate,
                    newSell: forms[currencyId].sell_rate,
                    currentBuy: currency.exchange_rate?.buy_rate,
                    currentSell: currency.exchange_rate?.sell_rate,
                    marketBuy: sugg?.buy_new,
                    marketSell: sugg?.sell_new,
                    deviationPercent: (diffRatio * 100).toFixed(0),
                    isInverted: false,
                };
                showDeviationDialog.value = true;
                return;
            }
        }
    }

    forms[currencyId].put(route('rates.update', currencyId), {
        preserveScroll: true,
        onSuccess: () => {
            showDeviationDialog.value = false;
        },
    });
}

function handleDeviationConfirm() {
    if (!deviationData.value) return;
    submit(deviationData.value.currencyId, true);
}

// حساب الفارق بين سعر النظام الحالي وسعر السوق
function getDifference(currency) {
    const sugg = getSpSuggestion(currency.code);
    if (!sugg) return null;

    const currentBuy = Number(forms[currency.id].buy_rate);
    if (!currentBuy) return null;

    const diff = currentBuy - sugg.buy_new;
    const percent = ((diff / sugg.buy_new) * 100).toFixed(1);

    return {
        diff,
        percent,
        isHigher: diff > 0,
        isLower: diff < 0,
    };
}

function formatTime(isoStr) {
    if (!isoStr) return '';
    try {
        const d = new Date(isoStr);
        return d.toLocaleTimeString(settings().locale ?? 'ar-SY', {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
}
</script>

<template>
    <Head title="أسعار الصرف واقتراحات السوق" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                        <NavIcon name="currency" class="h-5 w-5" />
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-black leading-tight text-slate-900 dark:text-white">
                                أسعار الصرف
                            </h2>
                            <span
                                v-if="!canUpdateRates"
                                class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                            >
                                للعرض فقط
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            إدارة نشرة الأسعار ومقارنتها مع مؤشرات السوق الموازي (sp-today)
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        :disabled="isRefreshing"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        title="تحديث فوري من موقع الليرة اليوم"
                        @click="refreshSpToday"
                    >
                        <NavIcon name="refresh" class="h-3.5 w-3.5 text-slate-500 transition-transform" :class="isRefreshing ? 'animate-spin' : ''" />
                        <span>{{ isRefreshing ? 'جاري التحديث...' : 'تحديث من الليرة اليوم' }}</span>
                    </button>

                    <button
                        v-if="canUpdateRates"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs shadow-teal-600/20 transition hover:from-teal-700 hover:to-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-500"
                        title="مراجعة واعتماد جميع أسعار الليرة اليوم لجميع العملات"
                        @click="openSyncAllDialog"
                    >
                        <NavIcon name="zap" class="h-3.5 w-3.5" />
                        <span>تطبيق واعتماد الكل فوراً</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                <NewLiraNotice dismiss-key="rates-index" />

                <!-- لوحة حالة مؤشر السوق الموازي (SP-Today Live Monitor) -->
                <div class="relative overflow-hidden rounded-2xl border border-teal-200/80 bg-gradient-to-br from-teal-50/70 via-white to-slate-50 p-4.5 shadow-xs dark:border-teal-800/40 dark:from-slate-900 dark:via-slate-900 dark:to-teal-950/20">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-sm shadow-teal-600/30">
                                <NavIcon name="trending-up" class="h-5 w-5" />
                            </span>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                        مؤشر الليرة اليوم المرجعي (sp-today.com)
                                    </h3>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold cursor-pointer hover:ring-2 hover:ring-teal-400/50 transition focus:outline-none"
                                        :class="spToday.success
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                        title="انقر لعرض تفاصيل نشرة السوق والعملات المتوفرة"
                                        @click="showMarketInfoDialog = true"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="spToday.success ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                                        <span>{{ spToday.success ? 'سوق دمشق المباشر (تفاصيل)' : 'بيانات غير متصلة' }}</span>
                                    </button>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    <span>يعرض النظام أسعار السوق الموازي كاقتراحات دقيقة محسوبة بالليرة الجديدة (مقسومة على 100 لتناسب نظام الصرف).</span>
                                    <template v-if="spToday.updated_at">
                                        <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                                        <span>آخر تحديث للسوق: {{ formatTime(spToday.updated_at) }}</span>
                                    </template>
                                </p>
                            </div>
                        </div>

                        <!-- أدوات التعبئة السريعة -->
                        <div v-if="canUpdateRates" class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-teal-300 bg-teal-50/80 px-2.5 py-1.5 text-xs font-bold text-teal-800 transition hover:bg-teal-100 dark:border-teal-700 dark:bg-teal-950/50 dark:text-teal-300"
                                title="تعبئة أسعار الليرة اليوم في حقول الصفحة لمراجعتها قبل الحفظ"
                                @click="openFillSuggestionsDialog"
                            >
                                <NavIcon name="zap" class="h-3.5 w-3.5" />
                                <span>تعبئة المقترحات للحقول</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- شريط البحث السريع عن العملات -->
                <div class="flex items-center justify-between gap-3">
                    <div class="relative flex-1 max-w-sm">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="ابحث عن العملة (USD, يورو, ريال)..."
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <span
                            v-if="searchQuery"
                            class="absolute end-3 top-1/2 -translate-y-1/2 cursor-pointer text-xs font-bold text-slate-400 hover:text-slate-600"
                            @click="searchQuery = ''"
                        >
                            ✕
                        </span>
                    </div>

                    <span class="text-xs font-bold text-slate-400">
                        {{ filteredCurrencies.length }} عملات نشطة
                    </span>
                </div>

                <!-- قائمة بطاقات أسعار العملات -->
                <div class="space-y-4">
                    <div
                        v-for="currency in filteredCurrencies"
                        :key="currency.id"
                        class="overflow-hidden rounded-2xl border bg-white shadow-xs transition-all duration-200 hover:shadow-md dark:bg-slate-800/90"
                        :class="recentlyApplied[currency.id]
                            ? 'border-teal-400 ring-2 ring-teal-400/30 dark:border-teal-600'
                            : 'border-slate-200/90 dark:border-slate-700'"
                    >
                        <form @submit.prevent="submit(currency.id)" class="p-4 sm:p-5">
                            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12 lg:items-center">
                                <!-- القسم ١: هوية العملة والسعر المسجل حالياً في النظام (4 أعمدة) -->
                                <div class="lg:col-span-4 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <!-- رمز أو راية العملة -->
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg shadow-xs dark:bg-slate-700">
                                            {{ getSpSuggestion(currency.code)?.flag || currency.symbol || currency.code.slice(0, 3) }}
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5">
                                                <h4 class="text-base font-black text-slate-900 dark:text-white">
                                                    {{ currency.code }}
                                                </h4>
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                    {{ currency.name }}
                                                </span>
                                            </div>

                                            <div class="mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                <span>النظام الحالي:</span>
                                                <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">
                                                    {{ editable(currency.exchange_rate?.buy_rate) || '—' }}
                                                </span>
                                                <span>/</span>
                                                <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">
                                                    {{ editable(currency.exchange_rate?.sell_rate) || '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- القسم ٢: بطاقة اقتراح الليرة اليوم المرجعي (sp-today suggestion box) (4 أعمدة) -->
                                <div class="lg:col-span-4">
                                    <template v-if="getSpSuggestion(currency.code)">
                                        <div class="rounded-xl border border-teal-200/90 bg-teal-50/60 p-3 dark:border-teal-800/50 dark:bg-teal-950/30">
                                            <div class="flex items-center justify-between text-[11px] font-bold text-teal-800 dark:text-teal-300">
                                                <div class="flex items-center gap-1">
                                                    <NavIcon name="zap" class="h-3 w-3 text-teal-600 dark:text-teal-400" />
                                                    <span>اقتراح الليرة اليوم (sp-today)</span>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <button
                                                        v-if="canUpdateRates"
                                                        type="button"
                                                        class="inline-flex items-center gap-1 rounded bg-teal-600 px-2 py-0.5 text-[10px] font-black text-white shadow-2xs hover:bg-teal-700 transition"
                                                        title="تطبيق هذا المقترح مباشرة في الحقول"
                                                        @click="applySuggestion(currency)"
                                                    >
                                                        <NavIcon name="check" class="h-2.5 w-2.5" />
                                                        <span>تطبيق</span>
                                                    </button>
                                                    <button
                                                        v-if="canUpdateRates"
                                                        type="button"
                                                        class="inline-flex items-center gap-0.5 rounded border border-teal-300 bg-white/90 px-1.5 py-0.5 text-[10px] font-bold text-teal-800 hover:bg-teal-100 dark:border-teal-700 dark:bg-slate-800 dark:text-teal-300 transition"
                                                        title="تطبيق السعر مع احتساب هامش ربح مخصص"
                                                        @click="openMarginDialog(currency)"
                                                    >
                                                        <span>هامش...</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- أسعار الشراء والبيع المقترحة -->
                                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                                <div class="rounded-lg bg-white/90 p-2 shadow-2xs dark:bg-slate-800/90">
                                                    <span class="block text-[10px] text-slate-400">شراء السوق المقترح</span>
                                                    <div class="flex items-baseline gap-1 mt-0.5">
                                                        <span class="text-sm font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                                            {{ getSpSuggestion(currency.code).buy_new }}
                                                        </span>
                                                        <span class="text-[10px] text-slate-400">ل.س جديدة</span>
                                                    </div>
                                                    <span class="block text-[9px] text-slate-400 tabular-nums">
                                                        ({{ number(getSpSuggestion(currency.code).buy_traditional) }} ل.س قديمة)
                                                    </span>
                                                </div>

                                                <div class="rounded-lg bg-white/90 p-2 shadow-2xs dark:bg-slate-800/90">
                                                    <span class="block text-[10px] text-slate-400">بيع السوق المقترح</span>
                                                    <div class="flex items-baseline gap-1 mt-0.5">
                                                        <span class="text-sm font-black tabular-nums text-rose-700 dark:text-rose-400">
                                                            {{ getSpSuggestion(currency.code).sell_new }}
                                                        </span>
                                                        <span class="text-[10px] text-slate-400">ل.س جديدة</span>
                                                    </div>
                                                    <span class="block text-[9px] text-slate-400 tabular-nums">
                                                        ({{ number(getSpSuggestion(currency.code).sell_traditional) }} ل.س قديمة)
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- مؤشر مقارنة السعر مع النظام -->
                                            <div v-if="getDifference(currency)" class="mt-2 flex items-center justify-between text-[10px]">
                                                <span class="text-slate-500 dark:text-slate-400">مقارنة مع السعر الحالي:</span>
                                                <span
                                                    class="font-bold tabular-nums"
                                                    :class="getDifference(currency).isHigher
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : getDifference(currency).isLower
                                                            ? 'text-rose-600 dark:text-rose-400'
                                                            : 'text-slate-500'"
                                                >
                                                    {{ getDifference(currency).isHigher ? '↑ أعلى' : '↓ أقل' }} بـ {{ Math.abs(getDifference(currency).diff).toFixed(2) }} ({{ getDifference(currency).percent }}%)
                                                </span>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="flex h-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-3 text-center text-xs text-slate-400 dark:border-slate-700 dark:bg-slate-800/40">
                                            لا يتوفر اقتباس مباشر لهذه العملة في نشرة دمشق الحالية
                                        </div>
                                    </template>
                                </div>

                                <!-- القسم ٣: حقول الإدخال والاعتماد (4 أعمدة) -->
                                <div class="lg:col-span-4">
                                    <div class="flex items-end gap-2.5">
                                        <div class="flex-1">
                                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                                سعر الشراء
                                            </label>
                                            <div class="relative mt-1">
                                                <input
                                                    v-model="forms[currency.id].buy_rate"
                                                    :disabled="!canUpdateRates"
                                                    type="number"
                                                    step="any"
                                                    min="0.0000000001"
                                                    placeholder="0.00"
                                                    class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-sm font-black tabular-nums text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:bg-slate-800"
                                                />
                                            </div>
                                            <InputError :message="forms[currency.id].errors.buy_rate" class="mt-1" />
                                        </div>

                                        <div class="flex-1">
                                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                                سعر البيع
                                            </label>
                                            <div class="relative mt-1">
                                                <input
                                                    v-model="forms[currency.id].sell_rate"
                                                    :disabled="!canUpdateRates"
                                                    type="number"
                                                    step="any"
                                                    min="0.0000000001"
                                                    placeholder="0.00"
                                                    class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-sm font-black tabular-nums text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:bg-slate-800"
                                                />
                                            </div>
                                            <InputError :message="forms[currency.id].errors.sell_rate" class="mt-1" />
                                        </div>

                                        <button
                                            v-if="canUpdateRates"
                                            type="submit"
                                            :disabled="forms[currency.id].processing"
                                            class="inline-flex h-[38px] items-center justify-center gap-1 rounded-xl bg-indigo-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                                        >
                                            <NavIcon name="check" class="h-3.5 w-3.5" />
                                            <span>حفظ</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- حوار اعتماد وتطبيق أسعار السوق لجميع العملات -->
        <SyncAllRatesDialog
            :show="showSyncAllDialog"
            :currencies="currencies"
            :sp-today="spToday"
            :processing="isSyncingAll"
            @close="showSyncAllDialog = false"
            @confirm="handleSyncAllConfirm"
        />

        <!-- حوار تعبئة المقترحات في الحقول -->
        <FillSuggestionsDialog
            :show="showFillSuggestionsDialog"
            :currencies="currencies"
            :sp-today="spToday"
            @close="showFillSuggestionsDialog = false"
            @apply="handleFillSuggestionsApply"
        />

        <!-- حوار تطبيق بهامش مخصص لعملة محددة -->
        <RateMarginDialog
            :show="showMarginDialog"
            :currency="marginTargetCurrency"
            :sugg="marginTargetCurrency ? getSpSuggestion(marginTargetCurrency.code) : null"
            @close="showMarginDialog = false"
            @apply="handleMarginApply"
        />

        <!-- حوار تنبيه الفارق السعري غير المعتاد أو الانعكاس -->
        <LargeDeviationDialog
            v-if="deviationData"
            :show="showDeviationDialog"
            :currency="deviationData.currency"
            :new-buy="deviationData.newBuy"
            :new-sell="deviationData.newSell"
            :current-buy="deviationData.currentBuy"
            :current-sell="deviationData.currentSell"
            :market-buy="deviationData.marketBuy"
            :market-sell="deviationData.marketSell"
            :deviation-percent="deviationData.deviationPercent"
            :is-inverted="deviationData.isInverted"
            :processing="forms[deviationData.currencyId]?.processing"
            @close="showDeviationDialog = false"
            @confirm="handleDeviationConfirm"
        />

        <!-- حوار معلومات مؤشر السوق -->
        <MarketInfoDialog
            :show="showMarketInfoDialog"
            :sp-today="spToday"
            :is-refreshing="isRefreshing"
            @close="showMarketInfoDialog = false"
            @refresh="refreshSpToday"
        />
    </AuthenticatedLayout>
</template>
