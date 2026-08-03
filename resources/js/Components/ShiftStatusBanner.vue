<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const shift = computed(() => page.props.currentShift);
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 px-4 py-2 text-sm sm:px-6 lg:px-8"
        :class="shift ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
    >
        <span v-if="shift">
            وردية مفتوحة منذ {{ shift.business_date }}
            <Link :href="route('shifts.show', shift.id)" class="font-semibold underline">عرض التفاصيل</Link>
        </span>
        <span v-else class="flex items-center gap-2">
            لا توجد وردية مفتوحة حالياً.
            <Link :href="route('shifts.create')" class="font-semibold underline">فتح وردية جديدة</Link>
        </span>
    </div>
</template>
