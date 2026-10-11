<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const props = defineProps({
    error: {
        type: [Boolean, String],
        default: false,
    },
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md shadow-sm dark:bg-slate-800 dark:text-white dark:placeholder-slate-500"
        :class="
            error
                ? 'border-red-400 focus:border-red-500 focus:ring-red-500 dark:border-red-500/70 dark:focus:border-red-400 dark:focus:ring-red-400'
                : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:focus:border-indigo-400 dark:focus:ring-indigo-400'
        "
        v-model="model"
        ref="input"
    />
</template>