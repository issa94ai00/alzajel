<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import CurrencyPicker from '@/Components/CurrencyPicker.vue';
import OldLiraHint from '@/Components/OldLiraHint.vue';
import TransactionSummary from '@/Components/TransactionSummary.vue';
import { useFormat } from '@/composables/useFormat';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    baseCurrency: { type: Object, default: null },
    balances: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:summary', 'request-deposit']);

const { number, rate } = useFormat();

const showCustomerFields = ref(false);
const amountInput = ref(null);
const totalInput = ref(null);
const rateInput = ref(null);
const currencyPickerRef = ref(null);
const typeGroup = ref(null);

const form = useForm({
    type: 'sell',
    currency_id: props.currencies[0]?.id ?? null,
    currency_tag: props.currencies[0]?.tag ?? '',
    amount: '',
    rate: '',
    customer_name: '',
    customer_phone: '',
    notes: '',
});

/* --------------------------- الحاسبة التبادلية --------------------------- */
// حقل المقابل بالليرة لتسهيل الحساب المباشر والعكسي بدون آلة حاسبة خارجية
const localTotal = ref('');
const activeCalculatorInput = ref('amount'); // 'amount' | 'total'

const isSell = computed(() => form.type === 'sell');
const selectedCurrency = computed(() => props.currencies.find((c) => c.id === Number(form.currency_id)));
const baseCode = computed(() => props.baseCurrency?.code ?? '');

const total = computed(() => {
    const amount = Number(form.amount);
    const r = Number(form.rate);

    if (!amount || !r) return 0;

    return amount * r;
});

// مزامنة حقل المقابل بالليرة عند تعديل المبلغ
watch(
    () => form.amount,
    (val) => {
        if (activeCalculatorInput.value === 'amount') {
            const num = Number(val);
            const r = Number(form.rate);
            if (num > 0 && r > 0) {
                localTotal.value = String(+(num * r).toFixed(2));
            } else {
                localTotal.value = '';
            }
        }
    },
);

// مزامنة حقل المبلغ بالعملة الأجنبية عند تعديل المقابل بالليرة (الحاسبة العكسية)
function onTotalInput(val) {
    activeCalculatorInput.value = 'total';
    localTotal.value = val;
    const tVal = Number(val);
    const r = Number(form.rate);
    if (tVal > 0 && r > 0) {
        form.amount = String(+(tVal / r).toFixed(2));
    } else {
        form.amount = '';
    }
}

function onAmountInput(val) {
    activeCalculatorInput.value = 'amount';
    form.amount = val;
}

/* ------------------------------- سعر الصرف ------------------------------- */
const officialRate = computed(() => {
    const rates = selectedCurrency.value?.exchange_rate;

    if (!rates) return null;

    return Number(isSell.value ? rates.sell_rate : rates.buy_rate);
});

const rateOverridden = computed(() => {
    if (officialRate.value === null || form.rate === '') return false;

    return Number(form.rate) !== officialRate.value;
});

function suggestRate() {
    form.rate = officialRate.value === null ? '' : String(officialRate.value);
    if (activeCalculatorInput.value === 'total' && Number(localTotal.value) > 0 && Number(form.rate) > 0) {
        form.amount = String(+(Number(localTotal.value) / Number(form.rate)).toFixed(2));
    } else if (Number(form.amount) > 0 && Number(form.rate) > 0) {
        localTotal.value = String(+(Number(form.amount) * Number(form.rate)).toFixed(2));
    }
}

function adjustRate(delta) {
    const current = Number(form.rate) || officialRate.value || 0;
    const updated = Math.max(0, current + delta);
    form.rate = String(+updated.toFixed(4));
    if (Number(form.amount) > 0) {
        localTotal.value = String(+(Number(form.amount) * Number(form.rate)).toFixed(2));
    }
}

watch(() => form.type, suggestRate);
watch(
    () => form.currency_id,
    () => {
        suggestRate();
        form.currency_tag = selectedCurrency.value?.tag ?? '';
    },
);

