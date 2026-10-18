<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
import ThemePicker from '@/Components/ThemePicker.vue';

const props = defineProps({
    plans: {
        type: Array,
        default: () => [],
    },
    market: {
        type: Object,
        default: () => ({ available: false, rates: [], gold: null }),
    },
    centralDomain: {
        type: String,
        default: 'exchange.damatech.sy',
    },
    tenantCount: {
        type: Number,
        default: 5,
    },
    auth: {
        type: Object,
        default: () => ({ user: null, isAuthenticated: false }),
    },
});

// Billing interval toggle
const isAnnual = ref(false);
const monthlyPrice = (plan) => (isAnnual.value ? plan.price * 0.8 : Number(plan.price));

// The exchange's own published rates (as on /rates), with sp-today as reference — new lira.
const rates = computed(() => props.market?.rates ?? []);
const featuredRates = computed(() => rates.value.filter((r) => r.featured));
const goldKarats = computed(() =>
    (props.market?.gold?.karats ?? []).filter((k) => ['24K', '21K', '18K'].includes(k.karat)),
);

const showAllRates = ref(false);
const hasMoreRates = computed(() => rates.value.length > featuredRates.value.length);
const rateSearch = ref('');
const filteredRates = computed(() => {
    const q = rateSearch.value.trim().toLowerCase();
    if (!q) return rates.value;
    return rates.value.filter((r) => r.code.toLowerCase().includes(q) || r.name.includes(q));
});

// The scheduler refreshes every 10 minutes; anything older means sp-today was unreachable.
const isStale = computed(() => {
    const fetched = props.market?.fetched_at ? new Date(props.market.fetched_at) : null;
    return !fetched || Date.now() - fetched.getTime() > 3 * 60 * 60 * 1000;
});

const formatDateTime = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString('ar-SY-u-nu-latn', {
        weekday: 'long',
        hour: '2-digit',
        minute: '2-digit',
        day: 'numeric',
        month: 'long',
        timeZone: 'Asia/Damascus',
    });
};

const changeLabel = (val) => {
    if (val === null || val === undefined) return '—';
    if (val === 0) return '0.00%';
    return `${val > 0 ? '+' : ''}${val.toFixed(2)}%`;
};

const changeTextClass = (val) => {
    if (!val) return 'text-slate-400';
    return val > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
};

// Hero calculator
const heroCurrency = ref('USD');
const heroType = ref('buy'); // 'buy' (نشتري من العميل) | 'sell' (نبيع للعميل)
const heroAmount = ref(100);

const heroRatesList = computed(() => {
    if (rates.value && rates.value.length > 0) return rates.value;
    return [
        { code: 'USD', name: 'دولار أمريكي', flag: '🇺🇸', buy: 13000, sell: 13150, change: 0.15 },
        { code: 'EUR', name: 'يورو أوروبي', flag: '🇪🇺', buy: 14200, sell: 14380, change: -0.22 },
        { code: 'SAR', name: 'ريال سعودي', flag: '🇸🇦', buy: 3460, sell: 3510, change: 0.05 },
        { code: 'AED', name: 'درهم إماراتي', flag: '🇦🇪', buy: 3540, sell: 3585, change: 0.08 },
        { code: 'TRY', name: 'ليرة تركية', flag: '🇹🇷', buy: 380, sell: 395, change: -0.45 },
    ];
});

const heroCurrencies = computed(() => {
    const list = heroRatesList.value;
    const codes = ['USD', 'EUR', 'SAR', 'AED', 'TRY'];
    const matched = codes.map((c) => list.find((r) => r.code === c)).filter(Boolean);
    return matched.length ? matched : list.slice(0, 5);
});

const heroSelectedRate = computed(() => {
    return heroRatesList.value.find((r) => r.code === heroCurrency.value) || heroRatesList.value[0];
});

const heroRateValue = computed(() => {
    if (!heroSelectedRate.value) return 0;
    return heroType.value === 'buy' ? heroSelectedRate.value.buy : heroSelectedRate.value.sell;
});

const heroTotal = computed(() => {
    const amt = Math.max(0, parseFloat(heroAmount.value) || 0);
    return Math.round(amt * heroRateValue.value * 100) / 100;
});

// Formatting helper
const formatNumber = (val, maxDecimals = 2) => {
    if (val === null || val === undefined) return '0';
    return Number(val).toLocaleString('en-US', { maximumFractionDigits: maxDecimals });
};

// FAQ Accordion State
const openFaq = ref(null);
const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

