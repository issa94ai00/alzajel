<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    // خريطة event => التسمية العربية، مصدرها ActivityLogger::EVENT_LABELS.
    events: { type: Object, default: () => ({}) },
    causers: { type: Array, default: () => [] },
});

const { dateTime } = useFormat();

const form = reactive({
    search: props.filters.search ?? '',
    event: props.filters.event ?? '',
    causer_id: props.filters.causer_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

let searchTimer;
watch(
    () => form.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 350);
    },
);

function applyFilters() {
    router.get(route('activity.index'), form, { preserveState: true, replace: true });
}

function resetFilters() {
    Object.assign(form, { search: '', event: '', causer_id: '', date_from: '', date_to: '' });
    applyFilters();
}

const rows = computed(() => props.logs.data ?? []);
const hasFilters = computed(() => Object.values(form).some((v) => v !== '' && v !== null));

// تفاصيل السطر تُطوى افتراضياً: الجدول يبقى مقروءاً، والتفاصيل تحت الطلب.
const expanded = ref(new Set());

function toggle(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

/**
 * لون الحدث حسب طبيعته — الحذف أحمر، الاستعادة والإنشاء أخضر، وهكذا.
 * الموظّف يمسح الصفحة بعينه بحثاً عن الأحمر أولاً.
 */
function toneFor(event) {
    if (event.endsWith('.deleted') || event === 'auth.failed' || event === 'auth.blocked') {
        return 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30';
    }

    if (event.endsWith('.restored') || event.endsWith('.created') || event === 'auth.login') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30';
    }

    if (event.startsWith('shift.')) {
        return 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-500/30';
    }

    return 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30';
}

const SUBJECT_LABELS = {
    User: 'مستخدم',
    Shift: 'وردية',
    ExchangeTransaction: 'عملية',
    Role: 'دور',
};

function subjectLabel(log) {
    if (!log.subject_type) return null;

    return `${SUBJECT_LABELS[log.subject_type] ?? log.subject_type} #${log.subject_id}`;
}

/** يحوّل خصائص السجل إلى أسطر "مفتاح: قيمة" صالحة للعرض. */
function detailRows(properties) {
    if (!properties || typeof properties !== 'object') return [];

    return Object.entries(properties).map(([key, value]) => ({
        key,
        label: PROPERTY_LABELS[key] ?? key,
        value: formatValue(value),
    }));
}

const PROPERTY_LABELS = {
    reason: 'السبب',
    shift_id: 'الوردية',
    user_id: 'الموظف',
    type: 'النوع',
    currency: 'العملة',
    currency_tag: 'الفئة',
    amount: 'المبلغ',
    rate: 'السعر',
    total: 'الإجمالي',
    profit: 'الربح',
    customer_name: 'العميل',
    recorded_at: 'وقت التسجيل',
    business_date: 'تاريخ العمل',
    owner_id: 'صاحب الوردية',
    closed_at: 'وقت الإغلاق',
    cascaded_transactions: 'عمليات حُذفت معها',
    restored_transactions: 'عمليات استُعيدت معها',
    email: 'البريد',
};

function formatValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'نعم' : 'لا';

    // حقول التعديل تأتي بصيغة {from, to}
    if (typeof value === 'object' && 'from' in value && 'to' in value) {
        return `${formatValue(value.from)} ← ${formatValue(value.to)}`;
    }

    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}
</script>

