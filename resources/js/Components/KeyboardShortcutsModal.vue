<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close']);

const shortcuts = [
    {
        category: 'نوع العملية',
        items: [
            { keys: ['Alt', '1'], altKeys: ['F1'], description: 'تحديد اتجاه العملية: بيع' },
            { keys: ['Alt', '2'], altKeys: ['F2'], description: 'تحديد اتجاه العملية: شراء' },
        ],
    },
    {
        category: 'التنقل بين الحقول',
        items: [
            { keys: ['Alt', 'A'], description: 'التركيز على حقل المبلغ بالعملة الأجنبية' },
            { keys: ['Alt', 'T'], description: 'التركيز على حقل الإجمالي بالليرة (الحاسبة العكسية)' },
            { keys: ['Alt', 'R'], description: 'التركيز على حقل سعر الصرف' },
            { keys: ['Alt', 'C'], description: 'التركيز على اختيار العملة' },
        ],
    },
    {
        category: 'الإجراءات والحفظ',
        items: [
            { keys: ['Enter'], altKeys: ['Ctrl', 'Enter'], description: 'تسجيل العملية وحفظها مباشرة' },
            { keys: ['Esc'], description: 'مسح الحقول وتفريغ النموذج' },
            { keys: ['Alt', 'E'], description: 'التبديل بين الوضع السهل والوضع المفصل' },
            { keys: ['?'], description: 'إظهار نافذة اختصارات لوحة المفاتيح' },
        ],
    },
];
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-5 sm:p-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">اختصارات لوحة المفاتيح</h3>
                        <p class="text-xs text-gray-500 dark:text-slate-400">لتسريع إدخال العمليات بدون استخدام الفأرة</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                    @click="$emit('close')"
                    aria-label="إغلاق"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <div v-for="section in shortcuts" :key="section.category" class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500">
                        {{ section.category }}
                    </h4>
                    <div class="divide-y divide-gray-100 rounded-lg border border-gray-100 bg-gray-50/50 text-xs dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900/30">
                        <div
                            v-for="(item, idx) in section.items"
                            :key="idx"
                            class="flex items-center justify-between p-2.5"
                        >
                            <span class="text-gray-700 dark:text-slate-300">{{ item.description }}</span>
                            <div class="flex items-center gap-1.5" dir="ltr">
                                <div class="flex items-center gap-1">
                                    <kbd
                                        v-for="k in item.keys"
                                        :key="k"
                                        class="rounded border border-gray-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        {{ k }}
                                    </kbd>
                                </div>
                                <template v-if="item.altKeys">
                                    <span class="text-[10px] text-gray-400">أو</span>
                                    <div class="flex items-center gap-1">
                                        <kbd
                                            v-for="k in item.altKeys"
                                            :key="k"
                                            class="rounded border border-gray-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                        >
                                            {{ k }}
                                        </kbd>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 border-t border-gray-100 pt-3 text-center text-xs text-gray-400 dark:border-slate-700 dark:text-slate-500">
                يمكنك الضغط على <kbd class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] font-mono dark:border-slate-700 dark:bg-slate-800">Esc</kbd> لإغلاق هذه النافذة في أي وقت.
            </div>
        </div>
    </Modal>
</template>
