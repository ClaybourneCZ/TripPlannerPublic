<script setup lang="ts">
import FeatureAction from '@/components/FeatureAction.vue';
import MapComponentRecommendation from '@/components/maps/MapComponentRecommendation.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Download, Globe2, Map, MapPin, Plus, Route } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        feature: 'dashboard',
        href: '/dashboard',
    },
];

interface TripCard {
    id: number;
    name: string;
    notes: string | null;
    distance: number | null;
    duration: number | null;
    points: [number, number][];
    stops: string[];
}
const props = defineProps<{
    trips: { data: TripCard[]; prev_page_url: string | null; next_page_url: string | null };
    notice: string | null;
}>();
const formatDistance = (meters: number | null) => (meters === null ? 'Distance unknown' : `${(meters / 1000).toFixed(1)} km`);
const formatDuration = (seconds: number | null) => (seconds === null ? 'Duration unknown' : `${Math.round(seconds / 60)} min`);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <p v-if="notice" role="status" class="rounded-md border p-3 text-sm">{{ notice }}</p>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative flex aspect-video flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs transition hover:border-primary/40 hover:shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">New itinerary</p>
                            <h2 class="mt-2 text-xl font-semibold tracking-normal text-foreground">Create Trip</h2>
                            <p class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                                Start a route plan, add stops, and shape the trip on the map.
                            </p>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-md border border-sidebar-border/70 bg-muted text-muted-foreground"
                        >
                            <Route class="size-5" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Map class="size-4" />
                            <span>Route planner</span>
                        </div>
                        <FeatureAction feature="plan" :href="route('trips.create')">
                            <Plus class="size-4" />
                            Create
                        </FeatureAction>
                    </div>
                </div>
                <div
                    class="flex aspect-video flex-col justify-between gap-3 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs dark:border-sidebar-border"
                >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-muted-foreground">Share your route</p>
                            <Globe2 class="size-5 text-muted-foreground" />
                        </div>
                        <h2 class="mt-2 text-xl font-semibold tracking-normal text-foreground">Publish a trip</h2>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">Choose one of your trips to share with other travelers.</p>
                    </div>

                    <FeatureAction feature="publish" class="w-fit" :href="route('dashboard.publish')">
                        <Globe2 class="size-4" />
                        Publish a trip
                    </FeatureAction>
                </div>
                <div
                    class="flex min-h-[220px] flex-col justify-between gap-3 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs dark:border-sidebar-border"
                >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-muted-foreground">Take it with you</p>
                            <Download class="size-5 text-muted-foreground" />
                        </div>
                        <h2 class="mt-2 text-xl font-semibold tracking-normal text-foreground">Export a trip</h2>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">Choose a trip, file format, and map app on the export page.</p>
                    </div>
                    <FeatureAction feature="export" class="w-fit" :href="route('dashboard.export')">
                        <Download class="size-4" />
                        Export trip
                    </FeatureAction>
                </div>
            </div>
            <div class="flex flex-1 flex-col gap-6 rounded-xl border border-sidebar-border/70 p-5 md:min-h-min dark:border-sidebar-border">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">My Trips</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-normal text-foreground">Your saved routes</h1>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Continue planning one of your saved routes with its stops and map preview ready to go.
                    </p>
                </div>

                <div v-if="!props.trips.data.length" class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                    No saved trips yet.
                    <FeatureAction feature="plan" variant="link" :href="route('trips.create')">Create your first trip</FeatureAction>.
                </div>
                <div v-else class="grid gap-4 xl:grid-cols-3">
                    <article
                        v-for="trip in props.trips.data"
                        :key="trip.id"
                        class="grid min-h-[300px] overflow-hidden rounded-lg border border-sidebar-border/70 bg-card shadow-sm md:grid-cols-[minmax(0,1fr)_220px] xl:grid-cols-1 dark:border-sidebar-border"
                    >
                        <div class="flex flex-col gap-4 p-5">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-card-foreground">{{ trip.name }}</h2>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ trip.notes }}</p>
                            </div>

                            <div class="mt-auto flex flex-wrap gap-2 text-sm text-muted-foreground">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1 dark:border-sidebar-border"
                                >
                                    <Route class="size-4" />
                                    {{ formatDistance(trip.distance) }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1 dark:border-sidebar-border"
                                >
                                    <MapPin class="size-4" />
                                    {{ formatDuration(trip.duration) }}
                                </span>
                            </div>

                            <div class="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap">
                                <span
                                    v-for="stop in trip.stops.slice(0, 3)"
                                    :key="stop"
                                    class="min-w-0 truncate rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ stop }}
                                </span>
                                <span v-if="trip.stops.length > 3" class="shrink-0 text-xs font-medium text-muted-foreground">
                                    +{{ trip.stops.length - 3 }} stops
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <FeatureAction feature="plan" :href="route('trips.edit', trip.id)">Open trip</FeatureAction>
                                <FeatureAction feature="notes" variant="outline" :href="route('notes.index', { trip: trip.id })"
                                    >Modify notes</FeatureAction
                                >
                            </div>
                        </div>

                        <div
                            class="relative min-h-[180px] border-t border-sidebar-border/70 md:border-t-0 md:border-l xl:border-t xl:border-l-0 dark:border-sidebar-border"
                        >
                            <MapComponentRecommendation :model-value="trip.points" height="100%" width="100%" />
                        </div>
                    </article>
                </div>
                <nav class="flex gap-4 text-sm" aria-label="Trip pages">
                    <Link v-if="props.trips.prev_page_url" :href="props.trips.prev_page_url" class="underline">Previous</Link>
                    <Link v-if="props.trips.next_page_url" :href="props.trips.next_page_url" class="underline">Next</Link>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
