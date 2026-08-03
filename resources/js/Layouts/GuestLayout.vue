<script setup>
import NavIcon from '@/Components/NavIcon.vue';

const features = ['إدارة الورديات والصرافين', 'تتبع الأرباح والخسائر لحظياً', 'تقارير دقيقة لكل عملية'];
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <!-- Brand panel -->
        <div class="relative hidden w-full max-w-md flex-col justify-between overflow-hidden bg-[#141b4d] px-10 py-12 lg:flex xl:max-w-lg">
            <div class="pointer-events-none absolute -top-24 -start-24 h-72 w-72 rounded-full bg-[#f2c94c]/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -end-16 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl"></div>

            <div class="relative flex flex-1 flex-col items-center justify-center text-center">
                <img src="/logo.png" alt="الزاجل" class="h-32 w-32 rounded-2xl shadow-lg shadow-black/30" />
                <h1 class="mt-6 text-2xl font-bold text-white">الزاجل</h1>
                <p class="mt-1 text-sm text-slate-300">نظام إدارة الصرافة</p>
            </div>

            <ul class="relative space-y-3">
                <li v-for="feature in features" :key="feature" class="flex items-center gap-3 text-sm text-slate-200">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f2c94c]/15">
                        <NavIcon name="check" class="h-3.5 w-3.5 text-[#f2c94c]" />
                    </span>
                    {{ feature }}
                </li>
            </ul>
        </div>

        <!-- Form panel -->
        <div class="flex w-full flex-1 flex-col items-center justify-center px-6 py-12 sm:px-10">
            <div class="mb-8 flex flex-col items-center lg:hidden">
                <img src="/logo.png" alt="الزاجل" class="h-16 w-16 rounded-xl shadow-md" />
                <p class="mt-3 text-lg font-bold text-slate-800">الزاجل</p>
                <p class="text-xs text-slate-400">نظام إدارة الصرافة</p>
            </div>

            <div class="w-full sm:max-w-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
