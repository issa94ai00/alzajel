import { useTheme } from './useTheme';

// واجهة قديمة مبقاة للتوافق؛ الحالة الفعلية في useTheme.
export function useDarkMode() {
    const { isDark, toggleDark } = useTheme();

    return { isDark, toggle: toggleDark };
}
