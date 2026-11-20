<script setup lang="ts">
import FeatureAction from '@/components/FeatureAction.vue';
import { useFeatures } from '@/composables/useFeatures';
import { tripSamples } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Globe2, MapPin, Route } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
const { isEnabled } = useFeatures();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', feature: 'dashboard', href: '/dashboard' },
    { title: 'Publish a trip', href: '/dashboard/publish' },
];

const selectedTripId = ref(tripSamples[0]?.id ?? null);
const publishedTripIds = ref<number[]>([]);
const selectedTrip = computed(() => tripSamples.find((trip) => trip.id === selectedTripId.value));
const isSelectedTripPublished = computed(() => selectedTripId.value !== null && publishedTripIds.value.includes(selectedTripId.value));

onMounted(() => {
    const savedPublishedTripIds = window.localStorage.getItem('publishedTripIds');
    if (savedPublishedTripIds) {
        try {
            publishedTripIds.value = JSON.parse(savedPublishedTripIds) as number[];
        } catch {
            publishedTripIds.value = [];
        }
    }
});

const togglePublished = () => {
    const tripId = selectedTripId.value;
    if (!isEnabled('publish') || tripId === null) return;

    publishedTripIds.value = isSelectedTripPublished.value
        ? publishedTripIds.value.filter((id) => id !== tripId)
        : [...publishedTripIds.value, tripId];
    window.localStorage.setItem('publishedTripIds', JSON.stringify(publishedTripIds.value));
};
</script>

<template>
    <Head title="Publish a trip" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-8">
            <div>
                <FeatureAction feature="dashboard" variant="ghost" size="sm" class="mb-3 -ml-3" :href="route('dashboard')"
                    ><ArrowLeft class="size-4" />Back to dashboard</FeatureAction
                >
                <p class="text-sm font-medium text-muted-foreground">Share your route</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Publish a trip</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                    Choose one of your saved trips to make it available to other travelers.
                </p>
            </div>

            <section class="grid gap-6 md:grid-cols-[minmax(0,1fr)_280px]">
                <div
                    class="flex flex-col gap-5 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs md:p-6 dark:border-sidebar-border"
                >
                    <div>
                        <label for="publish-trip" class="text-sm font-semibold">Choose a trip</label>
                        <p class="mt-1 text-sm text-muted-foreground">Select the itinerary you want to share.</p>
                        <select
                            id="publish-trip"
                            v-model="selectedTripId"
                            class="mt-3 w-full rounded-md border border-input bg-background px-3 py-2.5 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option v-for="trip in tripSamples" :key="trip.id" :value="trip.id">{{ trip.name }}</option>
                        </select>
                    </div>

                    <div v-if="selectedTrip" class="rounded-lg bg-muted/50 p-4">
                        <h2 class="font-semibold">{{ selectedTrip.name }}</h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">{{ selectedTrip.notes }}</p>
                        <div class="mt-4 flex flex-wrap gap-2 text-sm text-muted-foreground">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 bg-background px-2.5 py-1.5 dark:border-sidebar-border"
                            >
                                <Route class="size-4" />{{ selectedTrip.distance }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 bg-background px-2.5 py-1.5 dark:border-sidebar-border"
                            >
                                <MapPin class="size-4" />{{ selectedTrip.waypoints.length }} stops
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-sidebar-border/70 pt-5 dark:border-sidebar-border">
                        <FeatureAction
                            feature="publish"
                            :variant="isSelectedTripPublished ? 'outline' : 'default'"
                            :disabled="!selectedTrip"
                            @click="togglePublished"
                        >
                            <Globe2 class="size-4" />{{ isSelectedTripPublished ? 'Unpublish trip' : 'Publish trip' }}
                        </FeatureAction>
                        <p v-if="selectedTrip" class="text-sm text-muted-foreground">
                            {{ isSelectedTripPublished ? `${selectedTrip.name} is published.` : 'Only the selected trip will be published.' }}
                        </p>
                    </div>
                </div>

                <aside class="h-fit rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs dark:border-sidebar-border">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground"><Globe2 class="size-5" /></div>
                    <h2 class="mt-4 font-semibold">Share your itinerary</h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        Published trips are marked as available for other travelers. You can unpublish a trip here at any time.
                    </p>
                </aside>
            </section>
        </main>
    </AppLayout>
</template>
