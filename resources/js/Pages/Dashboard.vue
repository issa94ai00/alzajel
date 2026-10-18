<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CurrencyBalanceCard from '@/Components/CurrencyBalanceCard.vue';
import NavIcon from '@/Components/NavIcon.vue';
import NewLiraNotice from '@/Components/NewLiraNotice.vue';
import { useFormat } from '@/composables/useFormat';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shift: { type: Object, default: null },
    stats: { type: Object, default: null },
    balances: { type: Array, default: () => [] },
    recentTransactions: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => page.props.auth.isAdmin);
const { number, rate: formatRate, date, settings } = useFormat();

const activeFilter = ref('all'); // 'all' | 'buy' | 'sell'

// تحية حسب وقت اليوم — تُحسب على العميل فقط.
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'صباح الخير';
    if (hour < 17) return 'طاب يومك';
    return 'مساء الخير';
});

// ترتيب الأرصدة بحيث تظهر عملة الأساس (الليرة) أولاً
const sortedBalances = computed(() => {
    return [...props.balances].sort((a, b) => {
        if (a.currency.is_base) return -1;
        if (b.currency.is_base) return 1;
        return a.currency.code.localeCompare(b.currency.code);
    });
});

const baseCurrency = computed(() => {
    return props.balances.find((b) => b.currency.is_base)?.currency ?? null;
});

const filteredTransactions = computed(() => {
    if (activeFilter.value === 'all') return props.recentTransactions;
    return props.recentTransactions.filter((t) => t.type === activeFilter.value);
});

