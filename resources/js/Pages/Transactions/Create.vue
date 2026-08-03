<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionForm from '@/Components/TransactionForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    shift: { type: Object, required: true },
    currencies: { type: Array, required: true },
});
</script>

<template>
    <Head title="عملية جديدة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">تسجيل عملية بيع/شراء</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <TransactionForm :currencies="currencies" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