<template>
    <Head title="سجل النشاط" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">سجل النشاط</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-500 dark:text-slate-400">
                    كل إجراء حسّاس في النظام — من فعله، ومتى، ومن أي عنوان. السجل للقراءة فقط ولا يُعدَّل.
                </p>

                <div class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:grid-cols-5">
                    <input
                        v-model="form.search"
                        type="text"
                        placeholder="بحث في الوصف أو الاسم أو IP"
                        class="col-span-2 rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500 sm:col-span-1"
                    />

                    <select v-model="form.event" @change="applyFilters" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">كل الأحداث</option>
                        <option v-for="(label, value) in events" :key="value" :value="value">{{ label }}</option>
                    </select>

                    <select v-model="form.causer_id" @change="applyFilters" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">كل المستخدمين</option>
                        <option v-for="user in causers" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>

                    <input v-model="form.date_from" type="date" @change="applyFilters" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                    <input v-model="form.date_to" type="date" @change="applyFilters" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />

                    <button
                        v-if="hasFilters"
                        type="button"
                        @click="resetFilters"
                        class="col-span-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 sm:col-span-5"
                    >
                        مسح الفلاتر
                    </button>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">الوقت</th>
                                    <th class="px-4 py-2 text-start">الحدث</th>
                                    <th class="px-4 py-2 text-start">الوصف</th>
                                    <th class="px-4 py-2 text-start">المستخدم</th>
                                    <th class="px-4 py-2 text-start">العنوان</th>
                                    <th class="px-4 py-2 text-start"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-if="rows.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-slate-500">
                                        لا توجد سجلات مطابقة.
                                    </td>
                                </tr>

                                <template v-for="log in rows" :key="log.id">
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-500 dark:text-slate-400">
                                            {{ dateTime(log.created_at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="toneFor(log.event)">
                                                {{ log.event_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ log.description }}
                                            <div v-if="subjectLabel(log)" class="text-xs text-gray-400 dark:text-slate-500">
                                                {{ subjectLabel(log) }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2">{{ log.causer ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-500 dark:text-slate-400" dir="ltr">
                                            {{ log.ip_address ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2">
                                            <button
                                                v-if="log.properties"
                                                type="button"
                                                @click="toggle(log.id)"
                                                class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                {{ expanded.has(log.id) ? 'إخفاء' : 'تفاصيل' }}
                                            </button>
                                        </td>
                                    </tr>

                                    <tr v-if="expanded.has(log.id) && log.properties" class="bg-gray-50/80 dark:bg-slate-900/40">
                                        <td colspan="6" class="px-4 py-3">
                                            <dl class="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                                                <div v-for="row in detailRows(log.properties)" :key="row.key" class="flex gap-2 text-xs">
                                                    <dt class="shrink-0 font-semibold text-gray-600 dark:text-slate-300">{{ row.label }}:</dt>
                                                    <dd class="min-w-0 break-words text-gray-800 dark:text-slate-100">{{ row.value }}</dd>
                                                </div>
                                            </dl>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- بطاقات على الجوال: جدول بستة أعمدة لا يُقرأ على شاشة ضيّقة. -->
                    <div class="divide-y divide-gray-100 sm:hidden dark:divide-slate-700">
                        <div v-if="rows.length === 0" class="px-4 py-10 text-center text-gray-400 dark:text-slate-500">
                            لا توجد سجلات مطابقة.
                        </div>

                        <div v-for="log in rows" :key="log.id" class="px-4 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="toneFor(log.event)">
                                    {{ log.event_label }}
                                </span>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-slate-500">{{ dateTime(log.created_at) }}</span>
                            </div>

                            <p class="mt-1 text-sm text-gray-800 dark:text-slate-100">{{ log.description }}</p>

                            <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                                {{ log.causer ?? '—' }}
                                <span v-if="log.ip_address"> · <span dir="ltr">{{ log.ip_address }}</span></span>
                            </p>

                            <button
                                v-if="log.properties"
                                type="button"
                                @click="toggle(log.id)"
                                class="mt-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ expanded.has(log.id) ? 'إخفاء التفاصيل' : 'تفاصيل' }}
                            </button>

                            <dl v-if="expanded.has(log.id) && log.properties" class="mt-2 space-y-1 rounded-md bg-gray-50 p-2 dark:bg-slate-900/40">
                                <div v-for="row in detailRows(log.properties)" :key="row.key" class="flex gap-2 text-xs">
                                    <dt class="shrink-0 font-semibold text-gray-600 dark:text-slate-300">{{ row.label }}:</dt>
                                    <dd class="min-w-0 break-words text-gray-800 dark:text-slate-100">{{ row.value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <Pagination :meta="logs" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
