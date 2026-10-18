<script setup>
import NavIcon from '@/Components/NavIcon.vue';
import ThemePicker from '@/Components/ThemePicker.vue';

const features = ['إدارة الورديات والصرافين', 'تتبع الأرباح والخسائر لحظياً', 'تقارير دقيقة لكل عملية'];
</script>

<template>
    <div class="flex min-h-screen bg-white dark:bg-slate-900">
        <!-- Brand panel: يتبع لون التمييز المختار (indigo مربوط بمتغيرات CSS) -->
        <div class="relative hidden w-full max-w-md flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 px-10 py-12 lg:flex xl:max-w-lg">
            <div class="pointer-events-none absolute -top-24 -start-24 h-72 w-72 rounded-full bg-[#f2c94c]/15 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -end-16 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.05]" style="background-image: radial-gradient(circle at 1px 1px, #ffffff 1px, transparent 0); background-size: 22px 22px;"></div>

            <div class="relative flex flex-1 flex-col items-center justify-center text-center">
                <div class="rounded-3xl bg-white/10 p-2.5 shadow-2xl shadow-black/40 ring-1 ring-white/20 backdrop-blur">
                    <img src="/logo-damatech.webp" alt="DamaTech" class="h-28 w-28 rounded-2xl" />
                </div>
                <h1 class="mt-7 text-3xl font-extrabold tracking-tight text-white">DamaTech</h1>
                <p class="mt-2 text-sm font-medium text-slate-300">نظام إدارة الصرافة</p>
            </div>

            <ul class="relative space-y-3.5">
                <li v-for="feature in features" :key="feature" class="flex items-center gap-3 text-sm text-slate-200">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f2c94c]/15">
                        <NavIcon name="check" class="h-3.5 w-3.5 text-[#f2c94c]" />
                    </span>
                    {{ feature }}
                </li>
            </ul>
        </div>

        <!-- Form panel -->
        <div class="relative flex w-full flex-1 flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-white to-slate-100 px-6 py-12 sm:px-10 dark:from-slate-900 dark:to-slate-950">
            <div class="pointer-events-none absolute -end-28 top-1/4 h-80 w-80 rounded-full bg-indigo-500/5 blur-3xl"></div>

            <div class="absolute end-4 top-4 z-10 sm:end-6 sm:top-6">
                <ThemePicker />
            </div>

            <div class="relative flex flex-col items-center lg:hidden">
                <div class="rounded-2xl bg-white/80 p-1.5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                    <img src="/logo-damatech.webp" alt="DamaTech" class="h-16 w-16 rounded-xl" />
                </div>
                <p class="mt-3 text-lg font-bold text-slate-800 dark:text-white">DamaTech</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">نظام إدارة الصرافة</p>
            </div>

            <div class="relative w-full sm:max-w-sm">
                <slot />
            </div>

            <div dir="ltr" class="relative mt-10 flex flex-col items-center gap-1 text-xs text-slate-400 dark:text-slate-500">
                <span class="h-px w-16 bg-slate-300/70 dark:bg-slate-700"></span>
                <a
                    href="https://damatech.sy"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2.5 flex items-center gap-1 rounded-md font-medium transition hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 dark:hover:text-slate-300"
                >
                    Powered by: <span class="font-bold">Damatech</span>
                </a>
            </div>
        </div>
    </div>
</template>