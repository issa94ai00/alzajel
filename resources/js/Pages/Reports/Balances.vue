<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CurrencyBalanceCard from '@/Components/CurrencyBalanceCard.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    shift: { type: Object, default: null },
    balances: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="تقرير الأرصدة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">تقرير الأرصدة الحالية</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <p v-if="!shift" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
                    لا توجد وردية مفتوحة حالياً.
                </p>
                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CurrencyBalanceCard
                        v-for="balance in balances"
                        :key="balance.currency.id"
                        :code="balance.currency.code"
                        :name="balance.currency.name"
                        :symbol="balance.currency.symbol"
                        :balance="balance.current_balance"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
