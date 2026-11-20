import type { AppPageProps, NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useFeatures() {
    const page = usePage<AppPageProps>();
    const isEnabled = (key: string): boolean => page.props.features?.[key] === 'enabled';
    const isVisibleInNavigation = (key: string): boolean => isEnabled(key) || page.props.features?.[key] === 'maintenance';
    const filterNavigation = (items: NavItem[]): NavItem[] => items.filter((item) => !item.feature || isVisibleInNavigation(item.feature));
    const appEntryUrl = computed(() => page.props.appEntryUrl);

    return { isEnabled, isVisibleInNavigation, filterNavigation, appEntryUrl };
}