/* ------------------------------ اتجاه العملية ----------------------------- */
function chooseType(type) {
    form.type = type;
    focusAmount();
}

function onTypeKeydown(event) {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;

    event.preventDefault();
    form.type = isSell.value ? 'buy' : 'sell';

    nextTick(() => typeGroup.value?.querySelector('[aria-checked="true"]')?.focus());
}

/* ------------------------------ كفاية الرصيد ----------------------------- */
function balanceOf(currencyId) {
    const value = Number(props.balances?.[currencyId]);

    return Number.isFinite(value) ? value : 0;
}

const fundingSide = computed(() => {
    if (!props.baseCurrency) {
        return { code: '', currency_id: null, available: 0, required: 0 };
    }

    if (isSell.value) {
        return {
            code: selectedCurrency.value?.code ?? '',
            currency_id: Number(form.currency_id),
            available: balanceOf(form.currency_id),
            required: Number(form.amount) || 0,
        };
    }

    return {
        code: baseCode.value,
        currency_id: props.baseCurrency?.id,
        available: balanceOf(props.baseCurrency?.id),
        required: total.value,
    };
});

const insufficient = computed(
    () => fundingSide.value.required > 0 && fundingSide.value.required > fundingSide.value.available,
);

const deficit = computed(() => Math.max(0, fundingSide.value.required - fundingSide.value.available));

function askDeposit() {
    emit('request-deposit', {
        currencyId: fundingSide.value.currency_id,
        currencyCode: fundingSide.value.code,
        available: fundingSide.value.available,
        required: fundingSide.value.required,
        deficit: deficit.value,
    });
}

/* -------------------------------- الملخص -------------------------------- */
const summary = computed(() => ({
    type: form.type,
    currencyCode: selectedCurrency.value?.code ?? '',
    baseCode: baseCode.value,
    amount: Number(form.amount) || 0,
    rate: Number(form.rate) || 0,
    total: total.value,
    officialRate: officialRate.value,
    rateOverridden: rateOverridden.value,
    funding: fundingSide.value,
    insufficient: insufficient.value,
}));

watch(summary, (value) => emit('update:summary', value), { immediate: true });

/* -------------------------------- الإرسال -------------------------------- */
const blocker = computed(() => {
    if (!selectedCurrency.value) return 'اختر العملة أولاً.';
    if (!(Number(form.amount) > 0)) return 'أدخل المبلغ للمتابعة.';
    if (!(Number(form.rate) > 0)) return 'أدخل سعر الصرف للمتابعة.';
    if (insufficient.value) return 'الرصيد في الصندوق غير كافٍ لإتمام العملية.';

    return null;
});

const canSubmit = computed(() => !form.processing && blocker.value === null);

const isDirty = computed(
    () =>
        form.amount !== '' ||
        rateOverridden.value ||
        form.customer_name !== '' ||
        form.customer_phone !== '' ||
        form.notes !== '',
);

function focusAmount() {
    nextTick(() => {
        activeCalculatorInput.value = 'amount';
        amountInput.value?.focus();
    });
}

function focusTotal() {
    nextTick(() => {
        activeCalculatorInput.value = 'total';
        totalInput.value?.focus();
    });
}

function focusRate() {
    nextTick(() => rateInput.value?.focus());
}

function focusCurrency() {
    currencyPickerRef.value?.focusSearch?.();
}

function clearEntry() {
    form.reset('amount', 'customer_name', 'customer_phone', 'notes');
    localTotal.value = '';
    form.clearErrors();
    form.currency_tag = selectedCurrency.value?.tag ?? '';
    suggestRate();
    focusAmount();
}

/** مبالغ شائعة سريعة */
const quickAmounts = [50, 100, 200, 500, 1000, 2000, 5000];

function setQuickAmount(val) {
    activeCalculatorInput.value = 'amount';
    form.amount = String(val);
    focusAmount();
}

