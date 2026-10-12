<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteWithReasonDialog from '@/Components/DeleteWithReasonDialog.vue';
import Pagination from '@/Components/Pagination.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shifts: { type: Object, required: true },
    canSeeAll: { type: Boolean, default: false },
    // الورديات المفتوحة الآن لكل الصرّافين — للمشرف فقط.
    openShifts: { type: Array, default: () => [] },
    myOpenShift: { type: Object, default: null },
    canSeeTrashed: { type: Boolean, default: false },
    trashedCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
});

const { dateTime, date, number } = useFormat();

const rows = computed(() => props.shifts.data ?? []);
const showingTrashed = computed(() => (props.filters.trashed ?? '') === 'only');

const searchQuery = ref(props.filters.search ?? '');
const selectedStatus = ref(props.filters.status ?? '');

function isMine(shift) {
    return props.myOpenShift?.id === shift.id;
}

function applyFilters(newFilters = {}) {
    const current = {
        trashed: props.filters.trashed || undefined,
        status: selectedStatus.value || undefined,
        search: searchQuery.value || undefined,
        ...newFilters,
    };

    // إزالة القيم الفارغة
    Object.keys(current).forEach((key) => {
        if (!current[key]) delete current[key];
    });

    router.get(route('shifts.index'), current, {
        preserveState: true,
        replace: true,
    });
}

function setStatusFilter(status) {
    selectedStatus.value = status;
    applyFilters({ status });
}

function setTrashed(scope) {
    applyFilters({ trashed: scope });
}

function onSearch() {
    applyFilters({ search: searchQuery.value });
}

function clearSearch() {
    searchQuery.value = '';
    applyFilters({ search: undefined });
}

const pendingDelete = ref(null);
const processing = ref(false);
const dialogError = ref('');

function askDelete(shift) {
    dialogError.value = '';
    pendingDelete.value = shift;
}

