<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    code: '',
    name: '',
    symbol: '',
    is_active: true,
});

function submit() {
    form.transform((data) => ({ ...data, code: data.code.toUpperCase() })).post(route('currencies.store'));
}
</script>

<template>
    <Head title="عملة جديدة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">عملة جديدة</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div>
                        <InputLabel for="code" value="رمز العملة (مثال: USD)" />
                        <TextInput
                            id="code"
                            v-model="form.code"
                            class="mt-1 block w-full uppercase"
                            maxlength="3"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.code" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="name" value="اسم العملة" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="symbol" value="العلامة (اختياري)" />
                        <TextInput id="symbol" v-model="form.symbol" class="mt-1 block w-full" />
                        <InputError :message="form.errors.symbol" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
                        <InputLabel for="is_active" value="مفعّلة" />
                    </div>

                    <PrimaryButton :disabled="form.processing">إنشاء العملة</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
