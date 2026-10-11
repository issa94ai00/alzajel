<script setup>
import { computed } from 'vue';
import { useNewLira } from '@/composables/useNewLira';

/**
 * أسفل حقل المبلغ:
 *  - تلميح هادئ يعرض ما يعادله بالليرة القديمة.
 *  - تحذير بارز إذا بدا المبلغ وكأنه أُدخل بالعملة القديمة سهواً، مع زر تصحيح.
 *
 * الخطأ المستهدف هو إدخال 125,000 بدل 1,250 — أي مضاعفة القيمة 100 مرة.
 */
const props = defineProps({
    amount: { type: [String, Number], default: '' },
    // العملة المرتبطة بالحقل؛ لا يظهر شيء إلا إذا كانت الليرة الجديدة.
    currency: { type: [Object, String], default: null },
});

const emit = defineEmits(['correct']);

const { isNewLira, noticeEnabled, looksLikeOldLira, asIfOldLira, formatOldLira, format } = useNewLira();

const relevant = computed(() => noticeEnabled() && isNewLira(props.currency));

const suspicious = computed(() => relevant.value && looksLikeOldLira(props.amount, props.currency));

const oldEquivalent = computed(() => formatOldLira(props.amount));

const suggestion = computed(() => asIfOldLira(props.amount));

const suggestionLabel = computed(() =>
    suggestion.value === null ? null : format(suggestion.value),
);

function applySuggestion() {
    if (suggestion.value !== null) emit('correct', suggestion.value);
}
</script>

<template>
    <div v-if="relevant && oldEquivalent">
        <!-- مبلغ كبير بشكل غير معتاد: يُرجّح أنه أُدخل بالعملة القديمة. -->
        <div
            v-if="suspicious"
            class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/25 dark:text-amber-200"
            role="alert"
        >
            <div class="font-semibold">مبلغ كبير — هل أدخلته بالليرة القديمة؟</div>
            <div class="mt-0.5">
                القيمة بالعملة الجديدة تكون
                <span class="font-semibold">{{ suggestionLabel }} ل.س ج</span>
            </div>
            <button
                type="button"
                class="mt-1.5 rounded border border-amber-400 bg-white px-2 py-1 font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-600 dark:bg-transparent dark:text-amber-200 dark:hover:bg-amber-800/40"
                @click="applySuggestion"
            >
                صحّح إلى {{ suggestionLabel }}
            </button>
        </div>

        <!-- الحالة العادية: تلميح هادئ بالمعادل القديم. -->
        <p v-else class="mt-1 text-xs text-slate-500 dark:text-slate-400">≈ {{ oldEquivalent }} ل.س قديمة</p>
    </div>
</template>
