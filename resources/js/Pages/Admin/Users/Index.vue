<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    openShiftOwners: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const { dateTime } = useFormat();

const form = reactive({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
    trashed: props.filters.trashed ?? false,
});

const selected = ref([]);
const bulkAction = ref('');
const bulkReason = ref('');

const openShiftSet = computed(() => new Set(props.openShiftOwners));

// المستخدمون الذين تسمح صلاحياتنا بالتصرّف بهم — نبني عليهم التحديد الجماعي.
const actionable = computed(() =>
    props.users.data.filter((u) => u.can.changeStatus || u.can.delete),
);

const allSelected = computed(
    () => actionable.value.length > 0 && selected.value.length === actionable.value.length,
);

// أي تغيير في الفلاتر يُلغي تحديداً ربما لم يعد معروضاً أصلاً.
watch(() => props.users, () => { selected.value = []; });

let searchTimer;
watch(
    () => form.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 350);
    },
);

function applyFilters() {
    router.get(route('users.index'), { ...form, sort: props.filters.sort, direction: props.filters.direction }, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    form.search = '';
    form.role = '';
    form.status = '';
    form.trashed = false;
    applyFilters();
}

function sortBy(column) {
    const direction =
        props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';

    router.get(route('users.index'), { ...form, sort: column, direction }, {
        preserveState: true,
        replace: true,
    });
}

function sortIcon(column) {
    if (props.filters.sort !== column) return '';
    return props.filters.direction === 'asc' ? '↑' : '↓';
}

function toggleAll(event) {
    selected.value = event.target.checked ? actionable.value.map((u) => u.id) : [];
}

function destroy(user) {
    if (confirm(`سيتم حذف المستخدم ${user.name}. تبقى عملياته وورديّاته السابقة محفوظة. متابعة؟`)) {
        router.delete(route('users.destroy', user.id), { preserveScroll: true });
    }
}

function restore(user) {
    router.post(route('users.restore', user.id), {}, { preserveScroll: true });
}

function quickStatus(user, status) {
    router.put(
        route('users.status', user.id),
        { status, reason: status === 'suspended' ? 'إيقاف سريع من قائمة المستخدمين' : null },
        { preserveScroll: true },
    );
}

const bulkLabels = {
    activate: 'تفعيل',
    suspend: 'إيقاف',
    deactivate: 'تعطيل',
    delete: 'حذف',
};

function runBulk() {
    if (!bulkAction.value || selected.value.length === 0) return;

    const label = bulkLabels[bulkAction.value];

    if (!confirm(`سيتم تنفيذ «${label}» على ${selected.value.length} مستخدم. متابعة؟`)) return;

    router.post(
        route('users.bulk'),
        { action: bulkAction.value, ids: selected.value, reason: bulkReason.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                bulkAction.value = '';
                bulkReason.value = '';
            },
        },
    );
}

const statCards = computed(() => [
    { key: 'total', label: 'إجمالي المستخدمين', value: props.stats.total, tone: 'text-slate-900 dark:text-white' },
    { key: 'active', label: 'نشطون', value: props.stats.active, tone: 'text-emerald-600 dark:text-emerald-400' },
    { key: 'suspended', label: 'موقوفون', value: props.stats.suspended, tone: 'text-rose-600 dark:text-rose-400' },
    { key: 'open_shifts', label: 'ورديات مفتوحة الآن', value: props.stats.open_shifts, tone: 'text-indigo-600 dark:text-indigo-400' },
]);
</script>