const faqs = [
    {
        q: 'هل بيانات شركتي معزولة عن الشركات الأخرى؟',
        a: 'نعم. لكل شركة قاعدة بيانات مستقلة تُنشأ عند التسجيل، ولا تُشارك مع أي جهة أخرى.',
    },
    {
        q: 'كيف تُحدَّث الأسعار؟',
        a: 'يجلب النظام أسعار الليرة اليوم دورياً، ويطبّق هامش الربح الذي تحدده للشراء والبيع.',
    },
    {
        q: 'ما الطابعات المدعومة؟',
        a: 'الطابعات الحرارية 80 و58 مم، وطابعات A4 وA5، مع شعار شركتك وتفقيط المبلغ.',
    },
    {
        q: 'هل أحتاج لتنصيب برامج؟',
        a: 'لا. النظام يعمل من المتصفح على أي جهاز.',
    },
    {
        q: 'كم يستغرق البدء؟',
        a: 'حوالي دقيقة: تعبّئ نموذج التسجيل ويُجهَّز حسابك ونطاقك فوراً.',
    },
];

const features = [
    { icon: 'clock', title: 'الورديات والصناديق', text: 'فتح وإغلاق الورديات وجرد الصندوق بكل العملات.' },
    { icon: 'coins', title: 'بيع وشراء سريع', text: 'تسجيل العملية في ثوانٍ مع التحقق من الرصيد.' },
    { icon: 'refresh', title: 'أسعار تلقائية', text: 'ربط مع الليرة اليوم وهوامش ربح تحددها أنت.' },
    { icon: 'trending-up', title: 'تقارير الأرباح', text: 'أرباح كل وردية وصراف وعملة، مع تصدير.' },
    { icon: 'printer', title: 'طباعة الإيصالات', text: 'إيصالات حرارية وفواتير A4 بشعار شركتك.' },
    { icon: 'shield-check', title: 'عزل وصلاحيات', text: 'قاعدة مستقلة لكل شركة وصلاحيات لكل مستخدم.' },
];

const whatsappHref = `https://wa.me/963968061662?text=${encodeURIComponent('مرحباً، أود الاستفسار عن نظام DamaTech Exchange لإدارة شركات الصرافة.')}`;

const steps = [
    'سجّل شركتك واختر نطاقك',
    'أضف العملات وأرصدة الافتتاح',
    'ابدأ العمل واطبع الإيصالات',
];
</script>