function time(value) {
    if (!value) return '';
    return new Date(value).toLocaleTimeString(settings().locale ?? 'ar-SY', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="لوحة التحكم الرئيسية" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-600/30">
                    <NavIcon name="home" class="h-4.5 w-4.5" />
                </span>
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-black leading-tight text-slate-900 dark:text-white">
                        الرئيسية
                    </h2>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                        نظرة عامة على ورديتك وأرصدة الصندوق
                    </p>
                </div>
            </div>
        </template>

        <div class="relative p-4 sm:p-6 lg:p-8">
            <!-- خلفية زخرفية ناعمة تعزز تأثير الزجاج -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-indigo-100/70 via-indigo-50/30 to-transparent dark:from-indigo-950/40 dark:via-indigo-950/10"></div>

            <div class="relative mx-auto max-w-7xl space-y-6">
                <!-- ═══════════ بطاقة الترحيب (Hero) ═══════════ -->
                <section
                    class="animate-rise-in relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 p-6 shadow-xl shadow-indigo-900/20 ring-1 ring-inset ring-white/10 sm:p-8"
                >
                    <!-- هالات مضيئة زخرفية (Glass Glow) -->
                    <div class="pointer-events-none absolute -top-24 -end-16 h-64 w-64 rounded-full bg-white/20 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-24 -start-10 h-56 w-56 rounded-full bg-violet-400/30 blur-3xl"></div>
                    <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 22px 22px; color: white;"></div>

                    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-sm font-semibold text-indigo-100">
                                <NavIcon name="sparkles" class="h-4 w-4" />
                                <span>{{ greeting }}</span>
                            </div>

                            <h1 class="mt-1.5 text-2xl font-black tracking-tight text-white sm:text-3xl">
                                {{ user?.name }}
                            </h1>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span
                                    v-if="shift"
                                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-md"
                                >
                                    <span class="relative flex h-2 w-2">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-75"></span>
                                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                                    </span>
                                    <span>وردية مفتوحة</span>
                                    <span class="text-indigo-200">·</span>
                                    <span class="text-indigo-100">{{ date(shift.business_date) }}</span>
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-2 rounded-full border border-amber-300/30 bg-amber-400/15 px-3 py-1.5 text-xs font-bold text-amber-100 backdrop-blur-md"
                                >
                                    <NavIcon name="clock" class="h-3.5 w-3.5" />
                                    <span>لا توجد وردية مفتوحة حالياً</span>
                                </span>

                                <span
                                    v-if="shift"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold text-white/90 backdrop-blur-md"
                                >
                                    <NavIcon name="receipt" class="h-3.5 w-3.5" />
                                    <span class="tabular-nums">{{ stats ? stats.transactions_count : 0 }}</span>
                                    <span class="font-medium text-indigo-200">عملية</span>
                                </span>
                            </div>
                        </div>

                        <!-- أزرار الإجراء الرئيسية بنمط زجاجي -->
                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                :href="route('transactions.create')"
                                class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-indigo-700 shadow-lg shadow-indigo-950/20 transition hover:-translate-y-0.5 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white/70"
                            >
                                <NavIcon name="plus-circle" class="h-4.5 w-4.5" />
                                <span>عملية صرافة جديدة</span>
                            </Link>

                            <Link
                                v-if="shift"
                                :href="route('shifts.show', shift.id)"
                                class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur-md transition hover:-translate-y-0.5 hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                            >
                                <NavIcon name="clock" class="h-4 w-4" />
                                <span>تفاصيل الوردية</span>
                            </Link>
                            <Link
                                v-else
                                :href="route('shifts.create')"
                                class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur-md transition hover:-translate-y-0.5 hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                            >
                                <NavIcon name="plus-circle" class="h-4 w-4" />
                                <span>فتح وردية</span>
                            </Link>
                        </div>
                    </div>
                </section>

                <NewLiraNotice :currencies="balances" dismiss-key="dashboard" />

                <!-- ═══════════ بطاقة حالة عدم وجود وردية مفتوحة ═══════════ -->
                <div
                    v-if="!shift"
                    class="animate-rise-in relative overflow-hidden rounded-3xl border border-white/60 bg-white/70 p-8 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-slate-800/50"
                >
                    <div class="pointer-events-none absolute -end-16 -top-16 h-48 w-48 rounded-full bg-amber-400/20 blur-3xl"></div>

                    <div class="relative mx-auto max-w-2xl space-y-4 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-lg shadow-amber-500/30">
                            <NavIcon name="clock" class="h-8 w-8" />
                        </div>

                        <div>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white">
                                لا توجد وردية صرافة مفتوحة حالياً
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                لتسجيل عمليات البيع والشراء ومتابعة أرصدة الصندوق، يجب أولاً فتح ورديتك وتأكيد الأرصدة الافتتاحية في الدرج.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                            <Link
                                :href="route('shifts.create')"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-amber-500/25 transition hover:-translate-y-0.5 hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                                <NavIcon name="plus-circle" class="h-5 w-5" />
                                <span>فتح وردية جديدة الآن</span>
                            </Link>

                            <Link
                                :href="route('shifts.index')"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-5 py-3 text-sm font-bold text-slate-700 shadow-sm backdrop-blur transition hover:-translate-y-0.5 hover:bg-white dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                <NavIcon name="clock" class="h-4 w-4" />
                                <span>سجل الورديات السابقة</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- ═══════════ المحتوى الكامل في حال وجود وردية نشطة ═══════════ -->
                <template v-else>
                    <!-- ١. بطاقات المؤشرات (KPI) -->
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                        <!-- حجم التداول الإجمالي -->
                        <div class="animate-rise-in group relative overflow-hidden rounded-2xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/50">
                            <div class="pointer-events-none absolute -end-8 -top-8 h-24 w-24 rounded-full bg-indigo-500/10 blur-2xl transition-colors duration-300 group-hover:bg-indigo-500/20"></div>
                            <div class="relative flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">حجم التداول اليومي</span>
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-600/30">
                                    <NavIcon name="currency" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="relative mt-3 flex items-baseline gap-1">
                                <span class="text-2xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                                    {{ stats ? number(stats.total_volume) : '0' }}
                                </span>
                                <span class="text-xs font-bold text-slate-400 dark:text-slate-500">
                                    {{ baseCurrency?.code ?? 'ل.س' }}
                                </span>
                            </div>
                            <p class="relative mt-1 truncate text-[11px] text-slate-400 dark:text-slate-500">
                                إجمالي قيمة عمليات الصرافة في الوردية
                            </p>
                        </div>

                        <!-- عدد العمليات -->
                        <div class="animate-rise-in group relative overflow-hidden rounded-2xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/50" style="animation-delay: 60ms;">
                            <div class="pointer-events-none absolute -end-8 -top-8 h-24 w-24 rounded-full bg-blue-500/10 blur-2xl transition-colors duration-300 group-hover:bg-blue-500/20"></div>
                            <div class="relative flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">عدد العمليات</span>
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-lg shadow-blue-600/30">
                                    <NavIcon name="receipt" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="relative mt-3 flex items-baseline gap-1.5">
                                <span class="text-2xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                                    {{ stats ? stats.transactions_count : '0' }}
                                </span>
                                <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">عملية</span>
                            </div>
                            <div v-if="stats" class="relative mt-1.5 flex items-center gap-2 text-[10px] font-bold">
                                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-emerald-600 dark:text-emerald-400">
                                    <NavIcon name="arrow-down-left" class="h-3 w-3" />
                                    {{ stats.buy_count }} شراء
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-rose-500/10 px-1.5 py-0.5 text-rose-600 dark:text-rose-400">
                                    <NavIcon name="arrow-up-right" class="h-3 w-3" />
                                    {{ stats.sell_count }} بيع
                                </span>
                            </div>
                        </div>

                        <!-- أرباح الصرف أو العملات النشطة -->
                        <div class="animate-rise-in group relative overflow-hidden rounded-2xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/50" style="animation-delay: 120ms;">
                            <div class="pointer-events-none absolute -end-8 -top-8 h-24 w-24 rounded-full bg-emerald-500/10 blur-2xl transition-colors duration-300 group-hover:bg-emerald-500/20"></div>
                            <div class="relative flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                                    {{ stats?.total_profit !== null ? 'أرباح الصرف المقدرة' : 'العملات في الصندوق' }}
                                </span>
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-600/30">
                                    <NavIcon :name="stats?.total_profit !== null ? 'trending-up' : 'coins'" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="relative mt-3 flex items-baseline gap-1">
                                <span
                                    class="text-2xl font-black tabular-nums tracking-tight sm:text-3xl"
                                    :class="stats?.total_profit !== null
                                        ? (Number(stats.total_profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')
                                        : 'text-slate-900 dark:text-white'"
                                >
                                    {{ stats?.total_profit !== null ? number(stats.total_profit) : balances.length }}
                                </span>
                                <span class="text-xs font-bold text-slate-400 dark:text-slate-500">
                                    {{ stats?.total_profit !== null ? (baseCurrency?.code ?? 'ل.س') : 'عملة' }}
                                </span>
                            </div>
                            <p class="relative mt-1 truncate text-[11px] text-slate-400 dark:text-slate-500">
                                {{ stats?.total_profit !== null ? 'صافي فارق أسعار الشراء والبيع' : 'العملات المدرجة بالوردية' }}
                            </p>
                        </div>

                        <!-- حالة الوردية -->
                        <div class="animate-rise-in group relative overflow-hidden rounded-2xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/50" style="animation-delay: 180ms;">
                            <div class="pointer-events-none absolute -end-8 -top-8 h-24 w-24 rounded-full bg-amber-500/10 blur-2xl transition-colors duration-300 group-hover:bg-amber-500/20"></div>
                            <div class="relative flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">حالة الوردية</span>
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg shadow-amber-600/30">
                                    <NavIcon name="clock" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="relative mt-3 flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5 shrink-0">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                </span>
                                <span class="text-base font-black text-emerald-600 dark:text-emerald-400">
                                    نشطة ومفتوحة
                                </span>
                            </div>
                            <div class="relative mt-1.5 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="tabular-nums">#{{ shift.id }} · {{ date(shift.business_date) }}</span>
                                <Link :href="route('shifts.show', shift.id)" class="inline-flex items-center gap-1 font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                                    <span>إدارة</span>
                                    <NavIcon name="chevron-left" class="h-3 w-3" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- ٢. شبكة أرصدة الصندوق والدرج -->
                    <div class="animate-rise-in" style="animation-delay: 120ms;">
                        <div class="mb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-600/30">
                                    <NavIcon name="wallet" class="h-4 w-4" />
                                </span>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    أرصدة الصندوق والدرج النقدي
                                </h3>
                                <span class="rounded-full border border-white/60 bg-white/70 px-2 py-0.5 text-xs font-bold text-slate-600 backdrop-blur dark:border-white/10 dark:bg-slate-800/60 dark:text-slate-300">
                                    {{ balances.length }} عملات
                                </span>
                            </div>

                            <Link
                                :href="route('shifts.show', shift.id)"
                                class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                <span>كشف الحركات النقدية الكامل</span>
                                <NavIcon name="chevron-left" class="h-3.5 w-3.5" />
                            </Link>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <CurrencyBalanceCard
                                v-for="balance in sortedBalances"
                                :key="balance.currency.id"
                                :code="balance.currency.code"
                                :name="balance.currency.name"
                                :symbol="balance.currency.symbol"
                                :balance="balance.current_balance"
                                :opening-balance="balance.opening_balance"
                                :is-base="Boolean(balance.currency.is_base)"
                            />
                        </div>
                    </div>

                    <!-- ٣. جدول آخر العمليات الحديثة -->
                    <div class="animate-rise-in overflow-hidden rounded-2xl border border-white/60 bg-white/70 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-slate-800/50" style="animation-delay: 180ms;">
                        <div class="flex flex-col gap-3 border-b border-slate-200/60 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-white/10">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-600/25">
                                    <NavIcon name="receipt" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-base font-black text-slate-900 dark:text-white">
                                        آخر عمليات الوردية
                                    </h3>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">
                                        أحدث {{ recentTransactions.length }} عمليات صرافة مسجلة في هذه الوردية
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- فلاتر سريعة -->
                                <div class="inline-flex rounded-xl border border-white/60 bg-white/60 p-1 backdrop-blur dark:border-white/10 dark:bg-slate-900/60">
                                    <button
                                        type="button"
                                        @click="activeFilter = 'all'"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                        :class="activeFilter === 'all'
                                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                                    >
                                        <NavIcon name="layout-grid" class="h-3 w-3" />
                                        <span>الكل</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="activeFilter = 'buy'"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                        :class="activeFilter === 'buy'
                                            ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-700 dark:text-emerald-400'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                                    >
                                        <NavIcon name="arrow-down-left" class="h-3 w-3" />
                                        <span>شراء</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="activeFilter = 'sell'"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                        :class="activeFilter === 'sell'
                                            ? 'bg-white text-rose-700 shadow-sm dark:bg-slate-700 dark:text-rose-400'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                                    >
                                        <NavIcon name="arrow-up-right" class="h-3 w-3" />
                                        <span>بيع</span>
                                    </button>
                                </div>

                                <Link
                                    :href="route('transactions.index')"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200/80 bg-white/60 px-3 py-1.5 text-xs font-bold text-indigo-600 backdrop-blur transition hover:bg-white dark:border-white/10 dark:bg-slate-900/50 dark:text-indigo-400 dark:hover:bg-slate-900"
                                >
                                    <span>سجل العمليات الكامل</span>
                                    <NavIcon name="chevron-left" class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>

                        <div v-if="filteredTransactions.length === 0" class="p-12 text-center text-slate-400 dark:text-slate-500">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                <NavIcon name="receipt" class="h-6 w-6" />
                            </div>
                            <p class="mt-3 font-bold">لا توجد عمليات تطابق هذا الفلتر في هذه الوردية بعد.</p>
                            <Link
                                :href="route('transactions.create')"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700"
                            >
                                <NavIcon name="plus-circle" class="h-3.5 w-3.5" />
                                <span>تسجيل أول عملية الآن</span>
                            </Link>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200/60 text-sm dark:divide-white/10">
                                <thead class="bg-slate-50/70 text-xs font-bold text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                                    <tr>
                                        <th class="px-5 py-3 text-start">
                                            <span class="inline-flex items-center gap-1">
                                                <NavIcon name="clock" class="h-3.5 w-3.5 text-slate-400" />
                                                <span>الوقت</span>
                                            </span>
                                        </th>
                                        <th class="px-5 py-3 text-start">
                                            <span class="inline-flex items-center gap-1">
                                                <NavIcon name="zap" class="h-3.5 w-3.5 text-slate-400" />
                                                <span>النوع</span>
                                            </span>
                                        </th>
                                        <th class="px-5 py-3 text-start">
                                            <span class="inline-flex items-center gap-1">
                                                <NavIcon name="coins" class="h-3.5 w-3.5 text-slate-400" />
                                                <span>العملة</span>
                                            </span>
                                        </th>
                                        <th class="px-5 py-3 text-start">المبلغ</th>
                                        <th class="px-5 py-3 text-start">السعر</th>
                                        <th class="px-5 py-3 text-start">الإجمالي بالليرة</th>
                                        <th class="px-5 py-3 text-start">
                                            <span class="inline-flex items-center gap-1">
                                                <NavIcon name="user" class="h-3.5 w-3.5 text-slate-400" />
                                                <span>العميل</span>
                                            </span>
                                        </th>
                                        <th class="px-5 py-3 text-center">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/60 dark:divide-white/5">
                                    <tr
                                        v-for="t in filteredTransactions"
                                        :key="t.id"
                                        class="transition hover:bg-white/60 dark:hover:bg-white/5"
                                    >
                                        <td class="px-5 py-3.5 font-medium tabular-nums text-slate-500 dark:text-slate-400">
                                            {{ time(t.created_at) }}
                                        </td>

                                        <td class="px-5 py-3.5">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-black shadow-2xs"
                                                :class="t.type === 'buy'
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                    : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'"
                                            >
                                                <NavIcon
                                                    :name="t.type === 'buy' ? 'arrow-down-left' : 'arrow-up-right'"
                                                    class="h-3.5 w-3.5"
                                                />
                                                <span>{{ t.type === 'buy' ? 'شراء' : 'بيع' }}</span>
                                            </span>
                                        </td>

                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-black text-slate-800 dark:text-slate-200">
                                                    {{ t.currency?.code }}
                                                </span>
                                                <span
                                                    v-if="t.currency_tag"
                                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                                >
                                                    {{ t.currency_tag }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-5 py-3.5 font-bold tabular-nums text-slate-800 dark:text-white">
                                            {{ number(t.amount) }}
                                        </td>

                                        <td class="px-5 py-3.5 font-medium tabular-nums text-slate-500 dark:text-slate-400">
                                            {{ formatRate(t.rate) }}
                                        </td>

                                        <td class="px-5 py-3.5 font-black tabular-nums text-slate-900 dark:text-white">
                                            {{ number(t.total) }}
                                        </td>

                                        <td class="px-5 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                                            {{ t.customer_name || '—' }}
                                        </td>

                                        <td class="px-5 py-3.5 text-center">
                                            <a
                                                :href="route('transactions.receipt', t.id)"
                                                target="_blank"
                                                class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 transition hover:bg-indigo-50 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-indigo-400"
                                                title="طباعة إيصال العملية"
                                            >
                                                <NavIcon name="printer" class="h-4 w-4" />
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
