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
    'group relative flex items-center transition-all duration-150 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900';

const classes = computed(() => {
    const layout = props.collapsed
        ? 'justify-center p-2.5 my-1 mx-auto w-11 h-11'
        : 'gap-3 px-3 py-2.5 text-sm my-0.5';

    const colors = props.active
        ? 'bg-gradient-to-r from-indigo-600/30 to-indigo-600/10 text-white font-bold ring-1 ring-indigo-500/30 shadow-sm'
        : 'font-medium text-slate-300 hover:bg-white/8 hover:text-white';

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
            class="absolute inset-y-1.5 start-0 w-1 rounded-full bg-indigo-400 shadow-[0_0_8px_rgba(129,140,248,0.7)]"
            aria-hidden="true"
        ></span>

        <span class="relative shrink-0 flex items-center justify-center">
            <NavIcon
                :name="icon"
                class="h-5 w-5 transition-transform duration-150 group-hover:scale-105"
                :class="active ? 'text-indigo-400' : 'text-slate-400 group-hover:text-slate-200'"
            />

            <!-- مؤشر نقطة في الوضع المنكمش إذا كان هناك شارة -->
            <span
                v-if="collapsed && badge"
                class="absolute -top-1 -end-1 h-2 w-2 rounded-full bg-indigo-400"
            />
        </span>

        <span v-if="!collapsed" class="truncate flex-1">
            <slot />
        </span>

        <span
            v-if="!collapsed && badge"
            class="ms-auto shrink-0 rounded-full bg-indigo-500/20 px-2 py-0.5 text-[10px] font-bold text-indigo-300"
        >
            {{ badge }}
        </span>

        <!-- تلميح يظهر عند التحويم في وضع الشريط المنكمش (Desktop Rail Tooltip) -->
        <span
            v-if="collapsed && title"
            class="pointer-events-none absolute start-full top-1/2 z-50 ms-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 border border-slate-700/80 px-2.5 py-1 text-xs font-bold text-white shadow-xl opacity-0 transition-opacity duration-150 group-hover:opacity-100"
        >
            {{ title }}
        </span>
    </Link>
</template>
