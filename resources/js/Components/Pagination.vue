<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, required: true },
});

const hasPages = computed(() => (props.meta.links?.length ?? 0) > 3);
</script>

<template>
    <div v-if="hasPages" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            عرض {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} من أصل {{ meta.total ?? 0 }}
        </p>

        <div class="flex flex-wrap gap-1">
            <Link
                v-for="link in meta.links"
                :key="link.label"
                :href="link.url ?? ''"
                v-html="link.label"
                preserve-scroll
                class="rounded-md px-3 py-1 text-sm transition"
                :class="[
                    link.active
                        ? 'bg-indigo-600 text-white'
                        : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                    link.url ? '' : 'cursor-not-allowed opacity-40',
                ]"
                :as="link.url ? 'a' : 'span'"
            />
        </div>
    </div>
</template>
