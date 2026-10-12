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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                            <NavIcon name="home" class="h-4.5 w-4.5" />
                        </span>
                        <h2 class="text-xl font-black leading-tight text-slate-900 dark:text-white">
                            الرئيسية
                        </h2>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                        <span>مرحباً بك،</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300">{{ user?.name }}</span>
                        <template v-if="shift">
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                وردية يوم {{ date(shift.business_date) }}
                            </span>
                        </template>
                    </p>
                </div>

                <div v-if="shift" class="flex items-center gap-2 flex-wrap">
                    <Link
                        :href="route('transactions.create')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-indigo-600/20 transition hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <NavIcon name="plus-circle" class="h-4 w-4" />
                        <span>عملية صرافة جديدة</span>
                    </Link>

                    <Link
                        :href="route('shifts.show', shift.id)"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <NavIcon name="clock" class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                        <span>تفاصيل الوردية</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="mx-auto max-w-7xl space-y-6">
                <NewLiraNotice :currencies="balances" dismiss-key="dashboard" />

                <!-- بطاقة حالة عدم وجود وردية مفتوحة -->
                <div
                    v-if="!shift"
                    class="relative overflow-hidden rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50/90 via-white to-amber-50/40 p-8 shadow-sm dark:border-amber-800/40 dark:from-slate-900 dark:via-slate-900 dark:to-amber-950/20"
                >
                    <div class="mx-auto max-w-2xl text-center space-y-4">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 shadow-inner dark:bg-amber-900/40 dark:text-amber-400">
                            <NavIcon name="clock" class="h-8 w-8" />
                        </div>

                        <div>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white">
                                لا توجد وردية صرافة مفتوحة حالياً
                            </h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                                لتسجيل عمليات البيع والشراء ومتابعة أرصدة الصندوق، يجب أولاً فتح ورديتك وتأكيد الأرصدة الافتتاحية في الدرج.
                            </p>
                        </div>

                        <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                            <Link
                                :href="route('shifts.create')"
                                class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-amber-600/20 transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                                <NavIcon name="plus-circle" class="h-5 w-5" />
                                <span>فتح وردية جديدة الآن</span>
                            </Link>

                            <Link
                                :href="route('shifts.index')"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                <NavIcon name="clock" class="h-4 w-4" />
                                <span>سجل الورديات السابقة</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- المحتوى الكامل في حال وجود وردية نشطة -->
                <template v-else>
                    <!-- ١. شبكة أيقونات الوصول السريع (Quick Navigation Icon Cards) -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900/80">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    <NavIcon name="zap" class="h-3.5 w-3.5" />
                                </span>
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">
                                    الوصول السريع بالأيقونات
                                </h3>
                            </div>
                            <span class="text-[11px] text-slate-400">
                                اختصارات سريعة للعمليات اليومية
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
                            <!-- عملية جديدة -->
                            <Link
                                :href="route('transactions.create')"
                                class="group flex flex-col items-center justify-center rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 text-center transition hover:border-indigo-300 hover:bg-indigo-100/60 hover:shadow-sm dark:border-indigo-900/30 dark:bg-indigo-950/30 dark:hover:border-indigo-800"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-sm shadow-indigo-600/30 transition-transform group-hover:scale-110">
                                    <NavIcon name="plus-circle" class="h-5 w-5" />
                                </span>
                                <span class="mt-2 text-xs font-black text-slate-900 dark:text-white">عملية جديدة</span>
                                <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold">صرف نقدي</span>
                            </Link>

                            <!-- سجل العمليات -->
                            <Link
                                :href="route('transactions.index')"
                                class="group flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-white p-3 text-center transition hover:border-blue-300 hover:bg-blue-50/40 hover:shadow-sm dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-blue-900"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-sm shadow-blue-600/30 transition-transform group-hover:scale-110">
                                    <NavIcon name="receipt" class="h-5 w-5" />
                                </span>
                                <span class="mt-2 text-xs font-bold text-slate-800 dark:text-slate-200">سجل العمليات</span>
                                <span class="text-[10px] text-slate-400">بحث وسوابق</span>
                            </Link>

                            <!-- إدارة الوردية -->
                            <Link
                                :href="route('shifts.show', shift.id)"
                                class="group flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-white p-3 text-center transition hover:border-amber-300 hover:bg-amber-50/40 hover:shadow-sm dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-amber-900"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 text-white shadow-sm shadow-amber-600/30 transition-transform group-hover:scale-110">
                                    <NavIcon name="clock" class="h-5 w-5" />
                                </span>
                                <span class="mt-2 text-xs font-bold text-slate-800 dark:text-slate-200">إدارة الوردية</span>
                                <span class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">حركات الدرج</span>
                            </Link>

                            <!-- التقارير -->
                            <Link
                                :href="route('reports.index')"
                                class="group flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-white p-3 text-center transition hover:border-purple-300 hover:bg-purple-50/40 hover:shadow-sm dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-purple-900"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-purple-700 text-white shadow-sm shadow-purple-600/30 transition-transform group-hover:scale-110">
                                    <NavIcon name="chart-bar" class="h-5 w-5" />
                                </span>
                                <span class="mt-2 text-xs font-bold text-slate-800 dark:text-slate-200">التقارير</span>
                                <span class="text-[10px] text-slate-400">أرباح وكشوفات</span>
                            </Link>

                            <!-- أسعار الصرف (للمدير) أو الورديات السابقة -->
                            <Link
                                :href="isAdmin ? route('rates.index') : route('shifts.index')"
                                class="group flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-white p-3 text-center transition hover:border-teal-300 hover:bg-teal-50/40 hover:shadow-sm dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-teal-900"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-sm shadow-teal-600/30 transition-transform group-hover:scale-110">
                                    <NavIcon :name="isAdmin ? 'currency' : 'clock'" class="h-5 w-5" />
                                </span>
                                <span class="mt-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ isAdmin ? 'أسعار الصرف' : 'الورديات' }}
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    {{ isAdmin ? 'تحديث النشرات' : 'السجل العام' }}
                                </span>
                            </Link>

                            <!-- العملات (للمدير) أو الملف الشخصي -->
                            <Link
                                :href="isAdmin ? route('currencies.index') : route('profile.edit')"
                                class="group flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-white p-3 text-center transition hover:border-orange-300 hover:bg-orange-50/40 hover:shadow-sm dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-orange-900"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-orange-700 text-white shadow-sm shadow-orange-600/30 transition-transform group-hover:scale-110">
                                    <NavIcon :name="isAdmin ? 'coins' : 'user'" class="h-5 w-5" />
                                </span>
                                <span class="mt-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ isAdmin ? 'العملات' : 'الملف الشخصي' }}
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    {{ isAdmin ? 'إدارة الفئات' : 'إعداد الحساب' }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- ٢. بطاقات المؤشرات السريعة (Shift KPI Stats) مع أيقونات محسنة -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- حجم التداول الإجمالي -->
                        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4.5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-bold">حجم التداول اليومي</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-sm shadow-indigo-600/25">
                                    <NavIcon name="currency" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="mt-3 flex items-baseline gap-1">
                                <span class="text-xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                                    {{ stats ? number(stats.total_volume) : '0' }}
                                </span>
                                <span class="text-xs font-bold text-slate-400 dark:text-slate-500">
                                    {{ baseCurrency?.code ?? 'ل.س' }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 truncate">
                                إجمالي قيمة عمليات الصرافة في الوردية
                            </p>
                        </div>

                        <!-- عدد العمليات والتقسيم -->
                        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4.5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-bold">عدد العمليات</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-sm shadow-blue-600/25">
                                    <NavIcon name="receipt" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="mt-3 flex items-baseline gap-1.5">
                                <span class="text-xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                                    {{ stats ? stats.transactions_count : '0' }}
                                </span>
                                <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">عملية</span>
                            </div>
                            <div v-if="stats" class="mt-1.5 flex items-center gap-2 text-[10px] font-bold">
                                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-1.5 py-0.5 rounded-md">
                                    <NavIcon name="arrow-down-left" class="h-3 w-3" />
                                    {{ stats.buy_count }} شراء
                                </span>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/50 px-1.5 py-0.5 rounded-md">
                                    <NavIcon name="arrow-up-right" class="h-3 w-3" />
                                    {{ stats.sell_count }} بيع
                                </span>
                            </div>
                        </div>

                        <!-- أرباح الصرف أو العملات النشطة -->
                        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4.5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-bold">
                                    {{ stats?.total_profit !== null ? 'أرباح الصرف المقدرة' : 'العملات في الصندوق' }}
                                </span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-sm shadow-emerald-600/25">
                                    <NavIcon :name="stats?.total_profit !== null ? 'trending-up' : 'coins'" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="mt-3 flex items-baseline gap-1">
                                <span
                                    class="text-xl font-black tabular-nums tracking-tight sm:text-2xl"
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
                            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 truncate">
                                {{ stats?.total_profit !== null ? 'صافي فارق أسعار الشراء والبيع' : 'العملات المدرجة بالوردية' }}
                            </p>
                        </div>

                        <!-- حالة الوردية -->
                        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4.5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-bold">حالة الوردية</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 text-white shadow-sm shadow-amber-600/25">
                                    <NavIcon name="clock" class="h-4.5 w-4.5" />
                                </span>
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5 shrink-0">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                </span>
                                <span class="text-base font-black text-emerald-600 dark:text-emerald-400">
                                    نشطة ومفتوحة
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                <span>#{{ shift.id }} · {{ date(shift.business_date) }}</span>
                                <Link :href="route('shifts.show', shift.id)" class="inline-flex items-center gap-1 font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                                    <span>إدارة</span>
                                    <NavIcon name="chevron-left" class="h-3 w-3" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- ٣. شبكة أرصدة الصندوق والدرج -->
                    <div>
                        <div class="mb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    <NavIcon name="wallet" class="h-4 w-4" />
                                </span>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    أرصدة الصندوق والدرج النقدي
                                </h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
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

                    <!-- ٤. جدول آخر العمليات الحديثة -->
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400">
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

                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- فلاتر سريعة مع أيقونات -->
                                <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-900">
                                    <button
                                        type="button"
                                        @click="activeFilter = 'all'"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                        :class="activeFilter === 'all'
                                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
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
                                            ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-800 dark:text-emerald-400'
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
                                            ? 'bg-white text-rose-700 shadow-sm dark:bg-slate-800 dark:text-rose-400'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                                    >
                                        <NavIcon name="arrow-up-right" class="h-3 w-3" />
                                        <span>بيع</span>
                                    </button>
                                </div>

                                <Link
                                    :href="route('transactions.index')"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-indigo-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-indigo-400 dark:hover:bg-slate-700"
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
                                class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition"
                            >
                                <NavIcon name="plus-circle" class="h-3.5 w-3.5" />
                                <span>تسجيل أول عملية الآن</span>
                            </Link>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-700">
                                <thead class="bg-slate-50/80 text-xs font-bold text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
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
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    <tr
                                        v-for="t in filteredTransactions"
                                        :key="t.id"
                                        class="transition hover:bg-slate-50/70 dark:hover:bg-slate-700/30"
                                    >
                                        <td class="px-5 py-3.5 font-medium text-slate-500 dark:text-slate-400 tabular-nums">
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
                                                class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-indigo-400 transition"
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
