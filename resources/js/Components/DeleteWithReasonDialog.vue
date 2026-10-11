<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref, watch } from 'vue';

/**
 * حوار حذف يطلب السبب قبل التأكيد.
 *
 * الحذف هنا ناعم وقابل للاستعادة، فالتحذير ليس "لا رجعة فيه" بل "سيُسجَّل
 * باسمك". والسبب إلزامي لأن سجل التدقيق بلا سبب لا يُجيب السؤال الذي يُطرح
 * عند المراجعة بعد شهور.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'حذف' },
    processing: { type: Boolean, default: false },
    // خطأ قادم من الخادم (تحقّق أو قاعدة عمل)، يُعرض داخل الحوار.
    error: { type: String, default: '' },
});

const emit = defineEmits(['confirm', 'cancel']);

const reason = ref('');
const localError = ref('');

watch(
    () => props.show,
    (open) => {
        if (open) {
            reason.value = '';
            localError.value = '';
        }
    },
);

function confirm() {
    const trimmed = reason.value.trim();

    // نمنع الإرسال الفارغ هنا بدل انتظار 422 من الخادم.
    if (trimmed.length < 3) {
        localError.value = 'اكتب سبباً مفهوماً (٣ أحرف على الأقل).';

        return;
    }

    localError.value = '';
    emit('confirm', trimmed);
}
</script>

<template>
    <ConfirmDialog
        :show="show"
        :title="title"
        :message="message"
        :confirm-label="confirmLabel"
        :processing="processing"
        tone="danger"
        @confirm="confirm"
        @cancel="emit('cancel')"
    >
        <template #details>
            <div v-if="$slots.summary" class="mb-3 space-y-1 border-b border-gray-200 pb-3 dark:border-slate-600">
                <slot name="summary" />
            </div>

            <label for="deletion-reason" class="block text-xs font-semibold text-gray-700 dark:text-slate-200">
                سبب الحذف <span class="text-rose-600 dark:text-rose-400">*</span>
            </label>

            <textarea
                id="deletion-reason"
                v-model="reason"
                rows="2"
                :disabled="processing"
                placeholder="مثال: أُدخلت بمبلغ خاطئ"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500"
            ></textarea>

            <p v-if="localError || error" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                {{ localError || error }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">
                يُحفظ السبب في سجل التدقيق مع اسمك ووقت الحذف. الحذف قابل للاستعادة لاحقاً.
            </p>
        </template>
    </ConfirmDialog>
</template>
