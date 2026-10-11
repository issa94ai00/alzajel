<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteWithReasonDialog from '@/Components/DeleteWithReasonDialog.vue';
import Pagination from '@/Components/Pagination.vue';
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

const { dateTime } = useFormat();

const rows = computed(() => props.shifts.data ?? []);

const showingTrashed = computed(() => (props.filters.trashed ?? '') === 'only');

function isMine(shift) {
    return props.myOpenShift?.id === shift.id;
}

function setTrashed(scope) {
    router.get(route('shifts.index'), scope ? { trashed: scope } : {}, {
        preserveState: true,
        replace: true,
    });
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

// عمود الإجراءات قائم دائماً (فيه رابط "عرض")؛ هذه للأزرار الإضافية فقط.
const showRowActions = computed(() => rows.value.some((s) => s.can?.delete || s.can?.restore));
</script>

<template>
    <Head title="الورديات" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">الورديات</h2>

                <Link
                    v-if="!myOpenShift"
                    :href="route('shifts.create')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
                >
                    + فتح وردية جديدة
                </Link>
                <Link
                    v-else
                    :href="route('shifts.show', myOpenShift.id)"
                    class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    ورديتي المفتوحة
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <!--
                    لوحة المشرف: من يعمل الآن. الخادم يرسلها أصلاً، ومن يشرف على
                    عدة صرّافين يحتاج أن يراها دون فتح كل وردية على حدة.
                -->
                <section v-if="canSeeAll && openShifts.length" class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-800/50 dark:bg-emerald-900/15">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-emerald-900 dark:text-emerald-300">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        ورديات مفتوحة الآن ({{ openShifts.length }})
                    </h3>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="entry in openShifts"
                            :key="entry.id"
                            :href="route('shifts.show', entry.id)"
                            class="rounded-lg border border-emerald-200 bg-white p-3 transition hover:border-emerald-400 hover:shadow-sm dark:border-emerald-800/60 dark:bg-slate-800 dark:hover:border-emerald-600"
                        >
                            <div class="truncate font-semibold text-gray-800 dark:text-white">{{ entry.user ?? '—' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">منذ {{ dateTime(entry.opened_at) }}</div>
                            <div class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">{{ entry.business_date }}</div>
                        </Link>
                    </div>
                </section>

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
                        الورديات السارية
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
                    ورديات محذوفة: أُخفيت هي وعملياتها من السجلات والتقارير، واستعادتها تُعيد الاثنين معاً.
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">
                            {{ showingTrashed ? 'الورديات المحذوفة' : (canSeeAll ? 'كل الورديات' : 'ورديّاتي') }}
                        </h3>
                    </div>

                    <!-- جدول على الشاشات الواسعة -->
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">التاريخ</th>
                                    <th class="px-4 py-2 text-start">الحالة</th>
                                    <th class="px-4 py-2 text-start">العمليات</th>
                                    <th class="px-4 py-2 text-start">فتحت بواسطة</th>
                                    <th class="px-4 py-2 text-start">أغلقت بواسطة</th>
                                    <th v-if="showingTrashed" class="px-4 py-2 text-start">الحذف</th>
                                    <th class="px-4 py-2 text-start"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-if="rows.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center">
                                        <p class="text-gray-400 dark:text-slate-500">
                                            {{ showingTrashed ? 'لا توجد ورديات محذوفة.' : 'لا توجد ورديات بعد.' }}
                                        </p>
                                        <Link v-if="!showingTrashed" :href="route('shifts.create')" class="mt-2 inline-block text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                                            فتح أول وردية
                                        </Link>
                                    </td>
                                </tr>
                                <tr
                                    v-for="shift in rows"
                                    :key="shift.id"
                                    :class="isMine(shift) ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : ''"
                                >
                                    <td class="whitespace-nowrap px-4 py-2">
                                        {{ shift.business_date }}
                                        <span v-if="isMine(shift)" class="ms-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            ورديتي
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                            :class="shift.status === 'open'
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30'
                                                : 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30'"
                                        >
                                            <span v-if="shift.status === 'open'" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ shift.status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 tabular-nums">{{ shift.exchange_transactions_count ?? 0 }}</td>
                                    <td class="px-4 py-2">
                                        {{ shift.opened_by?.name ?? '—' }}
                                        <div class="text-xs text-gray-400 dark:text-slate-500">{{ dateTime(shift.opened_at) }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <template v-if="shift.closed_by">
                                            {{ shift.closed_by.name }}
                                            <div class="text-xs text-gray-400 dark:text-slate-500">{{ dateTime(shift.closed_at) }}</div>
                                        </template>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <!-- من حذف، ولماذا، ومتى — ما يجعل السلّة سجلَّ تدقيق. -->
                                    <td v-if="showingTrashed" class="px-4 py-2">
                                        <div class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ shift.deleted_by ?? '—' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-slate-400">{{ dateTime(shift.deleted_at) }}</div>
                                        <div v-if="shift.deletion_reason" class="mt-0.5 max-w-xs text-xs text-gray-600 dark:text-slate-300">
                                            {{ shift.deletion_reason }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <div class="flex items-center gap-3">
                                            <Link
                                                v-if="!shift.deleted_at"
                                                :href="route('shifts.show', shift.id)"
                                                class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                عرض
                                            </Link>
                                            <button
                                                v-if="shift.can?.delete"
                                                type="button"
                                                @click="askDelete(shift)"
                                                class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400"
                                            >
                                                حذف
                                            </button>
                                            <button
                                                v-if="shift.can?.restore"
                                                type="button"
                                                @click="restore(shift)"
                                                class="text-xs font-semibold text-emerald-600 hover:underline dark:text-emerald-400"
                                            >
                                                استعادة
                                            </button>
                                            <!-- الوردية المفتوحة تُغلق قبل أن تُحذف. -->
                                            <span
                                                v-else-if="showRowActions && !shift.deleted_at && shift.status === 'open'"
                                                class="text-xs text-gray-400 dark:text-slate-500"
                                                title="أغلق الوردية قبل حذفها"
                                            >
                                                مفتوحة
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- بطاقات على الجوال: الجدول ذو الستة أعمدة لا يُقرأ على شاشة ضيّقة. -->
                    <div class="divide-y divide-gray-100 sm:hidden dark:divide-slate-700">
                        <div v-if="rows.length === 0" class="px-4 py-10 text-center">
                            <p class="text-gray-400 dark:text-slate-500">لا توجد ورديات بعد.</p>
                            <Link :href="route('shifts.create')" class="mt-2 inline-block text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                                فتح أول وردية
                            </Link>
                        </div>
                        <component
                            :is="shift.deleted_at ? 'div' : Link"
                            v-for="shift in rows"
                            :key="shift.id"
                            :href="shift.deleted_at ? undefined : route('shifts.show', shift.id)"
                            class="block px-4 py-3 transition"
                            :class="[
                                shift.deleted_at ? 'bg-rose-50/40 dark:bg-rose-900/10' : 'hover:bg-gray-50 dark:hover:bg-slate-700/40',
                                isMine(shift) ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : '',
                            ]"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-gray-800 dark:text-white">{{ shift.business_date }}</span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="shift.status === 'open'
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30'
                                        : 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30'"
                                >
                                    {{ shift.status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                {{ shift.opened_by?.name ?? '—' }} · {{ shift.exchange_transactions_count ?? 0 }} عملية
                            </div>
                            <div v-if="shift.deleted_at" class="mt-1 text-xs text-rose-700 dark:text-rose-300">
                                حُذفت بواسطة {{ shift.deleted_by ?? '—' }} · {{ dateTime(shift.deleted_at) }}
                                <span v-if="shift.deletion_reason"> — {{ shift.deletion_reason }}</span>
                            </div>
                            <div v-if="shift.can?.delete || shift.can?.restore" class="mt-2 flex gap-3">
                                <button
                                    v-if="shift.can?.delete"
                                    type="button"
                                    @click.prevent="askDelete(shift)"
                                    class="text-xs font-semibold text-rose-600 dark:text-rose-400"
                                >
                                    حذف
                                </button>
                                <button
                                    v-if="shift.can?.restore"
                                    type="button"
                                    @click.prevent="restore(shift)"
                                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    استعادة
                                </button>
                            </div>
                        </component>
                    </div>
                </div>

                <!-- الترقيم: الخادم يُرجع 20 وردية في الصفحة، ولم تكن بقية الصفحات قابلة للوصول. -->
                <Pagination :meta="shifts" />
            </div>
        </div>

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
                <div v-if="pendingDelete" class="space-y-0.5 text-xs text-gray-700 dark:text-slate-200">
                    <div>الوردية #{{ pendingDelete.id }} · {{ pendingDelete.business_date }}</div>
                    <div>صاحبها: {{ pendingDelete.opened_by?.name ?? '—' }}</div>
                    <div class="font-semibold">
                        ستُحذف معها {{ pendingDelete.exchange_transactions_count ?? 0 }} عملية.
                    </div>
                </div>
            </template>
        </DeleteWithReasonDialog>
    </AuthenticatedLayout>
</template>
