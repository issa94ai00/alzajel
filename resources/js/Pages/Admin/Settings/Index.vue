<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    reportDefinitions: { type: Array, required: true },
    receiptTemplates: { type: Array, required: true },
    builders: { type: Array, required: true },
    papers: { type: Array, required: true },
    rangeOptions: { type: Array, required: true },
});

const tabs = [
    { key: 'general', label: 'عام' },
    { key: 'reports', label: 'التقارير' },
    { key: 'receipts', label: 'الإيصالات' },
];

const activeTab = ref('general');

/* ------------------------------ الإعدادات العامة ----------------------------- */

const settingsForm = useForm({
    company: { ...props.settings.company },
    format: { ...props.settings.format },
    reports: { ...props.settings.reports },
    receipts: { ...props.settings.receipts },
    currency: { ...props.settings.currency },
});

function saveSettings() {
    settingsForm.put(route('settings.update'), { preserveScroll: true });
}

function resetGroup(group) {
    if (!confirm('إعادة هذه المجموعة إلى القيم الافتراضية؟')) return;

    router.post(route('settings.reset'), { group }, { preserveScroll: true });
}

function toggleExportFormat(format) {
    const current = settingsForm.reports.export_formats ?? [];

    settingsForm.reports.export_formats = current.includes(format)
        ? current.filter((item) => item !== format)
        : [...current, format];
}

/* -------------------------------- التقارير -------------------------------- */

const reportsForm = useForm({
    reports: props.reportDefinitions.map((report) => ({
        id: report.id,
        key: report.key,
        builder: report.builder,
        name: report.name,
        description: report.description ?? '',
        is_enabled: report.is_enabled,
        admin_only: report.admin_only,
        sort_order: report.sort_order,
        options: {
            hidden_columns: [...(report.options.hidden_columns ?? [])],
            default_range: report.options.default_range ?? '',
        },
        columns: report.columns,
        is_known: report.is_known,
    })),
});

function saveReports() {
    reportsForm
        .transform((data) => ({
            reports: data.reports.map(({ columns, is_known, key, builder, ...rest }) => rest),
        }))
        .put(route('settings.reports.update'), { preserveScroll: true });
}

function toggleColumn(report, columnKey) {
    const hidden = report.options.hidden_columns;
    const index = hidden.indexOf(columnKey);

    if (index === -1) hidden.push(columnKey);
    else hidden.splice(index, 1);
}

const newReport = useForm({
    key: '',
    builder: props.builders[0]?.value ?? '',
    name: '',
    description: '',
    admin_only: false,
    is_enabled: true,
    sort_order: null,
    options: { default_range: '' },
});

function addReport() {
    newReport.post(route('settings.reports.store'), {
        preserveScroll: true,
        onSuccess: () => newReport.reset(),
    });
}

function deleteReport(report) {
    if (!confirm(`حذف التقرير «${report.name}» من القائمة؟`)) return;

    router.delete(route('settings.reports.destroy', report.key), { preserveScroll: true });
}

/* ------------------------------- الإيصالات -------------------------------- */

const emptyTemplate = () => ({
    key: '',
    name: '',
    subject: 'transaction',
    paper: '80mm',
    copies: 1,
    is_active: true,
    is_default: false,
    options: { title: '', header_text: '', footer_text: '', show_logo: true },
});

const templateForm = useForm(emptyTemplate());
const editingTemplate = ref(null);

function editTemplate(template) {
    editingTemplate.value = template.key;
    templateForm.defaults(emptyTemplate());
    Object.assign(templateForm, {
        key: template.key,
        name: template.name,
        subject: template.subject,
        paper: template.paper,
        copies: template.copies,
        is_active: template.is_active,
        is_default: template.is_default,
        options: {
            title: template.options?.title ?? '',
            header_text: template.options?.header_text ?? '',
            footer_text: template.options?.footer_text ?? '',
            show_logo: template.options?.show_logo ?? true,
        },
    });
}

function cancelTemplate() {
    editingTemplate.value = null;
    templateForm.reset();
    templateForm.clearErrors();
}

function saveTemplate() {
    if (editingTemplate.value) {
        templateForm.put(route('settings.receipt-templates.update', editingTemplate.value), {
            preserveScroll: true,
            onSuccess: cancelTemplate,
        });
        return;
    }

    templateForm.post(route('settings.receipt-templates.store'), {
        preserveScroll: true,
        onSuccess: cancelTemplate,
    });
}

