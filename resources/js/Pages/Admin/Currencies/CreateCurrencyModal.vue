<script setup>
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const form = useForm({
    code: '',
    name: '',
    symbol: '',
    tag: '',
    is_active: true,
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        form.reset();
        form.clearErrors();
        form.is_active = true;
    },
);

function submit() {
    form.post(route('currencies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="!form.processing" @close="emit('close')">
        <form @submit.prevent="submit" class="flex flex-col">
            <!-- الرأس -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-indigo-50/50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-800 dark:to-indigo-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/25">
                            <NavIcon name="plus-circle" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                إضافة عملة جديدة مخصصة
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                تسجيل عملة أو فئة نقدية جديدة في النظام
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="form.processing"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition"
                        @click="emit('close')"
                    >
                        <NavIcon name="close" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- الحقول -->
            <div class="p-5 space-y-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        رمز العملة (ISO 4217) *
                    </label>
                    <input
                        v-model="form.code"
                        type="text"
                        maxlength="3"
                        placeholder="USD"
                        class="mt-1 w-full uppercase font-mono rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        required
                    />
                    <InputError :message="form.errors.code" class="mt-1" />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        اسم العملة بالعربية *
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="دولار أمريكي"
                        class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        required
                    />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            علامة العملة (رمز مختصر)
                        </label>
                        <input
                            v-model="form.symbol"
                            type="text"
                            placeholder="$"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        />
                        <InputError :message="form.errors.symbol" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            رمز الفئة (اختياري)
                        </label>
                        <input
                            v-model="form.tag"
                            type="text"
                            placeholder="مثال: 50 أو 100"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        />
                        <InputError :message="form.errors.tag" class="mt-1" />
                    </div>
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                        />
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            تفعيل العملة فوراً بعد الإضافة
                        </span>
                    </label>
                </div>
            </div>

            <!-- التذييل -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/90 flex items-center justify-end gap-2">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition"
                    @click="emit('close')"
                >
                    إلغاء
                </button>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black text-white hover:bg-indigo-700 shadow-xs transition disabled:opacity-50"
                >
                    <NavIcon name="check" class="h-3.5 w-3.5" :class="form.processing ? 'animate-spin' : ''" />
                    <span>{{ form.processing ? 'جارٍ الحفظ...' : 'حفظ العملة' }}</span>
                </button>
            </div>
        </form>
    </Modal>
</template>
