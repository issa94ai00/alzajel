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
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">{{ name }}</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600">{{ code.slice(0, 2) }}</span>
        </div>
        <div class="mt-3 text-2xl font-bold text-slate-900">
            {{ formatNumber(balance) }}
            <span class="text-base font-normal text-slate-400">{{ symbol }}</span>
        </div>
    </div>
</template>
