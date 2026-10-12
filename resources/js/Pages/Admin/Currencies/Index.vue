<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import NavIcon from '@/Components/NavIcon.vue';
import ImportSpTodayModal from './ImportSpTodayModal.vue';
import CreateCurrencyModal from './CreateCurrencyModal.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    spToday: { type: Object, default: () => ({ success: false, rates: {} }) },
    basePreview: { type: Object, default: null },
});

const { number, rate } = useFormat();

const currentBase = computed(() => props.currencies.find((c) => c.is_base) ?? null);
const candidates = computed(() => props.currencies.filter((c) => !c.is_base && c.is_active));

/** عملة بلا سعر لا يمكن تسجيل أي عملية عليها — يرفضها الخادم. */
const missingRates = computed(() =>
    props.currencies.filter((c) => !c.is_base && c.is_active && !c.exchange_rate),
);

// النوافذ المنبثقة
const showImportModal = ref(false);
const showCreateModal = ref(false);

// حالات التحميل والعمليات السريعة
const togglingId = ref(null);
const addingCode = ref(null);

// التحديد الجماعي
const selectedIds = ref({});

/* -------------------------- مؤشر الليرة اليوم (sp-today) ------------------------- */

const spTodayRatesList = computed(() => {
    if (!props.spToday?.rates) return [];
    return Object.entries(props.spToday.rates).map(([code, item]) => {
        const systemCurrency = props.currencies.find(
            (c) => !c.tag && c.code.toUpperCase() === code.toUpperCase()
        );
        return {
            code,
            ...item,
            inSystem: Boolean(systemCurrency),
            systemCurrency,
            isActive: systemCurrency ? systemCurrency.is_active : false,
            isBase: systemCurrency ? systemCurrency.is_base : false,
        };
    });
});

const missingFromSystemList = computed(() => {
    return spTodayRatesList.value.filter((i) => !i.inSystem);
});

/* ------------------------------ التبويبات والتصفية ----------------------------- */

const activeTab = ref('system'); // 'system' | 'sptoday'
const search = ref('');
const statusFilter = ref('all');

const filters = computed(() => [
    { value: 'all', label: 'الكل' },
    { value: 'active', label: `المفعّلة (${props.currencies.filter(c => c.is_active).length})` },
    { value: 'inactive', label: `المعطّلة (${props.currencies.filter(c => !c.is_active).length})` },
    { value: 'norate', label: `بلا سعر (${missingRates.value.length})` },
]);

const visibleSystemCurrencies = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.currencies.filter((c) => {
        if (statusFilter.value === 'active' && !c.is_active) return false;
        if (statusFilter.value === 'inactive' && c.is_active) return false;
        if (statusFilter.value === 'norate' && (c.is_base || c.exchange_rate)) return false;

        if (!term) return true;

        return [c.code, c.name, c.symbol, c.tag].some((field) =>
            String(field ?? '').toLowerCase().includes(term),
        );
    });
});

const visibleSpTodayCurrencies = computed(() => {
    const term = search.value.trim().toLowerCase();

    return spTodayRatesList.value.filter((item) => {
        if (statusFilter.value === 'active' && (!item.inSystem || !item.isActive)) return false;
        if (statusFilter.value === 'inactive' && (!item.inSystem || item.isActive)) return false;
        if (statusFilter.value === 'norate' && item.inSystem && (item.isBase || item.systemCurrency?.exchange_rate)) return false;

        if (!term) return true;

        return [item.code, item.name_ar].some((field) =>
            String(field ?? '').toLowerCase().includes(term),
        );
    });
});

// العمليات السريعة للعملات
function toggleCurrency(currency) {
    if (currency.is_base || togglingId.value) return;

    togglingId.value = currency.id;
    router.post(
        route('currencies.toggle', currency.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                togglingId.value = null;
            },
        }
    );
}

