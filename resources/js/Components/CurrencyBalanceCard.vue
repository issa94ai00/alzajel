<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useFormat } from '@/composables/useFormat';

const props = defineProps({
    code: { type: String, required: true },
    name: { type: String, required: true },
    symbol: { type: String, default: '' },
    balance: { type: [String, Number], required: true },
    openingBalance: { type: [String, Number], default: null },
    isBase: { type: Boolean, default: false },
});

const { number } = useFormat();

const current = computed(() => Number(props.balance) || 0);
const opening = computed(() => (props.openingBalance !== null && props.openingBalance !== undefined) ? Number(props.openingBalance) : null);

const diff = computed(() => {
    if (opening.value === null) return 0;
    return current.value - opening.value;
});

const hasIncreased = computed(() => diff.value > 0);
const hasDecreased = computed(() => diff.value < 0);
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-2xl border p-5 transition duration-200 hover:shadow-lg dark:bg-slate-800/90"
        :class="isBase
            ? 'border-indigo-300/80 bg-gradient-to-br from-indigo-50/90 via-white to-indigo-50/40 shadow-sm ring-1 ring-indigo-500/20 dark:border-indigo-700/60 dark:from-slate-800 dark:via-slate-800 dark:to-indigo-950/30'
            : 'border-slate-200 bg-white shadow-sm hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'"
    >
        <!-- الرأس: اسم العملة ورمزها -->
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-black text-sm shadow-sm"
                    :class="isBase
                        ? 'bg-indigo-600 text-white shadow-indigo-600/30 dark:bg-indigo-500'
                        : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'"
                >
                    {{ symbol || code.slice(0, 3) }}
                </span>

                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-base font-black text-slate-900 dark:text-white">
                            {{ code }}
                        </span>
                        <span
                            v-if="isBase"
                            class="rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                        >
                            عملة الأساس
                        </span>
                    </div>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400" :title="name">
                        {{ name }}
                    </p>
                </div>
            </div>

            <!-- شارة صافي حركة الوردية (الفرق عن الرصيد الافتتاحي) -->
            <div v-if="opening !== null" class="shrink-0 text-end">
                <span
                    class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                    :class="hasIncreased
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                        : hasDecreased
                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400'"
                >
                    <span v-if="hasIncreased">↑ +{{ number(Math.abs(diff)) }}</span>
                    <span v-else-if="hasDecreased">↓ -{{ number(Math.abs(diff)) }}</span>
                    <span v-else>مستقر</span>
                </span>
            </div>
        </div>

        <!-- الرصيد الحالي -->
        <div class="mt-4">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                الرصيد المتاح حالياً
            </span>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                    {{ number(balance) }}
                </span>
                <span class="text-sm font-bold text-slate-400 dark:text-slate-500">
                    {{ symbol || code }}
                </span>
            </div>
        </div>

        <!-- معلومات الرصيد الافتتاحي وزر الإجراء -->
        <div class="mt-3.5 flex items-center justify-between border-t border-dashed border-slate-200/80 pt-3 text-xs dark:border-slate-700/80">
            <div v-if="opening !== null" class="text-slate-500 dark:text-slate-400">
                <span>الافتتاحي: </span>
                <span class="font-bold tabular-nums text-slate-700 dark:text-slate-300">{{ number(opening) }}</span>
            </div>
            <div v-else></div>

            <Link
                v-if="!isBase"
                :href="route('transactions.create')"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
            >
                <span>صرافة</span>
                <span aria-hidden="true">←</span>
            </Link>
        </div>
    </div>
</template>
