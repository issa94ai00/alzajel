<script setup>
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    currency: { type: Object, default: null },
    sugg: { type: Object, default: null },
});

const emit = defineEmits(['close', 'apply']);

const { number, settings } = useFormat();

const spreadPercent = ref(0.5);
const customSpread = ref('');
const isCustomSpread = ref(false);

const PRESETS = [
    { label: '0% (مباشر)', value: 0 },
    { label: '0.25%', value: 0.25 },
    { label: '0.50%', value: 0.5 },
    { label: '1.00%', value: 1.0 },
    { label: '1.50%', value: 1.5 },
    { label: '2.00%', value: 2.0 },
];

const activeSpread = computed(() => {
    if (isCustomSpread.value) {
        const val = parseFloat(customSpread.value);
        return !isNaN(val) && val >= 0 ? val : 0;
    }
    return spreadPercent.value;
});

const calculated = computed(() => {
    if (!props.sugg) return null;

    const spread = activeSpread.value;
    const buy = spread > 0 ? props.sugg.buy_new * (1 - spread / 100) : props.sugg.buy_new;
    const sell = spread > 0 ? props.sugg.sell_new * (1 + spread / 100) : props.sugg.sell_new;
    const decimals = settings().decimals ?? 2;

    const formattedBuy = buy.toFixed(decimals);
    const formattedSell = sell.toFixed(decimals);
    const spreadDiff = (Number(formattedSell) - Number(formattedBuy)).toFixed(decimals);

    return {
        buy: formattedBuy,
        sell: formattedSell,
        spreadDiff,
    };
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        spreadPercent.value = 0.5;
        isCustomSpread.value = false;
        customSpread.value = '';
    },
);

function handleApply() {
    if (!calculated.value || !props.currency) return;
    emit('apply', {
        currency: props.currency,
        buy: calculated.value.buy,
        sell: calculated.value.sell,
        spread: activeSpread.value,
    });
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div v-if="currency && sugg" class="flex flex-col">
            <!-- الرأس -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-teal-50/50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-800 dark:to-teal-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-teal-600 text-white text-lg shadow-md shadow-teal-600/25">
                            {{ sugg.flag || currency.symbol || currency.code }}
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    تطبيق بهامش: {{ currency.code }}
                                </h3>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ currency.name }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                احتساب سعر الشراء والبيع وفق هامش ربحي على سعر السوق
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
            <div class="p-5 space-y-4">
                <!-- سعر السوق المرجعي الحالي -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 p-3">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block mb-1.5">
                        سعر السوق المرجعي المباشر (sp-today):
                    </span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-white p-2 shadow-2xs dark:bg-slate-800">
                            <span class="text-[10px] text-slate-400">شراء السوق:</span>
                            <div class="text-sm font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                                {{ sugg.buy_new }} <span class="text-[10px] font-normal text-slate-400">ل.س</span>
                            </div>
                            <span class="text-[9px] text-slate-400 tabular-nums">({{ number(sugg.buy_traditional) }} قديمة)</span>
                        </div>
                        <div class="rounded-lg bg-white p-2 shadow-2xs dark:bg-slate-800">
                            <span class="text-[10px] text-slate-400">بيع السوق:</span>
                            <div class="text-sm font-black text-rose-700 dark:text-rose-400 tabular-nums">
                                {{ sugg.sell_new }} <span class="text-[10px] font-normal text-slate-400">ل.س</span>
                            </div>
                            <span class="text-[9px] text-slate-400 tabular-nums">({{ number(sugg.sell_traditional) }} قديمة)</span>
                        </div>
                    </div>
                </div>

                <!-- اختيار الهامش -->
                <div>
                    <label class="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1.5">
                        نسبة هامش الربح المطلوب (Spread):
                    </label>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            v-for="preset in PRESETS"
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
                            @click="isCustomSpread = true; if (!customSpread) customSpread = '1.0'"
                        >
                            مخصص
                        </button>
                    </div>

                    <div v-if="isCustomSpread" class="mt-2 flex items-center gap-2">
                        <input
                            v-model="customSpread"
                            type="number"
                            step="0.05"
                            min="0"
                            class="w-24 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        />
                        <span class="text-xs text-slate-500 dark:text-slate-400">%</span>
                    </div>
                </div>

                <!-- السعر المحسوب بعد تطبيق الهامش -->
                <div v-if="calculated" class="rounded-xl border border-teal-200 bg-teal-50/70 p-3.5 dark:border-teal-800/60 dark:bg-teal-950/30">
                    <div class="flex items-center justify-between text-xs font-black text-teal-900 dark:text-teal-200 mb-2">
                        <span>النتيجة بعد تطبيق هامش {{ activeSpread }}%:</span>
                        <span class="text-[11px] font-bold text-teal-700 dark:text-teal-300">
                            فارق الصرف: {{ calculated.spreadDiff }} ل.س
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg bg-white p-2.5 shadow-2xs dark:bg-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">سعر الشراء المُحتسب:</span>
                            <div class="text-base font-black text-emerald-700 dark:text-emerald-400 tabular-nums mt-0.5">
                                {{ calculated.buy }}
                            </div>
                            <span class="text-[10px] text-slate-400">أقل بـ {{ activeSpread }}% من السوق</span>
                        </div>

                        <div class="rounded-lg bg-white p-2.5 shadow-2xs dark:bg-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">سعر البيع المُحتسب:</span>
                            <div class="text-base font-black text-rose-700 dark:text-rose-400 tabular-nums mt-0.5">
                                {{ calculated.sell }}
                            </div>
                            <span class="text-[10px] text-slate-400">أعلى بـ {{ activeSpread }}% من السوق</span>
                        </div>
                    </div>
                </div>
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
                    class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-xs font-black text-white hover:bg-teal-700 shadow-xs transition"
                    @click="handleApply"
                >
                    <NavIcon name="check" class="h-3.5 w-3.5" />
                    <span>تطبيق في حقول العملة</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
