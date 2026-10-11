<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import OldLiraHint from '@/Components/OldLiraHint.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    currentShiftOpen: { type: Boolean, default: false },
    currentShift: { type: Object, default: null },
    previousBalances: { type: Object, default: () => ({}) },
    // 'current_shift' أو 'previous_shift' — من أين جاء الرقم المقترح.
    balanceSource: { type: String, default: 'previous_shift' },
    strandedBalances: { type: Array, default: () => [] },
});

const { number, dateTime } = useFormat();

/**
 * الأرصدة تصل كسلاسل decimal(18,4) مثل "600.0000". حقل الرقم يعرضها كما هي،
 * فتبدو الأصفار الزائدة كأنها جزء من المبلغ. نقصّها للعرض دون تغيير القيمة.
 */
function normalize(value) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? String(parsed) : String(value ?? '0');
}

const form = useForm({
    opening_balances: Object.fromEntries(
        props.currencies.map((c) => [c.id, normalize(props.previousBalances[c.id] ?? '0')]),
    ),
});

const confirmingReplace = ref(false);

function suggested(currency) {
    return normalize(props.previousBalances[currency.id] ?? '0');
}

/** هل عدّل المستخدم هذا الحقل عن الرقم المقترح؟ */
function isEdited(currency) {
    return Number(form.opening_balances[currency.id] || 0) !== Number(suggested(currency));
}

const editedCount = computed(() => props.currencies.filter(isEdited).length);

const sourceLabel = computed(() =>
    props.balanceSource === 'current_shift'
        ? 'الأرقام المقترحة هي أرصدة ورديتك المفتوحة الآن.'
        : 'الأرقام المقترحة هي أرصدة إغلاق ورديتك السابقة.',
);

function resetOne(currency) {
    form.opening_balances[currency.id] = suggested(currency);
}

function resetAll() {
    props.currencies.forEach(resetOne);
}

function zeroAll() {
    props.currencies.forEach((c) => (form.opening_balances[c.id] = '0'));
}

/** العملات التي ستُفتح الوردية الجديدة برصيد فيها — لعرضها في حوار التأكيد. */
const fundedOpening = computed(() =>
    props.currencies
        .map((c) => ({ currency: c, amount: Number(form.opening_balances[c.id]) || 0 }))
        .filter((row) => row.amount !== 0),
);

function submit() {
    // استبدال وردية مفتوحة إجراء لا رجعة فيه، فيمرّ بتأكيد صريح أولاً.
    if (props.currentShiftOpen) {
        confirmingReplace.value = true;

        return;
    }

    store();
}

