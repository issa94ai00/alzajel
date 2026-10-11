<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NavIcon from '@/Components/NavIcon.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    reports: { type: Array, required: true },
});
</script>

<template>
    <Head title="التقارير" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">التقارير</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                <NewLiraNotice dismiss-key="reports-index" />

                <p class="text-sm text-gray-500 dark:text-slate-400">
                    اختر نوع التقرير الذي تريد عرضه. يمكن للمدير التحكم بالتقارير المتاحة وأعمدتها من
                    <Link v-if="$page.props.auth.isAdmin" :href="route('settings.index')" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">صفحة الإعدادات</Link>
                    <span v-else>صفحة الإعدادات</span>.
                </p>

                <div v-if="reports.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    لا توجد تقارير مفعّلة حالياً.
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="report in reports"
                        :key="report.key"
                        :href="route('reports.show', report.key)"
                        class="group flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-500"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                <NavIcon name="chart-bar" class="h-5 w-5" />
                            </span>
                            <span class="font-semibold text-gray-800 group-hover:text-indigo-700 dark:text-white dark:group-hover:text-indigo-400">
                                {{ report.name }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-slate-400">{{ report.description || 'عرض وتصدير البيانات.' }}</p>
                        <span v-if="report.admin_only" class="mt-auto w-fit rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            للمدير فقط
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
