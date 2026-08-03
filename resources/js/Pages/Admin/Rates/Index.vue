<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    currencies: { type: Array, required: true },
});

const forms = Object.fromEntries(
    props.currencies.map((c) => [
        c.id,
        useForm({
            buy_rate: c.exchange_rate?.buy_rate ?? '',
            sell_rate: c.exchange_rate?.sell_rate ?? '',
        }),
    ])
);

function submit(currencyId) {
    forms[currencyId].put(route('rates.update', currencyId));
}
</script>

<template>
    <Head title="أسعار الصرف" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">أسعار الصرف</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <form
                    v-for="currency in currencies"
                    :key="currency.id"
                    @submit.prevent="submit(currency.id)"
                    class="flex items-end gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div class="w-24 font-semibold text-gray-800">{{ currency.name }} ({{ currency.code }})</div>

                    <div>
                        <label class="block text-xs text-gray-500">سعر الشراء</label>
                        <input
                            v-model="forms[currency.id].buy_rate"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            class="mt-1 w-32 rounded-md border-gray-300 text-sm"
                        />
                        <InputError :message="forms[currency.id].errors.buy_rate" />
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500">سعر البيع</label>
                        <input
                            v-model="forms[currency.id].sell_rate"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            class="mt-1 w-32 rounded-md border-gray-300 text-sm"
                        />
                        <InputError :message="forms[currency.id].errors.sell_rate" />
                    </div>

                    <button
                        type="submit"
                        :disabled="forms[currency.id].processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        حفظ
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
