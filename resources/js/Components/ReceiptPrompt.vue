<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * السؤال عن طباعة الإيصال بعد إتمام العملية.
 *
 * كانت النافذة تُفتح تلقائياً وبصمت، وهو ما يحجبه مانع النوافذ المنبثقة في
 * أغلب المتصفحات لأنه لم يأتِ من نقرة مستخدم. هنا تُفتح من زر يضغطه الصرّاف،
 * فتمرّ دائماً. ومن فعّل الفتح التلقائي من الإعدادات يحتفظ بسلوكه القديم.
 */
const page = usePage();

const pending = ref(null);

watch(
    () => page.props.flash?.receipt,
    (receipt) => {
        if (!receipt?.url) return;

        if (receipt.auto) {
            window.open(receipt.url, '_blank');

            return;
        }

        pending.value = receipt;
    },
    { immediate: true },
);

function print() {
    const url = pending.value?.url;

    pending.value = null;

    if (url) window.open(url, '_blank');
}
</script>

<template>
    <Modal :show="Boolean(pending)" max-width="md" @close="pending = null">
        <div class="p-6 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">تم تسجيل العملية بنجاح</h2>

            <p v-if="pending?.summary" class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                {{ pending.summary }}
                <span v-if="pending?.number" class="text-gray-400 dark:text-slate-500">— {{ pending.number }}</span>
            </p>

            <p class="mt-4 text-sm text-gray-700 dark:text-slate-300">هل تريد طباعة الإيصال؟</p>

            <div class="mt-5 flex justify-center gap-3">
                <PrimaryButton autofocus @click="print">طباعة الإيصال</PrimaryButton>
                <SecondaryButton @click="pending = null">لاحقاً</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
