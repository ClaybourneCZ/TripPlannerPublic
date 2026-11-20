<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useFeatures } from '@/composables/useFeatures';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ featureName: string }>();
const { appEntryUrl } = useFeatures();
</script>

<template>
    <Head title="Under maintenance" />
    <AppLayout>
        <main class="mx-auto flex max-w-xl flex-col items-start gap-4 p-8">
            <p class="text-sm text-muted-foreground">{{ featureName }}</p>
            <h1 class="text-3xl font-semibold">Under maintenance</h1>
            <p>This page is temporarily unavailable. Please try again later.</p>
            <Button as-child><Link :href="appEntryUrl">Back to available pages</Link></Button>
        </main>
    </AppLayout>
</template>
