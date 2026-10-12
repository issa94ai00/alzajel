<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import NavIcon from '@/Components/NavIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    navGroups: { type: Array, required: true },
});

const emit = defineEmits(['close']);

const page = usePage();
const searchQuery = ref('');
const searchInput = ref(null);

watch(
    () => props.show,
    async (val) => {
        if (val) {
            searchQuery.value = '';
            await nextTick();
            searchInput.value?.focus();
        }
    },
);

const allItems = computed(() => {
    const items = [];
    const descriptions = {
        'dashboard': 'لوحة التحكم، إحصائيات النشاط، وحالة الصناديق',
        'transactions.create': 'تسجيل عملية صرف، بيع أو شراء عملات',
        'transactions.index': 'سجل وسوابق جميع عمليات الصرف المنفذة',
        'shifts.index': 'سجل وإدارة الورديات ومطابقة الصناديق',
        'reports.*': 'تقارير الأرباح والكشوفات وحركات النقدية',
        'rates.index': 'عرض وتحديث أسعار الصرف للعملات المختلفة',
        'currencies.*': 'إدارة العملات المدعومة والفئات النقدية',
        'users.*': 'إدارة حسابات المستخدمين والصرافين والصلاحيات',
        'settings.*': 'إعدادات النظام العامة والإيصالات والطباعة',
    };

    const colors = {
        'home': 'from-indigo-500 to-indigo-700 text-white shadow-indigo-500/25',
        'plus-circle': 'from-emerald-500 to-emerald-700 text-white shadow-emerald-500/25',
        'receipt': 'from-blue-500 to-blue-700 text-white shadow-blue-500/25',
        'clock': 'from-amber-500 to-amber-700 text-white shadow-amber-500/25',
        'chart-bar': 'from-purple-500 to-purple-700 text-white shadow-purple-500/25',
        'currency': 'from-teal-500 to-teal-700 text-white shadow-teal-500/25',
        'coins': 'from-orange-500 to-orange-700 text-white shadow-orange-500/25',
        'users': 'from-rose-500 to-rose-700 text-white shadow-rose-500/25',
        'settings': 'from-slate-600 to-slate-800 text-white shadow-slate-600/25',
    };

    props.navGroups.forEach((group) => {
        if (group.links) {
            group.links.forEach((link) => {
                items.push({
                    groupLabel: group.label,
                    label: link.label,
                    href: link.href,
                    icon: link.icon,
                    current: link.current,
                    badge: link.badge,
                    description: descriptions[link.current] || 'الانتقال إلى ' + link.label,
                    colorClass: colors[link.icon] || 'from-indigo-500 to-indigo-700 text-white shadow-indigo-500/25',
                });
            });
        }
    });

    return items;
});

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return allItems.value;
    return allItems.value.filter(
        (item) =>
            item.label.toLowerCase().includes(q) ||
            item.description.toLowerCase().includes(q) ||
            (item.groupLabel && item.groupLabel.toLowerCase().includes(q)),
    );
});

function handleSelect() {
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-5 sm:p-6 dark:bg-slate-900">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 text-white shadow-md shadow-indigo-600/30">
                        <NavIcon name="grid" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">لوحة الأيقونات والتنقل السريع</h3>
                        <p class="text-xs text-gray-500 dark:text-slate-400">انقر على أي أيقونة للانتقال الفوري إلى قسم النظام</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800 dark:hover:text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="إغلاق"
                    @click="emit('close')"
                >
                    <NavIcon name="close" class="h-5 w-5" />
                </button>
            </div>

            <!-- Search Filter Bar -->
            <div class="mt-4">
                <div class="relative">
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        placeholder="ابحث بالاسم أو الوصف (مثال: عملية، تقارير، عملات)..."
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800/80 dark:text-white dark:placeholder:text-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                    />
                    <span
                        v-if="searchQuery"
                        class="absolute end-3 top-1/2 -translate-y-1/2 cursor-pointer text-xs font-bold text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        @click="searchQuery = ''"
                    >
                        مسح
                    </span>
                </div>
            </div>

            <!-- Icons Grid -->
            <div class="mt-5 max-h-[60vh] overflow-y-auto pe-1">
                <div
                    v-if="filteredItems.length === 0"
                    class="py-12 text-center text-sm text-gray-500 dark:text-slate-400"
                >
                    لا توجد أقسام مطابقة للبحث "{{ searchQuery }}"
                </div>

                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="item in filteredItems"
                        :key="item.label"
                        :href="item.href"
                        class="group relative flex items-start gap-3.5 rounded-2xl border p-3.5 transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        :class="[
                            route().current(item.current)
                                ? 'border-indigo-500/50 bg-indigo-50/70 shadow-xs dark:border-indigo-500/40 dark:bg-indigo-950/30'
                                : 'border-gray-200/80 bg-white hover:border-indigo-300 hover:bg-slate-50/80 hover:shadow-sm dark:border-slate-800 dark:bg-slate-800/50 dark:hover:border-slate-700 dark:hover:bg-slate-800',
                        ]"
                        @click="handleSelect"
                    >
                        <!-- Large Colorful Icon Box -->
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br shadow-md transition-transform duration-150 group-hover:scale-110"
                            :class="item.colorClass"
                        >
                            <NavIcon :name="item.icon" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-bold text-gray-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400 transition-colors">
                                    {{ item.label }}
                                </span>
                                <span
                                    v-if="item.badge"
                                    class="rounded-full bg-indigo-500/20 px-1.5 py-0.2 text-[9px] font-bold text-indigo-700 dark:text-indigo-300"
                                >
                                    {{ item.badge }}
                                </span>
                                <span
                                    v-if="route().current(item.current)"
                                    class="ms-auto flex h-2 w-2 rounded-full bg-indigo-500"
                                    title="الصفحة الحالية"
                                ></span>
                            </div>

                            <p class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-slate-400">
                                {{ item.description }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="mt-5 flex items-center justify-between border-t border-gray-100 pt-3 text-xs text-gray-400 dark:border-slate-800 dark:text-slate-500">
                <div class="flex items-center gap-1.5">
                    <span>اختصار الفتح:</span>
                    <kbd class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono text-[11px] text-gray-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Alt + G</kbd>
                </div>
                <div>
                    <span>اضغط </span>
                    <kbd class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono text-[11px] text-gray-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Esc</kbd>
                    <span> للإغلاق</span>
                </div>
            </div>
        </div>
    </Modal>
</template>