function confirmDelete(reason) {
    const shift = pendingDelete.value;
    if (!shift) return;

    processing.value = true;

    router.delete(route('shifts.destroy', shift.id), {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
        onError: (errors) => {
            dialogError.value = errors.reason ?? errors.shift ?? 'تعذّر حذف الوردية.';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function restore(shift) {
    router.post(route('shifts.restore', shift.id), {}, { preserveScroll: true });
}

const showRowActions = computed(() => rows.value.some((s) => s.can?.delete || s.can?.restore));
</script>

<template>
    <Head title="إدارة وسجل الورديات" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black leading-tight text-slate-900 dark:text-white">
                        الورديات
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ canSeeAll ? 'متابعة وإدارة ورديات جميع الصرافين' : 'سجل وردياتك الخاصة وأرصدة الصندوق' }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="!myOpenShift"
                        :href="route('shifts.create')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                    >
                        <NavIcon name="plus-circle" class="h-4 w-4" />
                        <span>فتح وردية جديدة</span>
                    </Link>

                    <Link
                        v-else
                        :href="route('shifts.show', myOpenShift.id)"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50/80 px-4 py-2 text-xs font-black text-emerald-800 shadow-sm transition hover:bg-emerald-100 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        </span>
                        <span>ورديتي المفتوحة (#{{ myOpenShift.id }})</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- لوحة المشرف: متابعة الورديات المفتوحة الآن في النظام -->
                <section
                    v-if="canSeeAll && openShifts.length"
                    class="overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/80 via-white to-emerald-50/30 p-4 shadow-sm dark:border-emerald-800/40 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/20 sm:p-5"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-sm font-black text-emerald-900 dark:text-emerald-300">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            </span>
                            <span>الورديات المفتوحة والنشطة حالياً</span>
                            <span class="rounded-full bg-emerald-200/80 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                {{ openShifts.length }}
                            </span>
                        </h3>
                        <span class="text-[11px] text-emerald-700/80 dark:text-emerald-400">متابعة فورية لنشاط الصرافين</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="entry in openShifts"
                            :key="entry.id"
                            :href="route('shifts.show', entry.id)"
                            class="group relative rounded-xl border border-emerald-200/80 bg-white p-3.5 shadow-sm transition hover:border-emerald-400 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-emerald-600"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="block truncate font-black text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">
                                        {{ entry.user ?? '—' }}
                                    </span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        بدأت: {{ dateTime(entry.opened_at) }}
                                    </span>
                                </div>
                                <span class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-black text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 shrink-0">
                                    #{{ entry.id }}
                                </span>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between border-t border-dashed border-slate-100 pt-2 text-[11px] dark:border-slate-700">
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">
                                    تاريخ العمل: {{ entry.business_date }}
                                </span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400 group-hover:underline">
                                    عرض التفاصيل ←
                                </span>
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- شريط الفلاتر والبحث والتبويبات -->
                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <!-- تبويبات الحالة السارية / المحذوفة والتصفية -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- تبويب الورديات السارية / المحذوفة -->
                        <div v-if="canSeeTrashed" class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-900">
                            <button
                                type="button"
                                @click="setTrashed('')"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                :class="!showingTrashed
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            >
                                السارية
                            </button>
                            <button
                                type="button"
                                @click="setTrashed('only')"
                                class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                :class="showingTrashed
                                    ? 'bg-rose-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            >
                                <span>المحذوفة</span>
                                <span
                                    v-if="trashedCount"
                                    class="rounded-full px-1.5 py-0.2 text-[10px] font-bold"
                                    :class="showingTrashed ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'"
                                >
                                    {{ trashedCount }}
                                </span>
                            </button>
                        </div>

                        <!-- فلاتر الحالة (الكل / مفتوحة / مغلقة) -->
                        <div v-if="!showingTrashed" class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-900">
                            <button
                                type="button"
                                @click="setStatusFilter('')"
                                class="rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                :class="selectedStatus === ''
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            >
                                الكل
                            </button>
                            <button
                                type="button"
                                @click="setStatusFilter('open')"
                                class="rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                :class="selectedStatus === 'open'
                                    ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-800 dark:text-emerald-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            >
                                مفتوحة
                            </button>
                            <button
                                type="button"
                                @click="setStatusFilter('closed')"
                                class="rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                :class="selectedStatus === 'closed'
                                    ? 'bg-white text-slate-700 shadow-sm dark:bg-slate-800 dark:text-slate-200'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            >
                                مغلقة
                            </button>
                        </div>
                    </div>

                    <!-- حقل البحث المباشر -->
                    <div class="relative w-full sm:w-64">
                        <div class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="بحث بالتاريخ أو اسم الصراف…"
                            @keydown.enter.prevent="onSearch"
                            class="w-full rounded-xl border-slate-200 ps-9 pe-8 py-1.5 text-xs shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="clearSearch"
                            class="absolute inset-y-0 end-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            aria-label="مسح البحث"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- تنبيه سلة المحذوفات إن كانت معروضة -->
                <div
                    v-if="showingTrashed"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200"
                >
                    تنبيه: الورديات المحذوفة مخفية مع كافة عملياتها من السجلات والتقارير. استعادة الوردية تعيد تفعيلها وعملياتها فورياً.
                </div>

                <!-- جدول الورديات -->
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ showingTrashed ? 'سجل الورديات المحذوفة' : (canSeeAll ? 'سجل كافة الورديات' : 'سجل وردياتي') }}
                            </h3>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ shifts.total ?? rows.length }} وردية
                            </span>
                        </div>
                    </div>

                    <!-- عرض الجدول على الشاشات الكبيرة والمتوسطة -->
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-700">
                            <thead class="bg-slate-50/80 text-xs font-bold text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3.5 text-start">الوردية والتاريخ</th>
                                    <th class="px-5 py-3.5 text-start">الحالة</th>
                                    <th class="px-5 py-3.5 text-start">العمليات</th>
                                    <th class="px-5 py-3.5 text-start">فتحت بواسطة</th>
                                    <th class="px-5 py-3.5 text-start">أغلقت بواسطة</th>
                                    <th v-if="showingTrashed" class="px-5 py-3.5 text-start">بيانات الحذف</th>
                                    <th class="px-5 py-3.5 text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <tr v-if="rows.length === 0">
                                    <td colspan="7" class="px-5 py-12 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                            <NavIcon name="clock" class="h-6 w-6" />
                                        </div>
                                        <p class="mt-3 text-sm font-bold text-slate-500 dark:text-slate-400">
                                            {{ showingTrashed ? 'لا توجد ورديات محذوفة تطابق البحث.' : 'لا توجد ورديات مسجلة تطابق هذه الشروط.' }}
                                        </p>
                                        <Link
                                            v-if="!showingTrashed && !myOpenShift"
                                            :href="route('shifts.create')"
                                            class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            <NavIcon name="plus-circle" class="h-3.5 w-3.5" />
                                            <span>فتح وردية جديدة</span>
                                        </Link>
                                    </td>
                                </tr>

                                <tr
                                    v-for="shift in rows"
                                    :key="shift.id"
                                    class="transition hover:bg-slate-50/70 dark:hover:bg-slate-700/30"
                                    :class="isMine(shift) ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : ''"
                                >
                                    <!-- التاريخ ورقم الوردية -->
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-900 dark:text-white">
                                                #{{ shift.id }}
                                            </span>
                                            <span class="font-bold tabular-nums text-slate-700 dark:text-slate-300">
                                                {{ shift.business_date }}
                                            </span>
                                            <span
                                                v-if="isMine(shift)"
                                                class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-black text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                            >
                                                ورديتي
                                            </span>
                                        </div>
                                    </td>

                                    <!-- الحالة -->
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-black"
                                            :class="shift.status === 'open'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                                        >
                                            <span v-if="shift.status === 'open'" class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>{{ shift.status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span>
                                        </span>
                                    </td>

                                    <!-- عدد العمليات -->
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1 font-black tabular-nums text-slate-800 dark:text-slate-200">
                                            <span>{{ shift.exchange_transactions_count ?? 0 }}</span>
                                            <span class="text-[11px] font-normal text-slate-400">عملية</span>
                                        </span>
                                    </td>

                                    <!-- فتحت بواسطة -->
                                    <td class="px-5 py-4 text-xs">
                                        <div class="font-bold text-slate-800 dark:text-slate-200">
                                            {{ shift.opened_by?.name ?? '—' }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                            {{ dateTime(shift.opened_at) }}
                                        </div>
                                    </td>

                                    <!-- أغلقت بواسطة -->
                                    <td class="px-5 py-4 text-xs">
                                        <template v-if="shift.closed_by">
                                            <div class="font-bold text-slate-800 dark:text-slate-200">
                                                {{ shift.closed_by.name }}
                                            </div>
                                            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                                {{ dateTime(shift.closed_at) }}
                                            </div>
                                        </template>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>

                                    <!-- بيانات الحذف (عند عرض المحذوفات) -->
                                    <td v-if="showingTrashed" class="px-5 py-4 text-xs">
                                        <div class="font-bold text-rose-700 dark:text-rose-300">
                                            {{ shift.deleted_by ?? '—' }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                            {{ dateTime(shift.deleted_at) }}
                                        </div>
                                        <div v-if="shift.deletion_reason" class="mt-1 max-w-xs text-xs text-slate-600 dark:text-slate-300 truncate" :title="shift.deletion_reason">
                                            {{ shift.deletion_reason }}
                                        </div>
                                    </td>

                                    <!-- الإجراءات -->
                                    <td class="whitespace-nowrap px-5 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <Link
                                                v-if="!shift.deleted_at"
                                                :href="route('shifts.show', shift.id)"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-indigo-600 shadow-sm transition hover:bg-indigo-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                                            >
                                                <span>عرض</span>
                                                <span aria-hidden="true">←</span>
                                            </Link>

                                            <button
                                                v-if="shift.can?.delete"
                                                type="button"
                                                @click="askDelete(shift)"
                                                class="rounded-lg px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 transition dark:text-rose-400 dark:hover:bg-rose-950/40"
                                            >
                                                حذف
                                            </button>

                                            <button
                                                v-if="shift.can?.restore"
                                                type="button"
                                                @click="restore(shift)"
                                                class="rounded-lg px-2 py-1 text-xs font-bold text-emerald-600 hover:bg-emerald-50 transition dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                            >
                                                استعادة
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- بطاقات الهواتف الذكية (Mobile Responsive Cards) -->
                    <div class="divide-y divide-slate-100 sm:hidden dark:divide-slate-700/60">
                        <div v-if="rows.length === 0" class="p-8 text-center text-slate-400 dark:text-slate-500">
                            <p class="font-bold">لا توجد ورديات تطابق هذه الشروط.</p>
                            <Link v-if="!showingTrashed && !myOpenShift" :href="route('shifts.create')" class="mt-2 inline-block text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                                فتح وردية جديدة
                            </Link>
                        </div>

                        <div
                            v-for="shift in rows"
                            :key="shift.id"
                            class="p-4 transition space-y-2.5"
                            :class="[
                                shift.deleted_at ? 'bg-rose-50/30 dark:bg-rose-950/10' : '',
                                isMine(shift) ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : '',
                            ]"
                        >
                            <!-- رأس البطاقة -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-slate-900 dark:text-white">#{{ shift.id }}</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ shift.business_date }}</span>
                                    <span v-if="isMine(shift)" class="rounded bg-emerald-100 px-1.5 py-0.2 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        ورديتي
                                    </span>
                                </div>

                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-black"
                                    :class="shift.status === 'open'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                                >
                                    {{ shift.status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                                </span>
                            </div>

                            <!-- التفاصيل -->
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>الصراف: {{ shift.opened_by?.name ?? '—' }}</span>
                                <span class="font-bold tabular-nums">{{ shift.exchange_transactions_count ?? 0 }} عملية</span>
                            </div>

                            <!-- وقت الفتح والإغلاق -->
                            <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                فُتحت: {{ dateTime(shift.opened_at) }}
                                <span v-if="shift.closed_at"> · أغلقت: {{ dateTime(shift.closed_at) }}</span>
                            </div>

                            <!-- الإجراءات -->
                            <div class="flex items-center justify-between pt-2 border-t border-dashed border-slate-100 dark:border-slate-700">
                                <Link
                                    v-if="!shift.deleted_at"
                                    :href="route('shifts.show', shift.id)"
                                    class="inline-flex items-center gap-1 text-xs font-black text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    <span>عرض التفاصيل والجرد</span>
                                    <span aria-hidden="true">←</span>
                                </Link>

                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="shift.can?.delete"
                                        type="button"
                                        @click="askDelete(shift)"
                                        class="text-xs font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        حذف
                                    </button>
                                    <button
                                        v-if="shift.can?.restore"
                                        type="button"
                                        @click="restore(shift)"
                                        class="text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        استعادة
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الترقيم والتنقل بين الصفحات -->
                <Pagination :meta="shifts" />
            </div>
        </div>

        <!-- نافذة تأكيد الحذف مع بيان السبب الإلزامي -->
        <DeleteWithReasonDialog
            :show="pendingDelete !== null"
            title="حذف وردية"
            message="ستُخفى الوردية وعملياتها من السجلات والتقارير. الأرصدة المسجّلة عليها تبقى كما هي."
            confirm-label="حذف الوردية"
            :processing="processing"
            :error="dialogError"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        >
            <template #summary>
                <div v-if="pendingDelete" class="space-y-0.5 text-xs text-slate-700 dark:text-slate-200">
                    <div>الوردية #{{ pendingDelete.id }} · {{ pendingDelete.business_date }}</div>
                    <div>الصراف المسؤول: {{ pendingDelete.opened_by?.name ?? '—' }}</div>
                    <div class="font-bold text-rose-600 dark:text-rose-400">
                        ستُحذف معها {{ pendingDelete.exchange_transactions_count ?? 0 }} عملية مسجلة.
                    </div>
                </div>
            </template>
        </DeleteWithReasonDialog>
    </AuthenticatedLayout>
</template>
