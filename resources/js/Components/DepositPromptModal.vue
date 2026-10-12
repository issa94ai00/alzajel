<script setup>
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useFormat } from '@/composables/useFormat';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    shiftId: { type: [Number, String], required: true },
    currencyId: { type: [Number, String], default: null },
    currencyCode: { type: String, default: '' },
    availableBalance: { type: Number, default: 0 },
    requiredAmount: { type: Number, default: 0 },
    deficit: { type: Number, default: 0 },
    canDeposit: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'deposited']);

const { number } = useFormat();
const amountInput = ref(null);

const form = useForm({
    type: 'deposit',
    currency_id: '',
    amount: '',
    notes: '',
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;

        form.reset();
        form.clearErrors();
        form.type = 'deposit';
        form.currency_id = props.currencyId;
        // تقريب العجز لأقرب خانتين وتعبئته تلقائياً
        form.amount = props.deficit > 0 ? String(+props.deficit.toFixed(2)) : '';
        form.notes = 'تعزيز رصيد لتغطية عملية صرافة';

        nextTick(() => amountInput.value?.focus());
    },
);

const depositAmount = computed(() => {
    const val = Number(form.amount);
    return Number.isFinite(val) && val > 0 ? val : 0;
});

const balanceAfter = computed(() => props.availableBalance + depositAmount.value);

const coversDeficit = computed(() => depositAmount.value >= props.deficit);

function submitDeposit() {
    if (!props.canDeposit || depositAmount.value <= 0 || form.processing) return;

    form.post(route('shifts.movements.store', props.shiftId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('deposited');
            emit('close');
        },
    });
}
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="!form.processing" @close="emit('close')">
        <div class="p-5 sm:p-6">
            <!-- رأس الحوار -->
            <div class="flex items-start gap-3 border-b border-gray-100 pb-4 dark:border-slate-700">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-black text-gray-900 dark:text-white">
                        الرصيد غير كافٍ — هل ترغب في تسجيل إيداع؟
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                        الرصيد الحالي لا يكفي لإتمام العملية الحالية.
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="form.processing"
                    @click="emit('close')"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                    aria-label="إغلاق"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- بطاقة تفاصيل النقص والعجز -->
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs dark:border-rose-900/50 dark:bg-rose-950/30">
                <div class="flex items-center justify-between py-1">
                    <span class="text-gray-600 dark:text-slate-300">الرصيد المتوفر في الصندوق:</span>
                    <span class="font-bold tabular-nums text-gray-900 dark:text-white">
                        {{ number(availableBalance) }} {{ currencyCode }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-1">
                    <span class="text-gray-600 dark:text-slate-300">المطلوب للعملية:</span>
                    <span class="font-bold tabular-nums text-gray-900 dark:text-white">
                        {{ number(requiredAmount) }} {{ currencyCode }}
                    </span>
                </div>
                <div class="mt-1.5 flex items-center justify-between border-t border-rose-200/80 pt-1.5 dark:border-rose-800/60">
                    <span class="font-bold text-rose-800 dark:text-rose-300">قيمة العجز المطلوب تغطيتها:</span>
                    <span class="text-sm font-black tabular-nums text-rose-700 dark:text-rose-400">
                        {{ number(deficit) }} {{ currencyCode }}
                    </span>
                </div>
            </div>

            <!-- محتوى نموذج الإيداع -->
            <div v-if="canDeposit" class="mt-4 space-y-3">
                <div>
                    <div class="flex items-center justify-between">
                        <label for="deposit-amount" class="text-xs font-bold text-gray-700 dark:text-slate-300">
                            مبلغ الإيداع المقترح ({{ currencyCode }})
                        </label>
                        <span class="text-[11px] text-gray-400">يمكنك تعديل المبلغ إذا أردت</span>
                    </div>

                    <div class="relative mt-1">
                        <TextInput
                            id="deposit-amount"
                            ref="amountInput"
                            v-model="form.amount"
                            type="number"
                            step="any"
                            min="0.01"
                            inputmode="decimal"
                            :disabled="form.processing"
                            class="block w-full pe-14 text-lg font-black tabular-nums"
                            :error="Boolean(form.errors.amount)"
                        />
                        <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs font-black text-gray-400">
                            {{ currencyCode }}
                        </span>
                    </div>
                    <InputError :message="form.errors.amount" class="mt-1" />
                </div>

                <!-- معاينة الرصيد بعد الإيداع -->
                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-900/50">
                    <span class="text-gray-500 dark:text-slate-400">الرصيد بعد الإيداع:</span>
                    <span class="font-black tabular-nums" :class="coversDeficit ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                        {{ number(balanceAfter) }} {{ currencyCode }}
                        <span v-if="coversDeficit" class="ms-1 text-[10px] font-normal text-emerald-600">(يغطي العملية ✓)</span>
                        <span v-else class="ms-1 text-[10px] font-normal text-amber-600">(لا يزال أقل من المطلوب)</span>
                    </span>
                </div>

                <div>
                    <label for="deposit-notes" class="text-xs font-semibold text-gray-700 dark:text-slate-300">
                        البيان / ملاحظات <span class="font-normal text-gray-400">(اختياري)</span>
                    </label>
                    <TextInput
                        id="deposit-notes"
                        v-model="form.notes"
                        type="text"
                        :disabled="form.processing"
                        placeholder="بيان حركة الإيداع..."
                        class="mt-1 block w-full text-xs"
                    />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>

                <InputError :message="form.errors.shift" class="mt-1" />
            </div>

            <!-- في حال عدم وجود صلاحية إيداع -->
            <div v-else class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <p class="font-bold">تنبيه:</p>
                <p class="mt-1">
                    لا تملك صلاحية تسجيل إيداع نقدي على هذه الوردية. يرجى طلب تعزيز الصندوق أو تسجيل الإيداع من المشرف المسؤول.
                </p>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :disabled="form.processing"
                    @click="emit('close')"
                    class="rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    إلغاء / تعديل العملية
                </button>

                <button
                    v-if="canDeposit"
                    type="button"
                    :disabled="form.processing || depositAmount <= 0"
                    @click="submitDeposit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-md shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>
                        {{ form.processing ? 'جارٍ تسجيل الإيداع…' : `نعم، إيداع ${number(depositAmount)} ${currencyCode}` }}
                    </span>
                </button>
            </div>
        </div>
    </Modal>
</template>
