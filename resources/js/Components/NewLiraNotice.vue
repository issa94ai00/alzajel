<script setup>
import { computed, ref } from 'vue';
import { useNewLira } from '@/composables/useNewLira';

/**
 * شريط يوضّح أن مبالغ الليرة السورية معروضة بالعملة الجديدة بعد حذف صفرين.
 *
 * الصياغة تخصّ الليرة وحدها عمداً: في نموذج العملية قد يكون المبلغ بالدولار
 * أو اليورو، فقول «جميع المبالغ» يكون غير دقيق.
 */
const props = defineProps({
    // سطر واحد مختصر، للاستخدام داخل النماذج.
    compact: { type: Boolean, default: false },
    // إن مُرِّرت، لا يظهر الشريط إلا إذا كانت الليرة السورية ضمنها.
    currencies: { type: Array, default: null },
    // مفتاح تذكّر الإخفاء؛ إن غاب فالشريط غير قابل للإخفاء.
    dismissKey: { type: String, default: null },
});

const { isNewLira, noticeEnabled, OLD_TO_NEW_FACTOR } = useNewLira();

const STORAGE_PREFIX = 'new-lira-notice:';

// قراءة التخزين قد تفشل (وضع خاص، تخزين محظور) — لا يجوز أن يكسر الصفحة.
function readDismissed(key) {
    if (!key) return false;

    try {
        return window.localStorage.getItem(STORAGE_PREFIX + key) === '1';
    } catch {
        return false;
    }
}

const dismissed = ref(readDismissed(props.dismissKey));

function dismiss() {
    dismissed.value = true;

    if (!props.dismissKey) return;

    try {
        window.localStorage.setItem(STORAGE_PREFIX + props.dismissKey, '1');
    } catch {
        // الإخفاء يبقى ساري المفعول لهذه الجلسة فقط.
    }
}

const hasLira = computed(
    () => props.currencies === null || props.currencies.some((c) => isNewLira(c?.currency ?? c)),
);

const show = computed(() => noticeEnabled() && hasLira.value && !dismissed.value);
</script>

<template>
    <div
        v-if="show"
        class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-800/50 dark:bg-sky-900/20 dark:text-sky-200"
        :class="compact ? 'p-3 text-xs' : 'p-4 text-sm'"
        role="note"
    >
        <svg
            class="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" d="M12 11v5" />
            <path stroke-linecap="round" d="M12 8h.01" />
        </svg>

        <div class="flex-1">
            <span class="font-semibold">مبالغ الليرة السورية بالعملة الجديدة</span>
            <span> — حُذف صفران من العملة القديمة.</span>
            <div class="mt-0.5 opacity-90">{{ OLD_TO_NEW_FACTOR }} ل.س قديمة = 1 ل.س ج</div>
        </div>

        <button
            v-if="dismissKey"
            type="button"
            class="shrink-0 rounded p-1 text-sky-700 transition hover:bg-sky-100 dark:text-sky-300 dark:hover:bg-sky-800/40"
            aria-label="إخفاء هذا التنبيه"
            title="إخفاء"
            @click="dismiss"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>
</template>
