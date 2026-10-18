<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';

const props = defineProps({
    navGroups: { type: Array, required: true },
    currentShift: { type: Object, default: null },
    isOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'toggleMode', 'openLauncher']);

const page = usePage();
const companyName = computed(() => page.props.settings?.company_name || 'DamaTech');
</script>

<template>
    <aside
        id="app-iconbar"
        aria-label="شريط الأيقونات"
        class="fixed inset-y-0 start-0 z-40 flex w-20 shrink-0 flex-col items-center justify-between border-e border-slate-200/80 bg-white shadow-2xl transition-all duration-300 ease-in-out dark:bg-slate-950 dark:border-slate-800 lg:static lg:shadow-none lg:translate-x-0"
        :class="isOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
    >
        <!-- Top Section: Logo + Shift Indicator + Nav Links -->
        <div class="flex w-full flex-col items-center gap-2 pt-3 pb-2 min-h-0">
            <!-- App Logo / Dashboard Link -->
            <div class="flex items-center justify-center w-full px-2">
                <Link
                    :href="route('dashboard')"
                    class="group relative flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 shadow-md shadow-indigo-600/30 transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                    :title="companyName + ' — الرئيسية'"
                    @click="emit('close')"
                >
                    <NavIcon name="currency" class="h-5 w-5 text-white" />
                    <!-- Tooltip -->
                    <span
                        class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-slate-700/80 px-2.5 py-1 text-xs font-bold text-white shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                    >
                        {{ companyName }} (الرئيسية)
                    </span>
                </Link>
            </div>

            <!-- Quick Shift Indicator Icon -->
            <div class="flex items-center justify-center w-full px-2 mt-1">
                <Link
                    v-if="currentShift"
                    :href="route('shifts.show', currentShift.id)"
                    class="group relative flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:border-emerald-500/60 dark:hover:bg-emerald-900/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                    @click="emit('close')"
                >
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <NavIcon name="clock" class="h-4.5 w-4.5 ms-1 text-emerald-600 dark:text-emerald-400" />
                    <!-- Tooltip -->
                    <span
                        class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-emerald-500/30 px-2.5 py-1 text-xs font-bold text-emerald-300 shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                    >
                        وردية مفتوحة: {{ currentShift.business_date }}
                    </span>
                </Link>

                <Link
                    v-else
                    :href="route('shifts.create')"
                    class="group relative flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 transition hover:border-amber-300 hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:border-amber-500/60 dark:hover:bg-amber-900/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
                    @click="emit('close')"
                >
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                    <NavIcon name="clock" class="h-4.5 w-4.5 ms-1 text-amber-600 dark:text-amber-400" />
                    <!-- Tooltip -->
                    <span
                        class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-amber-500/30 px-2.5 py-1 text-xs font-bold text-amber-300 shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                    >
                        لا توجد وردية مفتوحة — فتح وردية
                    </span>
                </Link>
            </div>

            <!-- Divider -->
            <div class="my-1 w-8 border-t border-slate-200 dark:border-white/10"></div>

            <!-- Scrollable Navigation Icons -->
            <nav class="flex w-full flex-col items-center gap-1.5 overflow-y-auto px-2 max-h-[calc(100vh-270px)]" aria-label="التنقل السريع بالأيقونات">
                <template v-for="(group, gIdx) in navGroups" :key="group.key">
                    <!-- Subtle divider between groups -->
                    <div
                        v-if="gIdx > 0"
                        class="my-1.5 flex w-full flex-col items-center"
                    >
                        <div class="w-8 border-t border-slate-200 dark:border-white/10"></div>
                    </div>

                    <Link
                        v-for="link in group.links"
                        :key="link.label"
                        :href="link.href"
                        class="group relative flex h-11 w-11 items-center justify-center rounded-xl transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                        :class="[
                            route().current(link.current)
                                ? 'bg-indigo-50 font-bold text-indigo-700 ring-1 ring-indigo-500/20 shadow-sm dark:bg-transparent dark:bg-gradient-to-tr dark:from-indigo-600/30 dark:to-indigo-600/10 dark:text-white dark:ring-indigo-500/40'
                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white',
                        ]"
                        :aria-current="route().current(link.current) ? 'page' : undefined"
                        @click="emit('close')"
                    >
                        <!-- Active bar indicator on start edge -->
                        <span
                            v-if="route().current(link.current)"
                            class="absolute inset-y-1.5 start-0 w-1 rounded-full bg-indigo-500 shadow-[0_0_8px_rgb(var(--accent-400)/0.7)] dark:bg-indigo-400"
                            aria-hidden="true"
                        ></span>

                        <NavIcon
                            :name="link.icon"
                            class="h-5 w-5 transition-transform duration-150 group-hover:scale-110"
                            :class="route().current(link.current) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200'"
                        />

                        <!-- Badge -->
                        <span
                            v-if="link.badge"
                            class="absolute -top-1 -end-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-indigo-600 px-1 text-[9px] font-black text-white ring-2 ring-white dark:ring-slate-950"
                        >
                            {{ link.badge }}
                        </span>

                        <!-- Tooltip on hover / keyboard focus -->
                        <span
                            class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-slate-700/80 px-2.5 py-1 text-xs font-bold text-white shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                        >
                            {{ link.label }}
                        </span>
                    </Link>
                </template>
            </nav>
        </div>

        <!-- Bottom Section: Launcher Button + Mode Switcher + User Profile Dropdown -->
        <div class="flex w-full shrink-0 flex-col items-center gap-2 border-t border-slate-200/80 px-2 py-3 dark:border-slate-800">
            <!-- Full Icon Grid Launcher Button -->
            <button
                type="button"
                class="group relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                aria-label="لوحة الأيقونات الشاملة (Alt+G)"
                @click="emit('openLauncher')"
            >
                <NavIcon name="grid" class="h-4.5 w-4.5 text-indigo-500 group-hover:scale-110 dark:text-indigo-400 transition-transform" />
                <span
                    class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-slate-700/80 px-2.5 py-1 text-xs font-bold text-white shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                >
                    لوحة الأيقونات الشاملة (Alt+G)
                </span>
            </button>

            <!-- Mode Switcher: Back to Sidebar Mode -->
            <button
                type="button"
                class="group relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                aria-label="التبديل إلى القائمة الجانبية (Alt+I)"
                @click="emit('toggleMode')"
            >
                <NavIcon name="sidebar" class="h-4.5 w-4.5 group-hover:scale-110 transition-transform" />
                <span
                    class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-slate-700/80 px-2.5 py-1 text-xs font-bold text-white shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                >
                    التبديل للقائمة الجانبية (Alt+I)
                </span>
            </button>

            <!-- Bottom Slot for User Profile Dropdown -->
            <slot name="bottom" />
        </div>
    </aside>
</template>
