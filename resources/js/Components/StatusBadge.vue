<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
});

const STYLES = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
    suspended: 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30',
    inactive: 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30',
};

const LABELS = { active: 'نشط', suspended: 'موقوف', inactive: 'غير مفعّل' };

const classes = computed(() => STYLES[props.status] ?? STYLES.inactive);
const text = computed(() => props.label ?? LABELS[props.status] ?? props.status);
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="classes">
        {{ text }}
    </span>
</template>
