<script setup>
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    currencies: { type: Array, required: true },
    spToday: { type: Object, default: () => ({ success: false, rates: {} }) },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const { number, settings } = useFormat();

const spreadPercent = ref(0);
const customSpread = ref('');
const isCustomSpread = ref(false);
const selectedCurrencies = ref({});

const SPREAD_PRESETS = [
    { label: '0% (السوق المباشر)', value: 0 },
    { label: '0.25%', value: 0.25 },
    { label: '0.50%', value: 0.5 },
    { label: '1.00%', value: 1.0 },
    { label: '1.50%', value: 1.5 },
];

const activeSpread = computed(() => {
    if (isCustomSpread.value) {
        const val = parseFloat(customSpread.value);
        return !isNaN(val) && val >= 0 ? val : 0;
    }
    return spreadPercent.value;
});

function getSpSuggestion(code) {
    if (!props.spToday?.rates) return null;
    return props.spToday.rates[code.toUpperCase()] ?? null;
}

// حساب الأسعار الجديدة مع تطبيق الهامش
function calculateNewRates(currency) {
    const sugg = getSpSuggestion(currency.code);
    if (!sugg) return null;

    const spread = activeSpread.value;
    const buy = spread > 0 ? sugg.buy_new * (1 - spread / 100) : sugg.buy_new;
    const sell = spread > 0 ? sugg.sell_new * (1 + spread / 100) : sugg.sell_new;

    const decimals = settings().decimals ?? 2;
    const formattedBuy = Number(buy.toFixed(decimals));
    const formattedSell = Number(sell.toFixed(decimals));

    const currentBuy = Number(currency.exchange_rate?.buy_rate) || 0;
    const currentSell = Number(currency.exchange_rate?.sell_rate) || 0;

    const buyDiff = currentBuy > 0 ? formattedBuy - currentBuy : 0;
    const buyDiffPercent = currentBuy > 0 ? ((buyDiff / currentBuy) * 100).toFixed(1) : null;

    return {
        buy: formattedBuy,
        sell: formattedSell,
        originalBuy: sugg.buy_new,
        originalSell: sugg.sell_new,
        buyDiff,
        buyDiffPercent,
        isHigher: buyDiff > 0,
        isLower: buyDiff < 0,
    };
}

// قائمة العملات مع تفاصيل التحديث
const currencyItems = computed(() => {
    return props.currencies.map((currency) => {
        const sugg = getSpSuggestion(currency.code);
        const calculated = sugg ? calculateNewRates(currency) : null;
        const hasQuote = Boolean(sugg);

        return {
            currency,
            hasQuote,
            sugg,
            calculated,
            isSelected: Boolean(selectedCurrencies.value[currency.id]),
        };
    });
});

const availableCount = computed(() => currencyItems.value.filter((i) => i.hasQuote).length);
const selectedCount = computed(() => currencyItems.value.filter((i) => i.hasQuote && i.isSelected).length);

const allSelected = computed(() => {
    const available = currencyItems.value.filter((i) => i.hasQuote);
    return available.length > 0 && available.every((i) => selectedCurrencies.value[i.currency.id]);
});

function toggleSelectAll() {
    const shouldSelect = !allSelected.value;
    currencyItems.value.forEach((item) => {
        if (item.hasQuote) {
            selectedCurrencies.value[item.currency.id] = shouldSelect;
        }
    });
}

function selectPreset(val) {
    isCustomSpread.value = false;
    spreadPercent.value = val;
}

function enableCustomSpread() {
    isCustomSpread.value = true;
    if (!customSpread.value) customSpread.value = '2.0';
}

// تهيئة التحديد عند فتح النافذة
watch(
    () => props.show,
    (open) => {
        if (!open) return;
        spreadPercent.value = 0;
        isCustomSpread.value = false;
        customSpread.value = '';

        const sel = {};
        props.currencies.forEach((c) => {
            if (getSpSuggestion(c.code)) {
                sel[c.id] = true;
            } else {
                sel[c.id] = false;
            }
        });
        selectedCurrencies.value = sel;
    },
    { immediate: true },
);