function store() {
    form.post(route('shifts.store'), {
        preserveScroll: true,
        onSuccess: () => (confirmingReplace.value = false),
        /*
         * الحوار يغطّي النموذج، وأخطاء التحقق تُعرض داخله. إبقاؤه مفتوحاً بعد
         * الرفض كان يترك المستخدم أمام حوار لا يشرح سبب الرفض ولا يتقدّم.
         * نغلقه ثم نقفز إلى أول حقل مرفوض.
         */
        onError: () => {
            confirmingReplace.value = false;

            nextTick(() => {
                document
                    .querySelector('[data-invalid="true"]')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },
    });
}

/** العملات التي رفض الخادم أرصدتها — لعرضها مجمّعة أعلى النموذج. */
const rejected = computed(() =>
    props.currencies
        .filter((c) => form.errors[`opening_balances.${c.id}`])
        .map((c) => ({ currency: c, message: form.errors[`opening_balances.${c.id}`] })),
);
</script>

<template>
    <Head title="وردية جديدة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ currentShiftOpen ? 'إغلاق الوردية الحالية وفتح وردية جديدة' : 'فتح وردية جديدة' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div
                    v-if="currentShiftOpen"
                    class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-300"
                >
                    <p class="font-semibold">لديك وردية مفتوحة.</p>
                    <p class="mt-1">
                        عند المتابعة ستُغلق بأرصدتها الحالية، ثم تُفتح وردية جديدة بالأرصدة أدناه.
                    </p>
                    <p v-if="currentShift" class="mt-1 text-xs opacity-90">
                        وردية {{ currentShift.business_date }} — مفتوحة منذ {{ dateTime(currentShift.opened_at) }}
                    </p>
                </div>

                <!--
                    رصيد عملة معطّلة لا ينتقل إلى الوردية الجديدة، لأن الفتح يمرّ
                    على المفعّلة فقط. يُعرض هنا حتى لا يختفي المبلغ بلا إشعار.
                -->
                <div
                    v-if="strandedBalances.length"
                    class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800/50 dark:bg-rose-900/20 dark:text-rose-200"
                    role="alert"
                >
                    <p class="font-semibold">أرصدة لن تنتقل إلى الوردية الجديدة</p>
                    <p class="mt-1 text-xs">
                        هذه العملات معطّلة، ولا يُفتح لها حساب في الوردية الجديدة. فعّلها أولاً إن كان الرصيد ما يزال في الصندوق.
                    </p>
                    <ul class="mt-2 space-y-0.5">
                        <li v-for="row in strandedBalances" :key="row.id" class="flex items-center justify-between gap-2">
                            <span>{{ row.code }} — {{ row.name }}</span>
                            <span class="font-semibold tabular-nums">{{ number(row.balance) }}</span>
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="submit" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-white">الأرصدة الافتتاحية</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                            {{ sourceLabel }} عدّلها لتطابق ما في الصندوق فعلاً.
                        </p>
                    </div>

                    <div
                        v-if="rejected.length || form.errors.shift"
                        class="rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/25 dark:text-rose-200"
                        role="alert"
                    >
                        <p class="font-semibold">تعذّر فتح الوردية.</p>
                        <p v-if="form.errors.shift" class="mt-1">{{ form.errors.shift }}</p>
                        <ul v-if="rejected.length" class="mt-1 list-inside list-disc space-y-0.5 text-xs">
                            <li v-for="row in rejected" :key="row.currency.id">
                                {{ row.currency.code }}: {{ row.message }}
                            </li>
                        </ul>
                    </div>

                    <NewLiraNotice compact :currencies="currencies" dismiss-key="shift-open" />

                    <!-- إجراءات سريعة: التصفير والاستعادة أكثر ما يُكرّر يدوياً هنا. -->
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 pb-4 dark:border-slate-700">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="resetAll"
                        >
                            استعادة الأرقام المقترحة
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="zeroAll"
                        >
                            تصفير الكل
                        </button>
                        <span v-if="editedCount" class="text-xs text-amber-700 dark:text-amber-400">
                            {{ editedCount }} حقل معدّل عن المقترح
                        </span>
                    </div>

                    <p v-if="currencies.length === 0" class="rounded-md bg-gray-50 p-4 text-center text-sm text-gray-500 dark:bg-slate-700/40 dark:text-slate-400">
                        لا توجد عملات مفعّلة — فعّل عملة واحدة على الأقل قبل فتح وردية.
                    </p>

                    <div v-for="currency in currencies" :key="currency.id" class="rounded-lg border border-gray-100 p-3 dark:border-slate-700">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <label :for="`balance-${currency.id}`" class="text-sm font-medium text-gray-700 dark:text-slate-200">
                                {{ currency.name }}
                                <span class="text-gray-400">({{ currency.code }})</span>
                                <span v-if="currency.tag" class="ms-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ currency.tag }}
                                </span>
                                <span v-if="currency.is_base" class="ms-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    عملة أساس
                                </span>
                            </label>

                            <!-- الرقم المقترح يبقى ظاهراً بعد التعديل، ليمكن الرجوع إليه. -->
                            <span v-if="isEdited(currency)" class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                                المقترح {{ number(suggested(currency)) }}
                                <button type="button" class="font-semibold underline hover:no-underline" @click="resetOne(currency)">استعادة</button>
                            </span>
                        </div>

                        <TextInput
                            :id="`balance-${currency.id}`"
                            v-model="form.opening_balances[currency.id]"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full tabular-nums"
                            :error="Boolean(form.errors[`opening_balances.${currency.id}`])"
                            :data-invalid="Boolean(form.errors[`opening_balances.${currency.id}`])"
                            :class="isEdited(currency) && !form.errors[`opening_balances.${currency.id}`]
                                ? 'border-amber-400 dark:border-amber-500/70'
                                : ''"
                        />
                        <OldLiraHint
                            :amount="form.opening_balances[currency.id]"
                            :currency="currency"
                            @correct="(value) => (form.opening_balances[currency.id] = String(value))"
                        />
                        <InputError :message="form.errors[`opening_balances.${currency.id}`]" class="mt-1" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing || currencies.length === 0">
                            <template v-if="form.processing && !confirmingReplace">جارٍ فتح الوردية…</template>
                            <template v-else>
                                {{ currentShiftOpen ? 'إغلاق الوردية الحالية وفتح وردية جديدة' : 'فتح الوردية' }}
                            </template>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmingReplace"
            title="إغلاق الوردية الحالية وفتح وردية جديدة"
            message="ستُغلق ورديتك المفتوحة بأرصدتها الحالية، ثم تُفتح وردية جديدة بالأرصدة التي أدخلتها."
            confirm-label="إغلاق وفتح وردية جديدة"
            cancel-label="تراجع"
            tone="warning"
            irreversible
            :processing="form.processing"
            @confirm="store"
            @cancel="confirmingReplace = false"
        >
            <template #details>
                <p class="mb-1 text-xs font-semibold text-gray-500 dark:text-slate-400">الأرصدة الافتتاحية للوردية الجديدة</p>
                <div v-if="fundedOpening.length" class="max-h-40 space-y-0.5 overflow-y-auto">
                    <div v-for="row in fundedOpening" :key="row.currency.id" class="flex items-center justify-between gap-2">
                        <span class="text-gray-600 dark:text-slate-300">{{ row.currency.code }}</span>
                        <span class="font-semibold tabular-nums text-gray-800 dark:text-slate-100">{{ number(row.amount) }}</span>
                    </div>
                </div>
                <p v-else class="text-xs text-gray-400 dark:text-slate-500">ستُفتح الوردية الجديدة بأرصدة صفرية.</p>

                <p v-if="editedCount" class="mt-2 border-t border-gray-200 pt-2 text-xs text-amber-700 dark:border-slate-600 dark:text-amber-400">
                    {{ editedCount }} حقل يختلف عن الأرقام المقترحة.
                </p>
            </template>
        </ConfirmDialog>
    </AuthenticatedLayout>
</template>
