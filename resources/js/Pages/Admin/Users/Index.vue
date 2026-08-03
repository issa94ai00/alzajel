<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    users: { type: Array, required: true },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const roleLabels = { admin: 'مدير', cashier: 'صراف' };

function destroy(user) {
    if (confirm(`هل أنت متأكد من حذف المستخدم ${user.name}؟`)) {
        router.delete(route('users.destroy', user.id));
    }
}
</script>

<template>
    <Head title="المستخدمون" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">المستخدمون</h2>
                <Link :href="route('users.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    + مستخدم جديد
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">الاسم</th>
                                <th class="px-4 py-2 text-start">البريد الإلكتروني</th>
                                <th class="px-4 py-2 text-start">الدور</th>
                                <th class="px-4 py-2 text-start"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-4 py-2">{{ user.name }}</td>
                                <td class="px-4 py-2">{{ user.email }}</td>
                                <td class="px-4 py-2">{{ roleLabels[user.roles[0]?.name] ?? '—' }}</td>
                                <td class="px-4 py-2 space-x-2 space-x-reverse">
                                    <Link :href="route('users.edit', user.id)" class="text-indigo-600 hover:underline">تعديل</Link>
                                    <button
                                        v-if="user.id !== currentUserId"
                                        @click="destroy(user)"
                                        class="text-rose-600 hover:underline"
                                    >
                                        حذف
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
