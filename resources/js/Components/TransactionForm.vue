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
    // العملة الأساس: الطرف المقابل لكل عملية، وبها يُحسب الإجمالي.
    baseCurrency: { type: Object, default: null },
    // currency_id => الرصيد الحالي في الوردية.
    balances: { type: Object, default: () => ({}) },
});

// الصفحة الأم تعرض الملخص في عمود جانبي على الشاشات الكبيرة.
const emit = defineEmits(['update:summary']);

const { number, rate } = useFormat();

const showCustomerFields = ref(false);
const amountInput = ref(null);
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

const isSell = computed(() => form.type === 'sell');

const selectedCurrency = computed(() => props.currencies.find((c) => c.id === Number(form.currency_id)));

const baseCode = computed(() => props.baseCurrency?.code ?? '');

const total = computed(() => {
    const amount = Number(form.amount);
    const rate = Number(form.rate);

    if (!amount || !rate) return 0;

    return amount * rate;
});

/* ------------------------------- سعر الصرف ------------------------------- */

/** السعر الرسمي المسجّل للعملة حسب اتجاه العملية. */
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

watch(() => form.type, suggestRate);
watch(() => form.currency_id, () => {
    suggestRate();
    // فئة العملة السابقة لا معنى لها بعد تبديل العملة، فتُستبدل بفئة الجديدة.
    form.currency_tag = selectedCurrency.value?.tag ?? '';
});

/* ------------------------------ اتجاه العملية ----------------------------- */

/** اختيار الاتجاه بالنقر: الخطوة التالية دائماً كتابة المبلغ، فننقل التركيز إليه. */
function chooseType(type) {
    form.type = type;
    focusAmount();
}

/**
 * الزرّان مجموعة اختيار واحدة، فتتنقّل الأسهم بينهما كما في أزرار الراديو
 * الأصلية، ويبقى التركيز على الخيار المحدَّد لا على المبلغ.
 */
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

/**
 * البيع يخصم من رصيد العملة المتداولة، والشراء يخصم من رصيد العملة الأساس.
 * نعرض الطرف الذي سيُخصم منه فعلاً حتى لا يفاجأ الصرّاف برفض بعد الحفظ.
 */
const fundingSide = computed(() => {
    // بلا عملة أساس لا يمكن حساب الطرف المقابل — نترك التحقق للخادم
    // بدل تعطيل الزر بصمت.
    if (!props.baseCurrency) {
        return { code: '', available: 0, required: 0 };
    }

    if (isSell.value) {
        return {
            code: selectedCurrency.value?.code ?? '',
            available: balanceOf(form.currency_id),
            required: Number(form.amount) || 0,
        };
    }

    return {
        code: baseCode.value,
        available: balanceOf(props.baseCurrency?.id),
        required: total.value,
    };
});

const insufficient = computed(
    () => fundingSide.value.required > 0 && fundingSide.value.required > fundingSide.value.available,
);

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

/**
 * زر معطّل بلا سبب ظاهر يترك الصرّاف يخمّن؛ نقول له ما الناقص تحديداً.
 */
