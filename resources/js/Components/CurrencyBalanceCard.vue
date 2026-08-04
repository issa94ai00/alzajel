<script setup>
defineProps({
    code: { type: String, required: true },
    name: { type: String, required: true },
    symbol: { type: String, default: '' },
    balance: { type: [String, Number], required: true },
});

function formatNumber(value) {
    return new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 2 }).format(Number(value));
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ name }}</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">{{ code.slice(0, 2) }}</span>
        </div>
        <div class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
            {{ formatNumber(balance) }}
            <span class="text-base font-normal text-slate-400">{{ symbol }}</span>
        </div>
    </div>
</template>
