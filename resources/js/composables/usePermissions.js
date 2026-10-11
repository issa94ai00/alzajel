import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * بوابة الصلاحيات في الواجهة.
 *
 * تعكس ما يفرضه الخادم بالضبط: القائمة تصل مشتركة عبر auth.permissions،
 * والمدير الأعلى يملكها كلها. الإخفاء هنا للراحة فقط — الحماية الحقيقية على الخادم.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.permissions ?? []);
    const roles = computed(() => page.props.auth?.roles ?? []);
    const isAdmin = computed(() => page.props.auth?.isAdmin ?? false);

    /** يملك صلاحية واحدة على الأقل من المذكورة. */
    function can(...names) {
        return names.flat().some((name) => permissions.value.includes(name));
    }

    /** يملك كل الصلاحيات المذكورة. */
    function canAll(...names) {
        return names.flat().every((name) => permissions.value.includes(name));
    }

    function hasRole(name) {
        return roles.value.includes(name);
    }

    return { can, canAll, hasRole, permissions, roles, isAdmin };
}