const blocker = computed(() => {
    if (!selectedCurrency.value) return 'اختر العملة أولاً.';
    if (!(Number(form.amount) > 0)) return 'أدخل المبلغ للمتابعة.';
    if (!(Number(form.rate) > 0)) return 'أدخل سعر الصرف للمتابعة.';
    if (insufficient.value) return 'الرصيد غير كافٍ لإتمام هذه العملية.';

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
    nextTick(() => amountInput.value?.focus());
}

/** يمسح ما يخصّ هذه العملية ويُبقي الاتجاه والعملة للعملية التالية. */
function clearEntry() {
    form.reset('amount', 'customer_name', 'customer_phone', 'notes');
    form.clearErrors();
    form.currency_tag = selectedCurrency.value?.tag ?? '';
    suggestRate();
    focusAmount();
}

/** ترتيب الحقول كما تظهر على الشاشة — به نختار أول خطأ يُقفَز إليه. */
const FIELD_ORDER = ['type', 'currency_id', 'currency_tag', 'amount', 'rate', 'customer_name', 'customer_phone', 'notes'];

const CUSTOMER_FIELDS = ['customer_name', 'customer_phone', 'notes'];

/**
 * رفض الخادم يأتي بعد أن يكون الصرّاف قد ترك الحقل؛ وعلى الهاتف قد يكون
 * الحقل خارج الشاشة أصلاً. نفتح القسم المطوي إن لزم، ثم نقفز إلى الحقل.
 */
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
    if (!canSubmit.value) return;

    form.post(route('transactions.store'), {
        // الصرّاف يسجّل عمليات متتالية بالاتجاه والعملة نفسيهما غالباً، فنُبقي
        // النموذج حيّاً ونمسح ما يخصّ العملية المنتهية فقط.
        preserveState: true,
        preserveScroll: true,
        onSuccess: clearEntry,
        onError: focusFirstError,
    });
}

/*
 * نافذة طباعة الإيصال تأخذ التركيز بعد الحفظ؛ وحين تُغلق يجب أن يعود إلى
 * حقل المبلغ كي يبدأ الصرّاف العملية التالية دون نقرة إضافية.
 */
onMounted(() => {
    suggestRate();
    focusAmount();
    window.addEventListener('receipt-prompt:closed', focusAmount);
});

