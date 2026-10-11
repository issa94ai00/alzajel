<script setup>
import { computed } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    // صلاحيات موروثة من الأدوار — تُعرض مؤشَّرة ومعطّلة لتوضيح مصدرها.
    inherited: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => new Set(props.modelValue));
const inheritedSet = computed(() => new Set(props.inherited));

function isChecked(name) {
    return selected.value.has(name) || inheritedSet.value.has(name);
}

function isLocked(name) {
    return props.disabled || inheritedSet.value.has(name);
}

function toggle(name) {
    if (isLocked(name)) return;

    const next = new Set(props.modelValue);
    next.has(name) ? next.delete(name) : next.add(name);

    emit('update:modelValue', [...next]);
}

function groupState(group) {
    const names = group.permissions.map((p) => p.name).filter((n) => !inheritedSet.value.has(n));

    if (names.length === 0) return 'all';
    const on = names.filter((n) => selected.value.has(n)).length;

    return on === 0 ? 'none' : on === names.length ? 'all' : 'some';
}

function toggleGroup(group) {
    if (props.disabled) return;

    const names = group.permissions.map((p) => p.name).filter((n) => !inheritedSet.value.has(n));
    const next = new Set(props.modelValue);

    if (groupState(group) === 'all') {
        names.forEach((n) => next.delete(n));
    } else {
        names.forEach((n) => next.add(n));
    }

    emit('update:modelValue', [...next]);
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <section
            v-for="group in groups"
            :key="group.key"
            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40"
        >
            <header class="mb-3 flex items-center justify-between gap-2 border-b border-slate-100 pb-2 dark:border-slate-700">
                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ group.label }}</h4>
                <button
                    v-if="!disabled"
                    type="button"
                    class="text-xs font-medium text-indigo-600 hover:underline disabled:opacity-40 dark:text-indigo-400"
                    @click="toggleGroup(group)"
                >
                    {{ groupState(group) === 'all' ? 'إلغاء الكل' : 'تحديد الكل' }}
                </button>
            </header>

            <ul class="space-y-2">
                <li v-for="permission in group.permissions" :key="permission.name">
                    <label
                        class="flex items-start gap-2 text-sm"
                        :class="isLocked(permission.name) ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'"
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                            :checked="isChecked(permission.name)"
                            :disabled="isLocked(permission.name)"
                            @change="toggle(permission.name)"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block text-slate-700 dark:text-slate-200">{{ permission.label }}</span>
                            <span class="block font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ permission.name }}</span>
                        </span>
                        <span
                            v-if="inheritedSet.has(permission.name)"
                            class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                        >
                            من الدور
                        </span>
                    </label>
                </li>
            </ul>
        </section>
    </div>
</template>
