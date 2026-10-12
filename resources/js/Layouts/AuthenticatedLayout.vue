<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavIcon from '@/Components/NavIcon.vue';
import IconBar from '@/Components/IconBar.vue';
import IconNavigatorModal from '@/Components/IconNavigatorModal.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import ShiftStatusBanner from '@/Components/ShiftStatusBanner.vue';
import ReceiptPrompt from '@/Components/ReceiptPrompt.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    // صفحة تذكر الوردية في رأسها بنفسها تستطيع إخفاء الشريط العام كي لا يتكرر.
    showShiftBanner: { type: Boolean, default: true },
});

const { isDark, toggle: toggleDarkMode } = useDarkMode();

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const user = computed(() => page.props.auth.user);
const currentShift = computed(() => page.props.currentShift);
const companyName = computed(() => page.props.settings?.company_name || 'DamaTech');
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const userInitial = computed(() => (user.value?.name?.trim()?.charAt(0) ?? '?').toUpperCase());

/*
 * مجموعات التنقل: التشغيل اليومي أولاً، ثم الإدارة والنظام.
 */
const navGroups = computed(() => {
    const groups = [
        {
            key: 'main',
            label: 'العمليات اليومية',
            links: [
                { href: route('dashboard'), label: 'الرئيسية', icon: 'home', current: 'dashboard' },
                { href: route('transactions.create'), label: 'عملية جديدة', icon: 'plus-circle', current: 'transactions.create', badge: 'جديد' },
                { href: route('transactions.index'), label: 'سجل العمليات', icon: 'receipt', current: 'transactions.index' },
                { href: route('shifts.index'), label: 'الورديات', icon: 'clock', current: 'shifts.index' },
                { href: route('reports.index'), label: 'التقارير', icon: 'chart-bar', current: 'reports.*' },
            ],
        },
    ];

    if (isAdmin.value) {
        groups.push({
            key: 'admin',
            label: 'الإدارة والنظام',
            links: [
                { href: route('rates.index'), label: 'أسعار الصرف', icon: 'currency', current: 'rates.index' },
                { href: route('currencies.index'), label: 'العملات', icon: 'coins', current: 'currencies.*' },
                { href: route('users.index'), label: 'المستخدمون', icon: 'users', current: 'users.*' },
                { href: route('settings.index'), label: 'الإعدادات', icon: 'settings', current: 'settings.*' },
            ],
        });
    }

    return groups;
});

// حالة الدرج على الجوال
const sidebarOpen = ref(false);

// حالة تصغير الشريط الجانبي على أجهزة سطح المكتب (Rail Mode)
const sidebarCollapsed = ref(
    typeof window !== 'undefined'
        ? (localStorage.getItem('sidebar_collapsed') === 'true' || localStorage.getItem('sidebar-collapsed') === 'true')
        : false,
);

// وضع الأيقونات (Icon Mode)
const iconMode = ref(
    typeof window !== 'undefined'
        ? (localStorage.getItem('nav-mode') === 'icons' || localStorage.getItem('nav_mode') === 'icons')
        : false,
);

// حالة نافذة شبكة الأيقونات السريعة (Icon Grid Modal)
const iconGridOpen = ref(false);

const sidebarEl = ref(null);
const menuButton = ref(null);
const closeButton = ref(null);

function toggleSidebarCollapse() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    if (typeof window !== 'undefined') {
        localStorage.setItem('sidebar_collapsed', String(sidebarCollapsed.value));
    }
}

function toggleNavMode() {
    iconMode.value = !iconMode.value;
    if (typeof window !== 'undefined') {
        const mode = iconMode.value ? 'icons' : 'sidebar';
        localStorage.setItem('nav-mode', mode);
        localStorage.setItem('nav_mode', mode);
    }
}

function openIconGrid() {
    iconGridOpen.value = true;
}

function closeIconGrid() {
    iconGridOpen.value = false;
}

const desktop = typeof window !== 'undefined' ? window.matchMedia('(min-width: 1024px)') : null;

function closeSidebar() {
    sidebarOpen.value = false;
}

function focusables() {
    return Array.from(
        sidebarEl.value?.querySelectorAll('a[href], button:not([disabled])') ?? [],
    ).filter((el) => el.offsetParent !== null);
}

