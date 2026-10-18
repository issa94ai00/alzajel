<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import { ACCENTS, MODES, useTheme } from '@/composables/useTheme';

defineProps({
    /** صنف زر الفتح، ليطابق أزرار الشريط الذي يوضع فيه */
    buttonClass: {
        type: String,
        default:
            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/70 bg-white text-slate-500 shadow-xs transition hover:bg-slate-100 hover:text-indigo-600 dark:border-slate-700/70 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-indigo-400',
    },
});

const { mode, accent, isDark } = useTheme();

const open = ref(false);
const root = ref(null);

function onDocumentClick(e) {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}

function onKeydown(e) {
    if (open.value && e.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            :class="[buttonClass, 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500']"
            title="المظهر والألوان"
            aria-label="المظهر والألوان"
            aria-haspopup="true"
            :aria-expanded="open"
            @click="open = !open"
        >
            <NavIcon :name="isDark ? 'moon' : 'sun'" class="h-4.5 w-4.5" />
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute end-0 top-full z-50 mt-2 w-64 origin-top rounded-2xl border border-slate-200 bg-white p-3 text-start shadow-xl ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-800 dark:ring-white/5"
                role="dialog"
                aria-label="المظهر والألوان"
            >
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">المظهر</p>
                <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-900/70" role="radiogroup" aria-label="المظهر">
                    <button
                        v-for="m in MODES"
                        :key="m.key"
                        type="button"
                        role="radio"
                        :aria-checked="mode === m.key"
                        class="flex flex-col items-center gap-1 rounded-lg px-2 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        :class="
                            mode === m.key
                                ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-700 dark:text-indigo-300'
                                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'
                        "
                        @click="mode = m.key"
                    >
                        <NavIcon :name="m.icon" class="h-4 w-4" />
                        {{ m.label }}
                    </button>
                </div>

                <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">لون التمييز</p>
                <div class="flex items-center justify-between gap-2" role="radiogroup" aria-label="لون التمييز">
                    <button
                        v-for="a in ACCENTS"
                        :key="a.key"
                        type="button"
                        role="radio"
                        :aria-checked="accent === a.key"
                        :title="a.label"
                        :aria-label="a.label"
                        class="flex h-9 w-9 items-center justify-center rounded-full ring-offset-2 ring-offset-white transition hover:scale-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:ring-offset-slate-800"
                        :class="accent === a.key ? 'ring-2 ring-slate-900/70 dark:ring-white/80' : ''"
                        :style="{ backgroundColor: a.swatch }"
                        @click="accent = a.key"
                    >
                        <NavIcon v-if="accent === a.key" name="check" class="h-4 w-4 text-white" />
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