onUnmounted(() => {
    window.removeEventListener('receipt-prompt:closed', focusAmount);
});
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <NewLiraNotice compact :currencies="currencies" dismiss-key="transaction-form" />

        <!-- ١. اتجاه العملية: الفرق بين البيع والشراء هو أكثر ما يُخطئ فيه، لذا يبقى أول قرار وأوضحه. -->
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
            <div class="mb-3 flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">1</span>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">اتجاه العملية</h3>
            </div>

            <div
                id="type"
                ref="typeGroup"
                class="grid grid-cols-2 gap-3"
                role="radiogroup"
                aria-label="اتجاه العملية"
                @keydown="onTypeKeydown"
            >
                <button
                    type="button"
                    role="radio"
                    :aria-checked="isSell"
                    :tabindex="isSell ? 0 : -1"
                    @click="chooseType('sell')"
                    class="flex flex-col items-center gap-2 rounded-lg border-2 px-3 py-3 text-center transition focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1 dark:focus:ring-offset-slate-800 sm:flex-row sm:gap-3 sm:px-4 sm:text-start"
                    :class="isSell
                        ? 'border-rose-600 bg-rose-50 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200'
                        : 'border-gray-200 text-gray-500 hover:border-rose-300 hover:bg-rose-50/40 dark:border-slate-600 dark:text-slate-400 dark:hover:border-rose-500/60'"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                        :class="isSell ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-400 dark:bg-slate-700 dark:text-slate-500'"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-base font-bold">بيع</span>
                        <span class="block text-[11px] leading-tight opacity-80 sm:text-xs">تُسلّم العملة للعميل</span>
                    </span>
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="!isSell"
                    :tabindex="isSell ? -1 : 0"
                    @click="chooseType('buy')"
                    class="flex flex-col items-center gap-2 rounded-lg border-2 px-3 py-3 text-center transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:focus:ring-offset-slate-800 sm:flex-row sm:gap-3 sm:px-4 sm:text-start"
                    :class="!isSell
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200'
                        : 'border-gray-200 text-gray-500 hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-600 dark:text-slate-400 dark:hover:border-emerald-500/60'"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                        :class="!isSell ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-400 dark:bg-slate-700 dark:text-slate-500'"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l6-6m-6 6l-6-6" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-base font-bold">شراء</span>
                        <span class="block text-[11px] leading-tight opacity-80 sm:text-xs">تستلم العملة من العميل</span>
                    </span>
                </button>
            </div>
            <InputError :message="form.errors.type" class="mt-2" />
        </section>

        <!-- ٢. العملة وفئتها -->
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
            <div class="mb-3 flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">2</span>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">العملة</h3>
            </div>

            <CurrencyPicker
                id="currency_id"
                v-model="form.currency_id"
                :currencies="currencies"
                :balances="balances"
                :type="form.type"
                @update:model-value="focusAmount"
            />
            <InputError :message="form.errors.currency_id" class="mt-1" />

            <!-- فئة الورقة النقدية: تُملأ تلقائياً من العملة المختارة، ويعدّلها
                 الصرّاف إن سلّم فئة أخرى، وتُطبع على الإيصال كما أُدخلت. -->
            <div class="mt-4 sm:w-1/2">
                <InputLabel for="currency_tag">
                    الفئة
                    <span class="font-normal text-gray-400">(اختياري)</span>
                </InputLabel>
                <TextInput
                    id="currency_tag"
                    v-model="form.currency_tag"
                    class="mt-1 block w-full"
                    maxlength="50"
                    :placeholder="selectedCurrency?.tag || 'مثال: USD-100'"
                />
                <InputError :message="form.errors.currency_tag" class="mt-1" />
            </div>
        </section>

        <!-- ٣. المبلغ والسعر -->
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
            <div class="mb-3 flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">3</span>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">المبلغ والسعر</h3>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="amount" value="المبلغ" />
                    <div class="relative mt-1">
                        <TextInput
                            id="amount"
                            ref="amountInput"
                            v-model="form.amount"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="block w-full pe-16 text-xl font-semibold tabular-nums"
                            :error="Boolean(form.errors.amount)"
                            autofocus
                        />
                        <span
                            v-if="selectedCurrency"
                            class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm font-bold text-gray-400 dark:text-slate-500"
                        >
                            {{ selectedCurrency.code }}
                        </span>
                    </div>
                    <OldLiraHint
                        :amount="form.amount"
                        :currency="selectedCurrency"
                        @correct="(value) => (form.amount = String(value))"
                    />
                    <InputError :message="form.errors.amount" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="rate">
                        السعر
                        <span v-if="selectedCurrency && baseCode" class="font-normal text-gray-400">
                            (لكل 1 {{ selectedCurrency.code }})
                        </span>
                    </InputLabel>
                    <div class="relative mt-1">
                        <TextInput
                            id="rate"
                            v-model="form.rate"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            min="0.0000000001"
                            placeholder="0.00"
                            class="block w-full pe-16 text-xl font-semibold tabular-nums"
                            :error="Boolean(form.errors.rate)"
                        />
                        <span
                            v-if="baseCode"
                            class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm font-bold text-gray-400 dark:text-slate-500"
                        >
                            {{ baseCode }}
                        </span>
                    </div>
                    <!-- تعديل السعر يدوياً مسموح، لكنه يغيّر الربح — فليكن ظاهراً لا صامتاً. -->
                    <p v-if="rateOverridden" class="mt-1 flex flex-wrap items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                        <span>سعر مُعدَّل — الرسمي {{ rate(officialRate) }}</span>
                        <button type="button" class="font-semibold underline hover:no-underline" @click="suggestRate">
                            استعادة
                        </button>
                    </p>
                    <p v-else-if="officialRate !== null" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        السعر الرسمي {{ isSell ? 'للبيع' : 'للشراء' }}
                    </p>
                    <p v-else class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        لا يوجد سعر صرف مسجّل لهذه العملة — أدخل السعر يدوياً.
                    </p>
                    <InputError :message="form.errors.rate" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- ٤. بيانات العميل: اختيارية ومطوية كي لا تبطئ العملية المعتادة. -->
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 p-4 text-start sm:p-5"
                :aria-expanded="showCustomerFields"
                aria-controls="customer-fields"
                @click="showCustomerFields = !showCustomerFields"
            >
                <span class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-600 dark:bg-slate-600 dark:text-slate-200">4</span>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">بيانات العميل</span>
                    <span class="text-xs font-normal text-gray-400">(اختياري)</span>
                    <span
                        v-if="!showCustomerFields && form.customer_name"
                        class="truncate rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                    >
                        {{ form.customer_name }}
                    </span>
                </span>
                <svg
                    class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                    :class="showCustomerFields ? 'rotate-180' : ''"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showCustomerFields" id="customer-fields" class="grid gap-4 border-t border-gray-100 p-4 sm:grid-cols-2 sm:p-5 dark:border-slate-700">
                <div>
                    <InputLabel for="customer_name" value="اسم العميل" />
                    <TextInput id="customer_name" v-model="form.customer_name" class="mt-1 block w-full" maxlength="255" />
                    <InputError :message="form.errors.customer_name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="customer_phone" value="رقم الهاتف" />
                    <TextInput id="customer_phone" v-model="form.customer_phone" type="tel" inputmode="tel" dir="ltr" class="mt-1 block w-full text-end" maxlength="50" />
                    <InputError :message="form.errors.customer_phone" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel for="notes" value="ملاحظات" />
                    <TextInput id="notes" v-model="form.notes" class="mt-1 block w-full" maxlength="1000" />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- الملخص داخل النموذج للشاشات الصغيرة؛ على الكبيرة يظهر في العمود الجانبي. -->
        <TransactionSummary :summary="summary" class="lg:hidden" />

        <InputError :message="form.errors.shift" />

        <!-- شريط الإجراءات: ثابت أسفل الشاشة على الهاتف كي لا يُبحث عن الزر بعد التمرير. -->
        <div class="sticky bottom-0 -mx-4 border-t border-gray-200 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:px-5 sm:pb-3 dark:border-slate-700 dark:bg-slate-800/95">
            <!-- على الهاتف تبتعد بطاقة الملخص عن الزر، فيبقى الإجمالي هنا أمام الإبهام. -->
            <div class="mb-2 flex items-center justify-between gap-3 text-sm lg:hidden">
                <span class="text-gray-500 dark:text-slate-400">
                    {{ isSell ? 'تستلم من العميل' : 'تدفع للعميل' }}
                </span>
                <span
                    class="tabular-nums"
                    :class="total > 0
                        ? (insufficient ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-bold text-gray-900 dark:text-white')
                        : 'text-gray-300 dark:text-slate-600'"
                >
                    {{ total > 0 ? number(total) : '—' }}
                    <span class="text-xs font-semibold text-gray-400 dark:text-slate-500">{{ baseCode }}</span>
                </span>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                    :disabled="!isDirty || form.processing"
                    @click="clearEntry"
                >
                    مسح
                </button>

                <button
                    type="submit"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-3 text-base font-bold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"
                    :class="isSell
                        ? 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500'
                        : 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500'"
                    :disabled="!canSubmit"
                >
                    <svg v-if="form.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ form.processing ? 'جارٍ الحفظ…' : isSell ? 'تسجيل عملية البيع' : 'تسجيل عملية الشراء' }}
                </button>
            </div>

            <!-- تلميح Enter لا معنى له على لوحة مفاتيح الهاتف، ويُخفى كلياً على الشاشات القصيرة (الهاتف بالعرض) توفيراً للمساحة. -->
            <p
                class="mt-2 text-center text-xs [@media(max-height:520px)]:hidden"
                :class="[
                    blocker && insufficient ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 dark:text-slate-500',
                    blocker ? '' : 'hidden sm:block',
                ]"
                aria-live="polite"
            >
                {{ blocker ?? 'اضغط Enter للتسجيل مباشرةً من حقل المبلغ أو السعر.' }}
            </p>
        </div>
    </form>
</template>