<template>
    <Head>
        <title>DamaTech Exchange — نظام إدارة شركات الصرافة</title>
        <meta name="description" content="نظام سحابي لإدارة شركات الصرافة: الورديات والصناديق، أسعار الليرة اليوم، الأرباح، وطباعة الإيصالات، بقاعدة بيانات مستقلة لكل شركة." />
    </Head>

    <div class="min-h-screen bg-white text-slate-800 transition-colors duration-200 dark:bg-[#0b1029] dark:text-slate-200 font-sans">
        <!-- ================= NAVBAR ================= -->
        <header class="sticky top-0 z-50 backdrop-blur-md bg-white/85 dark:bg-[#0b1029]/85 border-b border-slate-100 dark:border-slate-800/60">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <Link href="/" class="flex items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60">
                        <img src="/logo-damatech.webp" alt="DamaTech" class="h-9 w-9 rounded-xl" />
                        <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">DamaTech <span class="font-medium text-slate-400">Exchange</span></span>
                    </Link>

                    <nav class="hidden md:flex items-center gap-8 text-sm text-slate-500 dark:text-slate-400">
                        <a href="#features" class="hover:text-slate-900 dark:hover:text-white transition">المميزات</a>
                        <a href="#rates" class="hover:text-slate-900 dark:hover:text-white transition">الأسعار</a>
                        <a href="#pricing" class="hover:text-slate-900 dark:hover:text-white transition">الباقات</a>
                        <a href="#faq" class="hover:text-slate-900 dark:hover:text-white transition">الأسئلة</a>
                    </nav>

                    <div class="flex items-center gap-2">
                        <ThemePicker
                            button-class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        />

                        <Link
                            v-if="auth.isAuthenticated"
                            href="/dashboard"
                            class="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white transition"
                        >
                            لوحة التحكم
                        </Link>
                        <template v-else>
                            <Link href="/login" class="hidden sm:inline-flex px-3 py-2 text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">
                                دخول
                            </Link>
                            <Link
                                href="/register-company"
                                class="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white transition"
                            >
                                ابدأ الآن
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <!-- ================= HERO ================= -->
        <section class="pt-16 pb-20 sm:pt-24 sm:pb-28">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    <div class="lg:col-span-7">
                        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-slate-900 dark:text-white leading-[1.3]">
                            إدارة صرافتك،
                            <span class="block text-indigo-600 dark:text-indigo-400">ببساطة ووضوح.</span>
                        </h1>

                        <p class="mt-6 text-lg text-slate-500 dark:text-slate-400 leading-relaxed max-w-xl">
                            سجّل العمليات، تابع الصناديق والأرباح، واطبع الإيصالات — بأسعار تُحدَّث تلقائياً.
                        </p>

                        <div class="mt-10 flex flex-col sm:flex-row gap-3">
                            <Link
                                href="/register-company"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-6 py-3.5 text-sm font-semibold text-white transition"
                            >
                                <span>سجّل شركتك</span>
                                <NavIcon name="chevron-left" class="h-4 w-4" />
                            </Link>
                            <Link
                                href="/login"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 px-6 py-3.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                            >
                                تسجيل الدخول
                            </Link>
                        </div>

                        <p class="mt-5 text-sm text-slate-400">تفعيل فوري · بدون بطاقة بنكية</p>
                    </div>

                    <!-- Calculator -->
                    <div class="lg:col-span-5">
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-6 space-y-5">
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">حاسبة التحويل</h2>
                                <span class="text-xs text-slate-400">بأسعار اليوم</span>
                            </div>
                            <div class="grid grid-cols-2 p-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm" role="group" aria-label="نوع العملية">
                                <button
                                    type="button"
                                    @click="heroType = 'buy'"
                                    :aria-pressed="heroType === 'buy'"
                                    class="py-2 rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60"
                                    :class="heroType === 'buy' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
                                >
                                    أبيع عملة
                                </button>
                                <button
                                    type="button"
                                    @click="heroType = 'sell'"
                                    :aria-pressed="heroType === 'sell'"
                                    class="py-2 rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60"
                                    :class="heroType === 'sell' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
                                >
                                    أشتري عملة
                                </button>
                            </div>

                            <div class="grid grid-cols-5 gap-1.5" role="group" aria-label="العملة">
                                <button
                                    v-for="c in heroCurrencies"
                                    :key="c.code"
                                    type="button"
                                    @click="heroCurrency = c.code"
                                    :aria-pressed="heroCurrency === c.code"
                                    :title="c.name"
                                    class="py-2 rounded-lg border text-xs font-semibold font-mono transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60"
                                    :class="heroCurrency === c.code ? 'border-indigo-500 text-indigo-600 dark:text-indigo-300 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800'"
                                >
                                    {{ c.code }}
                                </button>
                            </div>

                            <div>
                                <label for="hero-amount" class="block mb-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ heroType === 'buy' ? 'المبلغ الذي تبيعه' : 'المبلغ الذي تريد شراءه' }}
                                </label>
                                <div class="relative">
                                <input
                                    id="hero-amount"
                                    type="number"
                                    dir="ltr"
                                    min="0"
                                    step="any"
                                    inputmode="decimal"
                                    v-model="heroAmount"
                                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3 pr-16 text-lg font-mono text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 focus:outline-none"
                                    placeholder="100"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-slate-400">{{ heroSelectedRate?.code }}</span>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
                                <div class="text-sm text-slate-400">{{ heroType === 'buy' ? 'ستحصل على' : 'ستدفع' }}</div>
                                <div class="mt-1 flex items-baseline gap-2" aria-live="polite">
                                    <span class="text-3xl font-semibold font-mono tabular-nums text-slate-900 dark:text-white">{{ formatNumber(heroTotal) }}</span>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">ليرة جديدة</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-400 tabular-nums">
                                    1 {{ heroSelectedRate?.code }} = {{ formatNumber(heroRateValue) }} ل.س
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= LIVE RATES ================= -->
        <section id="rates" class="py-20 bg-slate-50/70 dark:bg-[#0d1333]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-8">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">أسعار الصرف اليوم</h2>
                        <p class="mt-2 text-sm text-slate-400">بالليرة الجديدة · دمشق</p>
                    </div>
                    <div v-if="market.available" class="text-sm text-slate-400 sm:text-end">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full" :class="isStale ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                            {{ formatDateTime(market.updated_at) }}
                        </span>
                        <a href="https://sp-today.com" target="_blank" rel="noopener" class="block mt-1 text-xs hover:text-slate-600 dark:hover:text-slate-300">المصدر: الليرة اليوم</a>
                    </div>
                </div>

                <p
                    v-if="market.available && isStale"
                    class="mb-6 rounded-lg bg-amber-50 dark:bg-amber-950/30 px-4 py-2.5 text-sm text-amber-700 dark:text-amber-300"
                >
                    قد لا تكون هذه الأسعار محدّثة.
                </p>

                <div v-if="!market.available" class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 p-10 text-center text-sm text-slate-400">
                    الأسعار غير متاحة حالياً.
                </div>

                <template v-else>
                    <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div
                            v-for="rate in featuredRates"
                            :key="rate.code"
                            class="p-5 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl leading-none">{{ rate.flag }}</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ rate.code }}</span>
                                </div>
                                <span v-if="rate.change" class="text-xs tabular-nums" :class="changeTextClass(rate.change)" dir="ltr">{{ changeLabel(rate.change) }}</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div>
                                    <div class="text-xs text-slate-400">شراء</div>
                                    <div class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatNumber(rate.buy) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-400">مبيع</div>
                                    <div class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatNumber(rate.sell) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="goldKarats.length" class="mt-4 flex flex-wrap items-center gap-x-8 gap-y-2 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 px-5 py-4 text-sm">
                        <span class="text-slate-400">الذهب (غرام)</span>
                        <span v-for="k in goldKarats" :key="k.karat" class="flex items-baseline gap-2">
                            <span class="text-slate-400">عيار {{ k.karat.replace('K', '') }}</span>
                            <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatNumber(k.sell_new, 0) }}</span>
                        </span>
                    </div>

                    <div v-if="hasMoreRates" class="mt-6">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md text-sm text-slate-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition"
                            :aria-expanded="showAllRates"
                            @click="showAllRates = !showAllRates"
                        >
                            <span>{{ showAllRates ? 'إخفاء' : 'عرض' }} جميع العملات ({{ rates.length }})</span>
                            <NavIcon name="chevron-down" class="h-4 w-4 transition-transform" :class="showAllRates ? 'rotate-180' : ''" />
                        </button>

                        <div v-if="showAllRates" class="mt-4 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 overflow-hidden">
                            <div class="p-3">
                                <input
                                    v-model="rateSearch"
                                    type="search"
                                    aria-label="ابحث عن عملة"
                                    placeholder="ابحث عن عملة"
                                    class="w-full sm:max-w-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-800 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none"
                                />
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-slate-400">
                                        <tr>
                                            <th class="px-4 py-2 text-start font-medium">العملة</th>
                                            <th class="px-3 py-2 text-start font-medium">شراء</th>
                                            <th class="px-3 py-2 text-start font-medium">مبيع</th>
                                            <th class="px-3 py-2 text-start font-medium">التغير</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="rate in filteredRates" :key="rate.code">
                                            <td class="px-4 py-2.5">
                                                <span class="me-2">{{ rate.flag }}</span>
                                                <span class="font-medium text-slate-900 dark:text-white">{{ rate.code }}</span>
                                                <span class="hidden sm:inline ms-2 text-slate-400">{{ rate.name }}</span>
                                            </td>
                                            <td class="px-3 py-2.5 tabular-nums">{{ formatNumber(rate.buy) }}</td>
                                            <td class="px-3 py-2.5 tabular-nums">{{ formatNumber(rate.sell) }}</td>
                                            <td class="px-3 py-2.5 text-xs tabular-nums" :class="changeTextClass(rate.change)" dir="ltr">{{ changeLabel(rate.change) }}</td>
                                        </tr>
                                        <tr v-if="!filteredRates.length">
                                            <td colspan="4" class="px-4 py-6 text-center text-slate-400">لا توجد نتائج</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- ================= FEATURES ================= -->
        <section id="features" class="py-24">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white text-center">كل ما تحتاجه في مكان واحد</h2>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-12">
                    <div v-for="f in features" :key="f.title">
                        <NavIcon :name="f.icon" class="h-6 w-6 text-indigo-500 dark:text-indigo-400" />
                        <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ f.title }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ f.text }}</p>
                    </div>
                </div>

                <!-- Steps -->
                <div class="mt-24 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div v-for="(step, i) in steps" :key="i" class="flex items-center gap-4">
                        <span class="h-9 w-9 shrink-0 rounded-full border border-slate-200 dark:border-slate-700 text-sm text-slate-500 dark:text-slate-400 flex items-center justify-center tabular-nums">{{ i + 1 }}</span>
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ step }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= PRICING ================= -->
        <section id="pricing" class="py-24 bg-slate-50/70 dark:bg-[#0d1333]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">الباقات</h2>

                    <div class="mt-6 inline-flex p-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm" role="group" aria-label="مدة الاشتراك">
                        <button
                            type="button"
                            @click="isAnnual = false"
                            :aria-pressed="!isAnnual"
                            :class="!isAnnual ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
                            class="px-4 py-1.5 rounded-md transition"
                        >
                            شهري
                        </button>
                        <button
                            type="button"
                            @click="isAnnual = true"
                            :aria-pressed="isAnnual"
                            :class="isAnnual ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
                            class="px-4 py-1.5 rounded-md transition"
                        >
                            سنوي <span class="text-emerald-600 dark:text-emerald-400">−20%</span>
                        </button>
                    </div>
                </div>

                <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        :class="plan.slug === 'pro' ? 'border-indigo-500 dark:border-indigo-500' : 'border-slate-100 dark:border-slate-800'"
                        class="p-7 rounded-2xl border bg-white dark:bg-slate-900/60 flex flex-col"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ plan.name }}</h3>
                            <span v-if="plan.slug === 'pro'" class="rounded-full bg-indigo-50 dark:bg-indigo-500/15 px-2.5 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-300">الأكثر طلباً</span>
                        </div>

                        <div class="mt-4 flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-slate-900 dark:text-white" dir="ltr">
                                ${{ monthlyPrice(plan).toFixed(2) }}
                            </span>
                            <span class="text-sm text-slate-400">/ شهر</span>
                        </div>
                        <p class="mt-1 h-5 text-xs text-slate-400">
                            <template v-if="isAnnual">
                                تُدفع سنوياً: <span dir="ltr" class="tabular-nums">${{ (monthlyPrice(plan) * 12).toFixed(2) }}</span>
                                <span class="line-through ms-1" dir="ltr">${{ (Number(plan.price) * 12).toFixed(2) }}</span>
                            </template>
                            <template v-else>تُدفع شهرياً، ألغِ متى شئت</template>
                        </p>

                        <ul class="mt-6 space-y-2.5 text-sm text-slate-600 dark:text-slate-400 flex-1">
                            <li v-for="(feat, idx) in plan.features" :key="idx" class="flex items-start gap-2">
                                <NavIcon name="check" class="h-4 w-4 shrink-0 text-emerald-500 mt-0.5" />
                                <span>{{ feat }}</span>
                            </li>
                        </ul>

                        <Link
                            :href="`/register-company?plan=${plan.slug}`"
                            :class="plan.slug === 'pro' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'"
                            class="mt-8 w-full text-center py-3 rounded-xl text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60"
                        >
                            اختيار الباقة
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FAQ ================= -->
        <section id="faq" class="py-24">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white text-center">أسئلة شائعة</h2>

                <div class="mt-12 divide-y divide-slate-100 dark:divide-slate-800 border-y border-slate-100 dark:border-slate-800">
                    <div v-for="(faq, index) in faqs" :key="index">
                        <button
                            type="button"
                            @click="toggleFaq(index)"
                            :id="`faq-q-${index}`"
                            :aria-expanded="openFaq === index"
                            :aria-controls="`faq-a-${index}`"
                            class="w-full flex items-center justify-between py-5 text-start font-medium text-slate-800 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white transition rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60"
                        >
                            <span>{{ faq.q }}</span>
                            <NavIcon name="chevron-down" class="h-4 w-4 text-slate-400 shrink-0 ms-3 transition-transform" :class="openFaq === index ? 'rotate-180' : ''" />
                        </button>
                        <p v-show="openFaq === index" :id="`faq-a-${index}`" role="region" :aria-labelledby="`faq-q-${index}`" class="pb-5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ faq.a }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FINAL CTA ================= -->
        <section class="py-20 bg-slate-50/70 dark:bg-[#0d1333]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">جاهز للبدء؟</h2>
                <p class="mt-3 text-slate-500 dark:text-slate-400">يُجهَّز حسابك ونطاقك خلال دقيقة، ونساعدك في إدخال العملات والأرصدة.</p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        href="/register-company"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-7 py-3.5 text-sm font-semibold text-white transition"
                    >
                        <span>سجّل شركتك</span>
                        <NavIcon name="chevron-left" class="h-4 w-4" />
                    </Link>
                    <a
                        :href="whatsappHref"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 px-7 py-3.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800 transition"
                    >
                        لديك سؤال؟ راسلنا على واتساب
                    </a>
                </div>
            </div>
        </section>

        <!-- ================= FOOTER ================= -->
        <footer class="border-t border-slate-100 dark:border-slate-800/60 py-8 text-sm text-slate-400">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span>DamaTech Exchange</span>
                <span dir="ltr">
                    © {{ new Date().getFullYear() }}
                    <a href="https://damatech.sy" target="_blank" rel="noopener noreferrer" class="hover:text-slate-600 dark:hover:text-slate-300">Damatech</a>
                </span>
            </div>
        </footer>
    </div>
</template>
