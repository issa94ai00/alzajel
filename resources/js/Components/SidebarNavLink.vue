<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';

const props = defineProps({
    href: { type: String, required: true },
    icon: { type: String, required: true },
    active: { type: Boolean, default: false },
});

const base =
    'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900';

const classes = computed(() =>
    props.active
        ? `${base} bg-white/10 font-semibold text-white`
        : `${base} font-medium text-slate-300 hover:bg-white/5 hover:text-white`,
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <!-- شريط الحافة يميّز الصفحة الحالية بلا اعتماد على فرق اللون وحده -->
        <span
            v-if="active"
            class="absolute inset-y-1.5 start-0 w-1 rounded-full bg-indigo-400"
            aria-hidden="true"
        ></span>

        <NavIcon
            :name="icon"
            class="h-5 w-5 shrink-0 transition-colors"
            :class="active ? 'text-indigo-400' : 'text-slate-400 group-hover:text-slate-200'"
        />
        <span class="truncate"><slot /></span>
    </Link>
</template>
