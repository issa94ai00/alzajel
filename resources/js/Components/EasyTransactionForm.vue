<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { useFormat } from '@/composables/useFormat';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    baseCurrency: { type: Object, default: null },
    balances: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['saved', 'request-deposit']);

const { number, rate } = useFormat();

const amountInput = ref(null);
const rateInput = ref(null);
const showExtraFields = ref(false);

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

/* --------------------------- الحسابات الأساسية --------------------------- */
const isSell = computed(() => form.type === 'sell');
const selectedCurrency = computed(() => props.currencies.find((c) => c.id === Number(form.currency_id)));
const baseCode = computed(() => props.baseCurrency?.code ?? '');

const total = computed(() => {
    const amount = Number(form.amount);
    const r = Number(form.rate);
    if (!amount || !r) return 0;
    return amount * r;
});

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
}

function adjustRate(delta) {
    const current = Number(form.rate) || officialRate.value || 0;
    const updated = Math.max(0, current + delta);
    form.rate = String(+updated.toFixed(4));
}

watch(() => form.type, suggestRate);
watch(
    () => form.currency_id,
    () => {
        suggestRate();
        form.currency_tag = selectedCurrency.value?.tag ?? '';
    },
);

/* ------------------------------ اختيار العملة ----------------------------- */
function selectCurrency(id) {
    form.currency_id = id;
    focusAmount();
}

/* ------------------------------ كفاية الرصيد ----------------------------- */
function balanceOf(currencyId) {
    const value = Number(props.balances?.[currencyId]);
    return Number.isFinite(value) ? value : 0;
}

const foreignBalance = computed(() => balanceOf(form.currency_id));
const baseBalance = computed(() => balanceOf(props.baseCurrency?.id));

const fundingSide = computed(() => {
    if (!props.baseCurrency) {
        return { code: '', currency_id: null, available: 0, required: 0 };
    }

    if (isSell.value) {
        return {
            code: selectedCurrency.value?.code ?? '',
            currency_id: Number(form.currency_id),
            available: foreignBalance.value,
            required: Number(form.amount) || 0,
        };
    }

    return {
        code: baseCode.value,
        currency_id: props.baseCurrency?.id,
        available: baseBalance.value,
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

/* ------------------------------ التحقق والإرسال ----------------------------- */
const blocker = computed(() => {
    if (!selectedCurrency.value) return 'اختر العملة أولاً.';
    if (!(Number(form.amount) > 0)) return 'أدخل المبلغ للمتابعة.';
    if (!(Number(form.rate) > 0)) return 'أدخل سعر الصرف للمتابعة.';
    if (insufficient.value) return 'الرصيد في الصندوق غير كافٍ لإتمام العملية.';
    return null;
});

const canSubmit = computed(() => !form.processing && blocker.value === null);

function focusAmount() {
    nextTick(() => amountInput.value?.focus());
}

function focusRate() {
    nextTick(() => rateInput.value?.focus());
}

function chooseType(type) {
    form.type = type;
    focusAmount();
}

function setQuickAmount(val) {
    form.amount = String(val);
    focusAmount();
}

function addAmount(delta) {
    const current = Number(form.amount) || 0;
    form.amount = String(current + delta);
    focusAmount();
}

function clearEntry() {
    form.reset('amount', 'customer_name', 'customer_phone', 'notes');
    form.clearErrors();
    form.currency_tag = selectedCurrency.value?.tag ?? '';
    suggestRate();
    focusAmount();
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
        onSuccess: () => {
            clearEntry();
            emit('saved');
        },
    });
}

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
        showExtraFields.value = true;
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
    focusRate,
    clearEntry,
});

/* ----------------------- اختصارات لوحة المفاتيح ----------------------- */
function handleKeyDown(e) {
    if (e.target.tagName === 'TEXTAREA') return;

    if ((e.altKey && (e.key === '1' || e.code === 'Digit1')) || e.key === 'F1') {
        e.preventDefault();
        chooseType('sell');
        return;
    }

    if ((e.altKey && (e.key === '2' || e.code === 'Digit2')) || e.key === 'F2') {
        e.preventDefault();
        chooseType('buy');
        return;
    }

    if (e.altKey && (e.key === 'a' || e.key === 'A' || e.code === 'KeyA')) {
        e.preventDefault();
        focusAmount();
        return;
    }

    if (e.altKey && (e.key === 'r' || e.key === 'R' || e.code === 'KeyR')) {
        e.preventDefault();
        focusRate();
        return;
    }

    if (e.key === 'Escape' && !e.defaultPrevented) {
        clearEntry();
        return;
    }

    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        submit();
    }
}

