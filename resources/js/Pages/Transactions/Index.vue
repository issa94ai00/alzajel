<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteWithReasonDialog from '@/Components/DeleteWithReasonDialog.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    transactions: { type: Object, required: true },
    currencies: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    canSeeTrashed: { type: Boolean, default: false },
    trashedCount: { type: Number, default: 0 },
});

const form = reactive({
    currency_id: props.filters.currency_id ?? '',
    type: props.filters.type ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    search: props.filters.search ?? '',
    trashed: props.filters.trashed ?? '',
});

function applyFilters() {
    router.get(route('transactions.index'), form, { preserveState: true, replace: true });
}

// عرض المحذوفات وحدها يقلب معنى الجدول، فيُطبَّق فوراً لا بزرّ "تطبيق".
function setTrashed(scope) {
    form.trashed = scope;
    applyFilters();
}

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}

// السعر له قاعدة عرض خاصة — انظر useFormat.
const { rate: formatRate, dateTime } = useFormat();

const showingTrashed = computed(() => form.trashed === 'only');

const pendingDelete = ref(null);
const processing = ref(false);
const dialogError = ref('');

function askDelete(transaction) {
    dialogError.value = '';
    pendingDelete.value = transaction;
}

function confirmDelete(reason) {
    const transaction = pendingDelete.value;

    if (!transaction) return;

    processing.value = true;

    router.delete(route('transactions.destroy', transaction.id), {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
        onError: (errors) => {
            dialogError.value = errors.reason ?? errors.transaction ?? 'تعذّر حذف العملية.';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function restore(transaction) {
    router.post(route('transactions.restore', transaction.id), {}, { preserveScroll: true });
}

// عمود الإجراءات يظهر فقط لمن يملك حذفاً أو استعادة على أي سطر معروض.
const showActions = computed(() =>
    props.transactions.data.some((t) => t.can?.delete || t.can?.restore),
);
</script>

<template>
    <Head title="سجل العمليات" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">سجل العمليات</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <NewLiraNotice dismiss-key="transactions-index" />

                <!-- تبويب السارية/المحذوفة — لمن يملك صلاحية الحذف وحده. -->
                <div v-if="canSeeTrashed" class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="setTrashed('')"
                        class="rounded-md px-3 py-1.5 text-sm font-semibold transition"
                        :class="!showingTrashed
                            ? 'bg-indigo-600 text-white'
                            : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    >
                        العمليات السارية
                    </button>
                    <button
                        type="button"
                        @click="setTrashed('only')"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                        :class="showingTrashed
                            ? 'bg-rose-600 text-white'
                            : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    >
                        المحذوفة
                        <span
                            v-if="trashedCount"
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                            :class="showingTrashed ? 'bg-white/20' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'"
                        >
                            {{ trashedCount }}
                        </span>
                    </button>
                </div>

                <div
                    v-if="showingTrashed"
                    class="rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-3 text-sm text-rose-900 dark:border-rose-800/50 dark:bg-rose-900/15 dark:text-rose-200"
                >
                    هذه عمليات محذوفة: أُخفيت من السجل والتقارير وعُكس أثرها على أرصدة الوردية، وما تزال قابلة للاستعادة.
                </div>

                <div class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:grid-cols-5">
                    <select v-model="form.currency_id" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">كل العملات</option>
                        <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.tag ? `${c.code} — ${c.tag}` : c.code }}</option>
                    </select>
                    <select v-model="form.type" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">كل الأنواع</option>
                        <option value="buy">شراء</option>
                        <option value="sell">بيع</option>
                    </select>
                    <input v-model="form.date_from" type="date" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                    <input v-model="form.date_to" type="date" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                    <input v-model="form.search" type="text" placeholder="بحث بالاسم أو الهاتف" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500" />
                    <button @click="applyFilters" class="col-span-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:col-span-5">
                        تطبيق الفلاتر
                    </button>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                        <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2 text-start">التاريخ</th>
                                <th class="px-4 py-2 text-start">النوع</th>
                                <th class="px-4 py-2 text-start">العملة</th>
                                <th class="px-4 py-2 text-start">المبلغ</th>
                                <th class="px-4 py-2 text-start">السعر</th>
                                <th class="px-4 py-2 text-start">الإجمالي</th>
                                <th class="px-4 py-2 text-start">العميل</th>
                                <th class="px-4 py-2 text-start">الموظف</th>
                                <th v-if="showingTrashed" class="px-4 py-2 text-start">الحذف</th>
                                <th v-if="$page.props.settings?.receipts_enabled && !showingTrashed" class="px-4 py-2 text-start">الإيصال</th>
                                <th v-if="showActions" class="px-4 py-2 text-start"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="11" class="px-4 py-6 text-center text-gray-400 dark:text-slate-500">
                                    {{ showingTrashed ? 'لا توجد عمليات محذوفة.' : 'لا توجد عمليات مطابقة.' }}
                                </td>
                            </tr>
                            <tr v-for="t in transactions.data" :key="t.id" :class="t.deleted_at ? 'bg-rose-50/40 dark:bg-rose-900/10' : ''">
                                <td class="whitespace-nowrap px-4 py-2">{{ new Date(t.created_at).toLocaleString('ar-SY') }}</td>
                                <td class="px-4 py-2">
                                    <span :class="t.type === 'buy' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" class="font-semibold">
                                        {{ t.type === 'buy' ? 'شراء' : 'بيع' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    {{ t.currency?.code }}
                                    <span v-if="t.currency_tag" class="ms-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ t.currency_tag }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ formatNumber(t.amount) }}</td>
                                <td class="px-4 py-2 tabular-nums">{{ formatRate(t.rate) }}</td>
                                <td class="px-4 py-2">{{ formatNumber(t.total) }}</td>
                                <td class="px-4 py-2">{{ t.customer_name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ t.user?.name ?? '—' }}</td>

                                <!-- من حذف، ولماذا، ومتى — العمود الذي يجعل السلّة سجلَّ تدقيق. -->
                                <td v-if="showingTrashed" class="px-4 py-2">
                                    <div class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ t.deleted_by ?? '—' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-slate-400">{{ dateTime(t.deleted_at) }}</div>
                                    <div v-if="t.deletion_reason" class="mt-0.5 max-w-xs text-xs text-gray-600 dark:text-slate-300">
                                        {{ t.deletion_reason }}
                                    </div>
                                    <div v-if="t.deleted_with_shift" class="mt-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                                        حُذفت مع الوردية #{{ t.shift_id }}
                                    </div>
                                </td>

                                <td v-if="$page.props.settings?.receipts_enabled && !showingTrashed" class="px-4 py-2">
                                    <a
                                        :href="route('transactions.receipt', t.id)"
                                        target="_blank"
                                        class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        طباعة
                                    </a>
                                </td>

                                <td v-if="showActions" class="whitespace-nowrap px-4 py-2">
                                    <button
                                        v-if="t.can?.delete"
                                        type="button"
                                        @click="askDelete(t)"
                                        class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400"
                                    >
                                        حذف
                                    </button>
                                    <button
                                        v-else-if="t.can?.restore"
                                        type="button"
                                        @click="restore(t)"
                                        class="text-xs font-semibold text-emerald-600 hover:underline dark:text-emerald-400"
                                    >
                                        استعادة
                                    </button>
                                    <!--
                                        الوردية أُغلقت: الحذف يعكس أرصدة الوردية، ورصيد
                                        الإغلاق صار رصيد افتتاح التالية.
                                    -->
                                    <span
                                        v-else-if="!t.deleted_at && t.shift_status === 'closed'"
                                        class="text-xs text-gray-400 dark:text-slate-500"
                                        title="لا يمكن حذف عملية بعد إغلاق ورديتها"
                                    >
                                        وردية مغلقة
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-1">
                    <Link
                        v-for="link in transactions.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="rounded-md px-3 py-1 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        :as="link.url ? 'a' : 'span'"
                    />
                </div>
            </div>
        </div>

        <DeleteWithReasonDialog
            :show="pendingDelete !== null"
            title="حذف عملية صرافة"
            message="ستختفي العملية من السجل والتقارير، وسيُعكس أثرها على أرصدة الوردية."
            confirm-label="حذف العملية"
            :processing="processing"
            :error="dialogError"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        >
            <template #summary>
                <div v-if="pendingDelete" class="space-y-0.5 text-xs text-gray-700 dark:text-slate-200">
                    <div>
                        <span class="font-semibold">{{ pendingDelete.type === 'buy' ? 'شراء' : 'بيع' }}</span>
                        {{ formatNumber(pendingDelete.amount) }} {{ pendingDelete.currency?.code }}
                        بسعر {{ formatRate(pendingDelete.rate) }}
                    </div>
                    <div>الإجمالي: {{ formatNumber(pendingDelete.total) }}</div>
                    <div>الوردية: #{{ pendingDelete.shift_id }} · الموظف: {{ pendingDelete.user?.name ?? '—' }}</div>
                </div>
            </template>
        </DeleteWithReasonDialog>
    </AuthenticatedLayout>
</template>
