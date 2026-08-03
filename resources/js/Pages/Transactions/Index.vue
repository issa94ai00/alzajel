<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    transactions: { type: Object, required: true },
    currencies: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    currency_id: props.filters.currency_id ?? '',
    type: props.filters.type ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    search: props.filters.search ?? '',
});

function applyFilters() {
    router.get(route('transactions.index'), form, { preserveState: true, replace: true });
}

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}
</script>

<template>
    <Head title="سجل العمليات" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">سجل العمليات</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-5">
                    <select v-model="form.currency_id" class="rounded-md border-gray-300 text-sm">
                        <option value="">كل العملات</option>
                        <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }}</option>
                    </select>
                    <select v-model="form.type" class="rounded-md border-gray-300 text-sm">
                        <option value="">كل الأنواع</option>
                        <option value="buy">شراء</option>
                        <option value="sell">بيع</option>
                    </select>
                    <input v-model="form.date_from" type="date" class="rounded-md border-gray-300 text-sm" />
                    <input v-model="form.date_to" type="date" class="rounded-md border-gray-300 text-sm" />
                    <input v-model="form.search" type="text" placeholder="بحث بالاسم أو الهاتف" class="rounded-md border-gray-300 text-sm" />
                    <button @click="applyFilters" class="col-span-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:col-span-5">
                        تطبيق الفلاتر
                    </button>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">التاريخ</th>
                                <th class="px-4 py-2 text-start">النوع</th>
                                <th class="px-4 py-2 text-start">العملة</th>
                                <th class="px-4 py-2 text-start">المبلغ</th>
                                <th class="px-4 py-2 text-start">السعر</th>
                                <th class="px-4 py-2 text-start">الإجمالي</th>
                                <th class="px-4 py-2 text-start">العميل</th>
                                <th class="px-4 py-2 text-start">الموظف</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="8" class="px-4 py-6 text-center text-gray-400">لا توجد عمليات مطابقة.</td>
                            </tr>
                            <tr v-for="t in transactions.data" :key="t.id">
                                <td class="px-4 py-2">{{ new Date(t.created_at).toLocaleString('ar-SY') }}</td>
                                <td class="px-4 py-2">
                                    <span :class="t.type === 'buy' ? 'text-emerald-700' : 'text-rose-700'" class="font-semibold">
                                        {{ t.type === 'buy' ? 'شراء' : 'بيع' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ t.currency.code }}</td>
                                <td class="px-4 py-2">{{ formatNumber(t.amount) }}</td>
                                <td class="px-4 py-2">{{ formatNumber(t.rate) }}</td>
                                <td class="px-4 py-2">{{ formatNumber(t.total) }}</td>
                                <td class="px-4 py-2">{{ t.customer_name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ t.user.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-1">
                    <Link
                        v-for="link in transactions.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="rounded-md px-3 py-1 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        :as="link.url ? 'a' : 'span'"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