<template>
    <Head title="المستخدمون" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">إدارة المستخدمين</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="can.manageRoles"
                        :href="route('roles.index')"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        الأدوار والصلاحيات
                    </Link>
                    <Link
                        v-if="can.viewActivity"
                        :href="route('activity.index')"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        سجل النشاط
                    </Link>
                    <Link
                        v-if="can.create"
                        :href="route('users.create')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        + مستخدم جديد
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- بطاقات موجزة -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div
                        v-for="card in statCards"
                        :key="card.key"
                        class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                    >
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                        <p class="mt-1 text-2xl font-bold" :class="card.tone">{{ card.value ?? 0 }}</p>
                    </div>
                </div>

                <!-- الفلاتر -->
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <input
                            v-model="form.search"
                            type="search"
                            placeholder="بحث بالاسم أو البريد أو الرقم الوظيفي"
                            class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500"
                        />
                        <select
                            v-model="form.role"
                            class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">كل الأدوار</option>
                            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <select
                            v-model="form.status"
                            class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">كل الحالات</option>
                            <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <div class="flex items-center gap-3">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input
                                    v-model="form.trashed"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-800"
                                    @change="applyFilters"
                                />
                                المحذوفون ({{ stats.trashed ?? 0 }})
                            </label>
                            <button
                                type="button"
                                class="text-sm text-slate-500 hover:underline dark:text-slate-400"
                                @click="resetFilters"
                            >
                                إعادة تعيين
                            </button>
                        </div>
                    </div>
                </div>

                <!-- شريط الإجراءات الجماعية -->
                <div
                    v-if="selected.length > 0"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/10"
                >
                    <span class="text-sm font-medium text-indigo-900 dark:text-indigo-200">
                        محدَّد: {{ selected.length }}
                    </span>
                    <select
                        v-model="bulkAction"
                        class="rounded-md border-indigo-200 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                    >
                        <option value="">اختر إجراءً…</option>
                        <option value="activate">تفعيل</option>
                        <option value="suspend">إيقاف</option>
                        <option value="deactivate">تعطيل</option>
                        <option value="delete">حذف</option>
                    </select>
                    <input
                        v-if="bulkAction === 'suspend'"
                        v-model="bulkReason"
                        type="text"
                        placeholder="سبب الإيقاف"
                        class="rounded-md border-indigo-200 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                    />
                    <button
                        type="button"
                        :disabled="!bulkAction"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40"
                        @click="runBulk"
                    >
                        تنفيذ
                    </button>
                    <button
                        type="button"
                        class="text-sm text-slate-500 hover:underline dark:text-slate-400"
                        @click="selected = []"
                    >
                        إلغاء التحديد
                    </button>
                </div>

                <!-- الجدول -->
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="w-10 px-4 py-2">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-800"
                                            :checked="allSelected"
                                            :disabled="actionable.length === 0"
                                            @change="toggleAll"
                                        />
                                    </th>
                                    <th class="cursor-pointer px-4 py-2 text-start hover:text-indigo-600" @click="sortBy('name')">
                                        الاسم {{ sortIcon('name') }}
                                    </th>
                                    <th class="cursor-pointer px-4 py-2 text-start hover:text-indigo-600" @click="sortBy('email')">
                                        البريد الإلكتروني {{ sortIcon('email') }}
                                    </th>
                                    <th class="px-4 py-2 text-start">الأدوار</th>
                                    <th class="cursor-pointer px-4 py-2 text-start hover:text-indigo-600" @click="sortBy('status')">
                                        الحالة {{ sortIcon('status') }}
                                    </th>
                                    <th class="px-4 py-2 text-start">العمليات</th>
                                    <th class="cursor-pointer px-4 py-2 text-start hover:text-indigo-600" @click="sortBy('last_login_at')">
                                        آخر دخول {{ sortIcon('last_login_at') }}
                                    </th>
                                    <th class="px-4 py-2 text-start">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-if="users.data.length === 0">
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 dark:text-slate-500">
                                        لا يوجد مستخدمون مطابقون للبحث.
                                    </td>
                                </tr>

                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-900/40"
                                    :class="user.deleted_at ? 'opacity-60' : ''"
                                >
                                    <td class="px-4 py-2">
                                        <input
                                            v-if="user.can.changeStatus || user.can.delete"
                                            v-model="selected"
                                            type="checkbox"
                                            :value="user.id"
                                            class="rounded border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-800"
                                        />
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-xs font-bold text-white">
                                                {{ (user.name || '?').trim().charAt(0).toUpperCase() }}
                                            </span>
                                            <div class="min-w-0">
                                                <component
                                                    :is="user.deleted_at ? 'span' : Link"
                                                    v-bind="user.deleted_at ? {} : { href: route('users.show', user.id) }"
                                                    class="block truncate font-medium"
                                                    :class="user.deleted_at ? '' : 'text-indigo-600 hover:underline dark:text-indigo-400'"
                                                >
                                                    {{ user.name }}
                                                </component>
                                                <span v-if="user.employee_no" class="block text-xs text-slate-400">
                                                    رقم وظيفي: {{ user.employee_no }}
                                                </span>
                                            </div>
                                            <span
                                                v-if="user.is_self"
                                                class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-700 dark:text-slate-300"
                                            >
                                                أنت
                                            </span>
                                            <NavIcon
                                                v-if="openShiftSet.has(user.id)"
                                                name="clock"
                                                class="h-4 w-4 shrink-0 text-emerald-500"
                                                title="وردية مفتوحة الآن"
                                            />
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span class="block truncate">{{ user.email }}</span>
                                        <span v-if="user.phone" class="block text-xs text-slate-400">{{ user.phone }}</span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span
                                            v-for="role in user.roles"
                                            :key="role"
                                            class="me-1 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                                        >
                                            {{ role }}
                                        </span>
                                        <span v-if="user.roles.length === 0" class="text-xs text-slate-400">—</span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <StatusBadge v-if="!user.deleted_at" :status="user.status" :label="user.status_label" />
                                        <span v-else class="text-xs font-medium text-rose-500">محذوف</span>
                                    </td>

                                    <td class="px-4 py-2 tabular-nums">{{ user.transactions_count ?? 0 }}</td>

                                    <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400">
                                        {{ user.last_login_at ? dateTime(user.last_login_at) : 'لم يسجّل دخولاً' }}
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            <button
                                                v-if="user.can.restore"
                                                class="font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                                                @click="restore(user)"
                                            >
                                                استعادة
                                            </button>

                                            <template v-if="!user.deleted_at">
                                                <Link
                                                    :href="route('users.show', user.id)"
                                                    class="font-medium text-slate-600 hover:underline dark:text-slate-300"
                                                >
                                                    عرض
                                                </Link>
                                                <Link
                                                    v-if="user.can.update"
                                                    :href="route('users.edit', user.id)"
                                                    class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                >
                                                    تعديل
                                                </Link>
                                                <button
                                                    v-if="user.can.changeStatus && user.status === 'active'"
                                                    class="font-medium text-amber-600 hover:underline dark:text-amber-400"
                                                    @click="quickStatus(user, 'suspended')"
                                                >
                                                    إيقاف
                                                </button>
                                                <button
                                                    v-else-if="user.can.changeStatus"
                                                    class="font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                                                    @click="quickStatus(user, 'active')"
                                                >
                                                    تفعيل
                                                </button>
                                                <button
                                                    v-if="user.can.delete"
                                                    class="font-medium text-rose-600 hover:underline dark:text-rose-400"
                                                    @click="destroy(user)"
                                                >
                                                    حذف
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :meta="users" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
