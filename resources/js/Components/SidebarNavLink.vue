<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';

const props = defineProps({
    href: { type: String, required: true },
    icon: { type: String, required: true },
    active: { type: Boolean, default: false },
});

const classes = computed(() =>
    props.active
        ? 'flex items-center gap-3 rounded-lg bg-white/10 px-3 py-2.5 text-sm font-semibold text-white'
        : 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <NavIcon :name="icon" class="h-5 w-5 shrink-0" :class="active ? 'text-indigo-400' : 'text-slate-400'" />
        <span><slot /></span>
    </Link>
</template>