function onKeydown(event) {
    // اختصار تبديل وضع الأيقونات / القائمة الجانبية (Alt + I أو Alt + ه)
    if (event.altKey && (event.key === 'i' || event.key === 'I' || event.key === 'ه')) {
        event.preventDefault();
        toggleNavMode();
        return;
    }

    // اختصار فتح شبكة الأيقونات الشاملة (Alt + G أو Alt + ل)
    if (event.altKey && (event.key === 'g' || event.key === 'G' || event.key === 'ل')) {
        event.preventDefault();
        iconGridOpen.value = !iconGridOpen.value;
        return;
    }

    // اختصار لوحة المفاتيح لتصغير/توسيع الشريط الجانبي على سطح المكتب
    if (event.ctrlKey && (event.key === 'b' || event.key === 'B' || event.key === 'لا')) {
        event.preventDefault();
        toggleSidebarCollapse();
        return;
    }

    if (!sidebarOpen.value) return;

    if (event.key === 'Escape') {
        closeSidebar();
        return;
    }

    if (event.key !== 'Tab') return;

    const items = focusables();
    if (!items.length) return;

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function onBreakpointChange(event) {
    if (event.matches) closeSidebar();
}

watch(sidebarOpen, async (open) => {
    await nextTick();
    const target = open ? closeButton.value : menuButton.value;
    if (target?.offsetParent !== null) target?.focus();
});

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    desktop?.addEventListener('change', onBreakpointChange);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    desktop?.removeEventListener('change', onBreakpointChange);
});
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-slate-50 supports-[height:100dvh]:h-dvh dark:bg-slate-950">
        <!-- غطاء الخلفية على الشاشات الصغيرة (Mobile Backdrop) -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-show="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-950/70 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="closeSidebar"
            ></div>
        </Transition>

        <!-- نافذة شبكة الأيقونات للتنقل السريع -->
        <IconNavigatorModal
            :show="iconGridOpen"
            :nav-groups="navGroups"
            @close="closeIconGrid"
        />

        <!-- وضع التنقل بالأيقونات (Icon Mode Rail) -->
        <IconBar
            v-if="iconMode"
            :nav-groups="navGroups"
            :current-shift="currentShift"
            :is-open="sidebarOpen"
            @close="closeSidebar"
            @toggle-mode="toggleNavMode"
            @open-launcher="openIconGrid"
        >
            <template #bottom>
                <Dropdown align="left" width="48" drop="up">
                    <template #trigger>
                        <button
                            type="button"
                            class="group flex h-11 w-11 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                            :title="user?.name"
                            aria-label="قائمة الحساب"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 text-sm font-black text-white shadow-sm shadow-indigo-600/30 ring-2 ring-transparent transition group-hover:ring-indigo-400">
                                {{ userInitial }}
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <div class="px-4 py-2 border-b border-gray-100 dark:border-slate-700">
                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ user?.name }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-slate-400 truncate">{{ user?.email }}</p>
                        </div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-700/60"
                            @click="toggleNavMode"
                        >
                            <NavIcon name="sidebar" class="h-4 w-4 text-indigo-500" />
                            <span>العودة للقائمة الجانبية</span>
                        </button>
                        <DropdownLink :href="route('profile.edit')">
                            الملف الشخصي
                        </DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            تسجيل الخروج
                        </DropdownLink>
                    </template>
                </Dropdown>
            </template>
        </IconBar>

        <!-- الوضع التقليدي (القائمة الجانبية الموسعة) -->
        <aside
            v-else
            id="app-sidebar"
            ref="sidebarEl"
            aria-label="القائمة الجانبية"
            class="fixed inset-y-0 start-0 z-40 flex shrink-0 flex-col bg-slate-900 border-e border-white/10 shadow-2xl transition-all duration-300 ease-in-out dark:bg-slate-950 dark:border-slate-800 lg:static lg:shadow-none"
            :class="[
                sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
                sidebarCollapsed ? 'lg:w-20' : 'lg:w-72',
                'w-72',
            ]"
        >
            <!-- رأس القائمة: الشعار وزر التصغير/الإغلاق -->
            <div
                class="flex h-16 shrink-0 items-center border-b border-white/10 px-4 transition-all duration-300 dark:border-slate-800"
                :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'justify-between'"
            >
                <!-- الشعار واسم النظام -->
                <Link
                    :href="route('dashboard')"
                    class="group flex min-w-0 items-center gap-3 rounded-xl p-1 transition hover:opacity-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                    :title="companyName"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 shadow-md shadow-indigo-600/30 transition-transform group-hover:scale-105">
                        <NavIcon name="currency" class="h-5 w-5 text-white" />
                    </span>

                    <span v-if="!sidebarCollapsed" class="min-w-0 transition-opacity duration-200">
                        <span class="block truncate text-base font-black leading-tight tracking-tight text-white">
                            {{ companyName }}
                        </span>
                        <span class="block truncate text-[11px] font-medium leading-tight text-slate-400">
                            نظام إدارة الصرافة
                        </span>
                    </span>
                </Link>

                <!-- أزرار الإجراء: إغلاق للجوال وتصغير لسطح المكتب -->
                <div class="flex items-center gap-1">
                    <!-- زر تصغير/توسيع الشريط لسطح المكتب -->
                    <button
                        type="button"
                        class="hidden rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 lg:inline-flex"
                        :title="sidebarCollapsed ? 'توسيع القائمة الجانبية (Ctrl+B)' : 'تصغير القائمة الجانبية (Ctrl+B)'"
                        aria-label="تبديل حجم القائمة الجانبية"
                        @click="toggleSidebarCollapse"
                    >
                        <NavIcon name="collapse" class="h-4 w-4 transition-transform duration-200" :class="sidebarCollapsed ? 'rotate-180' : ''" />
                    </button>

                    <!-- زر إغلاق الدرج على الجوال -->
                    <button
                        ref="closeButton"
                        type="button"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 lg:hidden"
                        aria-label="إغلاق القائمة"
                        @click="closeSidebar"
                    >
                        <NavIcon name="close" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- مصغر حالة الوردية الحالية داخل القائمة -->
            <div
                v-if="!sidebarCollapsed"
                class="px-3 pt-3.5 pb-1 transition-all"
            >
                <Link
                    v-if="currentShift"
                    :href="route('shifts.show', currentShift.id)"
                    class="group flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-950/40 p-2.5 text-xs text-emerald-300 transition hover:bg-emerald-900/40 hover:border-emerald-500/50"
                    title="الوردية الحالية مفتوحة — انقر لعرض التفاصيل"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="relative flex h-2.5 w-2.5 shrink-0">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        </span>
                        <div class="min-w-0">
                            <span class="block truncate font-bold">وردية مفتوحة</span>
                            <span class="block text-[10px] text-emerald-400/80 tabular-nums">{{ currentShift.business_date }}</span>
                        </div>
                    </div>
                    <NavIcon name="chevron-left" class="h-3.5 w-3.5 shrink-0 text-emerald-400 opacity-60 transition-transform group-hover:-translate-x-0.5" />
                </Link>

                <Link
                    v-else
                    :href="route('shifts.create')"
                    class="flex items-center justify-between rounded-xl border border-amber-500/30 bg-amber-950/40 p-2.5 text-xs text-amber-300 transition hover:bg-amber-900/40"
                    title="لا توجد وردية مفتوحة — انقر لفتح وردية"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="h-2 w-2 shrink-0 rounded-full bg-amber-400"></span>
                        <span class="truncate font-bold">لا توجد وردية مفتوحة</span>
                    </div>
                    <span class="text-[10px] font-bold underline shrink-0">فتح</span>
                </Link>
            </div>

            <!-- روابط التنقل الرئيسية -->
            <nav class="flex-1 overflow-y-auto px-2.5 py-3 space-y-4" aria-label="التنقل الرئيسي">
                <div
                    v-for="group in navGroups"
                    :key="group.key"
                    class="space-y-1"
                >
                    <!-- عنوان المجموعة (يُخفى في الوضع المنكمش) -->
                    <p
                        v-if="!sidebarCollapsed && group.label"
                        class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500"
                    >
                        {{ group.label }}
                    </p>

                    <!-- خط فاصل لطيف في الوضع المنكمش -->
                    <div
                        v-else-if="sidebarCollapsed && group.label"
                        class="mx-auto my-2 w-8 border-t border-white/10 dark:border-slate-800"
                    />

                    <SidebarNavLink
                        v-for="link in group.links"
                        :key="link.label"
                        :href="link.href"
                        :icon="link.icon"
                        :active="route().current(link.current)"
                        :collapsed="sidebarCollapsed"
                        :title="link.label"
                        :badge="link.badge"
                        @click="closeSidebar"
                    >
                        {{ link.label }}
                    </SidebarNavLink>
                </div>
            </nav>

            <!-- أسفل القائمة الجانبية: بطاقة المستخدم وقائمة الحساب -->
            <div class="shrink-0 border-t border-white/10 p-2.5 transition-all dark:border-slate-800">
                <Dropdown align="left" width="48" drop="up">
                    <template #trigger>
                        <button
                            type="button"
                            class="group flex w-full items-center gap-3 rounded-xl p-2 text-start transition hover:bg-white/8 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                            :class="sidebarCollapsed ? 'justify-center' : ''"
                            :title="user?.name"
                            aria-label="قائمة الحساب"
                        >
                            <!-- الصورة الرمزية للمستخدم -->
                            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-sm font-black text-white shadow-sm shadow-indigo-600/30">
                                {{ userInitial }}
                            </span>

                            <span v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-white group-hover:text-indigo-200 transition-colors">
                                    {{ user?.name }}
                                </span>
                                <span class="block truncate text-[11px] text-slate-400">
                                    {{ isAdmin ? 'مدير النظام' : 'صراف' }}
                                </span>
                            </span>

                            <NavIcon v-if="!sidebarCollapsed" name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:text-white" />
                        </button>
                    </template>

                    <template #content>
                        <div class="px-4 py-2 border-b border-gray-100 dark:border-slate-700">
                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ user?.name }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-slate-400 truncate">{{ user?.email }}</p>
                        </div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-700/60"
                            @click="toggleNavMode"
                        >
                            <NavIcon name="grid" class="h-4 w-4 text-indigo-500" />
                            <span>التحويل لوضع الأيقونات</span>
                        </button>
                        <DropdownLink :href="route('profile.edit')">
                            الملف الشخصي
                        </DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            تسجيل الخروج
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <!-- العمود الرئيسي للمحتوى -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <!-- شريط الرأس العلوي -->
            <header class="flex min-h-[4rem] shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/90 px-3.5 py-2.5 backdrop-blur-md transition-colors dark:border-slate-800/80 dark:bg-slate-900/90 sm:px-6 lg:px-8">
                <!-- زر فتح القائمة على الهواتف والأجهزة اللوحية -->
                <button
                    ref="menuButton"
                    type="button"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/70 bg-white text-slate-600 shadow-xs transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700/70 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white lg:hidden"
                    aria-label="فتح قائمة التنقل"
                    :aria-controls="iconMode ? 'app-iconbar' : 'app-sidebar'"
                    :aria-expanded="sidebarOpen"
                    @click="sidebarOpen = true"
                >
                    <NavIcon name="menu" class="h-5 w-5" />
                </button>

                <!-- زر تبديل حجم القائمة الجانبية على سطح المكتب بجوار العنوان (يظهر فقط في وضع الشريط الجانبي) -->
                <button
                    v-if="!iconMode"
                    type="button"
                    class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/60 bg-white/80 text-slate-500 shadow-xs transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700/60 dark:bg-slate-800/80 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white lg:inline-flex"
                    :title="sidebarCollapsed ? 'توسيع القائمة (Ctrl+B)' : 'تصغير القائمة (Ctrl+B)'"
                    @click="toggleSidebarCollapse"
                >
                    <NavIcon name="collapse" class="h-4 w-4 transition-transform duration-200" :class="sidebarCollapsed ? 'rotate-180' : ''" />
                </button>

                <!-- عنوان أو محتوى الرأس المخصص للصفحة -->
                <div v-if="$slots.header" class="min-w-0 flex-1">
                    <slot name="header" />
                </div>
                <div v-else class="flex-1"></div>

                <!-- زر فتح لوحة الأيقونات السريعة للتنقل -->
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/70 bg-white text-slate-500 shadow-xs transition hover:bg-slate-100 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700/70 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-indigo-400"
                    title="لوحة الأيقونات الشاملة (Alt+G)"
                    aria-label="لوحة الأيقونات الشاملة (Alt+G)"
                    @click="openIconGrid"
                >
                    <NavIcon name="grid" class="h-4.5 w-4.5" />
                </button>

                <!-- زر تبديل وضع التنقل (وضع الأيقونات / القائمة الجانبية) -->
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/70 bg-white text-slate-500 shadow-xs transition hover:bg-slate-100 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700/70 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-indigo-400"
                    :title="iconMode ? 'التبديل لوضع القائمة الجانبية (Alt+I)' : 'التبديل لوضع الأيقونات (Alt+I)'"
                    :aria-label="iconMode ? 'التبديل لوضع القائمة الجانبية (Alt+I)' : 'التبديل لوضع الأيقونات (Alt+I)'"
                    @click="toggleNavMode"
                >
                    <NavIcon :name="iconMode ? 'sidebar' : 'layout-grid'" class="h-4.5 w-4.5" />
                </button>

                <!-- زر تبديل الوضع الداكن/الفاتح -->
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/70 bg-white text-slate-500 shadow-xs transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700/70 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white"
                    :title="isDark ? 'التحويل للوضع الفاتح' : 'التحويل للوضع الداكن'"
                    :aria-label="isDark ? 'التحويل للوضع الفاتح' : 'التحويل للوضع الداكن'"
                    @click="toggleDarkMode"
                >
                    <NavIcon :name="isDark ? 'sun' : 'moon'" class="h-4.5 w-4.5 transition-transform duration-200 hover:rotate-12" />
                </button>
            </header>

            <ShiftStatusBanner v-if="showShiftBanner" />

            <ReceiptPrompt />

            <!-- رسائل النجاح والخطأ العامة -->
            <div
                v-if="flashSuccess"
                class="mx-4 mt-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300 sm:mx-6 lg:mx-8 shadow-sm"
                role="status"
            >
                <NavIcon name="check" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <span>{{ flashSuccess }}</span>
            </div>

            <div
                v-if="flashError"
                class="mx-4 mt-4 flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300 sm:mx-6 lg:mx-8 shadow-sm"
                role="alert"
            >
                <NavIcon name="close" class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400" />
                <span>{{ flashError }}</span>
            </div>

            <!-- الحاوية الرئيسية للمحتوى -->
            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