function deleteTemplate(template) {
    if (!confirm(`حذف القالب «${template.name}»؟`)) return;

    router.delete(route('settings.receipt-templates.destroy', template.key), { preserveScroll: true });
}

const transactionTemplates = computed(() => props.receiptTemplates.filter((t) => t.subject === 'transaction'));
const shiftTemplates = computed(() => props.receiptTemplates.filter((t) => t.subject === 'shift'));

const inputClass =
    'mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white';
const labelClass = 'block text-xs font-medium text-gray-600 dark:text-slate-400';
const cardClass = 'rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800';
</script>

<template>
    <Head title="الإعدادات" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">إعدادات النظام</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
                <!-- التبويبات -->
                <div class="flex gap-2 border-b border-gray-200 dark:border-slate-700">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === tab.key
                            ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-slate-400'"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- ======================= عام ======================= -->
                <form v-show="activeTab === 'general'" class="space-y-5" @submit.prevent="saveSettings">
                    <div :class="cardClass">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-800 dark:text-white">بيانات الجهة</h3>
                            <button type="button" class="text-xs text-gray-500 hover:text-rose-600 dark:text-slate-400" @click="resetGroup('company')">
                                استعادة الافتراضي
                            </button>
                        </div>
                        <p class="mb-4 text-xs text-gray-500 dark:text-slate-400">تظهر هذه البيانات في ترويسة الإيصالات والتقارير المطبوعة.</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">اسم المكتب</label>
                                <input v-model="settingsForm.company.name" type="text" :class="inputClass" />
                                <InputError :message="settingsForm.errors['company.name']" class="mt-1" />
                            </div>
                            <div>
                                <label :class="labelClass">الفرع</label>
                                <input v-model="settingsForm.company.branch" type="text" :class="inputClass" />
                            </div>
                            <div>
                                <label :class="labelClass">الهاتف</label>
                                <input v-model="settingsForm.company.phone" type="text" :class="inputClass" />
                            </div>
                            <div>
                                <label :class="labelClass">الرقم الضريبي</label>
                                <input v-model="settingsForm.company.tax_number" type="text" :class="inputClass" />
                            </div>
                            <div class="sm:col-span-2">
                                <label :class="labelClass">العنوان</label>
                                <input v-model="settingsForm.company.address" type="text" :class="inputClass" />
                            </div>
                            <div>
                                <label :class="labelClass">نص الشعار (يظهر أعلى الإيصال)</label>
                                <input v-model="settingsForm.company.logo_text" type="text" :class="inputClass" />
                            </div>
                        </div>
                    </div>

                    <div :class="cardClass">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-800 dark:text-white">تنسيق الأرقام والتواريخ</h3>
                            <button type="button" class="text-xs text-gray-500 hover:text-rose-600 dark:text-slate-400" @click="resetGroup('format')">
                                استعادة الافتراضي
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label :class="labelClass">عدد المنازل العشرية</label>
                                <input v-model.number="settingsForm.format.decimals" type="number" min="0" max="6" :class="inputClass" />
                                <InputError :message="settingsForm.errors['format.decimals']" class="mt-1" />
                            </div>
                            <div>
                                <label :class="labelClass">لغة/منطقة العرض</label>
                                <input v-model="settingsForm.format.locale" type="text" :class="inputClass" />
                            </div>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.format.thousands_separator" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                فاصل الآلاف
                            </label>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-slate-700">
                        <div class="mb-3">
                            <h3 class="font-semibold text-gray-800 dark:text-white">الليرة السورية الجديدة</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                إرشادات انتقالية بعد حذف صفرين من العملة القديمة. أطفئها عند اعتياد الصرّافين على العملة الجديدة.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.currency.new_lira_notice" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار تنبيه العملة الجديدة
                            </label>
                            <div>
                                <label :class="labelClass">تحذير عند تجاوز المبلغ (ل.س ج)</label>
                                <input
                                    v-model.number="settingsForm.currency.old_lira_warn_above"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    :class="inputClass"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">0 = تعطيل التحذير</p>
                                <InputError :message="settingsForm.errors['currency.old_lira_warn_above']" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="settingsForm.processing"
                        >
                            حفظ الإعدادات
                        </button>
                    </div>
                </form>

                <!-- ======================= التقارير ======================= -->
                <div v-show="activeTab === 'reports'" class="space-y-5">
                    <form :class="cardClass" @submit.prevent="saveSettings">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-800 dark:text-white">سلوك التقارير</h3>
                            <button type="button" class="text-xs text-gray-500 hover:text-rose-600 dark:text-slate-400" @click="resetGroup('reports')">
                                استعادة الافتراضي
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label :class="labelClass">النطاق الزمني الافتراضي</label>
                                <select v-model="settingsForm.reports.default_range" :class="inputClass">
                                    <option v-for="option in rangeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label :class="labelClass">عدد الصفوف في الصفحة</label>
                                <input v-model.number="settingsForm.reports.page_size" type="number" min="10" max="500" :class="inputClass" />
                                <InputError :message="settingsForm.errors['reports.page_size']" class="mt-1" />
                            </div>
                            <div>
                                <label :class="labelClass">حجم ورق الطباعة</label>
                                <select v-model="settingsForm.reports.print_paper" :class="inputClass">
                                    <option value="a4">A4</option>
                                    <option value="a5">A5</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label :class="labelClass">ترويسة الطباعة</label>
                                <input v-model="settingsForm.reports.print_header" type="text" :class="inputClass" />
                            </div>
                            <div>
                                <label :class="labelClass">تذييل الطباعة</label>
                                <input v-model="settingsForm.reports.print_footer" type="text" :class="inputClass" />
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-5">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.reports.show_totals" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار بطاقات الإجماليات
                            </label>
                            <span class="text-sm text-gray-500 dark:text-slate-400">صيغ التصدير المتاحة:</span>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600"
                                    :checked="(settingsForm.reports.export_formats ?? []).includes('print')"
                                    @change="toggleExportFormat('print')"
                                />
                                طباعة
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600"
                                    :checked="(settingsForm.reports.export_formats ?? []).includes('csv')"
                                    @change="toggleExportFormat('csv')"
                                />
                                CSV
                            </label>

                            <button
                                type="submit"
                                class="ms-auto rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="settingsForm.processing"
                            >
                                حفظ
                            </button>
                        </div>
                    </form>

                    <form :class="cardClass" @submit.prevent="saveReports">
                        <h3 class="mb-1 font-semibold text-gray-800 dark:text-white">أنواع التقارير</h3>
                        <p class="mb-4 text-xs text-gray-500 dark:text-slate-400">
                            فعّل أو أخفِ أي تقرير، وحدّد من يراه، ورتّب ظهوره، واختر الأعمدة التي تريد إخفاءها.
                        </p>

                        <div class="space-y-3">
                            <div
                                v-for="(report, index) in reportsForm.reports"
                                :key="report.id"
                                class="rounded-lg border border-gray-200 p-4 dark:border-slate-700"
                                :class="{ 'opacity-60': !report.is_enabled }"
                            >
                                <div class="flex flex-wrap items-center gap-3">
                                    <input v-model="report.name" type="text" class="w-56 rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-slate-700 dark:text-slate-300">{{ report.key }}</span>
                                    <span v-if="!report.is_known" class="rounded bg-rose-50 px-2 py-0.5 text-xs text-rose-600 dark:bg-rose-500/10">نوع غير معروف</span>

                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-slate-300">
                                        <input v-model="report.is_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> مفعّل
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-slate-300">
                                        <input v-model="report.admin_only" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> للمدير فقط
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-slate-300">
                                        الترتيب
                                        <input v-model.number="report.sort_order" type="number" min="0" class="w-16 rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-slate-300">
                                        النطاق
                                        <select v-model="report.options.default_range" class="rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                            <option value="">حسب الإعداد العام</option>
                                            <option v-for="option in rangeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </label>

                                    <button type="button" class="ms-auto text-xs text-rose-600 hover:underline" @click="deleteReport(report)">حذف</button>
                                </div>

                                <input
                                    v-model="report.description"
                                    type="text"
                                    placeholder="وصف مختصر يظهر للمستخدم"
                                    class="mt-3 w-full rounded-md border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                />

                                <div v-if="report.columns.length" class="mt-3">
                                    <p class="text-xs text-gray-500 dark:text-slate-400">الأعمدة الظاهرة:</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button
                                            v-for="column in report.columns"
                                            :key="column.key"
                                            type="button"
                                            class="rounded-full border px-3 py-1 text-xs transition"
                                            :class="report.options.hidden_columns.includes(column.key)
                                                ? 'border-gray-200 bg-gray-50 text-gray-400 line-through dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500'
                                                : 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300'"
                                            @click="toggleColumn(report, column.key)"
                                        >
                                            {{ column.label }}
                                        </button>
                                    </div>
                                </div>

                                <InputError :message="reportsForm.errors[`reports.${index}.name`]" class="mt-1" />
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="submit"
                                class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="reportsForm.processing"
                            >
                                حفظ التقارير
                            </button>
                        </div>
                    </form>

                    <form :class="cardClass" @submit.prevent="addReport">
                        <h3 class="mb-4 font-semibold text-gray-800 dark:text-white">إضافة نسخة جديدة من تقرير</h3>

                        <div class="grid gap-4 sm:grid-cols-4">
                            <div>
                                <label :class="labelClass">المعرّف (إنجليزي)</label>
                                <input v-model="newReport.key" type="text" placeholder="monthly-operations" :class="inputClass" />
                                <InputError :message="newReport.errors.key" class="mt-1" />
                            </div>
                            <div>
                                <label :class="labelClass">نوع التقرير</label>
                                <select v-model="newReport.builder" :class="inputClass">
                                    <option v-for="builder in builders" :key="builder.value" :value="builder.value">{{ builder.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label :class="labelClass">الاسم المعروض</label>
                                <input v-model="newReport.name" type="text" :class="inputClass" />
                                <InputError :message="newReport.errors.name" class="mt-1" />
                            </div>
                            <div>
                                <label :class="labelClass">النطاق الافتراضي</label>
                                <select v-model="newReport.options.default_range" :class="inputClass">
                                    <option value="">حسب الإعداد العام</option>
                                    <option v-for="option in rangeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label :class="labelClass">الوصف</label>
                                <input v-model="newReport.description" type="text" :class="inputClass" />
                            </div>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="newReport.admin_only" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                للمدير فقط
                            </label>
                            <div class="mt-4 flex items-start">
                                <button
                                    type="submit"
                                    class="rounded-md border border-indigo-600 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 disabled:opacity-50 dark:hover:bg-indigo-500/10"
                                    :disabled="newReport.processing"
                                >
                                    إضافة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ======================= الإيصالات ======================= -->
                <div v-show="activeTab === 'receipts'" class="space-y-5">
                    <form :class="cardClass" @submit.prevent="saveSettings">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-800 dark:text-white">خيارات الإيصالات</h3>
                            <button type="button" class="text-xs text-gray-500 hover:text-rose-600 dark:text-slate-400" @click="resetGroup('receipts')">
                                استعادة الافتراضي
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label :class="labelClass">القالب الافتراضي للعمليات</label>
                                <select v-model="settingsForm.receipts.default_transaction_template" :class="inputClass">
                                    <option v-for="template in transactionTemplates" :key="template.key" :value="template.key">{{ template.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label :class="labelClass">القالب الافتراضي للورديات</label>
                                <select v-model="settingsForm.receipts.default_shift_template" :class="inputClass">
                                    <option v-for="template in shiftTemplates" :key="template.key" :value="template.key">{{ template.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label :class="labelClass">بادئة رقم الإيصال</label>
                                <input v-model="settingsForm.receipts.number_prefix" type="text" :class="inputClass" />
                            </div>
                            <div class="sm:col-span-3">
                                <label :class="labelClass">ملاحظة التذييل</label>
                                <input v-model="settingsForm.receipts.footer_note" type="text" :class="inputClass" />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                تفعيل طباعة الإيصالات
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.auto_open_after_transaction" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                فتح الإيصال تلقائياً بعد العملية (دون سؤال)
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.show_rate" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار سعر الصرف
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.show_customer" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار بيانات العميل
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.show_operator" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار اسم الموظف
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.show_notes" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار الملاحظات
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.show_profit" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار الربح (داخلي)
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <input v-model="settingsForm.receipts.show_signature" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                إظهار خانة التوقيع
                            </label>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="submit"
                                class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="settingsForm.processing"
                            >
                                حفظ
                            </button>
                        </div>
                    </form>

                    <div :class="cardClass">
                        <h3 class="mb-4 font-semibold text-gray-800 dark:text-white">قوالب الإيصالات</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                                <thead class="text-start text-xs text-gray-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2 text-start">الاسم</th>
                                        <th class="px-3 py-2 text-start">النوع</th>
                                        <th class="px-3 py-2 text-start">الورق</th>
                                        <th class="px-3 py-2 text-start">النسخ</th>
                                        <th class="px-3 py-2 text-start">الحالة</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-slate-200">
                                    <tr v-for="template in receiptTemplates" :key="template.key">
                                        <td class="px-3 py-2">
                                            {{ template.name }}
                                            <span v-if="template.is_default" class="ms-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">افتراضي</span>
                                        </td>
                                        <td class="px-3 py-2">{{ template.subject === 'shift' ? 'وردية' : 'عملية' }}</td>
                                        <td class="px-3 py-2">{{ template.paper }}</td>
                                        <td class="px-3 py-2">{{ template.copies }}</td>
                                        <td class="px-3 py-2">{{ template.is_active ? 'مفعّل' : 'معطّل' }}</td>
                                        <td class="px-3 py-2 text-end">
                                            <button type="button" class="text-xs text-indigo-600 hover:underline" @click="editTemplate(template)">تعديل</button>
                                            <button type="button" class="ms-3 text-xs text-rose-600 hover:underline" @click="deleteTemplate(template)">حذف</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form class="mt-5 border-t border-gray-100 pt-5 dark:border-slate-700" @submit.prevent="saveTemplate">
                            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-slate-200">
                                {{ editingTemplate ? `تعديل القالب: ${editingTemplate}` : 'قالب جديد' }}
                            </h4>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label :class="labelClass">المعرّف (إنجليزي)</label>
                                    <input v-model="templateForm.key" type="text" :disabled="!!editingTemplate" :class="inputClass" />
                                    <InputError :message="templateForm.errors.key" class="mt-1" />
                                </div>
                                <div>
                                    <label :class="labelClass">الاسم</label>
                                    <input v-model="templateForm.name" type="text" :class="inputClass" />
                                    <InputError :message="templateForm.errors.name" class="mt-1" />
                                </div>
                                <div>
                                    <label :class="labelClass">يُستخدم مع</label>
                                    <select v-model="templateForm.subject" :class="inputClass">
                                        <option value="transaction">عملية صرف</option>
                                        <option value="shift">وردية</option>
                                    </select>
                                </div>
                                <div>
                                    <label :class="labelClass">حجم الورق</label>
                                    <select v-model="templateForm.paper" :class="inputClass">
                                        <option v-for="paper in papers" :key="paper.value" :value="paper.value">{{ paper.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label :class="labelClass">عدد النسخ</label>
                                    <input v-model.number="templateForm.copies" type="number" min="1" max="5" :class="inputClass" />
                                    <InputError :message="templateForm.errors.copies" class="mt-1" />
                                </div>
                                <div>
                                    <label :class="labelClass">عنوان الإيصال</label>
                                    <input v-model="templateForm.options.title" type="text" :class="inputClass" />
                                </div>
                                <div>
                                    <label :class="labelClass">نص الترويسة</label>
                                    <input v-model="templateForm.options.header_text" type="text" :class="inputClass" />
                                </div>
                                <div>
                                    <label :class="labelClass">نص التذييل</label>
                                    <input v-model="templateForm.options.footer_text" type="text" :class="inputClass" />
                                </div>
                                <div class="flex flex-col justify-end gap-2 text-sm text-gray-700 dark:text-slate-300">
                                    <label class="flex items-center gap-2">
                                        <input v-model="templateForm.options.show_logo" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> إظهار الشعار
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input v-model="templateForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> مفعّل
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input v-model="templateForm.is_default" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> افتراضي لهذا النوع
                                    </label>
                                </div>
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <button v-if="editingTemplate" type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm dark:border-slate-600 dark:text-slate-200" @click="cancelTemplate">
                                    إلغاء
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="templateForm.processing"
                                >
                                    {{ editingTemplate ? 'حفظ التعديلات' : 'إضافة القالب' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
