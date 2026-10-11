<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    currency: { type: Object, required: true },
});

const form = useForm({
    code: props.currency.code,
    name: props.currency.name,
    symbol: props.currency.symbol ?? '',
    tag: props.currency.tag ?? '',
    is_active: props.currency.is_active,
});

function submit() {
    form.transform((data) => ({ ...data, code: data.code.toUpperCase() })).put(route('currencies.update', props.currency.id));
}
</script>

<template>
    <Head title="تعديل عملة" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">تعديل عملة</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div v-if="currency.is_base" class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-800 dark:border-indigo-800/50 dark:bg-indigo-900/20 dark:text-indigo-300">
                        هذه هي العملة الأساسية للنظام، ولا يمكن تعطيلها.
                    </div>

                    <div>
                        <InputLabel for="code" value="رمز العملة" />
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

                    <!-- رمز الفئة: ما يسمح بوجود USD مرتين — فئة المئة وفئة الخمسين — بسعر ورصيد مستقلين لكل منهما. -->
                    <div>
                        <InputLabel for="tag" value="رمز الفئة (اختياري)" />
                        <TextInput id="tag" v-model="form.tag" class="mt-1 block w-full" maxlength="50" placeholder="مثال: USD-100" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                            سلسلة مميزة تفرّق بين فئات العملة الواحدة. اتركه فارغاً إن كانت للعملة فئة واحدة.
                        </p>
                        <InputError :message="form.errors.tag" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-gray-300 dark:border-slate-600 dark:bg-slate-800"
                            :disabled="currency.is_base"
                        />
                        <InputLabel for="is_active" value="مفعّلة" />
                    </div>

                    <PrimaryButton :disabled="form.processing">حفظ التعديلات</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
