<script setup>
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    spToday: { type: Object, default: () => ({ success: false, rates: {} }) },
    isRefreshing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'refresh']);

const { number, settings } = useFormat();

const rateList = computed(() => {
    if (!props.spToday?.rates) return [];
    return Object.entries(props.spToday.rates).map(([code, item]) => ({
        code,
        ...item,
    }));
});

function formatTime(isoStr) {
    if (!isoStr) return '';
    try {
        const d = new Date(isoStr);
        return d.toLocaleTimeString(settings().locale ?? 'ar-SY', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch {
        return '';
    }
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="flex flex-col max-h-[85vh]">
            <!-- الرأس -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-teal-50/50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-800 dark:to-teal-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-md shadow-teal-600/25">
                            <NavIcon name="trending-up" class="h-6 w-6" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    مؤشر الليرة اليوم المرجعي
                                </h3>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="spToday.success
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="spToday.success ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                                    <span>{{ spToday.success ? 'متصل ومحدث' : 'غير متصل' }}</span>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                مصدر الأسعار: موقع الليرة اليوم (sp-today.com) - نشرة دمشق
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition"
                        @click="emit('close')"
                    >
                        <NavIcon name="close" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- المحتوى -->
            <div class="p-5 space-y-4 overflow-y-auto">
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 p-3">
                        <span class="text-[11px] text-slate-400 block">آخر فحص وتحديث:</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 tabular-nums">
                            {{ formatTime(spToday.updated_at) || 'غير محدد' }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 p-3">
                        <span class="text-[11px] text-slate-400 block">العملات المتاحة في النشرة:</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                            {{ rateList.length }} عملات
                        </span>
                    </div>
                </div>

                <!-- جدول العملات المتاحة -->
                <div>
                    <h4 class="text-xs font-black text-slate-700 dark:text-slate-300 mb-2">
                        أسعار النشرة المباشرة (بالليرة الجديدة والقديمة):
                    </h4>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700/60 max-h-56 overflow-y-auto bg-white dark:bg-slate-800">
                        <div
                            v-for="r in rateList"
                            :key="r.code"
                            class="p-2.5 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-base">{{ r.flag || '💱' }}</span>
                                <div>
                                    <span class="font-black text-slate-900 dark:text-white">{{ r.code }}</span>
                                    <span class="text-[10px] text-slate-400 ms-1.5">{{ r.name }}</span>
                                </div>
                            </div>

                            <div class="text-end tabular-nums">
                                <div class="flex items-center justify-end gap-2 font-bold">
                                    <span class="text-emerald-700 dark:text-emerald-400">شراء: {{ r.buy_new }}</span>
                                    <span class="text-slate-300 dark:text-slate-600">|</span>
                                    <span class="text-rose-700 dark:text-rose-400">بيع: {{ r.sell_new }}</span>
                                </div>
                                <span class="text-[9px] text-slate-400 block">
                                    ({{ number(r.buy_traditional) }} / {{ number(r.sell_traditional) }} قديمة)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-teal-50/80 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-800/60 p-3 text-xs text-teal-800 dark:text-teal-300">
                    <p class="font-bold">ملاحظة تقنية:</p>
                    <p class="text-[11px] mt-0.5">
                        يتم جلب هذه الأسعار تلقائياً وتخزينها مؤقتاً في النظام لمنع الضغط على الموقع المرجعي وسرعة الاستجابة. يمكنك طلب التحديث الفوري في أي لحظة.
                    </p>
                </div>
            </div>

            <!-- التذييل -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/90 flex items-center justify-between gap-2">
                <button
                    type="button"
                    :disabled="isRefreshing"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition disabled:opacity-50"
                    @click="emit('refresh')"
                >
                    <NavIcon name="refresh" class="h-3.5 w-3.5 text-slate-500" :class="isRefreshing ? 'animate-spin' : ''" />
                    <span>{{ isRefreshing ? 'جاري التحديث...' : 'تحديث من المصدر الآن' }}</span>
                </button>

                <button
                    type="button"
                    class="rounded-xl bg-slate-200 dark:bg-slate-700 px-4 py-2 text-xs font-bold text-slate-800 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition"
                    @click="emit('close')"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </Modal>
</template>
