<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PermissionMatrix from '@/Components/PermissionMatrix.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    targetUser: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    permissionGroups: { type: Array, default: () => [] },
    // كل الصلاحيات السارية (موروثة من الأدوار + مباشرة)
    effectivePermissions: { type: Array, default: () => [] },
    // المسنَدة لهذا المستخدم مباشرةً، خارج أدواره
    directPermissions: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    activity: { type: Array, default: () => [] },
    sessions: { type: Array, default: () => [] },
    tokens: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const { dateTime } = useFormat();

const isTrashed = computed(() => Boolean(props.targetUser.deleted_at));

/* ------------------------------ الحالة ------------------------------ */

const statusForm = useForm({ status: props.targetUser.status, reason: '' });
const changingStatus = ref(false);

function openStatus(status) {
    statusForm.status = status;
    statusForm.reason = '';
    statusForm.clearErrors();
    changingStatus.value = true;
}

function submitStatus() {
    statusForm.put(route('users.status', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => (changingStatus.value = false),
    });
}

/* -------------------------- الأدوار والصلاحيات -------------------------- */

const accessForm = useForm({
    roles: [...props.targetUser.roles],
    permissions: [...props.directPermissions],
});

// ما يأتي من الأدوار لا من الإسناد المباشر — يُعرض مقفلاً ليعرف المدير مصدره.
const inheritedPermissions = computed(() =>
    props.effectivePermissions.filter((p) => !props.directPermissions.includes(p)),
);

function toggleRole(name) {
    const i = accessForm.roles.indexOf(name);
    i === -1 ? accessForm.roles.push(name) : accessForm.roles.splice(i, 1);
}

function submitAccess() {
    accessForm.put(route('users.access', props.targetUser.id), { preserveScroll: true });
}

/* ---------------------------- كلمة المرور ---------------------------- */

const passwordForm = useForm({ password: '', password_confirmation: '', must_change_password: true });

