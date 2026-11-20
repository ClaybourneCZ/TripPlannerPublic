<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { useFeatures } from '@/composables/useFeatures';
import { Link } from '@inertiajs/vue3';
const { isEnabled } = useFeatures();

interface BreadcrumbItemType {
    title: string;
    href?: string;
    feature?: string;
}

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1 || (item.feature && !isEnabled(item.feature))">
                        <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href ?? '#'">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
