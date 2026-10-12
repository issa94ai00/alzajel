<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    currency: { type: Object, default: null },
    newBuy: { type: [Number, String], default: '' },
    newSell: { type: [Number, String], default: '' },
    currentBuy: { type: [Number, String], default: '' },
    currentSell: { type: [Number, String], default: '' },
    marketBuy: { type: [Number, String], default: null },
    marketSell: { type: [Number, String], default: null },
    deviationPercent: { type: [Number, String], default: null },
    isInverted: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const title = computed(() => {
    if (props.isInverted) {
        return `خطأ: سعر الشراء أكبر من سعر البيع (${props.currency?.code ?? ''})`;
    }
    return `تأكيد فارق سعري غير معتاد (${props.currency?.code ?? ''})`;
});

const message = computed(() => {
    if (props.isInverted) {
        return 'سعر الشراء المُدخل أعلى من سعر البيع، مما يعني تحقيق خسارة فورية في كل عملية صرافة. هل أنت متأكد من المتابعة؟';
    }
    return `السعر المُدخل يختلف بنسبة تقارب ${props.deviationPercent}% عن السعر المسجل حالياً أو سعر السوق المرجعي. يُرجى مراجعة الخانات لتفادي أخطاء الإدخال.`;
});
</script>

<template>
    <ConfirmDialog
        :show="show"
        max-width="lg"
        :title="title"
        :message="message"
        :confirm-label="isInverted ? 'حفظ رغم التحذير' : 'نعم، تثبيت هذا السعر'"
        cancel-label="العودة للتصحيح"
        :tone="isInverted ? 'danger' : 'warning'"
        :processing="processing"
        @cancel="emit('close')"
        @confirm="emit('confirm')"
    >
        <template #details>
            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                    <span>العملة:</span>
                    <span class="font-bold text-slate-900 dark:text-white">
                        {{ currency?.code }} - {{ currency?.name }}
                    </span>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-700/60 pt-2 flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">السعر المسجل حالياً في النظام:</span>
                    <span class="font-mono font-bold text-slate-700 dark:text-slate-300 tabular-nums">
                        شراء {{ currentBuy || '—' }} / بيع {{ currentSell || '—' }}
                    </span>
                </div>

                <div v-if="marketBuy" class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">سعر السوق المرجعي (sp-today):</span>
                    <span class="font-mono font-bold text-teal-600 dark:text-teal-400 tabular-nums">
                        شراء {{ marketBuy }} / بيع {{ marketSell }}
                    </span>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-700/60 pt-2 flex items-center justify-between bg-slate-100/60 dark:bg-slate-800/60 p-2 rounded-lg">
                    <span class="font-black text-slate-800 dark:text-slate-200">السعر الجديد المطلوب حفظه:</span>
                    <span class="font-mono font-black text-indigo-700 dark:text-indigo-300 tabular-nums text-sm">
                        شراء {{ newBuy }} / بيع {{ newSell }}
                    </span>
                </div>
            </div>
        </template>
    </ConfirmDialog>
</template>
