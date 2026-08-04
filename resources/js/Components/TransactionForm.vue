<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
});

const showCustomerFields = ref(false);
const amountInput = ref(null);

const form = useForm({
    type: 'sell',
    currency_id: props.currencies[0]?.id ?? null,
    amount: '',
    rate: '',
    customer_name: '',
    customer_phone: '',
    notes: '',
});

const selectedCurrency = computed(() => props.currencies.find((c) => c.id === Number(form.currency_id)));

const total = computed(() => {
    const amount = Number(form.amount);
    const rate = Number(form.rate);
    if (!amount || !rate) return 0;
    return amount * rate;
});

function suggestRate() {
    const currency = selectedCurrency.value;
    if (!currency?.exchange_rate) {
        form.rate = '';
        return;
    }
    form.rate = form.type === 'buy' ? currency.exchange_rate.buy_rate : currency.exchange_rate.sell_rate;
}

watch(() => form.type, suggestRate);
watch(() => form.currency_id, suggestRate);

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(value);
}

function submit() {
    form.post(route('transactions.store'), {
        onSuccess: () => {
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
    <form @submit.prevent="submit" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="grid grid-cols-2 gap-2">
            <button
                type="button"
                @click="form.type = 'sell'"
                class="rounded-md border px-4 py-3 text-center font-semibold transition"
                :class="form.type === 'sell' ? 'border-rose-600 bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : 'border-gray-200 text-gray-500 dark:border-slate-600 dark:text-slate-400'"
            >
                بيع (تسليم عملة للعميل)
            </button>
            <button
                type="button"
                @click="form.type = 'buy'"
                class="rounded-md border px-4 py-3 text-center font-semibold transition"
                :class="form.type === 'buy' ? 'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'border-gray-200 text-gray-500 dark:border-slate-600 dark:text-slate-400'"
            >
                شراء (استلام عملة من العميل)
            </button>
        </div>

        <div>
            <InputLabel for="currency_id" value="العملة" />
            <select
                id="currency_id"
                v-model="form.currency_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            >
                <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
            </select>
            <InputError :message="form.errors.currency_id" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <InputLabel for="amount" value="المبلغ" />
                <TextInput
                    id="amount"
                    ref="amountInput"
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    class="mt-1 block w-full text-lg"
                    autofocus
                />
                <InputError :message="form.errors.amount" class="mt-1" />
            </div>
            <div>
                <InputLabel for="rate" value="السعر" />
                <TextInput
                    id="rate"
                    v-model="form.rate"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    class="mt-1 block w-full text-lg"
                />
                <InputError :message="form.errors.rate" class="mt-1" />
            </div>
        </div>

        <div class="rounded-md bg-indigo-50 p-4 text-center dark:bg-indigo-900/30">
            <span class="text-sm text-gray-500 dark:text-slate-400">الإجمالي</span>
            <div class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ formatNumber(total) }}</div>
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

        <PrimaryButton :disabled="form.processing" class="w-full justify-center py-3 text-base">
            تسجيل العملية
        </PrimaryButton>
    </form>
</template>
