<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    definition: { type: Object, required: true },
    reports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    filterDefinitions: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    totals: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    exportFormats: { type: Array, default: () => [] },
});

const { cell, integer } = useFormat();

const form = reactive({});

function syncForm() {
    props.filterDefinitions.forEach((filter) => {
        form[filter.key] = props.filters[filter.key] ?? filter.default ?? '';
    });
}

syncForm();
watch(() => props.definition.key, syncForm);

const activeFilters = computed(() =>
    Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null)),
);

function apply() {
    router.get(route('reports.show', props.definition.key), activeFilters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function reset() {
    router.get(route('reports.show', props.definition.key), {}, { replace: true });
}

function goToPage(page) {
    router.get(
        route('reports.show', props.definition.key),
        { ...activeFilters.value, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function exportUrl(format) {
    return route('reports.export', {
        report: props.definition.key,
        ...activeFilters.value,
        format,
        ...(format === 'print' ? { autoprint: 1 } : {}),
    });
}

function isNegative(value, format) {
    return format === 'signed' && Number(value) < 0;
}

const hasRows = computed(() => props.rows.length > 0);
const pageRange = computed(() => {
    const { page = 1, per_page: perPage = 50, total_rows: total = 0 } = props.meta;
    if (!total) return '0';
    const from = (page - 1) * perPage + 1;
    return `${from}–${Math.min(page * perPage, total)} من ${integer(total)}`;
});
</script>

<template>
    <Head :title="definition.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('reports.index')" class="text-sm text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">التقارير</Link>
                <span class="text-gray-300 dark:text-slate-600">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">{{ definition.name }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- تبديل سريع بين أنواع التقارير -->
                <div v-if="reports.length > 1" class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in reports"
                        :key="item.key"
                        :href="route('reports.show', item.key)"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition"
                        :class="item.key === definition.key
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                    >
                        {{ item.name }}
                    </Link>
                </div>

                <!-- الفلاتر -->
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-wrap items-end gap-3">
                        <div v-for="filter in filterDefinitions" :key="filter.key" class="min-w-[9rem]">
                            <label class="block text-xs text-gray-500 dark:text-slate-400">{{ filter.label }}</label>

                            <select
                                v-if="filter.type === 'select'"
                                v-model="form[filter.key]"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                @change="apply"
                            >
                                <option v-for="option in filter.options" :key="String(option.value)" :value="String(option.value)">
                                    {{ option.label }}
                                </option>
                            </select>

                            <input
                                v-else
                                v-model="form[filter.key]"
                                :type="filter.type === 'date' ? 'date' : 'text'"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                @keyup.enter="apply"
                            />
                        </div>

                        <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="apply">
                            عرض
                        </button>
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700" @click="reset">
                            إعادة تعيين
                        </button>

                        <div class="ms-auto flex gap-2">
                            <a
                                v-if="exportFormats.includes('print')"
                                :href="exportUrl('print')"
                                target="_blank"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                طباعة
                            </a>
                            <a
                                v-if="exportFormats.includes('csv')"
                                :href="exportUrl('csv')"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                تصدير CSV
                            </a>
                        </div>
                    </div>
                    <p v-if="definition.description" class="mt-3 text-xs text-gray-400 dark:text-slate-500">{{ definition.description }}</p>
                </div>

                <!-- بطاقات الإجماليات -->
                <div v-if="totals.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="total in totals"
                        :key="total.key"
                        class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                    >
                        <p class="text-xs text-gray-500 dark:text-slate-400">{{ total.label }}</p>
                        <p
                            class="mt-1 text-lg font-bold"
                            :class="isNegative(total.value, total.format) ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-white'"
                        >
                            {{ cell(total.value, total.format ?? 'text') }}
                        </p>
                    </div>
                </div>

                <!-- الجدول -->
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                        <thead class="bg-gray-50 text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th v-for="column in columns" :key="column.key" class="whitespace-nowrap px-4 py-2 text-start font-medium">
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                            <tr v-if="!hasRows">
                                <td :colspan="Math.max(columns.length, 1)" class="px-4 py-8 text-center text-gray-400 dark:text-slate-500">
                                    {{ meta.empty_message ?? 'لا توجد بيانات ضمن هذا النطاق.' }}
                                </td>
                            </tr>
                            <tr v-for="(row, index) in rows" :key="index" class="hover:bg-gray-50 dark:hover:bg-slate-700/40">
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="whitespace-nowrap px-4 py-2"
                                    :class="{
                                        'font-semibold text-rose-600 dark:text-rose-400': isNegative(row[column.key], column.format),
                                        'text-emerald-700 dark:text-emerald-400': column.format === 'type' && row[column.key] === 'buy',
                                        'text-rose-700 dark:text-rose-400': column.format === 'type' && row[column.key] === 'sell',
                                    }"
                                >
                                    {{ cell(row[column.key], column.format ?? 'text') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ترقيم الصفحات -->
                <div class="flex items-center justify-between text-sm text-gray-500 dark:text-slate-400">
                    <span>{{ pageRange }}</span>
                    <div v-if="(meta.last_page ?? 1) > 1" class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1 disabled:opacity-40 dark:border-slate-600"
                            :disabled="(meta.page ?? 1) <= 1"
                            @click="goToPage((meta.page ?? 1) - 1)"
                        >
                            السابق
                        </button>
                        <span class="px-2 py-1">{{ meta.page }} / {{ meta.last_page }}</span>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1 disabled:opacity-40 dark:border-slate-600"
                            :disabled="(meta.page ?? 1) >= (meta.last_page ?? 1)"
                            @click="goToPage((meta.page ?? 1) + 1)"
                        >
                            التالي
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
