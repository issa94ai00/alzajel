<script setup>
import { useFormat } from '@/composables/useFormat';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    modelValue: { type: [Number, String], default: null },
    // currency_id => الرصيد الحالي في الوردية.
    balances: { type: Object, default: () => ({}) },
    // 'sell' أو 'buy' — يحدّد أيّ سعر رسمي يُعرض على البطاقة.
    type: { type: String, default: 'sell' },
});

const emit = defineEmits(['update:modelValue']);

const { number, rate } = useFormat();

const searchInput = ref(null);
const query = ref('');

const visible = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) return props.currencies;

    return props.currencies.filter((c) =>
        [c.code, c.name, c.tag, c.symbol].some((field) => String(field ?? '').toLowerCase().includes(term)),
    );
});

function balanceOf(currency) {
    const value = Number(props.balances?.[currency.id]);

    return Number.isFinite(value) ? value : 0;
}

function officialRateOf(currency) {
    const rates = currency.exchange_rate;

    if (!rates) return null;

    return Number(props.type === 'sell' ? rates.sell_rate : rates.buy_rate);
}

function isSelected(currency) {
    return Number(props.modelValue) === currency.id;
}

function select(currency) {
    emit('update:modelValue', currency.id);
}

function focusSearch() {
    nextTick(() => searchInput.value?.focus());
}

defineExpose({
    focusSearch,
});
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-2 mb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">
                    العملات المتاحة
                </span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-600 dark:bg-slate-700 dark:text-slate-300">
                    {{ visible.length }}
                </span>
            </div>

            <div class="relative w-full max-w-[200px] sm:max-w-[240px]">
                <div class="pointer-events-none absolute inset-y-0 start-2.5 flex items-center text-gray-400">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    ref="searchInput"
                    v-model="query"
                    type="search"
                    placeholder="بحث في العملات…"
                    class="w-full rounded-lg border-gray-200 ps-8 pe-7 py-1 text-xs shadow-sm transition placeholder:text-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400"
                    aria-label="تصفية العملات"
                />
                <button
                    v-if="query"
                    type="button"
                    @click="query = ''"
                    class="absolute inset-y-0 end-2 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"
                    aria-label="مسح البحث"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4">
            <button
                v-for="currency in visible"
                :key="currency.id"
                type="button"
                :aria-pressed="isSelected(currency)"
                @click="select(currency)"
                class="group relative min-w-0 rounded-xl border p-3 text-start transition duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-slate-900"
                :class="isSelected(currency)
                    ? 'border-indigo-600 bg-indigo-50/70 shadow-sm ring-1 ring-indigo-600 dark:border-indigo-400 dark:bg-indigo-950/40 dark:ring-indigo-400'
                    : 'border-gray-200 bg-white hover:border-indigo-200 hover:bg-slate-50/70 dark:border-slate-700 dark:bg-slate-800/80 dark:hover:border-slate-600 dark:hover:bg-slate-700/50'"
            >
                <!-- علامة التحديد النشط -->
                <div
                    v-if="isSelected(currency)"
                    class="absolute top-2.5 end-2.5 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-white dark:bg-indigo-500"
                >
                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <div class="flex items-baseline justify-between pe-5">
                    <span
                        class="text-base font-extrabold tracking-tight"
                        :class="isSelected(currency) ? 'text-indigo-900 dark:text-indigo-200' : 'text-gray-900 dark:text-white'"
                    >
                        {{ currency.code }}
                    </span>
                    <span v-if="currency.symbol" class="text-xs font-semibold text-gray-400 dark:text-slate-400">
                        {{ currency.symbol }}
                    </span>
                </div>

                <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-slate-400" :title="currency.name">
                    {{ currency.name }}
                </div>

                <!-- فئة الورقة النقدية إن وُجدت -->
                <div v-if="currency.tag" class="mt-1.5 flex">
                    <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                        {{ currency.tag }}
                    </span>
                </div>

                <!-- الرصيد والسعر -->
                <div class="mt-2.5 border-t border-dashed border-gray-200/90 pt-2 text-[11px] dark:border-slate-700">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-gray-500 dark:text-slate-400">الرصيد:</span>
                        <span
                            class="font-bold tabular-nums"
                            :class="balanceOf(currency) > 0
                                ? 'text-emerald-700 dark:text-emerald-400'
                                : 'text-slate-400 dark:text-slate-500'"
                        >
                            {{ number(balanceOf(currency)) }}
                        </span>
                    </div>

                    <div class="mt-1 flex items-center justify-between gap-1">
                        <span class="text-gray-500 dark:text-slate-400">
                            {{ type === 'sell' ? 'سعر البيع:' : 'سعر الشراء:' }}
                        </span>
                        <span
                            v-if="officialRateOf(currency) !== null"
                            class="font-bold tabular-nums"
                            :class="isSelected(currency) ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-800 dark:text-slate-200'"
                        >
                            {{ rate(officialRateOf(currency)) }}
                        </span>
                        <span v-else class="font-medium text-amber-600 dark:text-amber-400">
                            غير محدّد
                        </span>
                    </div>
                </div>
            </button>
        </div>

        <p v-if="!visible.length" class="mt-2 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4 text-center text-xs text-gray-500 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400">
            {{ currencies.length ? 'لا توجد عملة تطابق عبارة البحث الحالية.' : 'لا توجد عملات مفعّلة في النظام.' }}
        </p>
    </div>
</template>
