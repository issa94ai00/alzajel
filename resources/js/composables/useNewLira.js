import { usePage } from '@inertiajs/vue3';

/**
 * الليرة السورية الجديدة: حُذف صفران من العملة القديمة.
 *
 * كل ما يتعلق بإعادة التسمية مجمّع هنا، حتى إذا انتهت فترة الانتقال يكفي
 * إطفاء الإعداد currency.new_lira_notice بدل تتبّع النصوص في الصفحات.
 */

/** 100 ليرة قديمة = 1 ليرة جديدة. */
export const OLD_TO_NEW_FACTOR = 100;

/** رمز العملة التي طالتها إعادة التسمية. */
export const NEW_LIRA_CODE = 'SYP';

export function useNewLira() {
    const page = usePage();

    const settings = () => page.props?.settings ?? {};

    /** هل هذه العملة هي الليرة السورية الجديدة؟ */
    function isNewLira(currency) {
        const code = typeof currency === 'string' ? currency : currency?.code;

        return String(code ?? '').toUpperCase() === NEW_LIRA_CODE;
    }

    /** هل يعرض النظام إرشادات الانتقال أصلاً؟ */
    function noticeEnabled() {
        return settings().new_lira_notice !== false;
    }

    /** العتبة التي يُعدّ المبلغ فوقها مشبوهاً (0 = التحذير مُعطّل). */
    function warnAbove() {
        const value = Number(settings().old_lira_warn_above ?? 0);

        return Number.isFinite(value) && value > 0 ? value : 0;
    }

    /** ما يعادل المبلغ بالليرة القديمة. */
    function toOldLira(amount) {
        const value = Number(amount);

        if (!Number.isFinite(value) || value === 0) return null;

        return value * OLD_TO_NEW_FACTOR;
    }

    /** القيمة المرجّحة إذا كان الصرّاف قد أدخل المبلغ بالعملة القديمة. */
    function asIfOldLira(amount) {
        const value = Number(amount);

        if (!Number.isFinite(value) || value === 0) return null;

        return value / OLD_TO_NEW_FACTOR;
    }

    /**
     * مبلغ كبير بالليرة الجديدة يوحي بأنه أُدخل بالعملة القديمة سهواً.
     * خطأ من هذا النوع يضاعف القيمة 100 مرة في نظام مالي.
     */
    function looksLikeOldLira(amount, currency) {
        const threshold = warnAbove();

        if (!threshold || !isNewLira(currency)) return false;

        const value = Number(amount);

        return Number.isFinite(value) && value >= threshold;
    }

    function format(value, locale) {
        return new Intl.NumberFormat(locale ?? settings().locale ?? 'ar-SY', {
            maximumFractionDigits: 2,
        }).format(value);
    }

    /** صياغة المعادل القديم للعرض، أو null إذا لا يوجد مبلغ صالح. */
    function formatOldLira(amount, locale) {
        const old = toOldLira(amount);

        return old === null ? null : format(old, locale);
    }

    return {
        isNewLira,
        noticeEnabled,
        warnAbove,
        toOldLira,
        asIfOldLira,
        looksLikeOldLira,
        formatOldLira,
        format,
        OLD_TO_NEW_FACTOR,
        NEW_LIRA_CODE,
    };
}
