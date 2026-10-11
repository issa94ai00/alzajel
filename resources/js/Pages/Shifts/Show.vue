<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import CashMovementDialog from '@/Components/CashMovementDialog.vue';
import DeleteWithReasonDialog from '@/Components/DeleteWithReasonDialog.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shift: { type: Object, required: true },
    balances: { type: Array, required: true },
    profitLoss: { type: Object, required: true },
    // صلاحية الإغلاق تأتي من السياسة على الخادم، لا من كون المستخدم مديراً:
    // دور مخصّص يملك صلاحية الإغلاق يجب أن يرى الزر أيضاً.
    canClose: { type: Boolean, default: false },
    isOwner: { type: Boolean, default: false },
    canRecordMovement: { type: Boolean, default: false },
    movements: { type: Array, default: () => [] },
    movementCurrencies: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const { number, dateTime } = useFormat();

const isOpen = computed(() => props.shift.status === 'open');

const netProfit = computed(() => Number(props.profitLoss.total_profit));

/* ------------------------------ إغلاق الوردية ----------------------------- */

const confirmingClose = ref(false);
const form = useForm({});

function closeShift() {
    form.post(route('shifts.close', props.shift.id), {
        onSuccess: () => (confirmingClose.value = false),
    });
}

/** العملات ذات الرصيد فقط — قائمة الأصفار لا تضيف معلومة في حوار التأكيد. */
const fundedBalances = computed(() => props.balances.filter((b) => Number(b.current_balance) !== 0));

/* ---------------------------- الحركات النقدية ---------------------------- */

const showMovementDialog = ref(false);
const movementForm = useForm({ type: 'deposit', currency_id: '', amount: '', notes: '' });

function submitMovement(payload) {
    movementForm.transform(() => payload).post(route('shifts.movements.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            showMovementDialog.value = false;
            movementForm.reset();
            movementForm.clearErrors();
        },
    });
}

const liveMovements = computed(() => props.movements.filter((m) => !m.deleted_at));
const trashedMovements = computed(() => props.movements.filter((m) => m.deleted_at));

/** صافي أثر الحركات اليدوية على كل عملة — لتفسير الفرق في جدول الأرصدة. */
function movementNet(balance) {
    return Number(balance.deposits ?? 0) - Number(balance.withdrawals ?? 0);
}

function hasMovements(balance) {
    return Number(balance.deposits ?? 0) !== 0 || Number(balance.withdrawals ?? 0) !== 0;
}

/**
 * ما تحرّكه عمليات الصرافة وحدها = إجمالي الحركة − صافي الإيداع والسحب.
 * فصلُهما هو ما يجعل الرقم مفهوماً بدل أن يبدو فرق جرد.
 */
function exchangeNet(balance) {
    return Number(balance.current_balance) - Number(balance.opening_balance) - movementNet(balance);
}