function quickAddFromSpToday(code) {
    if (addingCode.value) return;

    addingCode.value = code;
    router.post(
        route('currencies.import-sp-today'),
        {
            codes: [code],
            include_rates: true,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                addingCode.value = null;
            },
        }
    );
}

// العمليات الجماعية
const selectedList = computed(() => {
    return Object.entries(selectedIds.value)
        .filter(([_, checked]) => checked)
        .map(([id]) => Number(id));
});

const allVisibleSelected = computed(() => {
    const nonBase = visibleSystemCurrencies.value.filter((c) => !c.is_base);
    return nonBase.length > 0 && nonBase.every((c) => selectedIds.value[c.id]);
});

function toggleSelectAllVisible() {
    const shouldSelect = !allVisibleSelected.value;
    visibleSystemCurrencies.value.forEach((c) => {
        if (!c.is_base) {
            selectedIds.value[c.id] = shouldSelect;
        }
    });
}

function batchToggle(isActive) {
    if (selectedList.value.length === 0) return;

    router.post(
        route('currencies.batch-status'),
        {
            currency_ids: selectedList.value,
            is_active: isActive,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = {};
            },
        }
    );
}

/* --------------------------- تغيير العملة الأساس --------------------------- */

const panelOpen = ref(Boolean(props.basePreview));
const targetId = ref(props.basePreview?.target?.id ?? candidates.value[0]?.id ?? null);
const mode = ref(props.basePreview?.mode ?? 'convert');

const modes = [
    {
        value: 'convert',
        label: 'حوّل الأسعار حسابياً',
        hint: 'الأسعار الحالية مُقوّمة فعلاً بالعملة الأساس الحالية، فتُقسَم على سعر العملة الجديدة.',
    },
    {
        value: 'relabel',
        label: 'الأسعار مُدخلة بالعملة الجديدة أصلاً',
        hint: 'الأرقام صحيحة كما هي وكان التصنيف فقط خاطئاً — تبقى دون تغيير.',
    },
];

function loadPreview() {
    if (!targetId.value) return;

    router.get(
        route('currencies.index'),
        { base_preview: targetId.value, mode: mode.value },
        { preserveState: true, preserveScroll: true, only: ['basePreview'] },
    );
}

function pickMode(value) {
    mode.value = value;
    loadPreview();
}

const applyForm = useForm({ mode: mode.value });
const blockers = computed(() => props.basePreview?.blockers ?? []);
const canApply = computed(() => Boolean(props.basePreview) && blockers.value.length === 0);
const confirmingApply = ref(false);

const previewImpact = computed(() => {
    const rows = props.basePreview?.rows ?? [];
    return {
        changed: rows.filter((r) => !r.is_new_base && r.after).length,
        needsRate: rows.filter((r) => !r.is_new_base && !r.after).length,
    };
});

function apply() {
    if (!canApply.value) return;

    applyForm.mode = props.basePreview.mode;
    applyForm.post(route('currencies.base', props.basePreview.target.id), {
        preserveScroll: true,
        onSuccess: () => (confirmingApply.value = false),
    });
}

function rateText(row) {
    return row ? `${rate(row.buy)} / ${rate(row.sell)}` : '—';
}
</script>

