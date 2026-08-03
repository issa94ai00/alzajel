<script setup>
defineProps({
    buyRate: { type: [String, Number], default: null },
    sellRate: { type: [String, Number], default: null },
});
</script>

<template>
    <div class="flex items-center gap-3 text-sm">
        <span class="flex items-center gap-1">
            <span class="text-gray-500">شراء</span>
            <span class="font-semibold text-emerald-700">{{ buyRate ?? '—' }}</span>
        </span>
        <span class="flex items-center gap-1">
            <span class="text-gray-500">بيع</span>
            <span class="font-semibold text-rose-700">{{ sellRate ?? '—' }}</span>
        </span>
    </div>
</template>
