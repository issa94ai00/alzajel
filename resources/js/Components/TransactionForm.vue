<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import CurrencyPicker from '@/Components/CurrencyPicker.vue';
import OldLiraHint from '@/Components/OldLiraHint.vue';
import { useFormat } from '@/composables/useFormat';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    // العملة الأساس: الطرف المقابل لكل عملية، وبها يُحسب الإجمالي.
    baseCurrency: { type: Object, default: null },
    // currency_id => الرصيد الحالي في الوردية.
    balances: { type: Object, default: () => ({}) },
});

const { number, rate } = useFormat();

const showCustomerFields = ref(false);
const amountInput = ref(null);

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

const remaining = computed(() => fundingSide.value.available - fundingSide.value.required);

const canSubmit = computed(
    () => !form.processing && !insufficient.value && Number(form.amount) > 0 && Number(form.rate) > 0,
);

function submit() {
    form.post(route('transactions.store'), {
        onSuccess: () => {
            form.currency_tag = selectedCurrency.value?.tag ?? '';
            nextTick(() => amountInput.value?.focus());
        },
    });
}

onMounted(() => {
    suggestRate();
    amountInput.value?.focus();
});
</script>

<template>
    <form
        @submit.prevent="submit"
        class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
    >
        <NewLiraNotice compact :currencies="currencies" dismiss-key="transaction-form" />

        <!-- اتجاه العملية: الفرق بين البيع والشراء هو أكثر ما يُخطئ فيه، لذا يبقى أول قرار وأوضحه. -->
        <div class="grid grid-cols-2 gap-2">
            <button
                type="button"
                :aria-pressed="isSell"
                @click="form.type = 'sell'"
                class="rounded-md border px-4 py-3 text-center transition"
                :class="isSell ? 'border-rose-600 bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : 'border-gray-200 text-gray-500 hover:border-gray-300 dark:border-slate-600 dark:text-slate-400'"
            >
                <span class="block font-semibold">بيع</span>
                <span class="block text-xs opacity-80">تُسلّم العملة للعميل</span>
            </button>
            <button
                type="button"
                :aria-pressed="!isSell"
                @click="form.type = 'buy'"
                class="rounded-md border px-4 py-3 text-center transition"
                :class="!isSell ? 'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'border-gray-200 text-gray-500 hover:border-gray-300 dark:border-slate-600 dark:text-slate-400'"
            >
                <span class="block font-semibold">شراء</span>
                <span class="block text-xs opacity-80">تستلم العملة من العميل</span>
            </button>
        </div>

        <div>
            <CurrencyPicker
                v-model="form.currency_id"
                :currencies="currencies"
                :balances="balances"
                :type="form.type"
            />
            <InputError :message="form.errors.currency_id" class="mt-1" />
        </div>

        <!-- فئة الورقة النقدية: تُملأ تلقائياً من العملة المختارة، ويعدّلها
             الصرّاف إن سلّم فئة أخرى، وتُطبع على الإيصال كما أُدخلت. -->
        <div>
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

        <div class="grid grid-cols-2 gap-4">
            <div>
                <InputLabel for="amount">
                    المبلغ
                    <span v-if="selectedCurrency" class="font-normal text-gray-400">({{ selectedCurrency.code }})</span>
                </InputLabel>
                <TextInput
                    id="amount"
                    ref="amountInput"
                    v-model="form.amount"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0.01"
                    class="mt-1 block w-full text-lg"
                    autofocus
                />
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
                        ({{ baseCode }} لكل 1 {{ selectedCurrency.code }})
                    </span>
                </InputLabel>
                <TextInput
                    id="rate"
                    v-model="form.rate"
                    type="number"
                    inputmode="decimal"
                    step="any"
                    min="0.0000000001"
                    class="mt-1 block w-full text-lg"
                />
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

        <!-- الإجمالي بالعملة الأساس، وباتجاه صريح: أيدفع الصرّاف أم يستلم؟ -->
        <div
            class="rounded-md p-4 text-center"
            :class="insufficient ? 'bg-rose-50 dark:bg-rose-900/25' : 'bg-indigo-50 dark:bg-indigo-900/30'"
        >
            <span class="text-sm text-gray-500 dark:text-slate-400">
                {{ isSell ? 'المستلم من العميل' : 'المدفوع للعميل' }}
            </span>
            <div
                class="text-2xl font-bold"
                :class="insufficient ? 'text-rose-700 dark:text-rose-300' : 'text-indigo-700 dark:text-indigo-300'"
            >
                {{ number(total) }}
                <span class="text-base font-normal opacity-70">{{ baseCode }}</span>
            </div>

            <p v-if="selectedCurrency && Number(form.amount) > 0" class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                {{ isSell ? 'تُسلّم' : 'تستلم' }} {{ number(form.amount) }} {{ selectedCurrency.code }}
                ·
                {{ isSell ? 'تستلم' : 'تدفع' }} {{ number(total) }} {{ baseCode }}
            </p>
        </div>

        <!-- كفاية الرصيد: الخادم يرفض العملية عند النقص، فنُظهره قبل الإرسال. -->
        <div
            v-if="fundingSide.code"
            class="rounded-md border p-3 text-xs"
            :class="insufficient
                ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/25 dark:text-rose-200'
                : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-slate-600 dark:bg-slate-700/40 dark:text-slate-300'"
            :role="insufficient ? 'alert' : null"
        >
            <div class="flex items-center justify-between gap-2">
                <span>الرصيد المتاح من {{ fundingSide.code }}</span>
                <span class="font-semibold">{{ number(fundingSide.available) }}</span>
            </div>
            <div v-if="fundingSide.required > 0" class="mt-1 flex items-center justify-between gap-2">
                <span>{{ insufficient ? 'النقص' : 'المتبقي بعد العملية' }}</span>
                <span class="font-semibold">{{ number(Math.abs(remaining)) }}</span>
            </div>
            <p v-if="insufficient" class="mt-1.5 font-semibold">
                الرصيد غير كافٍ لإتمام هذه العملية.
            </p>
        </div>

        <button
            type="button"
            @click="showCustomerFields = !showCustomerFields"
            class="text-sm text-indigo-600 hover:underline"
        >
            {{ showCustomerFields ? 'إخفاء بيانات العميل' : '+ إضافة بيانات العميل (اختياري)' }}
        </button>

        <div v-if="showCustomerFields" class="space-y-4">
            <div>
                <InputLabel for="customer_name" value="اسم العميل" />
                <TextInput id="customer_name" v-model="form.customer_name" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="customer_phone" value="رقم الهاتف" />
                <TextInput id="customer_phone" v-model="form.customer_phone" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="notes" value="ملاحظات" />
                <TextInput id="notes" v-model="form.notes" class="mt-1 block w-full" />
            </div>
        </div>

        <InputError :message="form.errors.shift" />

        <PrimaryButton :disabled="!canSubmit" class="w-full justify-center py-3 text-base">
            {{ form.processing ? 'جارٍ الحفظ…' : 'تسجيل العملية' }}
        </PrimaryButton>
    </form>
</template>