<template>
    <Head title="إدارة العملات" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <NavIcon name="coins" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-black leading-tight text-slate-900 dark:text-white">
                            إدارة العملات
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            التحكم بالعملات وتفعيلها وإلغاء تفعيلها والربط مع مؤشر الليرة اليوم (sp-today)
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- زر استيراد من الليرة اليوم -->
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs shadow-teal-600/20 hover:from-teal-700 hover:to-teal-800 transition focus:outline-none focus:ring-2 focus:ring-teal-500"
                        title="استيراد العملات المتوفرة في السوق الموازي"
                        @click="showImportModal = true"
                    >
                        <NavIcon name="zap" class="h-3.5 w-3.5" />
                        <span>استيراد من الليرة اليوم</span>
                        <span v-if="missingFromSystemList.length" class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] font-black">
                            +{{ missingFromSystemList.length }}
                        </span>
                    </button>

                    <!-- زر عملة مخصصة جديدة -->
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition"
                        @click="showCreateModal = true"
                    >
                        <NavIcon name="plus-circle" class="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400" />
                        <span>+ عملة مخصصة</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                <NewLiraNotice dismiss-key="currencies-index" />

                <!-- إحصائيات سريعة للعملات -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-xs dark:border-slate-800 dark:bg-slate-800/90">
                        <span class="text-xs font-bold text-slate-400">عملات النظام الإجمالية</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-xl font-black text-slate-900 dark:text-white tabular-nums">{{ currencies.length }}</span>
                            <span class="text-[11px] text-slate-400">عملة مسجلة</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/40 p-3.5 shadow-xs dark:border-emerald-800/40 dark:bg-emerald-950/20">
                        <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300">العملات المفعّلة</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-xl font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                                {{ currencies.filter(c => c.is_active).length }}
                            </span>
                            <span class="text-[11px] text-emerald-600/70 dark:text-emerald-400/70">جاهزة للتداول</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-xs dark:border-slate-800 dark:bg-slate-800/90">
                        <span class="text-xs font-bold text-slate-400">العملات المعطّلة</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-xl font-black text-slate-600 dark:text-slate-300 tabular-nums">
                                {{ currencies.filter(c => !c.is_active).length }}
                            </span>
                            <span class="text-[11px] text-slate-400">موقوفة مؤقتاً</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-teal-200/70 bg-teal-50/40 p-3.5 shadow-xs dark:border-teal-800/40 dark:bg-teal-950/20">
                        <span class="text-xs font-bold text-teal-800 dark:text-teal-300">نشرة الليرة اليوم المتاحة</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-xl font-black text-teal-700 dark:text-teal-400 tabular-nums">
                                {{ spTodayRatesList.length }}
                            </span>
                            <span class="text-[11px] text-teal-600/70 dark:text-teal-400/70">
                                ({{ missingFromSystemList.length }} غير مضافة)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- تنبيه العملات التي بدون سعر -->
                <div
                    v-if="missingRates.length"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900 shadow-xs dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200"
                    role="alert"
                >
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200 font-bold">!</span>
                        <span>
                            {{ missingRates.length }} عملة مفعّلة بلا سعر صرف (لن يمكن استخدامها في الصرافة):
                            <strong class="font-black">{{ missingRates.map((c) => c.code).join('، ') }}</strong>
                        </span>
                    </div>
                    <Link :href="route('rates.index')" class="rounded-lg bg-amber-200/70 px-2.5 py-1 text-xs font-bold text-amber-900 hover:bg-amber-200 dark:bg-amber-900/60 dark:text-amber-200 transition">
                        تحديد الأسعار الآن ←
                    </Link>
                </div>

                <!-- لوحة العملة الأساس وتبديلها -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800/90">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                <NavIcon name="wallet" class="h-5 w-5" />
                            </span>
                            <div>
                                <div class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>العملة الأساس:</span>
                                    <span class="rounded-md bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {{ currentBase ? `${currentBase.name} (${currentBase.code})` : 'غير محددة' }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    العملة التي تُقوَّم بها بقية أسعار العملات في النظام ويُحسب إجمالي كل عملية على أساسها.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-xl border border-indigo-200 bg-indigo-50/60 px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                            @click="panelOpen = !panelOpen"
                        >
                            {{ panelOpen ? 'إغلاق اللوحة' : 'تغيير العملة الأساس' }}
                        </button>
                    </div>

                    <div v-if="panelOpen" class="mt-4 space-y-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <div>
                            <label for="base-target" class="block text-xs font-bold text-slate-700 dark:text-slate-300">العملة الأساس الجديدة</label>
                            <select
                                id="base-target"
                                v-model="targetId"
                                class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold shadow-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white sm:max-w-xs"
                                @change="loadPreview"
                            >
                                <option v-for="c in candidates" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.code }}){{ c.tag ? ` — ${c.tag}` : '' }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <span class="block text-xs font-bold text-slate-700 dark:text-slate-300">طريقة معالجة الأسعار</span>
                            <div class="mt-1 grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="m in modes"
                                    :key="m.value"
                                    type="button"
                                    :aria-pressed="mode === m.value"
                                    @click="pickMode(m.value)"
                                    class="rounded-xl border p-3 text-start transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="mode === m.value
                                        ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600 dark:border-indigo-400 dark:bg-indigo-950/40 dark:ring-indigo-400'
                                        : 'border-slate-200 hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-500/60'"
                                >
                                    <span class="block text-xs font-bold text-slate-900 dark:text-white">{{ m.label }}</span>
                                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">{{ m.hint }}</span>
                                </button>
                            </div>
                        </div>

                        <button
                            v-if="!basePreview"
                            type="button"
                            class="rounded-xl bg-slate-800 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-slate-900"
                            @click="loadPreview"
                        >
                            اعرض المعاينة
                        </button>

                        <div v-if="basePreview" class="space-y-3">
                            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                                <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-slate-700">
                                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                                        <tr>
                                            <th class="px-3 py-2 text-start font-bold">العملة</th>
                                            <th class="px-3 py-2 text-start font-bold">السعر الحالي (شراء/بيع)</th>
                                            <th class="px-3 py-2 text-start font-bold">بعد التبديل</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 dark:text-slate-200">
                                        <tr v-for="row in basePreview.rows" :key="`${row.code}-${row.tag ?? ''}`">
                                            <td class="whitespace-nowrap px-3 py-2 font-bold">
                                                {{ row.code }}<span v-if="row.tag" class="text-slate-400"> · {{ row.tag }}</span>
                                                <span v-if="row.is_new_base" class="ms-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                    الأساس الجديد
                                                </span>
                                                <span v-else-if="row.was_base" class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                    الأساس السابق
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 tabular-nums text-slate-500 dark:text-slate-400">{{ rateText(row.before) }}</td>
                                            <td class="px-3 py-2 font-bold tabular-nums">
                                                <span v-if="row.is_new_base" class="text-slate-400">لا سعر (هي الأساس)</span>
                                                <span v-else-if="!row.after" class="text-amber-700 dark:text-amber-400">يلزم إدخال سعر</span>
                                                <span v-else>{{ rateText(row.after) }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-if="blockers.length"
                                class="rounded-xl border border-rose-300 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/25 dark:text-rose-200"
                                role="alert"
                            >
                                <div class="font-bold">لا يمكن التبديل الآن:</div>
                                <ul class="mt-1 list-inside list-disc space-y-0.5">
                                    <li v-for="b in blockers" :key="b">{{ b }}</li>
                                </ul>
                            </div>

                            <button
                                type="button"
                                class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canApply || applyForm.processing"
                                @click="confirmingApply = true"
                            >
                                اعتمد العملة الأساس الجديدة
                            </button>
                        </div>
                    </div>
                </div>

                <!-- شريط التبويبات الرئيسي (Tabs) -->
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2 gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                            :class="activeTab === 'system'
                                ? 'bg-indigo-600 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                            @click="activeTab = 'system'"
                        >
                            <NavIcon name="wallet" class="h-3.5 w-3.5" />
                            <span>عملات النظام المسجلة ({{ currencies.length }})</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                            :class="activeTab === 'sptoday'
                                ? 'bg-teal-600 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                            @click="activeTab = 'sptoday'"
                        >
                            <NavIcon name="trending-up" class="h-3.5 w-3.5" />
                            <span>مؤشر الليرة اليوم ({{ spTodayRatesList.length }} عملة)</span>
                            <span v-if="missingFromSystemList.length" class="rounded-full bg-teal-200 text-teal-900 dark:bg-teal-950 dark:text-teal-200 px-1.5 py-0.2 text-[10px]">
                                {{ missingFromSystemList.length }} جديدة
                            </span>
                        </button>
                    </div>

                    <!-- شريط أدوات التحديد الجماعي عند تحديد عناصر -->
                    <div v-if="activeTab === 'system' && selectedList.length > 0" class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-xl text-xs">
                        <span class="font-bold text-slate-700 dark:text-slate-300">
                            تم تحديد <strong class="text-indigo-600 dark:text-indigo-400">{{ selectedList.length }}</strong>
                        </span>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <button
                            type="button"
                            class="font-bold text-emerald-700 hover:underline dark:text-emerald-400"
                            @click="batchToggle(true)"
                        >
                            تفعيل الكل
                        </button>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <button
                            type="button"
                            class="font-bold text-rose-700 hover:underline dark:text-rose-400"
                            @click="batchToggle(false)"
                        >
                            تعطيل الكل
                        </button>
                    </div>
                </div>

                <!-- صندوق البحث والتصفية -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative flex-1 min-w-[220px]">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="activeTab === 'system' ? 'ابحث بالرمز أو الاسم أو الفئة…' : 'ابحث في نشرة السوق (USD, ريال, يورو)…'"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <span
                            v-if="search"
                            class="absolute end-3 top-1/2 -translate-y-1/2 cursor-pointer text-xs font-bold text-slate-400 hover:text-slate-600"
                            @click="search = ''"
                        >
                            ✕
                        </span>
                    </div>

                    <!-- أزرار التصفية -->
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="f in filters"
                            :key="f.value"
                            type="button"
                            :aria-pressed="statusFilter === f.value"
                            @click="statusFilter = f.value"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-bold transition"
                            :class="statusFilter === f.value
                                ? 'bg-indigo-600 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
                        >
                            {{ f.label }}
                        </button>
                    </div>
                </div>

                <!-- ================== التبويب ١: عملات النظام ================== -->
                <div v-if="activeTab === 'system'" class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs dark:border-slate-700 dark:bg-slate-800/90">
                    <!-- الجدول للشاشات الكبيرة والمتوسطة -->
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-slate-700">
                            <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="w-8 px-3 py-3 text-start">
                                        <input
                                            type="checkbox"
                                            :checked="allVisibleSelected"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                            @change="toggleSelectAllVisible"
                                        />
                                    </th>
                                    <th class="px-4 py-3 text-start font-bold">الرمز</th>
                                    <th class="px-4 py-3 text-start font-bold">الاسم</th>
                                    <th class="px-4 py-3 text-start font-bold">العلامة</th>
                                    <th class="px-4 py-3 text-start font-bold">الفئة</th>
                                    <th class="px-4 py-3 text-start font-bold">السعر (شراء/بيع)</th>
                                    <th class="px-4 py-3 text-start font-bold">النوع</th>
                                    <th class="px-4 py-3 text-start font-bold">الحالة (تفعيل/تعطيل)</th>
                                    <th class="px-4 py-3 text-start font-bold">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 dark:text-slate-200">
                                <tr v-if="visibleSystemCurrencies.length === 0">
                                    <td colspan="9" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                                        لا توجد عملات تطابق خيارات البحث الحالية.
                                    </td>
                                </tr>
                                <tr
                                    v-for="currency in visibleSystemCurrencies"
                                    :key="currency.id"
                                    class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-700/30"
                                    :class="[
                                        currency.is_base ? 'bg-indigo-50/30 dark:bg-indigo-950/20' : '',
                                        currency.is_active ? '' : 'opacity-60 bg-slate-50/40 dark:bg-slate-900/20',
                                    ]"
                                >
                                    <!-- مربع التحديد الجماعي -->
                                    <td class="px-3 py-3">
                                        <input
                                            v-if="!currency.is_base"
                                            v-model="selectedIds[currency.id]"
                                            type="checkbox"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                        />
                                    </td>

                                    <!-- الرمز -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-mono text-xs font-black shadow-2xs dark:bg-slate-700">
                                                {{ currency.symbol || currency.code }}
                                            </span>
                                            <span class="font-black text-slate-900 dark:text-white">{{ currency.code }}</span>
                                        </div>
                                    </td>

                                    <!-- الاسم -->
                                    <td class="px-4 py-3 font-bold text-slate-700 dark:text-slate-300">
                                        {{ currency.name }}
                                    </td>

                                    <!-- العلامة -->
                                    <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">
                                        {{ currency.symbol ?? '—' }}
                                    </td>

                                    <!-- الفئة -->
                                    <td class="px-4 py-3">
                                        <span v-if="currency.tag" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            {{ currency.tag }}
                                        </span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>

                                    <!-- السعر -->
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">
                                        <span v-if="currency.is_base" class="text-slate-400">—</span>
                                        <span v-else-if="currency.exchange_rate" class="font-bold">
                                            {{ rate(currency.exchange_rate.buy_rate) }} / {{ rate(currency.exchange_rate.sell_rate) }}
                                        </span>
                                        <Link v-else :href="route('rates.index')" class="font-bold text-amber-700 underline underline-offset-2 hover:text-amber-800 dark:text-amber-400">
                                            لا سعر
                                        </Link>
                                    </td>

                                    <!-- النوع -->
                                    <td class="px-4 py-3">
                                        <span v-if="currency.is_base" class="whitespace-nowrap rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                            عملة أساس
                                        </span>
                                        <span v-else class="text-slate-400 text-[11px]">عملة أجنبية</span>
                                    </td>

                                    <!-- الحالة مع زر التبديل الفوري (Quick Toggle) -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="!currency.is_base"
                                                type="button"
                                                :disabled="togglingId === currency.id"
                                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                                :class="currency.is_active ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-600'"
                                                :title="currency.is_active ? 'انقر لتعطيل العملة' : 'انقر لتفعيل العملة'"
                                                @click="toggleCurrency(currency)"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                                    :class="currency.is_active ? '-translate-x-4' : 'translate-x-0'"
                                                />
                                            </button>
                                            <span
                                                class="text-[11px] font-bold"
                                                :class="currency.is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400'"
                                            >
                                                {{ currency.is_base ? 'أساس (دائمة)' : (currency.is_active ? 'مفعّلة' : 'معطّلة') }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- إجراءات -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <Link
                                                :href="route('currencies.edit', currency.id)"
                                                class="font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                تعديل
                                            </Link>
                                            <span class="text-slate-300 dark:text-slate-600">·</span>
                                            <Link
                                                :href="route('rates.index')"
                                                class="font-bold text-slate-500 hover:text-slate-700 dark:text-slate-400"
                                                title="إدارة سعر الصرف"
                                            >
                                                السعر
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- بطاقات للجوال -->
                    <div class="divide-y divide-slate-100 sm:hidden dark:divide-slate-700">
                        <p v-if="visibleSystemCurrencies.length === 0" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">
                            لا توجد عملات تطابق البحث.
                        </p>
                        <div
                            v-for="currency in visibleSystemCurrencies"
                            :key="currency.id"
                            class="p-4 transition hover:bg-slate-50 dark:hover:bg-slate-700/40"
                            :class="currency.is_active ? '' : 'opacity-60'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-sm text-slate-900 dark:text-white">
                                        {{ currency.code }}
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ currency.name }}
                                    </span>
                                    <span v-if="currency.tag" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ currency.tag }}
                                    </span>
                                </div>

                                <!-- التبديل الفوري -->
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="!currency.is_base"
                                        type="button"
                                        :disabled="togglingId === currency.id"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                        :class="currency.is_active ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-600'"
                                        @click="toggleCurrency(currency)"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition"
                                            :class="currency.is_active ? '-translate-x-4' : 'translate-x-0'"
                                        />
                                    </button>
                                    <span v-if="currency.is_base" class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        أساس
                                    </span>
                                </div>
                            </div>

                            <div class="mt-2 flex items-center justify-between text-xs tabular-nums border-t border-slate-100 dark:border-slate-700/60 pt-2">
                                <div>
                                    <span class="text-slate-400 text-[10px]">السعر: </span>
                                    <span v-if="currency.is_base" class="text-slate-400">—</span>
                                    <span v-else-if="currency.exchange_rate" class="font-bold text-slate-700 dark:text-slate-300">
                                        {{ rate(currency.exchange_rate.buy_rate) }} / {{ rate(currency.exchange_rate.sell_rate) }}
                                    </span>
                                    <span v-else class="font-bold text-amber-700 dark:text-amber-400">لا سعر</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Link :href="route('currencies.edit', currency.id)" class="font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                                        تعديل
                                    </Link>
                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                    <Link :href="route('rates.index')" class="font-bold text-slate-500 dark:text-slate-400">
                                        الأسعار
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================== التبويب ٢: مؤشر الليرة اليوم المرجعي ================== -->
                <div v-else-if="activeTab === 'sptoday'" class="space-y-4">
                    <!-- لوحة التوجيه السريعة -->
                    <div class="rounded-2xl border border-teal-200/90 bg-gradient-to-r from-teal-50/70 via-white to-slate-50 p-4 dark:border-teal-800/40 dark:from-slate-800 dark:via-slate-800 dark:to-teal-950/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white shadow-sm shadow-teal-600/30">
                                <NavIcon name="trending-up" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                    جميع عملات نشرة دمشق المباشرة (sp-today.com)
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    يمكنك تفعيل أو إضافة أي عملة إلى نظامك بنقرة واحدة مع تعيين سعر صرفها الفعلي تلقائياً
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="missingFromSystemList.length"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-3.5 py-2 text-xs font-black text-white hover:bg-teal-700 shadow-xs transition"
                            @click="showImportModal = true"
                        >
                            <NavIcon name="zap" class="h-3.5 w-3.5" />
                            <span>إضافة جميع المتبقي ({{ missingFromSystemList.length }} عملة)</span>
                        </button>
                    </div>

                    <!-- قائمة بطاقات عملات الليرة اليوم -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="item in visibleSpTodayCurrencies"
                            :key="item.code"
                            class="rounded-2xl border bg-white p-4 shadow-xs transition hover:shadow-md dark:bg-slate-800/90"
                            :class="item.inSystem
                                ? (item.isActive ? 'border-emerald-200 dark:border-emerald-800/40' : 'border-slate-200 dark:border-slate-700 opacity-75')
                                : 'border-teal-200/80 bg-teal-50/20 dark:border-teal-800/50'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-base shadow-2xs dark:bg-slate-700">
                                        {{ item.flag || item.code }}
                                    </span>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <h4 class="text-sm font-black text-slate-900 dark:text-white">
                                                {{ item.code }}
                                            </h4>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ item.name_ar }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5">
                                            <span
                                                v-if="item.isBase"
                                                class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                                            >
                                                العملة الأساس للنظام
                                            </span>
                                            <span
                                                v-else-if="item.inSystem && item.isActive"
                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                مفعّلة في النظام
                                            </span>
                                            <span
                                                v-else-if="item.inSystem && !item.isActive"
                                                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                            >
                                                معطّلة في النظام
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300"
                                            >
                                                غير مضافة بعد
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- أزرار التحكم الفورية -->
                                <div>
                                    <!-- إذا كانت موجودة وغير أساس: زر التبديل السريع -->
                                    <button
                                        v-if="item.inSystem && !item.isBase"
                                        type="button"
                                        :disabled="togglingId === item.systemCurrency.id"
                                        class="rounded-xl px-2.5 py-1 text-xs font-bold transition"
                                        :class="item.isActive
                                            ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                                            : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'"
                                        @click="toggleCurrency(item.systemCurrency)"
                                    >
                                        {{ item.isActive ? 'تعطيل' : 'تفعيل' }}
                                    </button>

                                    <!-- إذا لم تكن موجودة: زر الإضافة الفورية بنقرة واحدة -->
                                    <button
                                        v-else-if="!item.inSystem"
                                        type="button"
                                        :disabled="addingCode === item.code"
                                        class="inline-flex items-center gap-1 rounded-xl bg-teal-600 px-2.5 py-1 text-xs font-black text-white hover:bg-teal-700 shadow-2xs transition disabled:opacity-50"
                                        @click="quickAddFromSpToday(item.code)"
                                    >
                                        <NavIcon name="plus-circle" class="h-3 w-3" :class="addingCode === item.code ? 'animate-spin' : ''" />
                                        <span>{{ addingCode === item.code ? 'جارٍ الإضافة...' : '+ إضافة وتفعيل' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- أسعار الصرف بالسوق الموازي -->
                            <div class="mt-3 rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 text-xs dark:border-slate-700/60 dark:bg-slate-900/40">
                                <span class="text-[10px] text-slate-400 block mb-1">أسعار النشرة المباشرة (دمشق):</span>
                                <div class="flex items-center justify-between font-black tabular-nums">
                                    <div>
                                        <span class="text-[10px] text-slate-400 me-1">شراء:</span>
                                        <span class="text-emerald-700 dark:text-emerald-400">{{ item.buy_new }}</span>
                                    </div>
                                    <span class="text-slate-300 dark:text-slate-600">|</span>
                                    <div>
                                        <span class="text-[10px] text-slate-400 me-1">بيع:</span>
                                        <span class="text-rose-700 dark:text-rose-400">{{ item.sell_new }}</span>
                                    </div>
                                </div>
                                <span class="text-[9px] text-slate-400 block mt-1 tabular-nums">
                                    ({{ number(item.buy_traditional) }} / {{ number(item.sell_traditional) }} ل.س قديمة)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- حوار استيراد العملات دفعة واحدة من الليرة اليوم -->
        <ImportSpTodayModal
            :show="showImportModal"
            :existing-currencies="currencies"
            :sp-today="spToday"
            @close="showImportModal = false"
        />

        <!-- حوار إضافة عملة مخصصة جديدة -->
        <CreateCurrencyModal
            :show="showCreateModal"
            @close="showCreateModal = false"
        />

        <!-- حوار تأكيد اعتماد العملة الأساس -->
        <ConfirmDialog
            v-if="basePreview"
            :show="confirmingApply"
            title="اعتماد عملة أساس جديدة"
            message="سيُعاد بناء كل أسعار الصرف حول العملة الجديدة."
            :confirm-label="`اعتمد ${basePreview.target.code}`"
            cancel-label="تراجع"
            tone="danger"
            irreversible
            :require-text="basePreview.target.code"
            :processing="applyForm.processing"
            @confirm="apply"
            @cancel="confirmingApply = false"
        >
            <template #details>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500 dark:text-slate-400">الأساس الحالي</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ basePreview.current_base?.code ?? '—' }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-slate-500 dark:text-slate-400">الأساس الجديد</span>
                    <span class="font-bold text-indigo-700 dark:text-indigo-300">
                        {{ basePreview.target.code }}{{ basePreview.target.tag ? ` · ${basePreview.target.tag}` : '' }}
                    </span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-slate-500 dark:text-slate-400">الطريقة</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">
                        {{ modes.find((m) => m.value === basePreview.mode)?.label }}
                    </span>
                </div>

                <div class="mt-3 border-t border-slate-200 pt-2 dark:border-slate-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500 dark:text-slate-400">أسعار سيُعاد حسابها</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ previewImpact.changed }}</span>
                    </div>
                    <div v-if="previewImpact.needsRate" class="mt-1 flex items-center justify-between gap-2">
                        <span class="text-amber-700 dark:text-amber-400">عملات ستبقى بلا سعر</span>
                        <span class="font-bold text-amber-700 dark:text-amber-400">{{ previewImpact.needsRate }}</span>
                    </div>
                </div>
            </template>
        </ConfirmDialog>
    </AuthenticatedLayout>
</template>