function addAmount(delta) {
    activeCalculatorInput.value = 'amount';
    const current = Number(form.amount) || 0;
    form.amount = String(current + delta);
    focusAmount();
}

/** فئات نقدية شائعة للعملة المختارة */
const commonTags = computed(() => {
    const code = selectedCurrency.value?.code ?? '';
    if (code === 'USD') return ['USD-100', 'USD-50', 'USD-20', 'طبعة جديدة', 'طبعة قديمة'];
    if (code === 'EUR') return ['EUR-100', 'EUR-50', 'EUR-200', 'EUR-20'];
    if (code === 'SAR') return ['SAR-500', 'SAR-100', 'SAR-50'];
    return ['طبعة جديدة', 'طبعة قديمة', '100', '50'];
});

function setTag(tag) {
    form.currency_tag = tag;
}

/** تعبئة النموذج من عملية سابقة (تكرار عملية) */
function populateFrom(transaction) {
    if (!transaction) return;

    form.type = transaction.type;
    if (transaction.currency?.id) {
        form.currency_id = transaction.currency.id;
    }
    form.currency_tag = transaction.currency_tag ?? '';
    form.rate = String(transaction.rate);
    form.amount = String(transaction.amount);

    if (transaction.customer_name) {
        form.customer_name = transaction.customer_name;
        showCustomerFields.value = true;
    }

    nextTick(() => {
        suggestRate();
        focusAmount();
    });
}

defineExpose({
    populateFrom,
    chooseType,
    focusAmount,
    focusTotal,
    focusRate,
    focusCurrency,
    clearEntry,
});

const FIELD_ORDER = ['type', 'currency_id', 'currency_tag', 'amount', 'rate', 'customer_name', 'customer_phone', 'notes'];
const CUSTOMER_FIELDS = ['customer_name', 'customer_phone', 'notes'];

function focusFirstError(errors) {
    const field = FIELD_ORDER.find((name) => errors[name]);

    if (!field) return;

    if (CUSTOMER_FIELDS.includes(field)) showCustomerFields.value = true;

    nextTick(() => {
        const element = document.getElementById(field);

        element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element?.focus?.({ preventScroll: true });
    });
}

function submit() {
    if (insufficient.value) {
        askDeposit();
        return;
    }

    if (!canSubmit.value) return;

    form.post(route('transactions.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: clearEntry,
        onError: focusFirstError,
    });
}

/* ----------------------- اختصارات لوحة المفاتيح ----------------------- */
function handleKeyDown(e) {
    // تجاهل الاختصارات داخل حقول الملاحظات متعددة الأسطر إن وجدت
    if (e.target.tagName === 'TEXTAREA') return;

    // Alt + 1: بيع
    if (e.altKey && (e.key === '1' || e.code === 'Digit1') || e.key === 'F1') {
        e.preventDefault();
        chooseType('sell');
        return;
    }

    // Alt + 2: شراء
    if (e.altKey && (e.key === '2' || e.code === 'Digit2') || e.key === 'F2') {
        e.preventDefault();
        chooseType('buy');
        return;
    }

    // Alt + A: التركيز على المبلغ
    if (e.altKey && (e.key === 'a' || e.key === 'A' || e.code === 'KeyA')) {
        e.preventDefault();
        focusAmount();
        return;
    }

    // Alt + T: التركيز على الإجمالي (الحاسبة العكسية)
    if (e.altKey && (e.key === 't' || e.key === 'T' || e.code === 'KeyT')) {
        e.preventDefault();
        focusTotal();
        return;
    }

    // Alt + R: التركيز على السعر
    if (e.altKey && (e.key === 'r' || e.key === 'R' || e.code === 'KeyR')) {
        e.preventDefault();
        focusRate();
        return;
    }

    // Alt + C: التركيز على العملة
    if (e.altKey && (e.key === 'c' || e.key === 'C' || e.code === 'KeyC')) {
        e.preventDefault();
        focusCurrency();
        return;
    }

    // Escape: مسح النموذج
    if (e.key === 'Escape' && isDirty.value && !e.defaultPrevented) {
        clearEntry();
        return;
    }

    // Ctrl + Enter: إرسال النموذج
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        submit();
    }
}

