<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavIcon from '@/Components/NavIcon.vue';
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
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const userInitial = computed(() => (user.value?.name?.trim()?.charAt(0) ?? '?').toUpperCase());

/*
 * روابط التشغيل اليومي أولاً، ثم روابط الإدارة تحت عنوانها. الفصل ليس تزييناً:
 * القائمة تطول إلى تسعة روابط عند المدير، فتضيع الورديات بين الإعدادات والعملات
 * ما لم يفصل بينها عنوان.
 */
const navGroups = computed(() => {
    const groups = [
        {
            key: 'main',
            label: null,
            links: [
                { href: route('dashboard'), label: 'الرئيسية', icon: 'home', current: 'dashboard' },
                { href: route('transactions.create'), label: 'عملية جديدة', icon: 'plus-circle', current: 'transactions.create' },
                { href: route('transactions.index'), label: 'سجل العمليات', icon: 'receipt', current: 'transactions.index' },
                { href: route('shifts.index'), label: 'الورديات', icon: 'clock', current: 'shifts.index' },
                { href: route('reports.index'), label: 'التقارير', icon: 'chart-bar', current: 'reports.*' },
            ],
        },
    ];

    if (isAdmin.value) {
        groups.push({
            key: 'admin',
            label: 'الإدارة',
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

const sidebarOpen = ref(false);
const sidebarEl = ref(null);
const menuButton = ref(null);
const closeButton = ref(null);

const desktop = typeof window !== 'undefined' ? window.matchMedia('(min-width: 1024px)') : null;

function closeSidebar() {
    sidebarOpen.value = false;
}

/*
 * على الجوال الدرج نافذة حوارية فعلياً: يغطي الشاشة ويحجب ما خلفه. فيلزمه إذاً
 * ما يلزم النافذة — مخرجٌ بالمفتاح Escape، وتركيزٌ محبوس داخله لا يتسلل إلى
 * روابط مخفية خلف الحجاب، ثم يعود إلى الزرّ الذي فتحه عند الإغلاق.
 */
function focusables() {
    return Array.from(
        sidebarEl.value?.querySelectorAll('a[href], button:not([disabled])') ?? [],
    ).filter((el) => el.offsetParent !== null);
}

function onKeydown(event) {
    if (! sidebarOpen.value) return;

    if (event.key === 'Escape') {
        closeSidebar();

        return;
    }

    if (event.key !== 'Tab') return;

    const items = focusables();

    if (! items.length) return;

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

/* الشاشة تتسع إلى مقاس سطح المكتب فيصير الدرج شريطاً ثابتاً — وحبس التركيز فيه عندئذٍ خطأ. */
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
        <!-- Mobile backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-show="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/60 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="closeSidebar"
            ></div>
        </Transition>

        <!--
            الصفحة كلها rtl (dir في app.blade.php)، فالدرج يخرج من حافة البداية
            يميناً و translate-x-full هو ما يزيحه خارج الشاشة.
        -->
        <aside
            id="app-sidebar"
            ref="sidebarEl"
            aria-label="القائمة الجانبية"
            class="fixed inset-y-0 end-0 z-40 flex w-72 shrink-0 transform flex-col bg-slate-900 shadow-2xl transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 lg:shadow-none"
            :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <Link
                    :href="route('dashboard')"
                    class="flex min-w-0 items-center gap-3 rounded-lg transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-500/20">
                        <NavIcon name="currency" class="h-5 w-5 text-indigo-400" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-bold leading-tight text-white">DamaTech</span>
                        <span class="block truncate text-xs leading-tight text-slate-400">نظام إدارة الصرافة</span>
                    </span>
                </Link>

                <button
                    ref="closeButton"
                    type="button"
                    class="ms-auto shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 lg:hidden"
                    aria-label="إغلاق القائمة"
                    @click="closeSidebar"
                >
                    <NavIcon name="close" class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="التنقل الرئيسي">
                <div
                    v-for="group in navGroups"
                    :key="group.key"
                    class="space-y-1"
                    :class="group.label ? 'mt-4 border-t border-white/5 pt-4' : ''"
                >
                    <p
                        v-if="group.label"
                        class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                    >
                        {{ group.label }}
                    </p>

                    <SidebarNavLink
                        v-for="link in group.links"
                        :key="link.label"
                        :href="link.href"
                        :icon="link.icon"
                        :active="route().current(link.current)"
                        @click="closeSidebar"
                    >
                        {{ link.label }}
                    </SidebarNavLink>
                </div>
            </nav>

            <div class="shrink-0 border-t border-white/10 p-3">
                <Dropdown align="left" width="48" drop="up">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start transition hover:bg-white/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                            aria-label="قائمة الحساب"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-sm font-bold text-white">
                                {{ userInitial }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">{{ user?.name }}</span>
                                <span class="block truncate text-xs text-slate-400">{{ user?.email }}</span>
                            </span>
                            <NavIcon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink :href="route('profile.edit')">الملف الشخصي</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">تسجيل الخروج</DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <header class="flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900 sm:px-6 lg:px-8">
                <button
                    ref="menuButton"
                    type="button"
                    class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
                    aria-label="فتح القائمة"
                    aria-controls="app-sidebar"
                    :aria-expanded="sidebarOpen"
                    @click="sidebarOpen = true"
                >
                    <NavIcon name="menu" class="h-6 w-6" />
                </button>
                <div v-if="$slots.header" class="min-w-0 flex-1">
                    <slot name="header" />
                </div>
                <button
                    type="button"
                    class="ms-auto rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800"
                    :title="isDark ? 'الوضع الفاتح' : 'الوضع الداكن'"
                    :aria-label="isDark ? 'الوضع الفاتح' : 'الوضع الداكن'"
                    @click="toggleDarkMode"
                >
                    <NavIcon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
                </button>
            </header>

            <ShiftStatusBanner v-if="showShiftBanner" />

            <ReceiptPrompt />

            <div v-if="flashSuccess" class="mx-4 mt-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 sm:mx-6 lg:mx-8">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="mx-4 mt-4 rounded-lg bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-800 dark:bg-rose-900/30 dark:text-rose-300 sm:mx-6 lg:mx-8">
                {{ flashError }}
            </div>

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
