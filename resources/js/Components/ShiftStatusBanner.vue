<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const shift = computed(() => page.props.currentShift);
</script>

<template>
    <div class="mx-4 mt-4 sm:mx-6 lg:mx-8">
        <div
            class="flex items-center justify-between gap-3 rounded-lg px-4 py-2.5 text-sm font-medium"
            :class="shift ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'"
        >
            <span v-if="shift" class="flex items-center gap-2">
                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                وردية مفتوحة منذ {{ shift.business_date }}
                <Link :href="route('shifts.show', shift.id)" class="font-semibold underline underline-offset-2">عرض التفاصيل</Link>
            </span>
            <span v-else class="flex items-center gap-2">
                <span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                لا توجد وردية مفتوحة حالياً.
                <Link :href="route('shifts.create')" class="font-semibold underline underline-offset-2">فتح وردية جديدة</Link>
            </span>
        </div>
    </div>
</template>
