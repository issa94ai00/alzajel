<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';

const props = defineProps({
    href: { type: String, required: true },
    icon: { type: String, required: true },
    active: { type: Boolean, default: false },
    collapsed: { type: Boolean, default: false },
    title: { type: String, default: '' },
    badge: { type: [String, Number], default: null },
});

const base =
    'group relative flex items-center rounded-xl transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-950';

const classes = computed(() => {
    const layout = props.collapsed
        ? 'justify-center p-2.5 my-1 mx-auto w-11 h-11'
        : 'gap-3 px-3 py-2.5 text-sm my-0.5';

    const colors = props.active
        ? 'bg-gradient-to-r rtl:bg-gradient-to-l from-indigo-500/10 via-violet-500/5 to-transparent font-bold text-indigo-700 ring-1 ring-inset ring-indigo-500/10 dark:from-indigo-500/25 dark:via-violet-500/15 dark:text-white dark:ring-white/10 dark:shadow-[0_2px_12px_-2px_rgb(var(--accent-500)/0.5)]'
        : 'font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white';

    return `${base} ${layout} ${colors}`;
});
</script>

<template>
    <Link
        :href="href"
        :class="classes"
        :aria-current="active ? 'page' : undefined"
        :title="collapsed ? title : undefined"
    >
        <!-- شريط الحافة البارز في حالة العنصر النشط -->
        <span
            v-if="active"
            class="absolute inset-y-1.5 start-0 w-1 rounded-full bg-gradient-to-b from-indigo-400 to-violet-500 shadow-[0_0_10px_rgba(129,140,248,0.8)]"
            aria-hidden="true"
        ></span>

        <span class="relative flex shrink-0 items-center justify-center">
            <NavIcon
                :name="icon"
                class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                :class="active ? 'text-indigo-600 dark:text-indigo-300' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200'"
            />

            <!-- مؤشر نقطة في الوضع المنكمش إذا كان هناك شارة -->
            <span
                v-if="collapsed && badge"
                class="absolute -top-1 -end-1 h-2 w-2 rounded-full bg-indigo-400 ring-2 ring-white dark:ring-slate-950"
            />
        </span>

        <span v-if="!collapsed" class="flex-1 truncate">
            <slot />
        </span>

        <span
            v-if="!collapsed && badge"
            class="ms-auto shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-600 ring-1 ring-inset ring-indigo-500/15 dark:bg-white/10 dark:text-indigo-200 dark:ring-white/10"
        >
            {{ badge }}
        </span>

        <!-- تلميح يظهر عند التحويم في وضع الشريط المنكمش (Desktop Rail Tooltip) -->
        <span
            v-if="collapsed && title"
            class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg border border-white/10 bg-slate-900/95 px-2.5 py-1 text-xs font-bold text-white shadow-xl backdrop-blur-md opacity-0 transition-opacity duration-150 group-hover:opacity-100"
        >
            {{ title }}
        </span>
    </Link>
</template>
