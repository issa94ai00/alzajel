import { ref, watchEffect } from 'vue';

const isDark = ref(document.documentElement.classList.contains('dark'));

watchEffect(() => {
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
});

export function useDarkMode() {
    function toggle() {
        isDark.value = !isDark.value;
    }

    return { isDark, toggle };
}
