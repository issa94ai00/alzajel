<script setup>
import { useFormat } from '@/composables/useFormat';
import { computed } from 'vue';

/**
 * بطاقة ملخص العملية الحيّة: ماذا يُسلّم الصرّاف وماذا يستلم، وبأي سعر،
 * وهل يكفي الرصيد.
 *
 * تُعرض مرتين: داخل النموذج على الشاشات الصغيرة، وفي عمود جانبي ثابت على
 * الشاشات الكبيرة — فيبقى الإجمالي أمام عين الصرّاف وهو يكتب المبلغ.
 */
const props = defineProps({
    summary: { type: Object, required: true },
});

const { number, rate } = useFormat();

const isSell = computed(() => props.summary.type === 'sell');

const hasAmount = computed(() => Number(props.summary.amount) > 0);

const hasTotal = computed(() => Number(props.summary.total) > 0);

/** الطرف الذي يخرج من الصندوق. */
const gives = computed(() =>
    isSell.value
        ? { value: props.summary.amount, code: props.summary.currencyCode, ready: hasAmount.value }
        : { value: props.summary.total, code: props.summary.baseCode, ready: hasTotal.value },
);

/** الطرف الذي يدخل إلى الصندوق. */
const receives = computed(() =>
    isSell.value
        ? { value: props.summary.total, code: props.summary.baseCode, ready: hasTotal.value }
        : { value: props.summary.amount, code: props.summary.currencyCode, ready: hasAmount.value },
);

const funding = computed(() => props.summary.funding ?? { code: '', available: 0, required: 0 });

const remaining = computed(() => funding.value.available - funding.value.required);
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-slate-800"
        :class="summary.insufficient ? 'border-rose-300 dark:border-rose-700/60' : 'border-gray-200 dark:border-slate-700'"
    >
        <div
            class="flex items-center justify-between gap-2 px-5 py-3"
            :class="isSell ? 'bg-rose-50 dark:bg-rose-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20'"
        >
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">ملخص العملية</h3>
            <span
                class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                :class="isSell ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'"
            >
                {{ isSell ? 'بيع' : 'شراء' }}
            </span>
        </div>

        <div class="space-y-3 px-5 py-4">
            <!-- ما يخرج من الصندوق -->
            <div class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6" />
                        </svg>
                    </span>
                    تُسلّم للعميل
                </span>
                <span class="text-end tabular-nums" :class="gives.ready ? 'text-lg font-bold text-gray-900 dark:text-white' : 'text-gray-300 dark:text-slate-600'">
                    {{ gives.ready ? number(gives.value) : '—' }}
                    <span class="text-xs font-semibold text-gray-400 dark:text-slate-500">{{ gives.code }}</span>
                </span>
            </div>

            <!-- ما يدخل إلى الصندوق -->
            <div class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l6-6m-6 6l-6-6" />
                        </svg>
                    </span>
                    تستلم من العميل
                </span>
                <span class="text-end tabular-nums" :class="receives.ready ? 'text-lg font-bold text-gray-900 dark:text-white' : 'text-gray-300 dark:text-slate-600'">
                    {{ receives.ready ? number(receives.value) : '—' }}
                    <span class="text-xs font-semibold text-gray-400 dark:text-slate-500">{{ receives.code }}</span>
                </span>
            </div>

            <!-- السعر المطبّق -->
            <div class="flex items-center justify-between gap-3 border-t border-dashed border-gray-200 pt-3 text-xs dark:border-slate-600">
                <span class="text-gray-500 dark:text-slate-400">السعر</span>
                <span class="flex items-center gap-2 tabular-nums">
                    <span v-if="Number(summary.rate) > 0" class="font-semibold text-gray-700 dark:text-slate-200">
                        1 {{ summary.currencyCode }} = {{ rate(summary.rate) }} {{ summary.baseCode }}
                    </span>
                    <span v-else class="text-gray-300 dark:text-slate-600">—</span>
                    <span
                        v-if="summary.rateOverridden"
                        class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                        :title="`الرسمي ${rate(summary.officialRate)}`"
                    >
                        مُعدَّل
                    </span>
                </span>
            </div>
        </div>

        <!-- كفاية الرصيد: الخادم يرفض العملية عند النقص، فنُظهره قبل الإرسال. -->
        <div
            v-if="funding.code"
            class="border-t px-5 py-3 text-xs"
            :class="summary.insufficient
                ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/25 dark:text-rose-200'
                : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-slate-700 dark:bg-slate-700/40 dark:text-slate-300'"
            :role="summary.insufficient ? 'alert' : null"
        >
            <div class="flex items-center justify-between gap-2">
                <span>رصيد {{ funding.code }} في الصندوق</span>
                <span class="font-semibold tabular-nums">{{ number(funding.available) }}</span>
            </div>
            <div v-if="funding.required > 0" class="mt-1 flex items-center justify-between gap-2">
                <span>{{ summary.insufficient ? 'النقص' : 'بعد العملية' }}</span>
                <span class="font-semibold tabular-nums">{{ number(Math.abs(remaining)) }}</span>
            </div>
            <p v-if="summary.insufficient" class="mt-1.5 font-semibold">الرصيد غير كافٍ لإتمام هذه العملية.</p>
        </div>
    </div>
</template>