const pendingDelete = ref(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

function askDeleteMovement(movement) {
    deleteError.value = '';
    pendingDelete.value = movement;
}

function confirmDeleteMovement(reason) {
    const movement = pendingDelete.value;

    if (!movement) return;

    deleteProcessing.value = true;

    router.delete(route('movements.destroy', movement.id), {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => { pendingDelete.value = null; },
        onError: (errors) => {
            deleteError.value = errors.reason ?? errors.movement ?? 'تعذّر حذف الحركة.';
        },
        onFinish: () => { deleteProcessing.value = false; },
    });
}

function restoreMovement(movement) {
    router.post(route('movements.restore', movement.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="تفاصيل الوردية" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('shifts.index')" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-slate-200" aria-label="رجوع إلى الورديات">
                        <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                        وردية {{ shift.business_date }}
                    </h2>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="isOpen
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30'
                            : 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30'"
                    >
                        <span v-if="isOpen" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ isOpen ? 'مفتوحة' : 'مغلقة' }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <a
                        v-if="page.props.settings?.receipts_enabled"
                        :href="route('shifts.receipt', shift.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" />
                        </svg>
                        طباعة كشف الوردية
                    </a>
                    <button
                        v-if="canRecordMovement"
                        @click="showMovementDialog = true"
                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                        </svg>
                        إيداع / سحب
                    </button>
                    <button
                        v-if="canClose"
                        @click="confirmingClose = true"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
                    >
                        إغلاق الوردية
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <NewLiraNotice dismiss-key="shift-show" />

                <!-- من فتح ومتى، ومن أغلق ومتى — أول ما يُسأل عنه عند مراجعة وردية. -->
                <dl class="grid gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 text-sm dark:border-slate-700 dark:bg-slate-700 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">فتحها</dt>
                        <dd class="mt-0.5 font-semibold text-gray-800 dark:text-white">{{ shift.opened_by?.name ?? '—' }}</dd>
                        <dd class="text-xs text-gray-400 dark:text-slate-500">{{ dateTime(shift.opened_at) }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">أغلقها</dt>
                        <dd class="mt-0.5 font-semibold text-gray-800 dark:text-white">{{ shift.closed_by?.name ?? '—' }}</dd>
                        <dd class="text-xs text-gray-400 dark:text-slate-500">{{ shift.closed_at ? dateTime(shift.closed_at) : 'ما تزال مفتوحة' }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">عدد العمليات</dt>
                        <dd class="mt-0.5 text-lg font-bold text-gray-800 dark:text-white">{{ profitLoss.transaction_count }}</dd>
                    </div>
                    <div v-if="isAdmin" class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">صافي الربح</dt>
                        <dd
                            class="mt-0.5 text-lg font-bold"
                            :class="netProfit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                        >
                            {{ number(profitLoss.total_profit) }}
                        </dd>
                    </div>
                </dl>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">الأرصدة</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">العملة</th>
                                    <th class="px-4 py-2 text-start">الرصيد الافتتاحي</th>
                                    <th class="px-4 py-2 text-start">الرصيد الحالي</th>
                                    <th class="px-4 py-2 text-start">من الصرافة</th>
                                    <th class="px-4 py-2 text-start">إيداع / سحب</th>
                                    <th class="px-4 py-2 text-start">صافي الحركة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-for="b in balances" :key="b.currency.id">
                                    <td class="whitespace-nowrap px-4 py-2">
                                        {{ b.currency.name }}
                                        <span class="text-gray-400">({{ b.currency.code }})</span>
                                    </td>
                                    <td class="px-4 py-2 tabular-nums">{{ number(b.opening_balance) }}</td>
                                    <td class="px-4 py-2 font-semibold tabular-nums">{{ number(b.current_balance) }}</td>

                                    <!-- فصل أثر الصرافة عن أثر الإيداع والسحب: الرقم المجمّع وحده
                                         يبدو فرق جرد لا يفسّره شيء. -->
                                    <td class="px-4 py-2 tabular-nums text-gray-500 dark:text-slate-400">
                                        {{ exchangeNet(b) >= 0 ? '+' : '−' }}{{ number(Math.abs(exchangeNet(b))) }}
                                    </td>
                                    <td class="px-4 py-2 tabular-nums">
                                        <span v-if="!hasMovements(b)" class="text-gray-300 dark:text-slate-600">—</span>
                                        <span
                                            v-else
                                            :class="movementNet(b) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                                        >
                                            {{ movementNet(b) >= 0 ? '+' : '−' }}{{ number(Math.abs(movementNet(b))) }}
                                        </span>
                                        <span v-if="hasMovements(b)" class="block text-[10px] text-gray-400 dark:text-slate-500">
                                            إيداع {{ number(b.deposits) }} · سحب {{ number(b.withdrawals) }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-2 font-semibold tabular-nums"
                                        :class="Number(b.current_balance) - Number(b.opening_balance) >= 0
                                            ? 'text-emerald-700 dark:text-emerald-400'
                                            : 'text-rose-700 dark:text-rose-400'"
                                    >
                                        {{ Number(b.current_balance) - Number(b.opening_balance) >= 0 ? '+' : '−' }}{{ number(Math.abs(Number(b.current_balance) - Number(b.opening_balance))) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- سجل الحركات النقدية: لماذا تغيّر الدرج خارج عمليات الصرافة. -->
                <div v-if="movements.length || canRecordMovement" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">
                            الحركات النقدية
                            <span v-if="liveMovements.length" class="text-sm font-normal text-gray-400">({{ liveMovements.length }})</span>
                        </h3>
                        <button
                            v-if="canRecordMovement"
                            @click="showMovementDialog = true"
                            class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            + حركة جديدة
                        </button>
                    </div>

                    <div v-if="movements.length === 0" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-slate-500">
                        لا توجد حركات نقدية على هذه الوردية.
                        <span v-if="!isOpen" class="block text-xs">الوردية مغلقة.</span>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">الوقت</th>
                                    <th class="px-4 py-2 text-start">النوع</th>
                                    <th class="px-4 py-2 text-start">العملة</th>
                                    <th class="px-4 py-2 text-start">المبلغ</th>
                                    <th class="px-4 py-2 text-start">البيان</th>
                                    <th class="px-4 py-2 text-start">سجّلها</th>
                                    <th class="px-4 py-2 text-start"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-for="m in movements" :key="m.id" :class="m.deleted_at ? 'bg-rose-50/40 dark:bg-rose-900/10' : ''">
                                    <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-500 dark:text-slate-400">{{ dateTime(m.created_at) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                            :class="m.type === 'deposit'
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30'
                                                : 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30'"
                                        >
                                            {{ m.type_label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">{{ m.currency?.code }}</td>
                                    <td
                                        class="px-4 py-2 font-semibold tabular-nums"
                                        :class="m.deleted_at
                                            ? 'text-gray-400 line-through dark:text-slate-500'
                                            : (m.type === 'deposit' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400')"
                                    >
                                        {{ m.type === 'deposit' ? '+' : '−' }}{{ number(m.amount) }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-slate-300">
                                        {{ m.notes ?? '—' }}
                                        <div v-if="m.deleted_at" class="mt-0.5 text-xs text-rose-700 dark:text-rose-300">
                                            حُذفت بواسطة {{ m.deleted_by ?? '—' }} · {{ dateTime(m.deleted_at) }}
                                            <span v-if="m.deletion_reason"> — {{ m.deletion_reason }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">{{ m.user ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <button
                                            v-if="m.can?.delete"
                                            type="button"
                                            @click="askDeleteMovement(m)"
                                            class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400"
                                        >
                                            حذف
                                        </button>
                                        <button
                                            v-else-if="m.can?.restore"
                                            type="button"
                                            @click="restoreMovement(m)"
                                            class="text-xs font-semibold text-emerald-600 hover:underline dark:text-emerald-400"
                                        >
                                            استعادة
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="trashedMovements.length" class="border-t border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-slate-700 dark:text-slate-400">
                        تتضمّن القائمة {{ trashedMovements.length }} حركة محذوفة — لا تُحتسب في الأرصدة.
                    </p>
                </div>

                <div v-if="isAdmin" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">الأرباح والخسائر</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">العملة</th>
                                    <th class="px-4 py-2 text-start">عدد العمليات</th>
                                    <th class="px-4 py-2 text-start">الربح</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-if="profitLoss.by_currency.length === 0">
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-400 dark:text-slate-500">لا توجد عمليات بعد.</td>
                                </tr>
                                <tr v-for="row in profitLoss.by_currency" :key="row.currency.id">
                                    <td class="whitespace-nowrap px-4 py-2">{{ row.currency.name }} ({{ row.currency.code }})</td>
                                    <td class="px-4 py-2 tabular-nums">{{ row.transaction_count }}</td>
                                    <td
                                        class="px-4 py-2 font-semibold tabular-nums"
                                        :class="Number(row.profit) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                                    >
                                        {{ number(row.profit) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- إغلاق الوردية: يثبّت الأرصدة نهائياً، فيُعرض ما سيُثبَّت قبل التأكيد. -->
        <ConfirmDialog
            :show="confirmingClose"
            title="إغلاق الوردية"
            message="سيتم تثبيت الأرصدة الحالية ولن يمكن تسجيل عمليات جديدة على هذه الوردية."
            confirm-label="إغلاق الوردية"
            cancel-label="تراجع"
            tone="danger"
            irreversible
            :processing="form.processing"
            @confirm="closeShift"
            @cancel="confirmingClose = false"
        >
            <template #details>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">تاريخ الوردية</span>
                    <span class="font-semibold text-gray-800 dark:text-slate-100">{{ shift.business_date }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">عدد العمليات</span>
                    <span class="font-semibold text-gray-800 dark:text-slate-100">{{ profitLoss.transaction_count }}</span>
                </div>
                <div v-if="isAdmin" class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">صافي الربح</span>
                    <span class="font-semibold" :class="netProfit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
                        {{ number(profitLoss.total_profit) }}
                    </span>
                </div>

                <div class="mt-3 border-t border-gray-200 pt-2 dark:border-slate-600">
                    <p class="mb-1 text-xs font-semibold text-gray-500 dark:text-slate-400">الأرصدة التي ستُثبَّت</p>
                    <div v-if="fundedBalances.length" class="max-h-40 space-y-0.5 overflow-y-auto">
                        <div v-for="b in fundedBalances" :key="b.currency.id" class="flex items-center justify-between gap-2">
                            <span class="text-gray-600 dark:text-slate-300">{{ b.currency.code }}</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-slate-100">{{ number(b.current_balance) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-slate-500">كل الأرصدة صفر.</p>
                </div>
            </template>
        </ConfirmDialog>

        <CashMovementDialog
            :show="showMovementDialog"
            :currencies="movementCurrencies"
            :processing="movementForm.processing"
            :errors="movementForm.errors"
            @submit="submitMovement"
            @cancel="showMovementDialog = false"
        />

        <DeleteWithReasonDialog
            :show="pendingDelete !== null"
            title="حذف حركة نقدية"
            message="سيُعكس أثر الحركة على رصيد الوردية، وتبقى مسجّلة في سجل التدقيق."
            confirm-label="حذف الحركة"
            :processing="deleteProcessing"
            :error="deleteError"
            @confirm="confirmDeleteMovement"
            @cancel="pendingDelete = null"
        >
            <template #summary>
                <div v-if="pendingDelete" class="space-y-0.5 text-xs text-gray-700 dark:text-slate-200">
                    <div>
                        <span class="font-semibold">{{ pendingDelete.type_label }}</span>
                        {{ number(pendingDelete.amount) }} {{ pendingDelete.currency?.code }}
                    </div>
                    <div>سجّلها: {{ pendingDelete.user ?? '—' }} · {{ dateTime(pendingDelete.created_at) }}</div>
                    <div v-if="pendingDelete.notes">البيان: {{ pendingDelete.notes }}</div>
                </div>
            </template>
        </DeleteWithReasonDialog>
    </AuthenticatedLayout>
</template>
