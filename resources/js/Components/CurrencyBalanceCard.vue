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
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-500">{{ name }}</span>
            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ code }}</span>
        </div>
        <div class="mt-2 text-2xl font-bold text-gray-900">
            {{ formatNumber(balance) }}
            <span class="text-base font-normal text-gray-400">{{ symbol }}</span>
        </div>
    </div>
</template>
