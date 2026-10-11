<script setup>
import { useFormat } from '@/composables/useFormat';
import { computed, ref } from 'vue';

/**
 * اختيار العملة ببطاقات قابلة للنقر بدل قائمة منسدلة.
 *
 * القائمة المنسدلة تُخفي ما يحتاجه الصرّاف وقت القرار — الرصيد والسعر وفئة
 * الورقة — ولا تُظهر إلا سطراً واحداً في كل مرة. البطاقات تعرض الثلاثة معاً،
 * وتُختار بنقرة واحدة بدل فتح القائمة ثم التمرير ثم النقر.
 */
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

const query = ref('');

/** البحث يظهر فقط حين تكبر القائمة بما يجعل المسح البصري أبطأ من الكتابة. */
const searchable = computed(() => props.currencies.length > 6);

const visible = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) return props.currencies;

    return props.currencies.filter((c) =>
        [c.code, c.name, c.tag].some((field) => String(field ?? '').toLowerCase().includes(term)),
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
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="block text-sm font-medium text-gray-700 dark:text-slate-300">العملة</span>
            <input
                v-if="searchable"
                v-model="query"
                type="search"
                placeholder="ابحث بالرمز أو الاسم أو الفئة…"
                class="w-full rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white sm:w-44"
                aria-label="تصفية العملات"
            />
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
            <button
                v-for="currency in visible"
                :key="currency.id"
                type="button"
                :aria-pressed="isSelected(currency)"
                @click="select(currency)"
                class="min-w-0 rounded-lg border p-2.5 text-start transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-slate-800 sm:p-3"
                :class="isSelected(currency)
                    ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600 dark:border-indigo-400 dark:bg-indigo-900/30 dark:ring-indigo-400'
                    : 'border-gray-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-slate-600 dark:bg-slate-800 dark:hover:border-indigo-500/60 dark:hover:bg-slate-700/40'"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span
                        class="text-base font-bold"
                        :class="isSelected(currency) ? 'text-indigo-700 dark:text-indigo-200' : 'text-gray-800 dark:text-white'"
                    >
                        {{ currency.code }}
                    </span>
                    <span v-if="currency.symbol" class="text-sm text-gray-400 dark:text-slate-400">{{ currency.symbol }}</span>
                </div>

                <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-slate-400" :title="currency.name">
                    {{ currency.name }}
                </div>

                <!-- فئة الورقة: ما يفرّق صفّي USD-100 و USD-50 عن بعضهما. -->
                <span
                    v-if="currency.tag"
                    class="mt-1.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                >
                    {{ currency.tag }}
                </span>

                <div class="mt-2 border-t border-dashed border-gray-200 pt-2 text-[11px] dark:border-slate-600">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500 dark:text-slate-400">الرصيد</span>
                        <span
                            class="font-semibold"
                            :class="balanceOf(currency) > 0
                                ? 'text-gray-700 dark:text-slate-200'
                                : 'text-rose-600 dark:text-rose-400'"
                        >
                            {{ number(balanceOf(currency)) }}
                        </span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between gap-2">
                        <span class="text-gray-500 dark:text-slate-400">{{ type === 'sell' ? 'سعر البيع' : 'سعر الشراء' }}</span>
                        <span v-if="officialRateOf(currency) !== null" class="font-semibold text-gray-700 dark:text-slate-200">
                            {{ rate(officialRateOf(currency)) }}
                        </span>
                        <span v-else class="font-semibold text-amber-700 dark:text-amber-400">لا سعر</span>
                    </div>
                </div>
            </button>
        </div>

        <p v-if="!visible.length" class="mt-2 rounded-md bg-gray-50 p-3 text-center text-xs text-gray-500 dark:bg-slate-700/40 dark:text-slate-400">
            {{ currencies.length ? 'لا عملة تطابق البحث.' : 'لا توجد عملات مفعّلة.' }}
        </p>
    </div>
</template>
