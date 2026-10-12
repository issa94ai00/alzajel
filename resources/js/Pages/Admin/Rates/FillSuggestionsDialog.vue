<script setup>
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    currencies: { type: Array, required: true },
    spToday: { type: Object, default: () => ({ success: false, rates: {} }) },
});

const emit = defineEmits(['close', 'apply']);

const { settings } = useFormat();

const spreadPercent = ref(0);
const customSpread = ref('');
const isCustomSpread = ref(false);

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

const applicableCurrencies = computed(() => {
    return props.currencies
        .map((c) => {
            const sugg = getSpSuggestion(c.code);
            if (!sugg) return null;

            const spread = activeSpread.value;
            const buy = spread > 0 ? sugg.buy_new * (1 - spread / 100) : sugg.buy_new;
            const sell = spread > 0 ? sugg.sell_new * (1 + spread / 100) : sugg.sell_new;
            const decimals = settings().decimals ?? 2;

            return {
                currency: c,
                sugg,
                buy: buy.toFixed(decimals),
                sell: sell.toFixed(decimals),
            };
        })
        .filter(Boolean);
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        spreadPercent.value = 0;
        isCustomSpread.value = false;
        customSpread.value = '';
    },
);

function handleApply() {
    emit('apply', {
        spread: activeSpread.value,
    });
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex flex-col max-h-[85vh]">
            <!-- الرأس -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-teal-50/40 via-white to-slate-50 dark:from-slate-800 dark:via-slate-800 dark:to-teal-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-300">
                            <NavIcon name="zap" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                تعبئة مقترحات السوق في الحقول
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                إدراج أسعار الليرة اليوم في حقول الإدخال لتتمكن من مراجعتها وتعديلها قبل الحفظ
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition"
                        @click="emit('close')"
                    >
                        <NavIcon name="close" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- المحتوى -->
            <div class="p-5 space-y-4 overflow-y-auto">
                <!-- اختيار هامش الربح -->
                <div>
                    <label class="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1.5">
                        اختر هامش الصرف المطلوب (Spread):
                    </label>

                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            v-for="preset in SPREAD_PRESETS"
                            :key="preset.value"
                            type="button"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-bold transition"
                            :class="!isCustomSpread && spreadPercent === preset.value
                                ? 'bg-teal-600 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600'"
                            @click="isCustomSpread = false; spreadPercent = preset.value"
                        >
                            {{ preset.label }}
                        </button>

                        <button
                            type="button"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-bold transition"
                            :class="isCustomSpread
                                ? 'bg-teal-600 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600'"
                            @click="isCustomSpread = true; if (!customSpread) customSpread = '2.0'"
                        >
                            مخصص %
                        </button>
                    </div>

                    <div v-if="isCustomSpread" class="mt-2 flex items-center gap-2">
                        <input
                            v-model="customSpread"
                            type="number"
                            step="0.05"
                            min="0"
                            placeholder="مثال: 0.5"
                            class="w-24 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        />
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            نسبة الهامش (% فارق ربحي)
                        </span>
                    </div>
                </div>

                <!-- معاينة سريعة للقيم المحسوبة -->
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400 mb-2">
                        <span>معاينة الأسعار التي ستُعبأ ({{ applicableCurrencies.length }} عملة):</span>
                        <span v-if="activeSpread > 0" class="text-teal-600 dark:text-teal-400">
                            بهامش ±{{ activeSpread }}%
                        </span>
                    </div>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700/60 max-h-48 overflow-y-auto bg-slate-50/50 dark:bg-slate-900/40">
                        <div
                            v-for="item in applicableCurrencies"
                            :key="item.currency.id"
                            class="p-2.5 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <span class="font-black text-slate-800 dark:text-slate-200">
                                    {{ item.currency.code }}
                                </span>
                                <span class="text-slate-400 text-[11px]">
                                    {{ item.currency.name }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3 tabular-nums font-black">
                                <div>
                                    <span class="text-[10px] text-slate-400 me-1">شراء:</span>
                                    <span class="text-emerald-700 dark:text-emerald-400">{{ item.buy }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 me-1">بيع:</span>
                                    <span class="text-rose-700 dark:text-rose-400">{{ item.sell }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500 dark:text-slate-400 rounded-lg bg-slate-100 dark:bg-slate-800 p-2.5">
                    💡 <strong>ملاحظة:</strong> التعبئة تقوم بتحديث حقول الإدخال في الصفحة فقط، ولا تحفظ في قاعدة البيانات حتى تقوم بالضغط على زر "حفظ" لكل عملة أو "اعتماد الكل".
                </p>
            </div>

            <!-- التذييل -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/90 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition"
                    @click="emit('close')"
                >
                    إلغاء
                </button>

                <button
                    type="button"
                    :disabled="applicableCurrencies.length === 0"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-xs font-black text-white hover:bg-teal-700 shadow-xs transition disabled:opacity-50"
                    @click="handleApply"
                >
                    <NavIcon name="zap" class="h-3.5 w-3.5" />
                    <span>تعبئة في حقول الصفحة</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
