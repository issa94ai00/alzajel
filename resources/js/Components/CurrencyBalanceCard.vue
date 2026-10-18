<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
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

// أيقونات وتدرجات مميزة لكل عملة
const currencyTheme = computed(() => {
    const glass = 'border-white/60 bg-white/70 backdrop-blur-xl dark:border-white/10 dark:bg-slate-800/50';
    const glow = 'bg-slate-400/0';

    if (props.isBase) {
        return {
            gradient: 'from-indigo-600 to-violet-700 text-white shadow-indigo-600/30',
            border: 'border-indigo-300/70 ring-1 ring-inset ring-indigo-500/20 dark:border-indigo-700/50',
            bg: 'bg-gradient-to-br from-indigo-50/80 via-white/70 to-violet-50/50 backdrop-blur-xl dark:from-slate-800/60 dark:via-slate-800/50 dark:to-indigo-950/40',
            glow: 'bg-indigo-500/15 group-hover:bg-indigo-500/25',
        };
    }

    const c = props.code.toUpperCase();
    if (c === 'USD') {
        return {
            gradient: 'from-emerald-500 to-emerald-700 text-white shadow-emerald-500/25',
            border: 'border-white/60 hover:border-emerald-300 dark:border-white/10 dark:hover:border-emerald-700',
            bg: glass,
            glow: 'bg-emerald-500/10 group-hover:bg-emerald-500/20',
        };
    }
    if (c === 'EUR') {
        return {
            gradient: 'from-blue-500 to-blue-700 text-white shadow-blue-500/25',
            border: 'border-white/60 hover:border-blue-300 dark:border-white/10 dark:hover:border-blue-700',
            bg: glass,
            glow: 'bg-blue-500/10 group-hover:bg-blue-500/20',
        };
    }
    if (c === 'SAR' || c === 'AED' || c === 'QAR' || c === 'KWD') {
        return {
            gradient: 'from-amber-500 to-amber-700 text-white shadow-amber-500/25',
            border: 'border-white/60 hover:border-amber-300 dark:border-white/10 dark:hover:border-amber-700',
            bg: glass,
            glow: 'bg-amber-500/10 group-hover:bg-amber-500/20',
        };
    }
    if (c === 'TRY') {
        return {
            gradient: 'from-rose-500 to-rose-700 text-white shadow-rose-500/25',
            border: 'border-white/60 hover:border-rose-300 dark:border-white/10 dark:hover:border-rose-700',
            bg: glass,
            glow: 'bg-rose-500/10 group-hover:bg-rose-500/20',
        };
    }
    if (c === 'GBP') {
        return {
            gradient: 'from-purple-500 to-purple-700 text-white shadow-purple-500/25',
            border: 'border-white/60 hover:border-purple-300 dark:border-white/10 dark:hover:border-purple-700',
            bg: glass,
            glow: 'bg-purple-500/10 group-hover:bg-purple-500/20',
        };
    }

    return {
        gradient: 'from-slate-600 to-slate-800 text-white shadow-slate-600/25',
        border: 'border-white/60 hover:border-slate-300 dark:border-white/10 dark:hover:border-slate-600',
        bg: glass,
        glow,
    };
});
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-2xl border p-5 shadow-sm backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
        :class="[currencyTheme.border, currencyTheme.bg]"
    >
        <!-- هالة لونية زخرفية خلف البطاقة -->
        <div
            class="pointer-events-none absolute -end-10 -top-10 h-28 w-28 rounded-full blur-2xl transition-colors duration-300"
            :class="currencyTheme.glow"
        ></div>

        <!-- الرأس: رمز العملة واسمها وشارة التغير -->
        <div class="relative flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0">
                <!-- أيقونة العملة مع تدرج لوني وظل -->
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br font-black text-sm shadow-md transition-transform duration-200 group-hover:scale-105"
                    :class="currencyTheme.gradient"
                >
                    <template v-if="isBase">
                        <NavIcon name="wallet" class="h-5 w-5" />
                    </template>
                    <template v-else>
                        {{ symbol || code.slice(0, 3) }}
                    </template>
                </span>

                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-base font-black text-slate-900 dark:text-white">
                            {{ code }}
                        </span>
                        <span
                            v-if="isBase"
                            class="inline-flex items-center gap-1 rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                        >
                            <NavIcon name="check" class="h-2.5 w-2.5" />
                            <span>عملة الأساس</span>
                        </span>
                    </div>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400" :title="name">
                        {{ name }}
                    </p>
                </div>
            </div>

            <!-- شارة صافي حركة الوردية مع أيقونة السهم -->
            <div v-if="opening !== null" class="shrink-0 text-end">
                <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold tabular-nums shadow-xs"
                    :class="hasIncreased
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                        : hasDecreased
                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400'"
                >
                    <template v-if="hasIncreased">
                        <NavIcon name="trending-up" class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                        <span>+{{ number(Math.abs(diff)) }}</span>
                    </template>
                    <template v-else-if="hasDecreased">
                        <NavIcon name="trending-down" class="h-3 w-3 text-rose-600 dark:text-rose-400" />
                        <span>-{{ number(Math.abs(diff)) }}</span>
                    </template>
                    <template v-else>
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                        <span>مستقر</span>
                    </template>
                </span>
            </div>
        </div>

        <!-- الرصيد الحالي المتاح -->
        <div class="relative mt-4">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                الرصيد المتاح حالياً
            </span>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-2xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                    {{ number(balance) }}
                </span>
                <span class="text-sm font-bold text-slate-400 dark:text-slate-500">
                    {{ symbol || code }}
                </span>
            </div>
        </div>

        <!-- معلومات الرصيد الافتتاحي وزر صرافة سريعة -->
        <div class="relative mt-3.5 flex items-center justify-between border-t border-dashed border-slate-200/80 pt-3 text-xs dark:border-slate-700/80">
            <div v-if="opening !== null" class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                <NavIcon name="clock" class="h-3.5 w-3.5 text-slate-400" />
                <span>الافتتاحي: </span>
                <span class="font-bold tabular-nums text-slate-700 dark:text-slate-300">{{ number(opening) }}</span>
            </div>
            <div v-else></div>

            <Link
                v-if="!isBase"
                :href="route('transactions.create')"
                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
            >
                <NavIcon name="plus-circle" class="h-3.5 w-3.5" />
                <span>صرافة</span>
            </Link>
        </div>
    </div>
</template>
