<script setup>
import Modal from '@/Components/Modal.vue';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * حوار تأكيد موحّد بدل confirm() الأصلي للمتصفح.
 *
 * حوار المتصفح لا يُنسّق ولا يحترم اتجاه RTL، ويعرض سطراً واحداً لا يتسع
 * لعواقب الإجراء — والمستخدم يضغط "موافق" دون أن يعرف ما الذي سيُقفل.
 * هنا يتّسع المكان لملخّص فعلي (فتحة details)، ويبقى زر الإلغاء هو المركّز
 * افتراضياً في الإجراءات الخطرة حتى لا يؤكّد أحد بضغطة Enter عابرة.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'تأكيد' },
    cancelLabel: { type: String, default: 'إلغاء' },
    tone: { type: String, default: 'danger' }, // danger | warning | primary
    processing: { type: Boolean, default: false },
    // تحذير يظهر أسفل الملخّص — لِما لا رجعة فيه.
    irreversible: { type: Boolean, default: false },
    /*
     * نص يجب كتابته حرفياً قبل تفعيل زر التأكيد. يُستخدم للإجراءات التي
     * تُعيد بناء بيانات واسعة: الضغط المتتابع وحده لا يكفي للموافقة عليها.
     */
    requireText: { type: String, default: null },
    requireTextHint: { type: String, default: 'للتأكيد، اكتب' },
});

const emit = defineEmits(['confirm', 'cancel']);

const cancelButton = ref(null);
const confirmButton = ref(null);
const textInput = ref(null);
const typed = ref('');

const textConfirmed = computed(() => !props.requireText || typed.value.trim() === props.requireText);

const TONES = {
    danger: {
        ring: 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400',
        button: 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500',
    },
    warning: {
        ring: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400',
        button: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
    },
    primary: {
        ring: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400',
        button: 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500',
    },
};

const tone = computed(() => TONES[props.tone] ?? TONES.danger);

/** الخطر يُركّز الإلغاء، وغيره يُركّز التأكيد — التركيز الافتراضي قرار أمان. */
watch(
    () => props.show,
    (open) => {
        if (!open) return;

        typed.value = '';

        nextTick(() => {
            if (props.requireText) {
                textInput.value?.focus();

                return;
            }

            const target = props.tone === 'primary' ? confirmButton.value : cancelButton.value;

            target?.focus();
        });
    },
);

function cancel() {
    if (props.processing) return;

    emit('cancel');
}
</script>

<template>
    <Modal :show="show" max-width="lg" :closeable="!processing" @close="cancel">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full" :class="tone.ring">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                        />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                    <p v-if="message" class="mt-1 text-sm text-gray-600 dark:text-slate-300">{{ message }}</p>
                </div>
            </div>

            <!-- الملخّص: ما الذي يُثبَّت فعلاً بهذا التأكيد. -->
            <div v-if="$slots.details" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-slate-600 dark:bg-slate-900/40">
                <slot name="details" />
            </div>

            <p
                v-if="irreversible"
                class="mt-3 flex items-center gap-2 rounded-md bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 dark:bg-rose-900/25 dark:text-rose-200"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                لا يمكن التراجع عن هذا الإجراء.
            </p>

            <div v-if="requireText" class="mt-4">
                <label :for="`confirm-text-${requireText}`" class="block text-xs text-gray-600 dark:text-slate-300">
                    {{ requireTextHint }}
                    <span class="font-mono font-bold text-gray-900 dark:text-white">{{ requireText }}</span>
                </label>
                <input
                    :id="`confirm-text-${requireText}`"
                    ref="textInput"
                    v-model="typed"
                    type="text"
                    autocomplete="off"
                    dir="ltr"
                    :disabled="processing"
                    class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                    @keyup.enter="textConfirmed && emit('confirm')"
                />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    ref="cancelButton"
                    type="button"
                    :disabled="processing"
                    @click="cancel"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-800"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    ref="confirmButton"
                    type="button"
                    :disabled="processing || !textConfirmed"
                    @click="emit('confirm')"
                    class="rounded-md px-4 py-2 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-slate-800"
                    :class="tone.button"
                >
                    {{ processing ? 'جارٍ التنفيذ…' : confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
