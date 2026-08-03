<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'cashier',
});

function submit() {
    form.post(route('users.store'));
}
</script>

<template>
    <Head title="مستخدم جديد" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">مستخدم جديد</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div>
                        <InputLabel for="name" value="الاسم" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="email" value="البريد الإلكتروني" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="role" value="الدور" />
                        <select id="role" v-model="form.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="cashier">صراف</option>
                            <option value="admin">مدير</option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="password" value="كلمة المرور" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required />
                    </div>

                    <PrimaryButton :disabled="form.processing">إنشاء المستخدم</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
