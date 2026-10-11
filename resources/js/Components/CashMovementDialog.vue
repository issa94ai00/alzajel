<script setup>
import Modal from '@/Components/Modal.vue';
import { useFormat } from '@/composables/useFormat';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * تسجيل إيداع أو سحب نقدي على درج الوردية.
 *
 * الفكرة الأساسية في هذه الشاشة: المستخدم يرى **الرصيد بعد الحركة** قبل أن
 * يضغط حفظ. الصرّاف يعرف كم في درجه لا كم أودع، فعرض النتيجة يمنع الخطأ
 * بدل أن يكتشفه لاحقاً عند الجرد. ولهذا أيضاً يُمنع السحب الزائد هنا بدل
 * انتظار رسالة خطأ من الخادم.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    // [{ id, code, name, current_balance }]
    currencies: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['submit', 'cancel']);

const { number } = useFormat();

const form = ref({ type: 'deposit', currency_id: '', amount: '', notes: '' });
const amountInput = ref(null);

watch(
    () => props.show,
    (open) => {
        if (!open) return;

        form.value = {
            type: 'deposit',
            currency_id: props.currencies[0]?.id ?? '',
            amount: '',
            notes: '',
        };

        nextTick(() => amountInput.value?.focus());
    },
);

const isDeposit = computed(() => form.value.type === 'deposit');

const selected = computed(
    () => props.currencies.find((c) => c.id === form.value.currency_id) ?? null,
);

const currentBalance = computed(() => Number(selected.value?.current_balance ?? 0));

const amountNumber = computed(() => {
    const n = Number(form.value.amount);

    return Number.isFinite(n) && n > 0 ? n : 0;
});

const balanceAfter = computed(
    () => currentBalance.value + (isDeposit.value ? amountNumber.value : -amountNumber.value),
);

// السحب الزائد يُمسك هنا: الخادم يرفضه أيضاً، لكن منعه قبل الإرسال أوضح.
const overdrawn = computed(() => !isDeposit.value && balanceAfter.value < 0);

const canSubmit = computed(
    () => !props.processing && form.value.currency_id !== '' && amountNumber.value > 0 && !overdrawn.value,
);

function submit() {
    if (!canSubmit.value) return;

    emit('submit', { ...form.value });
}

/** يملأ الحقل بكامل الرصيد — الحالة الشائعة: تفريغ الدرج إلى الخزنة. */
function fillAll() {
    form.value.amount = String(currentBalance.value);
}
</script>

<template>
    <Modal :show="show" max-width="lg" :closeable="!processing" @close="emit('cancel')">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">حركة نقدية على الوردية</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">
                إدخال مال إلى الدرج أو إخراجه منه خارج عمليات الصرافة — يُسجَّل باسمك في سجل التدقيق.
            </p>

            <!-- النوع: زرّان كبيران بدل قائمة، فالاختيار بين اثنين لا يحتاج فتح قائمة. -->
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    :disabled="processing"
                    @click="form.type = 'deposit'"
                    class="rounded-lg border-2 px-4 py-3 text-sm font-semibold transition disabled:opacity-50"
                    :class="isDeposit
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/25 dark:text-emerald-300'
                        : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'"
                >
                    ↓ إيداع
                    <span class="mt-0.5 block text-xs font-normal opacity-80">مال يدخل الدرج</span>
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    @click="form.type = 'withdrawal'"
                    class="rounded-lg border-2 px-4 py-3 text-sm font-semibold transition disabled:opacity-50"
                    :class="!isDeposit
                        ? 'border-rose-500 bg-rose-50 text-rose-700 dark:bg-rose-900/25 dark:text-rose-300'
                        : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'"
                >
                    ↑ سحب
                    <span class="mt-0.5 block text-xs font-normal opacity-80">مال يخرج من الدرج</span>
                </button>
            </div>

            <div class="mt-4">
                <label for="movement-currency" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">العملة</label>
                <select
                    id="movement-currency"
                    v-model="form.currency_id"
                    :disabled="processing"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                >
                    <option v-for="c in currencies" :key="c.id" :value="c.id">
                        {{ c.code }} — {{ c.name }} (الرصيد: {{ number(c.current_balance) }})
                    </option>
                </select>
                <p v-if="errors.currency_id" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ errors.currency_id }}</p>
            </div>

            <div class="mt-4">
                <div class="flex items-baseline justify-between">
                    <label for="movement-amount" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">المبلغ</label>
                    <button
                        v-if="!isDeposit && currentBalance > 0"
                        type="button"
                        :disabled="processing"
                        @click="fillAll"
                        class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        كامل الرصيد
                    </button>
                </div>
                <input
                    id="movement-amount"
                    ref="amountInput"
                    v-model="form.amount"
                    type="number"
                    step="any"
                    min="0"
                    inputmode="decimal"
                    :disabled="processing"
                    placeholder="0"
                    class="mt-1 block w-full rounded-md border-gray-300 text-lg font-semibold tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                    :class="overdrawn ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : ''"
                />
                <p v-if="errors.amount" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ errors.amount }}</p>
            </div>

            <!-- لبّ الشاشة: أثر الحركة على الدرج، محسوباً لحظياً. -->
            <div
                class="mt-4 rounded-lg border p-3 text-sm"
                :class="overdrawn
                    ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-900/20'
                    : 'border-gray-200 bg-gray-50 dark:border-slate-600 dark:bg-slate-900/40'"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">الرصيد الحالي</span>
                    <span class="font-semibold tabular-nums text-gray-800 dark:text-slate-100">
                        {{ number(currentBalance) }} {{ selected?.code }}
                    </span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">{{ isDeposit ? 'يُضاف' : 'يُخصم' }}</span>
                    <span
                        class="font-semibold tabular-nums"
                        :class="isDeposit ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                    >
                        {{ isDeposit ? '+' : '−' }}{{ number(amountNumber) }}
                    </span>
                </div>
                <div class="mt-2 flex items-center justify-between gap-2 border-t border-gray-200 pt-2 dark:border-slate-600">
                    <span class="font-semibold text-gray-700 dark:text-slate-200">الرصيد بعد الحركة</span>
                    <span
                        class="text-base font-bold tabular-nums"
                        :class="overdrawn ? 'text-rose-700 dark:text-rose-400' : 'text-gray-900 dark:text-white'"
                    >
                        {{ number(balanceAfter) }} {{ selected?.code }}
                    </span>
                </div>

                <p v-if="overdrawn" class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">
                    الرصيد لا يكفي: المتاح {{ number(currentBalance) }} {{ selected?.code }} فقط.
                </p>
            </div>

            <div class="mt-4">
                <label for="movement-notes" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">
                    البيان <span class="font-normal text-gray-400">(اختياري)</span>
                </label>
                <input
                    id="movement-notes"
                    v-model="form.notes"
                    type="text"
                    :disabled="processing"
                    :placeholder="isDeposit ? 'مثال: تعزيز من الخزنة الرئيسية' : 'مثال: تسليم إلى المالك'"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500"
                />
                <p v-if="errors.notes" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ errors.notes }}</p>
            </div>

            <p v-if="errors.shift || errors.movement" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 dark:bg-rose-900/25 dark:text-rose-200">
                {{ errors.shift || errors.movement }}
            </p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('cancel')"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="rounded-md px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-50"
                    :class="isDeposit ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'"
                >
                    {{ processing ? 'جارٍ الحفظ…' : (isDeposit ? 'تسجيل الإيداع' : 'تسجيل السحب') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
