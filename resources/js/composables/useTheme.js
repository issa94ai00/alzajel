import { computed, ref, watch } from 'vue';

// المظهر: light | dark | system، ولون التمييز يُطبَّق عبر data-accent على <html>.
// السكربت المضمّن في app.blade.php يطبّق القيم نفسها قبل تحميل Vue لتجنّب الوميض.
export const ACCENTS = [
    { key: 'indigo', label: 'نيلي', swatch: '#4f46e5' },
    { key: 'blue', label: 'أزرق', swatch: '#2563eb' },
    { key: 'teal', label: 'فيروزي', swatch: '#0d9488' },
    { key: 'purple', label: 'بنفسجي', swatch: '#9333ea' },
];

export const MODES = [
    { key: 'light', label: 'فاتح', icon: 'sun' },
    { key: 'dark', label: 'داكن', icon: 'moon' },
    { key: 'system', label: 'تلقائي', icon: 'monitor' },
];

function read(key, allowed, fallback) {
    try {
        const value = localStorage.getItem(key);
        return allowed.includes(value) ? value : fallback;
    } catch {
        return fallback;
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // التخزين غير متاح (وضع التصفح الخاص) — يبقى الاختيار للجلسة الحالية فقط
    }
}

const media = window.matchMedia('(prefers-color-scheme: dark)');
const systemDark = ref(media.matches);
media.addEventListener('change', (e) => (systemDark.value = e.matches));

const mode = ref(read('theme', MODES.map((m) => m.key), 'system'));
const accent = ref(read('theme-accent', ACCENTS.map((a) => a.key), 'indigo'));

const isDark = computed(() => (mode.value === 'system' ? systemDark.value : mode.value === 'dark'));

watch(
    isDark,
    (dark) => {
        document.documentElement.classList.toggle('dark', dark);
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#0f172a' : '#ffffff');
    },
    { immediate: true },
);

watch(mode, (value) => write('theme', value));

watch(
    accent,
    (value) => {
        document.documentElement.dataset.accent = value;
        write('theme-accent', value);
    },
    { immediate: true },
);

export function useTheme() {
    function toggleDark() {
        mode.value = isDark.value ? 'light' : 'dark';
    }

    return { mode, accent, isDark, toggleDark };
}