onMounted(() => {
    suggestRate();
    focusAmount();
    window.addEventListener('receipt-prompt:closed', focusAmount);
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('receipt-prompt:closed', focusAmount);
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <NewLiraNotice compact :currencies="currencies" dismiss-key="transaction-form" />

        <!-- ١. اتجاه العملية -->
        <section
            class="rounded-2xl border bg-white p-4 shadow-sm transition-all sm:p-5 dark:bg-slate-800"
            :class="isSell
                ? 'border-rose-100 ring-1 ring-rose-100 dark:border-rose-900/40 dark:ring-rose-900/20'
                : 'border-emerald-100 ring-1 ring-emerald-100 dark:border-emerald-900/40 dark:ring-emerald-900/20'"
        >
            <div class="mb-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-6 w-6 items-center justify-center rounded-lg text-xs font-black text-white"
                        :class="isSell ? 'bg-rose-600' : 'bg-emerald-600'"
                    >
                        1
                    </span>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">اتجاه العملية</h3>
                </div>

                <span class="text-[11px] font-medium text-gray-400 dark:text-slate-500 hidden sm:inline">
                    استخدم الأسهم أو <kbd class="font-mono text-[10px]">Alt+1</kbd> / <kbd class="font-mono text-[10px]">Alt+2</kbd>
                </span>
            </div>

            <div
                id="type"
                ref="typeGroup"
                class="grid grid-cols-2 gap-3"
                role="radiogroup"
                aria-label="اتجاه العملية"
                @keydown="onTypeKeydown"
            >
                <!-- خيار البيع -->
                <button
                    type="button"
                    role="radio"
                    :aria-checked="isSell"
                    :tabindex="isSell ? 0 : -1"
                    @click="chooseType('sell')"
                    class="group relative flex flex-col items-start rounded-xl border-2 p-3.5 text-start transition duration-150 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 sm:p-4"
                    :class="isSell
                        ? 'border-rose-600 bg-rose-50/70 text-rose-950 shadow-sm dark:border-rose-500 dark:bg-rose-950/40 dark:text-rose-100'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-rose-200 hover:bg-rose-50/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:border-rose-900/60'"
                >
                    <div class="flex w-full items-center justify-between">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition"
                            :class="isSell ? 'bg-rose-600 text-white shadow-sm' : 'bg-gray-100 text-gray-400 dark:bg-slate-700 dark:text-slate-500'"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6" />
                            </svg>
                        </span>

                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                            :class="isSell ? 'bg-rose-200/80 text-rose-800 dark:bg-rose-900 dark:text-rose-200' : 'bg-gray-100 text-gray-400 dark:bg-slate-700 dark:text-slate-400'"
                        >
                            Alt+1
                        </span>
                    </div>

                    <div class="mt-3">
                        <span class="block text-lg font-black tracking-tight" :class="isSell ? 'text-rose-900 dark:text-rose-100' : 'text-gray-800 dark:text-white'">
                            بيع
                        </span>
                        <span class="block text-xs leading-relaxed opacity-85">
                            تسلّم العميل العملة الأجنبية وتقبض الليرة
                        </span>
                    </div>
                </button>

                <!-- خيار الشراء -->
                <button
                    type="button"
                    role="radio"
                    :aria-checked="!isSell"
                    :tabindex="isSell ? -1 : 0"
                    @click="chooseType('buy')"
                    class="group relative flex flex-col items-start rounded-xl border-2 p-3.5 text-start transition duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 sm:p-4"
                    :class="!isSell
                        ? 'border-emerald-600 bg-emerald-50/70 text-emerald-950 shadow-sm dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-100'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-emerald-200 hover:bg-emerald-50/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:border-emerald-900/60'"
                >
                    <div class="flex w-full items-center justify-between">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition"
                            :class="!isSell ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-100 text-gray-400 dark:bg-slate-700 dark:text-slate-500'"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l6-6m-6 6l-6-6" />
                            </svg>
                        </span>

                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                            :class="!isSell ? 'bg-emerald-200/80 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-gray-100 text-gray-400 dark:bg-slate-700 dark:text-slate-400'"
                        >
                            Alt+2
                        </span>
                    </div>

                    <div class="mt-3">
                        <span class="block text-lg font-black tracking-tight" :class="!isSell ? 'text-emerald-900 dark:text-emerald-100' : 'text-gray-800 dark:text-white'">
                            شراء
                        </span>
                        <span class="block text-xs leading-relaxed opacity-85">
                            تستلم من العميل العملة الأجنبية وتدفع الليرة
                        </span>
                    </div>
                </button>
            </div>
            <InputError :message="form.errors.type" class="mt-2" />
        </section>

        <!-- ٢. اختيار العملة والفئة -->
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
            <div class="mb-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-600 text-xs font-black text-white">
                        2
                    </span>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">العملة وفئة الورقة النقدية</h3>
                </div>

                <span class="text-[11px] font-medium text-gray-400 dark:text-slate-500 hidden sm:inline">
                    اختصار: <kbd class="font-mono text-[10px]">Alt+C</kbd>
                </span>
            </div>

            <CurrencyPicker
                id="currency_id"
                ref="currencyPickerRef"
                v-model="form.currency_id"
                :currencies="currencies"
                :balances="balances"
                :type="form.type"
                @update:model-value="focusAmount"
            />
            <InputError :message="form.errors.currency_id" class="mt-1" />

            <!-- فئة الورقة النقدية وسريعات الفئة -->
            <div class="mt-4 border-t border-gray-100 pt-3 dark:border-slate-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <InputLabel for="currency_tag" class="!mb-0 text-xs">
                        فئة الورقة النقدية
                        <span class="font-normal text-gray-400">(اختياري — تُطبع على الإيصال)</span>
                    </InputLabel>

                    <!-- خيارات الفئات الشائعة السريعة -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] text-gray-400">فئات سريعة:</span>
                        <button
                            v-for="tag in commonTags"
                            :key="tag"
                            type="button"
                            @click="setTag(tag)"
                            class="rounded-md border px-2 py-0.5 text-[11px] font-semibold transition"
                            :class="form.currency_tag === tag
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/40 dark:text-indigo-300'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                        >
                            {{ tag }}
                        </button>
                    </div>
                </div>

                <div class="mt-2 sm:w-1/2">
                    <TextInput
                        id="currency_tag"
                        v-model="form.currency_tag"
                        class="block w-full text-sm"
                        maxlength="50"
                        :placeholder="selectedCurrency?.tag || 'مثال: USD-100 أو طبعة جديدة'"
                    />
                    <InputError :message="form.errors.currency_tag" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- ٣. المبلغ، الحاسبة التبادلية وسعر الصرف -->
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
            <div class="mb-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-600 text-xs font-black text-white">
                        3
                    </span>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">المبلغ والسعر (حاسبة ذكية متبادلة)</h3>
                </div>

                <span class="text-[11px] font-medium text-gray-400 dark:text-slate-500 hidden sm:inline">
                    المبلغ: <kbd class="font-mono text-[10px]">Alt+A</kbd> · الإجمالي: <kbd class="font-mono text-[10px]">Alt+T</kbd>
                </span>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- حقل المبلغ بالعملة الأجنبية -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <InputLabel for="amount" class="text-xs font-bold text-gray-700 dark:text-slate-300">
                            المبلغ بالعملة الأجنبية
                            <span v-if="selectedCurrency" class="text-indigo-600 dark:text-indigo-400">
                                ({{ selectedCurrency.code }})
                            </span>
                        </InputLabel>
                        <button
                            v-if="form.amount"
                            type="button"
                            @click="form.amount = ''; localTotal = ''; focusAmount();"
                            class="text-[11px] font-semibold text-rose-600 hover:underline dark:text-rose-400"
                        >
                            تفريغ المبلغ
                        </button>
                    </div>

                    <div class="relative">
                        <TextInput
                            id="amount"
                            ref="amountInput"
                            :model-value="form.amount"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="block w-full pe-20 text-2xl font-black tabular-nums transition"
                            :class="activeCalculatorInput === 'amount' ? 'ring-2 ring-indigo-500/20' : ''"
                            :error="Boolean(form.errors.amount)"
                            @update:model-value="onAmountInput"
                            @focus="activeCalculatorInput = 'amount'"
                            autofocus
                        />
                        <span
                            v-if="selectedCurrency"
                            class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm font-black text-gray-400 dark:text-slate-500"
                        >
                            {{ selectedCurrency.code }}
                        </span>
                    </div>

                    <!-- أزرار المبالغ السريعة -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[11px] text-gray-400">سريع:</span>
                        <button
                            v-for="amt in quickAmounts"
                            :key="amt"
                            type="button"
                            @click="setQuickAmount(amt)"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-bold text-gray-700 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500"
                        >
                            {{ amt }}
                        </button>
                        <button
                            type="button"
                            @click="addAmount(100)"
                            class="rounded-md border border-dashed border-gray-300 bg-white px-1.5 py-0.5 text-[11px] font-bold text-gray-500 hover:border-gray-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
                        >
                            +100
                        </button>
                    </div>

                    <OldLiraHint
                        :amount="form.amount"
                        :currency="selectedCurrency"
                        @correct="(value) => { form.amount = String(value); localTotal = ''; }"
                    />
                    <InputError :message="form.errors.amount" class="mt-1" />
                </div>

                <!-- حقل المقابل بالليرة (حاسبة عكسية تفاعلية) -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <InputLabel for="localTotal" class="text-xs font-bold text-gray-700 dark:text-slate-300">
                            المقابل الإجمالي بالليرة
                            <span v-if="baseCode" class="text-emerald-600 dark:text-emerald-400">
                                ({{ baseCode }})
                            </span>
                        </InputLabel>
                        <span class="text-[10px] text-gray-400">
                            (أدخل هنا لحساب العملة الأجنبية تلقائياً)
                        </span>
                    </div>

                    <div class="relative">
                        <TextInput
                            id="localTotal"
                            ref="totalInput"
                            :model-value="localTotal"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            placeholder="0.00"
                            class="block w-full pe-20 text-2xl font-black tabular-nums transition"
                            :class="activeCalculatorInput === 'total' ? 'ring-2 ring-emerald-500/20' : ''"
                            @update:model-value="onTotalInput"
                            @focus="activeCalculatorInput = 'total'"
                        />
                        <span
                            v-if="baseCode"
                            class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm font-black text-gray-400 dark:text-slate-500"
                        >
                            {{ baseCode }}
                        </span>
                    </div>

                    <p class="text-[11px] text-gray-500 dark:text-slate-400">
                        {{ isSell ? 'المبلغ الذي ستقبضه من العميل بالليرة.' : 'المبلغ الذي ستدفعه للعميل بالليرة.' }}
                    </p>
                </div>
            </div>

            <!-- ضبط سعر الصرف -->
            <div class="mt-5 border-t border-gray-100 pt-4 dark:border-slate-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                    <InputLabel for="rate" class="!mb-0 text-xs font-bold text-gray-700 dark:text-slate-300">
                        سعر الصرف المطبق
                        <span v-if="selectedCurrency && baseCode" class="font-normal text-gray-400">
                            (لكل 1 {{ selectedCurrency.code }})
                        </span>
                    </InputLabel>

                    <!-- أزرار التعديل السريع للسعر -->
                    <div class="flex items-center gap-1">
                        <span class="text-[11px] text-gray-400">تعديل دقيق:</span>
                        <button
                            type="button"
                            @click="adjustRate(-10)"
                            class="rounded border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-bold text-gray-600 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            -10
                        </button>
                        <button
                            type="button"
                            @click="adjustRate(10)"
                            class="rounded border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-bold text-gray-600 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            +10
                        </button>
                        <button
                            v-if="rateOverridden"
                            type="button"
                            @click="suggestRate"
                            class="ms-1 rounded bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 hover:bg-amber-200 dark:bg-amber-900/60 dark:text-amber-200"
                        >
                            ↺ استعادة الرسمي
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="relative">
                        <TextInput
                            id="rate"
                            ref="rateInput"
                            v-model="form.rate"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            min="0.0000000001"
                            placeholder="0.00"
                            class="block w-full pe-20 text-xl font-bold tabular-nums"
                            :error="Boolean(form.errors.rate)"
                        />
                        <span
                            v-if="baseCode"
                            class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm font-bold text-gray-400 dark:text-slate-500"
                        >
                            {{ baseCode }}
                        </span>
                    </div>

                    <div class="flex items-center text-xs">
                        <div v-if="rateOverridden" class="rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200 w-full">
                            <span class="font-bold">سعر مُعدّل يدوياً:</span>
                            السعر الرسمي المسجل هو <span class="font-bold tabular-nums">{{ rate(officialRate) }}</span>
                        </div>
                        <div v-else-if="officialRate !== null" class="text-slate-500 dark:text-slate-400">
                            مطابق للسعر الرسمي المسجل في النظام
                            <span class="font-semibold text-gray-700 dark:text-slate-200">({{ isSell ? 'بيع' : 'شراء' }}: {{ rate(officialRate) }})</span>
                        </div>
                        <div v-else class="text-amber-700 dark:text-amber-400 font-medium">
                            لا يوجد سعر رسمي مسجل لهذه العملة — يرجى تحديد السعر يدوياً.
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.rate" class="mt-1" />
            </div>
        </section>

        <!-- ٤. بيانات العميل والملاحظات -->
        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 overflow-hidden">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 p-4 text-start sm:p-5 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition"
                :aria-expanded="showCustomerFields"
                aria-controls="customer-fields"
                @click="showCustomerFields = !showCustomerFields"
            >
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gray-100 text-xs font-black text-gray-600 dark:bg-slate-700 dark:text-slate-300">
                        4
                    </span>
                    <span class="text-sm font-bold text-gray-900 dark:text-white">بيانات العميل والملاحظات</span>
                    <span class="text-xs font-normal text-gray-400">(اختياري)</span>

                    <span
                        v-if="form.customer_name"
                        class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                    >
                        {{ form.customer_name }}
                    </span>
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-400">
                    <span>{{ showCustomerFields ? 'إخفاء' : 'إظهار' }}</span>
                    <svg
                        class="h-4 w-4 shrink-0 transition-transform duration-200"
                        :class="showCustomerFields ? 'rotate-180' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>

            <div
                v-show="showCustomerFields"
                id="customer-fields"
                class="grid gap-4 border-t border-gray-100 p-4 sm:grid-cols-2 sm:p-5 dark:border-slate-700"
            >
                <div>
                    <InputLabel for="customer_name" value="اسم العميل" class="text-xs font-semibold" />
                    <TextInput
                        id="customer_name"
                        v-model="form.customer_name"
                        placeholder="الاسم الكامل للعميل"
                        class="mt-1 block w-full text-sm"
                        maxlength="255"
                    />
                    <InputError :message="form.errors.customer_name" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="customer_phone" value="رقم الهاتف" class="text-xs font-semibold" />
                    <TextInput
                        id="customer_phone"
                        v-model="form.customer_phone"
                        type="tel"
                        inputmode="tel"
                        dir="ltr"
                        placeholder="09xxxxxxxx"
                        class="mt-1 block w-full text-sm text-end"
                        maxlength="50"
                    />
                    <InputError :message="form.errors.customer_phone" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="notes" value="ملاحظات على العملية" class="text-xs font-semibold" />
                    <TextInput
                        id="notes"
                        v-model="form.notes"
                        placeholder="أي تفاصيل أو ملاحظات إضافية تود حفظها…"
                        class="mt-1 block w-full text-sm"
                        maxlength="1000"
                    />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- الملخص المدمج للشاشات الصغيرة -->
        <TransactionSummary :summary="summary" class="lg:hidden" />

        <InputError :message="form.errors.shift" />

        <!-- شريط الإجراءات السفلي (ثابت على الهواتف والأجهزة اللوحية) -->
        <div class="sticky bottom-0 -mx-4 border-t border-gray-200 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-lg backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:px-5 sm:pb-4 dark:border-slate-700 dark:bg-slate-800/95">
            <!-- شريط معلومات مصغر للإبهام على الشاشات الصغيرة -->
            <div class="mb-2.5 flex items-center justify-between gap-3 text-sm lg:hidden">
                <span class="text-xs font-bold text-gray-500 dark:text-slate-400">
                    {{ isSell ? 'المقبوض من العميل:' : 'المدفوع للعميل:' }}
                </span>
                <span
                    class="text-base tabular-nums"
                    :class="total > 0
                        ? (insufficient ? 'font-black text-rose-600 dark:text-rose-400' : 'font-black text-gray-900 dark:text-white')
                        : 'text-gray-300 dark:text-slate-600'"
                >
                    {{ total > 0 ? number(total) : '0.00' }}
                    <span class="text-xs font-bold text-gray-500 dark:text-slate-400">{{ baseCode }}</span>
                </span>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-gray-300 px-4 py-3.5 text-sm font-bold text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                    :disabled="!isDirty || form.processing"
                    @click="clearEntry"
                >
                    مسح (Esc)
                </button>

                <button
                    v-if="insufficient"
                    type="button"
                    @click="askDeposit"
                    class="group relative flex flex-1 items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 py-3.5 text-base font-black text-white shadow-md shadow-amber-600/20 transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                >
                    <span>⚠️ الرصيد في الصندوق لا يكفي — هل ترغب في تسجيل إيداع؟</span>
                    <span class="hidden rounded bg-black/15 px-1.5 py-0.5 text-xs font-mono font-normal opacity-80 sm:inline">
                        Enter ↵
                    </span>
                </button>

                <button
                    v-else
                    type="submit"
                    class="group relative flex flex-1 items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-base font-black text-white shadow-md transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"
                    :class="isSell
                        ? 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500 shadow-rose-600/20'
                        : 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 shadow-emerald-600/20'"
                    :disabled="!canSubmit"
                >
                    <svg v-if="form.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>

                    <span>{{ form.processing ? 'جارٍ الحفظ…' : isSell ? 'تسجيل عملية البيع' : 'تسجيل عملية الشراء' }}</span>

                    <span class="hidden rounded bg-black/15 px-1.5 py-0.5 text-xs font-mono font-normal opacity-80 sm:inline">
                        Enter ↵
                    </span>
                </button>
            </div>

            <!-- رسالة مانع التسجيل إن وجد -->
            <div
                v-if="blocker && insufficient"
                class="mt-2 flex flex-wrap items-center justify-center gap-1.5 text-xs font-bold text-rose-600 dark:text-rose-400"
                aria-live="polite"
            >
                <span>الرصيد في الصندوق غير كافٍ لإتمام العملية (العجز: {{ number(deficit) }} {{ fundingSide.code }}).</span>
                <button
                    type="button"
                    @click="askDeposit"
                    class="text-indigo-600 underline hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    هل ترغب في تسجيل إيداع لتغطية النقص؟
                </button>
            </div>
            <p
                v-else
                class="mt-2 text-center text-xs"
                :class="[
                    blocker ? 'text-gray-400 dark:text-slate-500' : 'hidden sm:block text-gray-400 dark:text-slate-500',
                ]"
                aria-live="polite"
            >
                {{ blocker ?? 'يمكنك الضغط على Enter من أي حقل للحفظ مباشرة.' }}
            </p>
        </div>
    </form>
</template>
