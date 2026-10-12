<script setup>
import { useFormat } from '@/composables/useFormat';
import { computed } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const emit = defineEmits(['request-deposit']);

const { number, rate } = useFormat();

const isSell = computed(() => props.summary.type === 'sell');

const hasAmount = computed(() => Number(props.summary.amount) > 0);
const hasTotal = computed(() => Number(props.summary.total) > 0);

/** الطرف الذي يخرج من الصندوق إلى يد العميل. */
const gives = computed(() =>
    isSell.value
        ? { label: 'تُسلّم للعميل (أجنبي)', value: props.summary.amount, code: props.summary.currencyCode, ready: hasAmount.value }
        : { label: 'تُسلّم للعميل (محلي)', value: props.summary.total, code: props.summary.baseCode, ready: hasTotal.value },
);

/** الطرف الذي يدخل إلى الصندوق من يد العميل. */
const receives = computed(() =>
    isSell.value
        ? { label: 'تستلم من العميل (محلي)', value: props.summary.total, code: props.summary.baseCode, ready: hasTotal.value }
        : { label: 'تستلم من العميل (أجنبي)', value: props.summary.amount, code: props.summary.currencyCode, ready: hasAmount.value },
);

const funding = computed(() => props.summary.funding ?? { code: '', available: 0, required: 0 });

const remaining = computed(() => funding.value.available - funding.value.required);
const deficit = computed(() => Math.max(0, funding.value.required - funding.value.available));
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border bg-white shadow-sm transition-all dark:bg-slate-800"
        :class="summary.insufficient
            ? 'border-rose-300 ring-1 ring-rose-300 dark:border-rose-700 dark:ring-rose-700/50'
            : 'border-gray-200 dark:border-slate-700'"
    >
        <!-- رأس الملخص -->
        <div
            class="flex items-center justify-between gap-3 border-b px-4 py-3 sm:px-5"
            :class="isSell
                ? 'border-rose-100 bg-rose-50/60 dark:border-rose-900/30 dark:bg-rose-950/20'
                : 'border-emerald-100 bg-emerald-50/60 dark:border-emerald-900/30 dark:bg-emerald-950/20'"
        >
            <div class="flex items-center gap-2">
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-white"
                    :class="isSell ? 'bg-rose-600' : 'bg-emerald-600'"
                >
                    <svg v-if="isSell" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6" />
                    </svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l6-6m-6 6l-6-6" />
                    </svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">ملخص العملية الحية</h3>
                    <p class="text-[11px] text-gray-500 dark:text-slate-400">
                        {{ isSell ? 'بيع عملة أجنبية وقبض الليرة' : 'شراء عملة أجنبية ودفع الليرة' }}
                    </p>
                </div>
            </div>

            <span
                class="rounded-full px-2.5 py-0.5 text-xs font-black tracking-wide"
                :class="isSell
                    ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200'
                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200'"
            >
                {{ isSell ? 'عملية بيع' : 'عملية شراء' }}
            </span>
        </div>

        <div class="space-y-4 p-4 sm:p-5">
            <!-- بطاقتا الدخول والخروج -->
            <div class="space-y-2.5">
                <!-- الطرف المستلم (يدخل الصندوق) -->
                <div class="rounded-xl border border-gray-100 bg-slate-50/70 p-3 dark:border-slate-700/60 dark:bg-slate-900/40">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-slate-400">
                        <span class="flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400">
                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                                ↙
                            </span>
                            {{ receives.label }}
                        </span>
                        <span class="text-[10px] text-gray-400 dark:text-slate-500">يدخل الصندوق</span>
                    </div>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span
                            class="text-xl font-black tabular-nums"
                            :class="receives.ready ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-slate-600'"
                        >
                            {{ receives.ready ? number(receives.value) : '0.00' }}
                        </span>
                        <span class="text-xs font-bold text-gray-600 dark:text-slate-300">
                            {{ receives.code }}
                        </span>
                    </div>
                </div>

                <!-- الطرف المسلّم (يخرج من الصندوق) -->
                <div class="rounded-xl border border-gray-100 bg-slate-50/70 p-3 dark:border-slate-700/60 dark:bg-slate-900/40">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-slate-400">
                        <span class="flex items-center gap-1.5 font-medium text-rose-700 dark:text-rose-400">
                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300">
                                ↗
                            </span>
                            {{ gives.label }}
                        </span>
                        <span class="text-[10px] text-gray-400 dark:text-slate-500">يخرج من الصندوق</span>
                    </div>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span
                            class="text-xl font-black tabular-nums"
                            :class="gives.ready ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-slate-600'"
                        >
                            {{ gives.ready ? number(gives.value) : '0.00' }}
                        </span>
                        <span class="text-xs font-bold text-gray-600 dark:text-slate-300">
                            {{ gives.code }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- السعر المطبق -->
            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/40 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/60">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-slate-400">سعر الصرف المعتمد:</span>
                    <div class="flex items-center gap-1.5 tabular-nums">
                        <span v-if="Number(summary.rate) > 0" class="font-bold text-gray-800 dark:text-slate-200">
                            1 {{ summary.currencyCode }} = {{ rate(summary.rate) }} {{ summary.baseCode }}
                        </span>
                        <span v-else class="text-gray-400 dark:text-slate-500">—</span>

                        <span
                            v-if="summary.rateOverridden"
                            class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-300"
                        >
                            مُعدَّل
                        </span>
                    </div>
                </div>
            </div>

            <!-- فحص كفاية الرصيد في الصندوق -->
            <div
                v-if="funding.code"
                class="rounded-xl border p-3 text-xs transition"
                :class="summary.insufficient
                    ? 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
                    : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-300'"
                :role="summary.insufficient ? 'alert' : null"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 font-medium">
                        <span
                            class="flex h-2 w-2 rounded-full"
                            :class="summary.insufficient ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500'"
                        />
                        <span>رصيد {{ funding.code }} المتاح في الدرج:</span>
                    </div>
                    <span class="font-bold tabular-nums">
                        {{ number(funding.available) }}
                    </span>
                </div>

                <div v-if="funding.required > 0" class="mt-2 flex items-center justify-between border-t border-dashed border-gray-200/80 pt-2 dark:border-slate-700/80">
                    <span>{{ summary.insufficient ? 'المبلغ المطلوب سحبه:' : 'الرصيد المتبقي بعد العملية:' }}</span>
                    <span class="font-bold tabular-nums" :class="summary.insufficient ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'">
                        {{ summary.insufficient ? number(funding.required) : number(remaining) }}
                    </span>
                </div>

                <!-- تنبيه العجز مع زر السؤال عن الإيداع -->
                <div v-if="summary.insufficient" class="mt-2.5 rounded-lg bg-rose-100/80 p-2.5 font-bold text-rose-800 dark:bg-rose-900/50 dark:text-rose-200">
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>عجز في الصندوق بمقدار {{ number(deficit) }} {{ funding.code }}</span>
                    </div>

                    <button
                        type="button"
                        @click="emit('request-deposit')"
                        class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-black text-white shadow-sm transition hover:bg-rose-700"
                    >
                        <span>➕ هل ترغب في تسجيل إيداع لتغطية العجز؟</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
