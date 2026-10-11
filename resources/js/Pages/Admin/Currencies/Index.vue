<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    basePreview: { type: Object, default: null },
});

const { number, rate } = useFormat();

const currentBase = computed(() => props.currencies.find((c) => c.is_base) ?? null);

const candidates = computed(() => props.currencies.filter((c) => !c.is_base && c.is_active));

/** عملة بلا سعر لا يمكن تسجيل أي عملية عليها — يرفضها الخادم. */
const missingRates = computed(() =>
    props.currencies.filter((c) => !c.is_base && c.is_active && !c.exchange_rate),
);

/* ------------------------------ تصفية الجدول ----------------------------- */

const search = ref('');
const statusFilter = ref('all');

const visible = computed(() => {
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

const filters = [
    { value: 'all', label: 'الكل' },
    { value: 'active', label: 'المفعّلة' },
    { value: 'inactive', label: 'المعطّلة' },
    { value: 'norate', label: 'بلا سعر' },
];

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

/** كم صفّاً سيتغيّر سعره فعلاً، وكم صفّاً سيبقى بلا سعر بعد التبديل. */
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
    <Head title="العملات" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">العملات</h2>
                <Link :href="route('currencies.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    + عملة جديدة
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <NewLiraNotice dismiss-key="currencies-index" />

                <!-- عملة بلا سعر تُرفض عند تسجيل العملية، فيُنبَّه إليها هنا لا عند الرفض. -->
                <div
                    v-if="missingRates.length"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-300"
                    role="alert"
                >
                    <span>
                        {{ missingRates.length }} عملة مفعّلة بلا سعر صرف — لا يمكن تسجيل عمليات عليها:
                        <span class="font-semibold">{{ missingRates.map((c) => c.code).join('، ') }}</span>
                    </span>
                    <Link :href="route('rates.index')" class="shrink-0 font-semibold underline underline-offset-2">تحديد الأسعار</Link>
                </div>

                <!-- شرح معنى العملة الأساس: هو ما يجعل بقية الأرقام مفهومة. -->
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-gray-800 dark:text-white">
                                العملة الأساس:
                                <span class="text-indigo-700 dark:text-indigo-300">
                                    {{ currentBase ? `${currentBase.name} (${currentBase.code})` : 'غير محددة' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                كل سعر في النظام يعني: كم وحدة من العملة الأساس مقابل وحدة واحدة من العملة الأجنبية.
                                وبها يُحسب إجمالي كل عملية.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-indigo-300 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                            @click="panelOpen = !panelOpen"
                        >
                            {{ panelOpen ? 'إغلاق' : 'تغيير العملة الأساس' }}
                        </button>
                    </div>

                    <div v-if="panelOpen" class="mt-4 space-y-4 border-t border-gray-200 pt-4 dark:border-slate-700">
                        <div>
                            <label for="base-target" class="block text-xs font-medium text-gray-600 dark:text-slate-300">العملة الأساس الجديدة</label>
                            <select
                                id="base-target"
                                v-model="targetId"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white sm:max-w-xs"
                                @change="loadPreview"
                            >
                                <option v-for="c in candidates" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.code }}){{ c.tag ? ` — ${c.tag}` : '' }}
                                </option>
                            </select>
                        </div>

                        <!--
                            الطريقتان تعطيان أرقاماً مختلفة تماماً، والاختيار الخاطئ يفسد
                            كل الأسعار. البطاقات تعرض الشرح مع الخيار بدل إخفائه تحت قائمة.
                        -->
                        <div>
                            <span class="block text-xs font-medium text-gray-600 dark:text-slate-300">طريقة معالجة الأسعار</span>
                            <div class="mt-1 grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="m in modes"
                                    :key="m.value"
                                    type="button"
                                    :aria-pressed="mode === m.value"
                                    @click="pickMode(m.value)"
                                    class="rounded-lg border p-3 text-start transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="mode === m.value
                                        ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600 dark:border-indigo-400 dark:bg-indigo-900/30 dark:ring-indigo-400'
                                        : 'border-gray-200 hover:border-indigo-300 dark:border-slate-600 dark:hover:border-indigo-500/60'"
                                >
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-white">{{ m.label }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-slate-400">{{ m.hint }}</span>
                                </button>
                            </div>
                        </div>

                        <button
                            v-if="!basePreview"
                            type="button"
                            class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="loadPreview"
                        >
                            اعرض المعاينة
                        </button>

                        <!-- المعاينة: لا تبديل قبل رؤية الأرقام الناتجة. -->
                        <div v-if="basePreview" class="space-y-3">
                            <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-slate-700">
                                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-slate-700">
                                    <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                        <tr>
                                            <th class="px-3 py-2 text-start">العملة</th>
                                            <th class="px-3 py-2 text-start">السعر الحالي (شراء/بيع)</th>
                                            <th class="px-3 py-2 text-start">بعد التبديل</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                        <tr v-for="row in basePreview.rows" :key="`${row.code}-${row.tag ?? ''}`">
                                            <td class="whitespace-nowrap px-3 py-2 font-medium">
                                                {{ row.code }}<span v-if="row.tag" class="text-gray-400"> · {{ row.tag }}</span>
                                                <span v-if="row.is_new_base" class="ms-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                    الأساس الجديد
                                                </span>
                                                <span v-else-if="row.was_base" class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                    الأساس السابق
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 tabular-nums text-gray-500 dark:text-slate-400">{{ rateText(row.before) }}</td>
                                            <td class="px-3 py-2 font-semibold tabular-nums">
                                                <span v-if="row.is_new_base" class="text-gray-400">لا سعر (هي الأساس)</span>
                                                <span v-else-if="!row.after" class="text-amber-700 dark:text-amber-400">يلزم إدخال سعر</span>
                                                <span v-else>{{ rateText(row.after) }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-if="blockers.length"
                                class="rounded-md border border-rose-300 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/25 dark:text-rose-200"
                                role="alert"
                            >
                                <div class="font-semibold">لا يمكن التبديل الآن:</div>
                                <ul class="mt-1 list-inside list-disc space-y-0.5">
                                    <li v-for="b in blockers" :key="b">{{ b }}</li>
                                </ul>
                            </div>

                            <button
                                type="button"
                                class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canApply || applyForm.processing"
                                @click="confirmingApply = true"
                            >
                                اعتمد العملة الأساس الجديدة
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <!-- التصفية: عدد الصفوف يتضاعف مع فئات العملة الواحدة. -->
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="ابحث بالرمز أو الاسم أو الفئة…"
                            class="w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white sm:w-64"
                            aria-label="تصفية العملات"
                        />
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="f in filters"
                                :key="f.value"
                                type="button"
                                :aria-pressed="statusFilter === f.value"
                                @click="statusFilter = f.value"
                                class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                                :class="statusFilter === f.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'"
                            >
                                {{ f.label }}
                            </button>
                        </div>
                        <span class="text-xs text-gray-400 dark:text-slate-500 sm:ms-auto">{{ visible.length }} من {{ currencies.length }}</span>
                    </div>

                    <div class="hidden overflow-x-auto sm:block">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2 text-start">الرمز</th>
                                    <th class="px-4 py-2 text-start">الاسم</th>
                                    <th class="px-4 py-2 text-start">العلامة</th>
                                    <th class="px-4 py-2 text-start">الفئة</th>
                                    <th class="px-4 py-2 text-start">السعر (شراء/بيع)</th>
                                    <th class="px-4 py-2 text-start">النوع</th>
                                    <th class="px-4 py-2 text-start">الحالة</th>
                                    <th class="px-4 py-2 text-start"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                <tr v-if="visible.length === 0">
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 dark:text-slate-500">
                                        لا عملة تطابق البحث.
                                    </td>
                                </tr>
                                <tr
                                    v-for="currency in visible"
                                    :key="currency.id"
                                    :class="[
                                        currency.is_base ? 'bg-indigo-50/40 dark:bg-indigo-900/10' : '',
                                        currency.is_active ? '' : 'opacity-60',
                                    ]"
                                >
                                    <td class="whitespace-nowrap px-4 py-2 font-medium">{{ currency.code }}</td>
                                    <td class="px-4 py-2">{{ currency.name }}</td>
                                    <td class="px-4 py-2">{{ currency.symbol ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="currency.tag" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            {{ currency.tag }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 tabular-nums">
                                        <span v-if="currency.is_base" class="text-gray-400">—</span>
                                        <span v-else-if="currency.exchange_rate">
                                            {{ rate(currency.exchange_rate.buy_rate) }} / {{ rate(currency.exchange_rate.sell_rate) }}
                                        </span>
                                        <Link v-else :href="route('rates.index')" class="font-semibold text-amber-700 underline underline-offset-2 dark:text-amber-400">
                                            لا سعر
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span v-if="currency.is_base" class="whitespace-nowrap rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                            عملة أساس
                                        </span>
                                        <span v-else class="text-gray-500 dark:text-slate-400">عملة أجنبية</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="currency.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400'"
                                        >
                                            {{ currency.is_active ? 'مفعّلة' : 'معطّلة' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Link :href="route('currencies.edit', currency.id)" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">تعديل</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- بطاقات على الجوال: ثمانية أعمدة لا تُقرأ على شاشة ضيّقة. -->
                    <div class="divide-y divide-gray-100 sm:hidden dark:divide-slate-700">
                        <p v-if="visible.length === 0" class="px-4 py-10 text-center text-gray-400 dark:text-slate-500">لا عملة تطابق البحث.</p>
                        <Link
                            v-for="currency in visible"
                            :key="currency.id"
                            :href="route('currencies.edit', currency.id)"
                            class="block px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-slate-700/40"
                            :class="currency.is_active ? '' : 'opacity-60'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-gray-800 dark:text-white">
                                    {{ currency.code }}
                                    <span v-if="currency.tag" class="ms-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ currency.tag }}
                                    </span>
                                </span>
                                <span v-if="currency.is_base" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">أساس</span>
                                <span v-else-if="!currency.is_active" class="text-xs text-gray-500 dark:text-slate-400">معطّلة</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ currency.name }}</div>
                            <div class="mt-1 text-xs tabular-nums">
                                <span v-if="currency.is_base" class="text-gray-400">—</span>
                                <span v-else-if="currency.exchange_rate" class="text-gray-600 dark:text-slate-300">
                                    {{ rate(currency.exchange_rate.buy_rate) }} / {{ rate(currency.exchange_rate.sell_rate) }}
                                </span>
                                <span v-else class="font-semibold text-amber-700 dark:text-amber-400">لا سعر</span>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!--
            تبديل العملة الأساس يُعيد بناء كل سعر في النظام. لذلك يُطلب كتابة رمز
            العملة صراحةً: ضغطتان متتابعتان لا تكفيان للموافقة على إجراء بهذا الأثر.
        -->
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
                    <span class="text-gray-500 dark:text-slate-400">الأساس الحالي</span>
                    <span class="font-semibold text-gray-800 dark:text-slate-100">{{ basePreview.current_base?.code ?? '—' }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">الأساس الجديد</span>
                    <span class="font-semibold text-indigo-700 dark:text-indigo-300">
                        {{ basePreview.target.code }}{{ basePreview.target.tag ? ` · ${basePreview.target.tag}` : '' }}
                    </span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <span class="text-gray-500 dark:text-slate-400">الطريقة</span>
                    <span class="font-semibold text-gray-800 dark:text-slate-100">
                        {{ modes.find((m) => m.value === basePreview.mode)?.label }}
                    </span>
                </div>

                <div class="mt-3 border-t border-gray-200 pt-2 dark:border-slate-600">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500 dark:text-slate-400">أسعار سيُعاد حسابها</span>
                        <span class="font-semibold text-gray-800 dark:text-slate-100">{{ previewImpact.changed }}</span>
                    </div>
                    <div v-if="previewImpact.needsRate" class="mt-1 flex items-center justify-between gap-2">
                        <span class="text-amber-700 dark:text-amber-400">عملات ستبقى بلا سعر</span>
                        <span class="font-semibold text-amber-700 dark:text-amber-400">{{ previewImpact.needsRate }}</span>
                    </div>
                </div>
            </template>
        </ConfirmDialog>
    </AuthenticatedLayout>
</template>
