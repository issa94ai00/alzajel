<script setup>
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    existingCurrencies: { type: Array, required: true },
    spToday: { type: Object, default: () => ({ success: false, rates: {} }) },
});

const emit = defineEmits(['close']);

const { number } = useFormat();

const isProcessing = ref(false);
const includeRates = ref(true);
const selectedCodes = ref({});

const existingCodesSet = computed(() => {
    return new Set(
        props.existingCurrencies
            .filter((c) => !c.tag)
            .map((c) => c.code.toUpperCase())
    );
});

const spTodayCurrencies = computed(() => {
    if (!props.spToday?.rates) return [];

    return Object.entries(props.spToday.rates).map(([code, data]) => {
        const isAdded = existingCodesSet.value.has(code.toUpperCase());
        const existingCurrency = props.existingCurrencies.find(
            (c) => !c.tag && c.code.toUpperCase() === code.toUpperCase()
        );

        return {
            code,
            name_ar: data.name_ar,
            flag: data.flag,
            buy_new: data.buy_new,
            sell_new: data.sell_new,
            buy_traditional: data.buy_traditional,
            sell_traditional: data.sell_traditional,
            isAdded,
            existingCurrency,
        };
    });
});

const newCurrencies = computed(() => spTodayCurrencies.value.filter((c) => !c.isAdded));
const selectedCount = computed(() => Object.values(selectedCodes.value).filter(Boolean).length);

const allSelected = computed(() => {
    return newCurrencies.value.length > 0 &&
        newCurrencies.value.every((c) => selectedCodes.value[c.code]);
});

function toggleSelectAll() {
    const shouldSelect = !allSelected.value;
    newCurrencies.value.forEach((c) => {
        selectedCodes.value[c.code] = shouldSelect;
    });
}

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        // افتراضياً نحدد جميع العملات غير المضافة بعد
        const sel = {};
        newCurrencies.value.forEach((c) => {
            sel[c.code] = true;
        });
        selectedCodes.value = sel;
    },
    { immediate: true },
);

function handleImport() {
    const codesToImport = Object.entries(selectedCodes.value)
        .filter(([_, isSelected]) => isSelected)
        .map(([code]) => code);

    if (codesToImport.length === 0 || isProcessing.value) return;

    isProcessing.value = true;
    router.post(
        route('currencies.import-sp-today'),
        {
            codes: codesToImport,
            include_rates: includeRates.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isProcessing.value = false;
                emit('close');
            },
        }
    );
}
</script>

<template>
    <Modal :show="show" max-width="2xl" :closeable="!isProcessing" @close="emit('close')">
        <div class="flex flex-col max-h-[90vh]">
            <!-- الرأس -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-teal-50/50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-800 dark:to-teal-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-md shadow-teal-600/25">
                            <NavIcon name="zap" class="h-6 w-6" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    استيراد العملات من مؤشر الليرة اليوم (sp-today)
                                </h3>
                                <span class="rounded-full bg-teal-100 px-2.5 py-0.5 text-[10px] font-bold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">
                                    {{ newCurrencies.length }} عملة جديدة متاحة
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                إضافة العملات المعترف بها في سوق دمشق الموازي وتفعيلها وتعيين أسعار صرفها تلقائياً
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="isProcessing"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition"
                        @click="emit('close')"
                    >
                        <NavIcon name="close" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- المحتوى -->
            <div class="p-5 overflow-y-auto space-y-4">
                <!-- خيارات الاستيراد -->
                <div class="rounded-xl border border-teal-200/80 bg-teal-50/60 p-3.5 dark:border-teal-800/50 dark:bg-teal-950/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="includeRates"
                            type="checkbox"
                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-800"
                        />
                        <span class="text-xs font-bold text-teal-950 dark:text-teal-200">
                            تعيين وتثبيت أسعار الصرف فوراً من النشرة المباشرة (شراء / بيع)
                        </span>
                    </label>

                    <div class="text-[11px] text-teal-700 dark:text-teal-300 font-semibold">
                        جاهزة لعمليات الصرافة فور الإضافة
                    </div>
                </div>

                <!-- شريط التحديد -->
                <div class="flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <input
                            id="select-all-new"
                            type="checkbox"
                            :checked="allSelected"
                            :disabled="newCurrencies.length === 0"
                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-800"
                            @change="toggleSelectAll"
                        />
                        <label for="select-all-new" class="font-bold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                            تحديد جميع العملات الجديدة ({{ newCurrencies.length }})
                        </label>
                    </div>

                    <span class="font-bold text-slate-500 dark:text-slate-400">
                        المحدد للاستيراد: <strong class="text-teal-600 dark:text-teal-400 font-black">{{ selectedCount }}</strong>
                    </span>
                </div>

                <!-- قائمة العملات الجديدة -->
                <div v-if="newCurrencies.length > 0" class="rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700/60 overflow-hidden bg-white dark:bg-slate-800/80 max-h-72 overflow-y-auto">
                    <div
                        v-for="currency in newCurrencies"
                        :key="currency.code"
                        class="p-3 transition-colors flex items-center justify-between gap-3 hover:bg-teal-50/30 dark:hover:bg-teal-950/20"
                        :class="selectedCodes[currency.code] ? 'bg-teal-50/20 dark:bg-teal-950/10' : ''"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <input
                                v-model="selectedCodes[currency.code]"
                                type="checkbox"
                                class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-800"
                            />

                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-bold shadow-2xs dark:bg-slate-700">
                                {{ currency.flag || currency.code }}
                            </span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black text-slate-900 dark:text-white">
                                        {{ currency.code }}
                                    </span>
                                    <span class="text-xs text-slate-600 dark:text-slate-300">
                                        {{ currency.name_ar }}
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400">
                                    غير موجودة في النظام حالياً
                                </span>
                            </div>
                        </div>

                        <!-- أسعار الصرف في السوق -->
                        <div class="text-end shrink-0 tabular-nums">
                            <div class="flex items-baseline justify-end gap-1.5 text-xs font-black">
                                <span class="text-[10px] text-slate-400">شراء:</span>
                                <span class="text-emerald-700 dark:text-emerald-400">{{ currency.buy_new }}</span>
                                <span class="text-slate-300 dark:text-slate-600">|</span>
                                <span class="text-[10px] text-slate-400">بيع:</span>
                                <span class="text-rose-700 dark:text-rose-400">{{ currency.sell_new }}</span>
                                <span class="text-[10px] font-normal text-slate-400">ل.س</span>
                            </div>
                            <span class="text-[9px] text-slate-400 block">
                                ({{ number(currency.buy_traditional) }} / {{ number(currency.sell_traditional) }} قديمة)
                            </span>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-dashed border-emerald-300 bg-emerald-50/50 p-6 text-center text-xs text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300">
                    <span class="block text-lg mb-1">🎉</span>
                    جميع العملات المتوفرة في مؤشر الليرة اليوم ({{ spTodayCurrencies.length }} عملة) مضافة بالفعل في النظام!
                </div>
            </div>

            <!-- التذييل -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/90 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    سيتم تفعيل العملات المضافة فوراً لتظهر في قائمة الصرافة
                </span>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        :disabled="isProcessing"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition disabled:opacity-50"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>

                    <button
                        type="button"
                        :disabled="isProcessing || selectedCount === 0"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-xs font-black text-white hover:bg-teal-700 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="handleImport"
                    >
                        <NavIcon name="zap" class="h-3.5 w-3.5" :class="isProcessing ? 'animate-spin' : ''" />
                        <span>{{ isProcessing ? 'جارٍ الاستيراد...' : `استيراد وتفعيل (${selectedCount}) عملات الآن` }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
