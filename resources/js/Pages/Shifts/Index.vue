<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    shifts: { type: Object, required: true },
});

const statusLabels = { open: 'مفتوحة', closed: 'مغلقة' };
</script>

<template>
    <Head title="الورديات" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">الورديات</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">التاريخ</th>
                                <th class="px-4 py-2 text-start">الحالة</th>
                                <th class="px-4 py-2 text-start">فتحت بواسطة</th>
                                <th class="px-4 py-2 text-start">أغلقت بواسطة</th>
                                <th class="px-4 py-2 text-start"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="shifts.data.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400">لا توجد ورديات بعد.</td>
                            </tr>
                            <tr v-for="shift in shifts.data" :key="shift.id">
                                <td class="px-4 py-2">{{ shift.business_date }}</td>
                                <td class="px-4 py-2">
                                    <span :class="shift.status === 'open' ? 'text-emerald-700' : 'text-gray-500'" class="font-semibold">
                                        {{ statusLabels[shift.status] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ shift.opened_by?.name }}</td>
                                <td class="px-4 py-2">{{ shift.closed_by?.name ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <Link :href="route('shifts.show', shift.id)" class="text-indigo-600 hover:underline">عرض</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