onMounted(() => {
    suggestRate();
    focusAmount();
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

const quickAmounts = [100, 200, 500, 1000];
</script>

<template>
    <form @submit.prevent="submit" class="space-y-3">
        <!-- الحاوية المدمجة الرئيسية: كل شيء معروض بوضوح بدون تمرير -->
        <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm sm:p-4.5 dark:border-slate-700 dark:bg-slate-800">
            <!-- ١. شريط اتجاه العملية (بيع / شراء) -->
            <div class="grid grid-cols-2 gap-2.5">
                <button
                    type="button"
                    @click="chooseType('sell')"
                    class="group relative flex items-center justify-between rounded-xl border-2 px-3.5 py-2.5 text-start transition focus:outline-none focus:ring-2 focus:ring-rose-500"
                    :class="isSell
                        ? 'border-rose-600 bg-rose-50 text-rose-950 shadow-sm dark:border-rose-500 dark:bg-rose-950/50 dark:text-rose-100'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-rose-200 hover:bg-rose-50/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg font-black text-white"
                            :class="isSell ? 'bg-rose-600' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                        >
                            ↗
                        </span>
                        <div>
                            <span class="block text-sm font-black" :class="isSell ? 'text-rose-900 dark:text-rose-100' : 'text-slate-800 dark:text-white'">
                                بيع
                            </span>
                            <span class="block text-[11px] leading-tight text-slate-500 dark:text-slate-400">
                                تسلّم أجنبي وتقبض ليرة
                            </span>
                        </div>
                    </div>
                    <span class="rounded bg-black/5 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-white/10 dark:text-slate-400">
                        Alt+1
                    </span>
                </button>

                <button
                    type="button"
                    @click="chooseType('buy')"
                    class="group relative flex items-center justify-between rounded-xl border-2 px-3.5 py-2.5 text-start transition focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    :class="!isSell
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-950 shadow-sm dark:border-emerald-500 dark:bg-emerald-950/50 dark:text-emerald-100'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-200 hover:bg-emerald-50/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg font-black text-white"
                            :class="!isSell ? 'bg-emerald-600' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                        >
                            ↙
                        </span>
                        <div>
                            <span class="block text-sm font-black" :class="!isSell ? 'text-emerald-900 dark:text-emerald-100' : 'text-slate-800 dark:text-white'">
                                شراء
                            </span>
                            <span class="block text-[11px] leading-tight text-slate-500 dark:text-slate-400">
                                تستلم أجنبي وتدفع ليرة
                            </span>
                        </div>
                    </div>
                    <span class="rounded bg-black/5 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-white/10 dark:text-slate-400">
                        Alt+2
                    </span>
                </button>
            </div>

            <!-- ٢. اختيار العملة ورصيد الصندوق في شريط أفقي سريع -->
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2.5 dark:border-slate-700">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400 ms-0.5">العملة:</span>
                    <button
                        v-for="curr in currencies"
                        :key="curr.id"
                        type="button"
                        @click="selectCurrency(curr.id)"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-black transition"
                        :class="curr.id === Number(form.currency_id)
                            ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm dark:border-indigo-500 dark:bg-indigo-600'
                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200'"
                    >
                        <span>{{ curr.code }}</span>
                        <span
                            class="text-[10px] font-normal opacity-85"
                            :class="curr.id === Number(form.currency_id) ? 'text-indigo-100' : 'text-slate-500 dark:text-slate-400'"
                        >
                            ({{ rate(isSell ? curr.exchange_rate?.sell_rate : curr.exchange_rate?.buy_rate) }})
                        </span>
                    </button>
                </div>

                <!-- رصيد الصندوق المتاح للعملية الحالية -->
                <div class="flex items-center gap-1 text-[11px]">
                    <span class="text-slate-400">الرصيد المتاح:</span>
                    <span
                        class="font-black tabular-nums"
                        :class="insufficient ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-200'"
                    >
                        {{ number(fundingSide.available) }} {{ fundingSide.code }}
                    </span>
                </div>
            </div>

            <!-- ٣. حقول الإدخال: المبلغ، سعر الصرف، والمقابل بالليرة -->
            <div class="mt-3 grid gap-3 sm:grid-cols-12 sm:items-start">
                <!-- حقل المبلغ بالعملة الأجنبية (5 أعمدة) -->
                <div class="sm:col-span-4">
                    <div class="flex items-center justify-between mb-1">
                        <label for="easy-amount" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            المبلغ ({{ selectedCurrency?.code ?? '' }})
                        </label>
                        <span class="text-[10px] text-slate-400">Alt+A</span>
                    </div>

                    <div class="relative">
                        <TextInput
                            id="easy-amount"
                            ref="amountInput"
                            v-model="form.amount"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="block w-full pe-14 text-xl font-black tabular-nums transition"
                            :error="Boolean(form.errors.amount)"
                            autofocus
                        />
                        <span class="pointer-events-none absolute inset-y-0 end-2.5 flex items-center text-xs font-black text-slate-400">
                            {{ selectedCurrency?.code }}
                        </span>
                    </div>

                    <!-- أزرار سريعة مصغرة للمبالغ -->
                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                        <button
                            v-for="amt in quickAmounts"
                            :key="amt"
                            type="button"
                            @click="setQuickAmount(amt)"
                            class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 transition hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-700/60 dark:text-slate-300"
                        >
                            {{ amt }}
                        </button>
                        <button
                            type="button"
                            @click="addAmount(100)"
                            class="rounded border border-dashed border-slate-300 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-500 hover:border-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
                        >
                            +100
                        </button>
                    </div>
                    <InputError :message="form.errors.amount" class="mt-1" />
                </div>

                <!-- حقل سعر الصرف (4 أعمدة) -->
                <div class="sm:col-span-4">
                    <div class="flex items-center justify-between mb-1">
                        <label for="easy-rate" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            سعر الصرف
                        </label>
                        <div class="flex items-center gap-1">
                            <button
                                v-if="rateOverridden"
                                type="button"
                                @click="suggestRate"
                                class="text-[10px] font-bold text-amber-600 hover:underline dark:text-amber-400"
                            >
                                ↺ الرسمي ({{ rate(officialRate) }})
                            </button>
                            <span v-else class="text-[10px] text-slate-400">رسمي</span>
                        </div>
                    </div>

                    <div class="relative">
                        <TextInput
                            id="easy-rate"
                            ref="rateInput"
                            v-model="form.rate"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            min="0.0000000001"
                            placeholder="0.00"
                            class="block w-full pe-12 text-xl font-bold tabular-nums"
                            :error="Boolean(form.errors.rate)"
                        />
                        <span class="pointer-events-none absolute inset-y-0 end-2 flex items-center text-[11px] font-bold text-slate-400">
                            {{ baseCode }}
                        </span>
                    </div>

                    <!-- أزرار تعديل دقيق للسعر -->
                    <div class="mt-1.5 flex items-center gap-1">
                        <span class="text-[10px] text-slate-400">تعديل:</span>
                        <button
                            type="button"
                            @click="adjustRate(-10)"
                            class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300"
                        >
                            -10
                        </button>
                        <button
                            type="button"
                            @click="adjustRate(10)"
                            class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300"
                        >
                            +10
                        </button>
                    </div>
                    <InputError :message="form.errors.rate" class="mt-1" />
                </div>

                <!-- بطاقة الإجمالي بالليرة (4 أعمدة) -->
                <div class="sm:col-span-4">
                    <div class="rounded-xl border p-2.5 text-start transition"
                        :class="insufficient
                            ? 'border-rose-300 bg-rose-50/80 dark:border-rose-800 dark:bg-rose-950/40'
                            : isSell
                                ? 'border-rose-100 bg-rose-50/50 dark:border-rose-900/30 dark:bg-rose-950/20'
                                : 'border-emerald-100 bg-emerald-50/50 dark:border-emerald-900/30 dark:bg-emerald-950/20'"
                    >
                        <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400">
                            {{ isSell ? 'المقبوض من العميل:' : 'المدفوع للعميل:' }}
                        </span>

                        <div class="mt-0.5 flex items-baseline gap-1">
                            <span
                                class="text-2xl font-black tabular-nums tracking-tight"
                                :class="total > 0
                                    ? (insufficient ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white')
                                    : 'text-slate-300 dark:text-slate-600'"
                            >
                                {{ total > 0 ? number(total) : '0.00' }}
                            </span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                                {{ baseCode }}
                            </span>
                        </div>

                        <!-- إشعار كفاية الرصيد مع زر السؤال عن الإيداع -->
                        <div class="mt-1 text-[11px] font-bold">
                            <div v-if="insufficient" class="flex flex-wrap items-center justify-between gap-1">
                                <span class="text-rose-600 dark:text-rose-400">
                                    ⚠️ عجز: {{ number(deficit) }} {{ fundingSide.code }}
                                </span>
                                <button
                                    type="button"
                                    @click="askDeposit"
                                    class="inline-flex items-center gap-1 rounded bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white shadow-sm transition hover:bg-rose-700"
                                    title="تسجيل إيداع لتغطية العجز"
                                >
                                    ➕ إيداع لتغطية العجز؟
                                </button>
                            </div>
                            <span v-else-if="total > 0" class="text-emerald-700 dark:text-emerald-400">
                                ✓ رصيد الصندوق كافٍ
                            </span>
                            <span v-else class="text-slate-400 font-normal">
                                بانتظار إدخال المبلغ
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ٤. تفاصيل اختيارية مدمجة (لا تأخذ مساحة إلا عند الرغبة) -->
            <div class="mt-2.5 border-t border-slate-100 pt-2 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        @click="showExtraFields = !showExtraFields"
                        class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        <span>{{ showExtraFields ? '− إخفاء البيانات الإضافية' : '+ إضافة اسم العميل أو ملاحظات' }}</span>
                        <span v-if="form.customer_name" class="rounded bg-indigo-50 px-1 py-0.2 text-[10px] text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                            ({{ form.customer_name }})
                        </span>
                    </button>

                    <div v-if="form.currency_tag" class="text-[10px] text-slate-400">
                        فئة: <span class="font-bold">{{ form.currency_tag }}</span>
                    </div>
                </div>

                <div v-if="showExtraFields" class="mt-2 grid gap-2 sm:grid-cols-3">
                    <div>
                        <input
                            v-model="form.customer_name"
                            type="text"
                            placeholder="اسم العميل (اختياري)"
                            class="w-full rounded-lg border-slate-200 px-2.5 py-1 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <input
                            v-model="form.currency_tag"
                            type="text"
                            placeholder="فئة الورقة (مثال: USD-100)"
                            class="w-full rounded-lg border-slate-200 px-2.5 py-1 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <input
                            v-model="form.notes"
                            type="text"
                            placeholder="ملاحظة سريعة..."
                            class="w-full rounded-lg border-slate-200 px-2.5 py-1 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>
                </div>
            </div>

            <!-- ٥. أزرار التنفيذ السريعة (Enter / Esc) -->
            <div class="mt-3.5 flex items-center gap-2.5">
                <button
                    type="button"
                    @click="clearEntry"
                    class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    مسح (Esc)
                </button>

                <button
                    v-if="insufficient"
                    type="button"
                    @click="askDeposit"
                    class="group relative flex flex-1 items-center justify-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-black text-white shadow-md shadow-amber-600/20 transition hover:bg-amber-700"
                >
                    <span>⚠️ الرصيد لا يكفي — هل ترغب في تسجيل إيداع؟</span>
                    <span class="rounded bg-black/15 px-1.5 py-0.2 text-[10px] font-mono opacity-85">
                        Enter ↵
                    </span>
                </button>

                <button
                    v-else
                    type="submit"
                    :disabled="!canSubmit"
                    class="group relative flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black text-white shadow-md transition disabled:cursor-not-allowed disabled:opacity-50"
                    :class="isSell
                        ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/20'
                        : 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/20'"
                >
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>

                    <span>
                        {{ form.processing ? 'جارٍ الحفظ…' : isSell ? 'إتمام عملية البيع' : 'إتمام عملية الشراء' }}
                    </span>

                    <span class="rounded bg-black/15 px-1.5 py-0.2 text-[10px] font-mono opacity-85">
                        Enter ↵
                    </span>
                </button>
            </div>

            <p v-if="blocker && !insufficient" class="mt-1.5 text-center text-[11px] text-slate-400">
                {{ blocker }}
            </p>
        </div>
    </form>
</template>
