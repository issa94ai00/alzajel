import { usePage } from '@inertiajs/vue3';

/**
 * أدنى قيمة يبقى فيها التقريب إلى `decimals` أميناً على الرقم.
 *
 * عند خانتين الحدّ هو 0.1: فوقه يحتفظ السعر برقمين معنويين بعد التقريب
 * (0.2646 → 0.26)، وتحته ينهار إلى رقم معنوي واحد (0.0074 → 0.01) فيتساوى
 * الشراء مع البيع بصرياً — وهو ما يجعل الرقم غير مقروء لا مقروءاً.
 */
function readableFloor(decimals) {
    return Math.pow(10, 1 - decimals);
}

/**
 * تنسيق قيم التقارير والإيصالات حسب إعدادات العرض التي يضبطها المدير.
 */
export function useFormat() {
    const page = usePage();

    const settings = () => page.props.settings ?? {};

    function number(value) {
        if (value === null || value === undefined || value === '') return '—';

        const { decimals = 2, thousands_separator: grouping = true, locale = 'ar-SY' } = settings();

        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
            useGrouping: grouping,
        }).format(Number(value));
    }

    /**
     * السعر نسبة لا مبلغ، لكنه يُعرض بخانتين مثل بقية الأرقام لسهولة القراءة.
     * الاستثناء الوحيد هو السعر الصغير جداً — الناتج عن قلب سعر عملة قوية عند
     * تبديل العملة الأساس — إذ يمحوه التقريب فتبقى خاناته المعنوية.
     */
    function rate(value) {
        if (value === null || value === undefined || value === '') return '—';

        const parsed = Number(value);

        if (!Number.isFinite(parsed)) return '—';

        const { decimals = 2, thousands_separator: grouping = true, locale = 'ar-SY' } = settings();

        if (parsed !== 0 && Math.abs(parsed) < readableFloor(decimals)) {
            return new Intl.NumberFormat(locale, {
                maximumSignificantDigits: 6,
                useGrouping: grouping,
            }).format(parsed);
        }

        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
            useGrouping: grouping,
        }).format(parsed);
    }

    function integer(value) {
        if (value === null || value === undefined || value === '') return '—';

        return new Intl.NumberFormat(settings().locale ?? 'ar-SY').format(Number(value));
    }

    function date(value) {
        if (!value) return '—';

        return new Date(value).toLocaleDateString(settings().locale ?? 'ar-SY');
    }

    function dateTime(value) {
        if (!value) return '—';

        return new Date(value).toLocaleString(settings().locale ?? 'ar-SY', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    /** تنسيق خلية تقرير حسب نوع العمود القادم من الخادم. */
    function cell(value, format = 'text') {
        if (value === null || value === undefined || value === '') return '—';

        switch (format) {
            case 'number':
            case 'signed':
                return number(value);
            case 'rate':
                return rate(value);
            case 'integer':
                return integer(value);
            case 'date':
                return date(value);
            case 'datetime':
                return dateTime(value);
            case 'type':
                return value === 'buy' ? 'شراء' : value === 'sell' ? 'بيع' : value;
            default:
                return String(value);
        }
    }

    return { number, rate, integer, date, dateTime, cell, settings };
}
