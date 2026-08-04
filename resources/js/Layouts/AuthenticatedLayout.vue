<script setup>
import { ref, computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavIcon from '@/Components/NavIcon.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import ShiftStatusBanner from '@/Components/ShiftStatusBanner.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { Link, usePage } from '@inertiajs/vue3';

const { isDark, toggle: toggleDarkMode } = useDarkMode();

const sidebarOpen = ref(false);
const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const user = computed(() => page.props.auth.user);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const userInitial = computed(() => (user.value?.name?.trim()?.charAt(0) ?? '?').toUpperCase());

const navLinks = computed(() => {
    const links = [
        { href: route('dashboard'), label: 'الرئيسية', icon: 'home', current: 'dashboard' },
        { href: route('transactions.create'), label: 'عملية جديدة', icon: 'plus-circle', current: 'transactions.create' },
        { href: route('transactions.index'), label: 'سجل العمليات', icon: 'receipt', current: 'transactions.index' },
        { href: route('shifts.index'), label: 'الورديات', icon: 'clock', current: 'shifts.index' },
        { href: route('reports.balances'), label: 'التقارير', icon: 'chart-bar', current: 'reports.*' },
    ];

    if (isAdmin.value) {
        links.push({ href: route('rates.index'), label: 'أسعار الصرف', icon: 'currency', current: 'rates.index' });
        links.push({ href: route('currencies.index'), label: 'العملات', icon: 'coins', current: 'currencies.*' });
        links.push({ href: route('users.index'), label: 'المستخدمون', icon: 'users', current: 'users.*' });
    }

    return links;
});
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
        <!-- Mobile backdrop -->
        <div
            v-show="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 z-40 flex w-72 shrink-0 transform flex-col bg-slate-900 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-500/20">
                    <NavIcon name="currency" class="h-5 w-5 text-indigo-400" />
                </div>
                <div>
                    <p class="text-sm font-bold leading-tight text-white">الزاجل</p>
                    <p class="text-xs leading-tight text-slate-400">نظام إدارة الصرافة</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <SidebarNavLink
                    v-for="link in navLinks"
                    :key="link.label"
                    :href="link.href"
                    :icon="link.icon"
                    :active="route().current(link.current)"
                >
                    {{ link.label }}
                </SidebarNavLink>
            </nav>

            <div class="border-t border-white/10 p-3">
                <Dropdown align="left" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start transition hover:bg-white/5"
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
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
                    @click="sidebarOpen = true"
                >
                    <NavIcon name="menu" class="h-6 w-6" />
                </button>
                <div v-if="$slots.header" class="min-w-0 flex-1">
                    <slot name="header" />
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    :title="isDark ? 'الوضع الفاتح' : 'الوضع الداكن'"
                    @click="toggleDarkMode"
                >
                    <NavIcon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
                </button>
            </header>

            <ShiftStatusBanner />

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