function submitPassword() {
    passwordForm.put(route('users.password', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

/* ------------------------ الجلسات والأجهزة ------------------------ */

const confirmingSessions = ref(false);
const confirmingTokens = ref(false);
const revoking = ref(false);

function revokeSessions() {
    revoking.value = true;
    router.delete(route('users.sessions.revoke', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => (confirmingSessions.value = false),
        onFinish: () => (revoking.value = false),
    });
}

function revokeTokens() {
    revoking.value = true;
    router.delete(route('users.tokens.revoke', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => (confirmingTokens.value = false),
        onFinish: () => (revoking.value = false),
    });
}

/* ------------------------------ الحذف ------------------------------ */

const confirmingDelete = ref(false);
const deleting = ref(false);

function destroyUser() {
    deleting.value = true;
    router.delete(route('users.destroy', props.targetUser.id), {
        onFinish: () => (deleting.value = false),
    });
}

function restoreUser() {
    router.post(route('users.restore', props.targetUser.id), {}, { preserveScroll: true });
}

/* ------------------------------ عرض ------------------------------ */

const details = computed(() => [
    { label: 'البريد الإلكتروني', value: props.targetUser.email, dir: 'ltr' },
    { label: 'الهاتف', value: props.targetUser.phone, dir: 'ltr' },
    { label: 'الرقم الوظيفي', value: props.targetUser.employee_no },
    { label: 'أنشأه', value: props.targetUser.created_by },
    { label: 'تاريخ الإنشاء', value: dateTime(props.targetUser.created_at) },
    { label: 'آخر دخول', value: props.targetUser.last_login_at ? dateTime(props.targetUser.last_login_at) : 'لم يدخل بعد' },
    { label: 'عنوان آخر دخول', value: props.targetUser.last_login_ip, dir: 'ltr' },
    {
        label: 'آخر تغيير لكلمة المرور',
        value: props.targetUser.password_changed_at ? dateTime(props.targetUser.password_changed_at) : '—',
    },
]);

const statusTones = {
    active: 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30',
    suspended: 'border-rose-300 text-rose-700 hover:bg-rose-50 dark:border-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/30',
    inactive: 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700',
};
</script>

<template>
    <Head :title="targetUser.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="route('users.index')" class="shrink-0 text-gray-400 transition hover:text-gray-600 dark:hover:text-slate-200" aria-label="رجوع إلى المستخدمين">
                        <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <h2 class="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-white">{{ targetUser.name }}</h2>
                    <StatusBadge :status="targetUser.status" :label="targetUser.status_label" />
                    <span v-if="isTrashed" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                        محذوف
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="can.update && !isTrashed"
                        :href="route('users.edit', targetUser.id)"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        تعديل البيانات
                    </Link>
                    <button
                        v-if="isTrashed && can.delete"
                        @click="restoreUser"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        استعادة
                    </button>
                    <button
                        v-else-if="can.delete"
                        @click="confirmingDelete = true"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
                    >
                        حذف المستخدم
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <!-- إيقاف الحساب: السبب أول ما يُسأل عنه لاحقاً، فيُعرض هنا لا في سجل بعيد. -->
                <div
                    v-if="targetUser.status === 'suspended' && targetUser.suspended_reason"
                    class="rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-3 text-sm dark:border-rose-800/50 dark:bg-rose-900/15"
                >
                    <p class="font-semibold text-rose-900 dark:text-rose-200">الحساب موقوف</p>
                    <p class="mt-0.5 text-rose-800 dark:text-rose-300">{{ targetUser.suspended_reason }}</p>
                    <p v-if="targetUser.suspended_at" class="mt-0.5 text-xs text-rose-700/80 dark:text-rose-400">
                        منذ {{ dateTime(targetUser.suspended_at) }}
                    </p>
                </div>

                <div
                    v-if="targetUser.must_change_password"
                    class="rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/15 dark:text-amber-200"
                >
                    مطلوب من هذا المستخدم تغيير كلمة المرور عند الدخول القادم.
                </div>

                <!-- نشاط المستخدم في أرقام. -->
                <dl class="grid gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 text-sm dark:border-slate-700 dark:bg-slate-700 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">إجمالي العمليات</dt>
                        <dd class="mt-0.5 text-lg font-bold text-gray-800 dark:text-white">{{ stats.transactions ?? 0 }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">عمليات اليوم</dt>
                        <dd class="mt-0.5 text-lg font-bold text-gray-800 dark:text-white">{{ stats.transactions_today ?? 0 }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">عدد الورديات</dt>
                        <dd class="mt-0.5 text-lg font-bold text-gray-800 dark:text-white">{{ stats.shifts ?? 0 }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-slate-800">
                        <dt class="text-xs text-gray-500 dark:text-slate-400">الوردية المفتوحة</dt>
                        <dd v-if="stats.open_shift" class="mt-0.5">
                            <Link :href="route('shifts.show', stats.open_shift.id)" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">
                                #{{ stats.open_shift.id }} — {{ stats.open_shift.business_date }}
                            </Link>
                        </dd>
                        <dd v-else class="mt-0.5 text-lg font-bold text-gray-300 dark:text-slate-600">—</dd>
                    </div>
                </dl>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">بيانات الحساب</h3>
                    </div>
                    <dl class="grid gap-x-6 gap-y-3 px-4 py-4 text-sm sm:grid-cols-2">
                        <div v-for="row in details" :key="row.label" class="flex flex-col">
                            <dt class="text-xs text-gray-500 dark:text-slate-400">{{ row.label }}</dt>
                            <dd class="mt-0.5 text-gray-800 dark:text-slate-100" :dir="row.dir">{{ row.value || '—' }}</dd>
                        </div>
                        <div class="flex flex-col sm:col-span-2">
                            <dt class="text-xs text-gray-500 dark:text-slate-400">الأدوار</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="role in targetUser.roles"
                                    :key="role"
                                    class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/30"
                                >
                                    {{ role }}
                                </span>
                                <span v-if="!targetUser.roles.length" class="text-xs text-gray-400">بلا دور</span>
                            </dd>
                        </div>
                        <div v-if="targetUser.notes" class="flex flex-col sm:col-span-2">
                            <dt class="text-xs text-gray-500 dark:text-slate-400">ملاحظات</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-gray-800 dark:text-slate-100">{{ targetUser.notes }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- تغيير الحالة -->
                <div v-if="can.changeStatus && !isTrashed" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">حالة الحساب</h3>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 px-4 py-4">
                        <button
                            v-for="(label, value) in statuses"
                            :key="value"
                            type="button"
                            :disabled="value === targetUser.status"
                            @click="openStatus(value)"
                            class="rounded-md border px-4 py-2 text-sm font-semibold transition disabled:cursor-default disabled:opacity-40"
                            :class="statusTones[value]"
                        >
                            {{ label }}
                            <span v-if="value === targetUser.status" class="text-xs font-normal">(الحالية)</span>
                        </button>
                    </div>
                </div>

                <!-- الأدوار والصلاحيات -->
                <div v-if="can.manageRoles && !isTrashed" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">الأدوار والصلاحيات</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                            الصلاحيات المؤشَّرة والمقفلة موروثة من الأدوار؛ ما تحته يُسنَد لهذا المستخدم مباشرةً.
                        </p>
                    </div>
                    <form class="space-y-4 px-4 py-4" @submit.prevent="submitAccess">
                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="role in roles"
                                :key="role.name"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition"
                                :class="accessForm.roles.includes(role.name)
                                    ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:border-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-200'
                                    : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    :checked="accessForm.roles.includes(role.name)"
                                    @change="toggleRole(role.name)"
                                />
                                <span class="font-semibold">{{ role.label }}</span>
                                <span class="text-xs text-gray-400">{{ role.permissions_count }}</span>
                            </label>
                        </div>
                        <p v-if="accessForm.errors.roles" class="text-xs font-semibold text-rose-600 dark:text-rose-400">{{ accessForm.errors.roles }}</p>

                        <PermissionMatrix
                            :groups="permissionGroups"
                            v-model="accessForm.permissions"
                            :inherited="inheritedPermissions"
                        />

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="accessForm.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ accessForm.processing ? 'جارٍ الحفظ…' : 'حفظ الأدوار والصلاحيات' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- كلمة المرور -->
                <div v-if="can.resetPassword && !isTrashed" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">إعادة تعيين كلمة المرور</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">تعيين كلمة جديدة يُنهي كل جلسات المستخدم النشطة.</p>
                    </div>
                    <form class="grid gap-3 px-4 py-4 sm:grid-cols-2" @submit.prevent="submitPassword">
                        <div>
                            <label for="new-password" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">كلمة المرور الجديدة</label>
                            <input
                                id="new-password"
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label for="new-password-confirm" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">تأكيد كلمة المرور</label>
                            <input
                                id="new-password-confirm"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            />
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-slate-200 sm:col-span-2">
                            <input
                                v-model="passwordForm.must_change_password"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                            />
                            إلزامه بتغييرها عند أول دخول
                        </label>
                        <div class="flex justify-end sm:col-span-2">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ passwordForm.processing ? 'جارٍ الحفظ…' : 'تعيين كلمة المرور' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- الجلسات والأجهزة -->
                <div v-if="can.manageSessions" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">
                            الجلسات النشطة <span class="text-sm font-normal text-gray-400">({{ sessions.length }})</span>
                        </h3>
                        <button
                            v-if="sessions.length"
                            @click="confirmingSessions = true"
                            class="text-sm font-semibold text-rose-600 hover:underline dark:text-rose-400"
                        >
                            إنهاء كل الجلسات
                        </button>
                    </div>
                    <div v-if="sessions.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-slate-500">لا توجد جلسات نشطة.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">الجهاز</th>
                                    <th class="px-4 py-2 text-start">العنوان</th>
                                    <th class="px-4 py-2 text-start">آخر نشاط</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-for="s in sessions" :key="s.id">
                                    <td class="px-4 py-2">
                                        {{ s.device }}
                                        <span v-if="s.is_current" class="ms-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            الجلسة الحالية
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs" dir="ltr">{{ s.ip_address ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-slate-400">{{ dateTime(s.last_active_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 border-y border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">
                            رموز تطبيق الجوال <span class="text-sm font-normal text-gray-400">({{ tokens.length }})</span>
                        </h3>
                        <button
                            v-if="tokens.length"
                            @click="confirmingTokens = true"
                            class="text-sm font-semibold text-rose-600 hover:underline dark:text-rose-400"
                        >
                            إلغاء كل الرموز
                        </button>
                    </div>
                    <div v-if="tokens.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-slate-500">لا توجد رموز.</div>
                    <ul v-else class="divide-y divide-gray-100 dark:divide-slate-700">
                        <li v-for="t in tokens" :key="t.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm dark:text-slate-200">
                            <span class="font-medium">{{ t.name }}</span>
                            <span class="text-xs text-gray-500 dark:text-slate-400">
                                آخر استخدام: {{ t.last_used_at ? dateTime(t.last_used_at) : 'لم يُستخدم' }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- سجل النشاط على هذا الحساب -->
                <div v-if="can.viewActivity" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white">آخر ما جرى على هذا الحساب</h3>
                        <Link :href="route('activity.index', { causer_id: targetUser.id })" class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                            سجل نشاطه الكامل
                        </Link>
                    </div>
                    <div v-if="activity.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-slate-500">لا توجد سجلات.</div>
                    <ul v-else class="divide-y divide-gray-100 dark:divide-slate-700">
                        <li v-for="log in activity" :key="log.id" class="px-4 py-2.5 text-sm">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-medium text-gray-800 dark:text-slate-100">{{ log.description }}</span>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-slate-500">{{ dateTime(log.created_at) }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                                {{ log.event_label }}
                                <span v-if="log.causer"> · بواسطة {{ log.causer }}</span>
                                <span v-if="log.ip_address"> · <span dir="ltr">{{ log.ip_address }}</span></span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- تغيير الحالة: الإيقاف يتطلّب سبباً، فيُطلب في الحوار نفسه. -->
        <ConfirmDialog
            :show="changingStatus"
            :title="`تغيير الحالة إلى «${statuses[statusForm.status] ?? statusForm.status}»`"
            :message="statusForm.status === 'suspended'
                ? 'لن يتمكّن المستخدم من تسجيل الدخول، وستُنهى جلساته.'
                : 'سيتم تحديث حالة الحساب.'"
            :confirm-label="`تغيير إلى ${statuses[statusForm.status] ?? statusForm.status}`"
            :tone="statusForm.status === 'suspended' ? 'danger' : 'primary'"
            :processing="statusForm.processing"
            @confirm="submitStatus"
            @cancel="changingStatus = false"
        >
            <template #details>
                <label for="status-reason" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">
                    السبب
                    <span v-if="statusForm.status === 'suspended'" class="text-rose-600 dark:text-rose-400">*</span>
                    <span v-else class="font-normal text-gray-400">(اختياري)</span>
                </label>
                <textarea
                    id="status-reason"
                    v-model="statusForm.reason"
                    rows="2"
                    :disabled="statusForm.processing"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                ></textarea>
                <p v-if="statusForm.errors.reason" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ statusForm.errors.reason }}</p>
                <p v-if="statusForm.errors.status" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ statusForm.errors.status }}</p>
            </template>
        </ConfirmDialog>

        <ConfirmDialog
            :show="confirmingSessions"
            title="إنهاء كل الجلسات"
            message="سيُخرج المستخدم من كل أجهزته ويحتاج إلى تسجيل الدخول من جديد."
            confirm-label="إنهاء الجلسات"
            :processing="revoking"
            @confirm="revokeSessions"
            @cancel="confirmingSessions = false"
        />

        <ConfirmDialog
            :show="confirmingTokens"
            title="إلغاء رموز تطبيق الجوال"
            message="سيتوقّف تطبيق الجوال لدى هذا المستخدم حتى يسجّل الدخول مرة أخرى."
            confirm-label="إلغاء الرموز"
            :processing="revoking"
            @confirm="revokeTokens"
            @cancel="confirmingTokens = false"
        />

        <ConfirmDialog
            :show="confirmingDelete"
            title="حذف المستخدم"
            :message="`سيتم حذف ${targetUser.name}. تبقى عملياته وورديّاته السابقة محفوظة منسوبةً إليه.`"
            confirm-label="حذف المستخدم"
            :processing="deleting"
            @confirm="destroyUser"
            @cancel="confirmingDelete = false"
        >
            <template #details>
                <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-gray-500 dark:text-slate-400">العمليات المسجّلة باسمه</span>
                    <span class="font-semibold text-gray-800 dark:text-slate-100">{{ stats.transactions ?? 0 }}</span>
                </div>
                <div v-if="stats.open_shift" class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-400">
                    تنبيه: لديه وردية مفتوحة (#{{ stats.open_shift.id }}).
                </div>
            </template>
        </ConfirmDialog>
    </AuthenticatedLayout>
</template>
