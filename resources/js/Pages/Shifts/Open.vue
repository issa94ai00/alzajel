<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    currencies: { type: Array, required: true },
    currentShiftOpen: { type: Boolean, default: false },
});

const form = useForm({
    opening_balances: Object.fromEntries(props.currencies.map((c) => [c.id, '0'])),
});

function submit() {
    if (props.currentShiftOpen && !confirm('سيتم إغلاق الوردية الحالية وفتح وردية جديدة بدلاً منها. هل تريد المتابعة؟')) {
        return;
    }

    form.post(route('shifts.store'));
}
</script>

<template>
    <Head title="وردية جديدة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ currentShiftOpen ? 'إغلاق الوردية الحالية وفتح وردية جديدة' : 'فتح وردية جديدة' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <div v-if="currentShiftOpen" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    توجد وردية مفتوحة حالياً. عند المتابعة سيتم إغلاقها تلقائياً بأرصدتها الحالية، ثم فتح وردية جديدة بالأرصدة أدناه.
                </div>

                <form @submit.prevent="submit" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-500">أدخل الرصيد الافتتاحي لكل عملة (القيمة الافتراضية 0، ويمكن تعديلها).</p>

                    <div v-for="currency in currencies" :key="currency.id">
                        <InputLabel :for="`balance-${currency.id}`" :value="`${currency.name} (${currency.code})`" />
                        <TextInput
                            :id="`balance-${currency.id}`"
                            v-model="form.opening_balances[currency.id]"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors[`opening_balances.${currency.id}`]" class="mt-1" />
                    </div>

                    <InputError :message="form.errors.shift" />

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            {{ currentShiftOpen ? 'إغلاق الوردية الحالية وفتح وردية جديدة' : 'فتح الوردية' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