function handleConfirm() {
    if (props.processing || selectedCount.value === 0) return;

    const ratesToSubmit = [];
    currencyItems.value.forEach((item) => {
        if (item.hasQuote && item.isSelected && item.calculated) {
            ratesToSubmit.push({
                currency_id: item.currency.id,
                buy_rate: item.calculated.buy,
                sell_rate: item.calculated.sell,
            });
        }
    });

    emit('confirm', {
        rates: ratesToSubmit,
        spread: activeSpread.value,
    });
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
    <Modal :show="show" max-width="2xl" :closeable="!processing" @close="emit('close')">
        <div class="flex flex-col max-h-[90vh]">
            <!-- الرأس (Header) -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-teal-50/50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-800 dark:to-teal-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-md shadow-teal-600/25">
                            <NavIcon name="zap" class="h-6 w-6" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    اعتماد وتطبيق أسعار السوق الموازي
                                </h3>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="spToday.success
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="spToday.success ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                                    <span>{{ spToday.success ? 'مباشر sp-today' : 'غير متصل' }}</span>
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                تحديث أسعار الصرف لجميع العملات في قاعدة البيانات فوراً استناداً إلى نشرة دمشق
                                <template v-if="spToday.updated_at">
                                    (آخر فحص: {{ formatTime(spToday.updated_at) }})
                                </template>
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="processing"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition"
                        @click="emit('close')"
                    >
                        <NavIcon name="close" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- المحتوى القابل للتمرير (Body) -->
            <div class="p-5 overflow-y-auto space-y-4">
                <!-- شريط خيارات هامش الربح (Spread Adjustment) -->
                <div class="rounded-xl border border-teal-200/90 bg-teal-50/50 p-3.5 dark:border-teal-800/50 dark:bg-teal-950/20">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                        <div>
                            <span class="text-xs font-black text-teal-900 dark:text-teal-200 flex items-center gap-1.5">
                                <NavIcon name="filter" class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" />
                                <span>هامش فارق الصرف (Spread / الربح التجاري):</span>
                            </span>
                            <p class="text-[11px] text-teal-700/80 dark:text-teal-300/70 mt-0.5">
                                شراء بسعر أقل بنسبة {{ activeSpread }}% وبيع بسعر أعلى بنسبة {{ activeSpread }}%
                            </p>
                        </div>

                        <!-- أزرار الاختيار السريع للهامش -->
                        <div class="flex items-center gap-1 flex-wrap">
                            <button
                                v-for="preset in SPREAD_PRESETS"
                                :key="preset.value"
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                :class="!isCustomSpread && spreadPercent === preset.value
                                    ? 'bg-teal-600 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-teal-100 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-teal-900/40 border border-teal-200 dark:border-teal-800'"
                                @click="selectPreset(preset.value)"
                            >
                                {{ preset.label }}
                            </button>

                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                :class="isCustomSpread
                                    ? 'bg-teal-600 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-teal-100 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-teal-900/40 border border-teal-200 dark:border-teal-800'"
                                @click="enableCustomSpread"
                            >
                                مخصص %
                            </button>
                        </div>
                    </div>

                    <!-- حقل النسبة المخصصة -->
                    <div v-if="isCustomSpread" class="mt-2.5 pt-2.5 border-t border-teal-200/60 dark:border-teal-800/40 flex items-center gap-2">
                        <label class="text-xs font-bold text-teal-900 dark:text-teal-200">
                            أدخل نسبة الهامش (%):
                        </label>
                        <input
                            v-model="customSpread"
                            type="number"
                            step="0.05"
                            min="0"
                            max="50"
                            placeholder="مثال: 0.75"
                            class="w-24 rounded-lg border border-teal-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        />
                        <span class="text-[11px] text-teal-700 dark:text-teal-300">
                            (مثال: 1% تعني فارق 1% عن سعر السوق المرجعي)
                        </span>
                    </div>
                </div>

                <!-- شريط إحصائي واختيار الكل -->
                <div class="flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <input
                            id="select-all-currencies"
                            type="checkbox"
                            :checked="allSelected"
                            :disabled="availableCount === 0"
                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-800"
                            @change="toggleSelectAll"
                        />
                        <label for="select-all-currencies" class="font-bold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                            تحديد جميع العملات المتوفرة ({{ availableCount }})
                        </label>
                    </div>

                    <span class="font-bold text-slate-500 dark:text-slate-400">
                        المحدد: <strong class="text-teal-600 dark:text-teal-400 font-black">{{ selectedCount }}</strong> من أصل {{ availableCount }}
                    </span>
                </div>

                <!-- جدول / قائمة العملات ومقارنة الأسعار -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700/60 overflow-hidden bg-white dark:bg-slate-800/80">
                    <div
                        v-for="item in currencyItems"
                        :key="item.currency.id"
                        class="p-3 transition-colors flex items-center justify-between gap-3"
                        :class="item.hasQuote
                            ? (item.isSelected ? 'bg-teal-50/20 dark:bg-teal-950/10 hover:bg-teal-50/40' : 'hover:bg-slate-50 dark:hover:bg-slate-700/30 opacity-75')
                            : 'bg-slate-50/70 dark:bg-slate-800/40 opacity-50'"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- مربع التحديد -->
                            <input
                                v-if="item.hasQuote"
                                v-model="selectedCurrencies[item.currency.id]"
                                type="checkbox"
                                class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <span v-else class="h-4 w-4 shrink-0 rounded border border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center text-[10px] text-slate-400">
                                ✕
                            </span>

                            <!-- راية ورمز العملة -->
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-bold shadow-2xs dark:bg-slate-700">
                                {{ item.sugg?.flag || item.currency.symbol || item.currency.code }}
                            </span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-black text-slate-900 dark:text-white">
                                        {{ item.currency.code }}
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ item.currency.name }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                    <span>النظام الحالي:</span>
                                    <span class="font-bold tabular-nums text-slate-600 dark:text-slate-300">
                                        {{ item.currency.exchange_rate?.buy_rate || '—' }} / {{ item.currency.exchange_rate?.sell_rate || '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- السعر الجديد ومؤشر الفارق -->
                        <div class="text-end shrink-0">
                            <template v-if="item.hasQuote && item.calculated">
                                <div class="flex items-baseline justify-end gap-1.5">
                                    <span class="text-xs font-bold text-slate-400">شراء:</span>
                                    <span class="text-sm font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                        {{ item.calculated.buy }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-300 dark:text-slate-600">|</span>
                                    <span class="text-xs font-bold text-slate-400">بيع:</span>
                                    <span class="text-sm font-black tabular-nums text-rose-700 dark:text-rose-400">
                                        {{ item.calculated.sell }}
                                    </span>
                                </div>

                                <div v-if="item.calculated.buyDiffPercent !== null" class="mt-0.5 flex items-center justify-end gap-1 text-[10px]">
                                    <span
                                        class="font-bold tabular-nums"
                                        :class="item.calculated.isHigher
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : item.calculated.isLower
                                                ? 'text-rose-600 dark:text-rose-400'
                                                : 'text-slate-400'"
                                    >
                                        {{ item.calculated.isHigher ? '↑' : item.calculated.isLower ? '↓' : '=' }}
                                        {{ Math.abs(item.calculated.buyDiff).toFixed(2) }} ({{ item.calculated.buyDiffPercent }}%)
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-400 dark:bg-slate-700/60 dark:text-slate-400">
                                    غير متوفرة في النشرة
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- التذييل وأزرار الإجراءات (Footer) -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/90 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    <strong class="font-bold text-slate-700 dark:text-slate-200">{{ selectedCount }}</strong> عملة جاهزة للحفظ المباشر
                </span>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        :disabled="processing"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition disabled:opacity-50"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>

                    <button
                        type="button"
                        :disabled="processing || selectedCount === 0"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 px-4 py-2 text-xs font-black text-white shadow-xs shadow-teal-600/30 hover:from-teal-700 hover:to-teal-800 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="handleConfirm"
                    >
                        <NavIcon
                            name="check"
                            class="h-4 w-4"
                            :class="processing ? 'animate-spin' : ''"
                        />
                        <span>{{ processing ? 'جارٍ الحفظ والاعتماد...' : `اعتماد وحفظ ${selectedCount} عملات الآن` }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
