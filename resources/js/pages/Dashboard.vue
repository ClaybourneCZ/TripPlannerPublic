<script setup lang="ts">
import MapComponentRecommendation from '@/components/maps/MapComponentRecommendation.vue';
import { Button } from '@/components/ui/button';
import { tripSamples } from '@/data/tripSamples';
import { downloadTripFile, getTripMapAppUrl, type TripExportFormat, type TripMapApp } from '@/services/tripExport';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Download, Globe2, Map, MapPin, Plus, Route } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const myTrips = tripSamples.map((trip) => ({
    ...trip,
    points: trip.waypoints.map((waypoint) => waypoint.point),
    stops: trip.waypoints.map((waypoint) => waypoint.name ?? 'Unnamed stop'),
}));

const selectedTripId = ref(myTrips[0]?.id ?? null);
const exportTripId = ref(myTrips[0]?.id ?? null);
const publishedTripIds = ref<number[]>([]);
const selectedTrip = computed(() => myTrips.find((trip) => trip.id === selectedTripId.value));
const exportTrip = computed(() => myTrips.find((trip) => trip.id === exportTripId.value));
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
    if (tripId === null) return;

    publishedTripIds.value = isSelectedTripPublished.value
        ? publishedTripIds.value.filter((id) => id !== tripId)
        : [...publishedTripIds.value, tripId];
    window.localStorage.setItem('publishedTripIds', JSON.stringify(publishedTripIds.value));
};

const downloadTrip = (format: TripExportFormat) => {
    const trip = exportTrip.value;
    if (!trip) return;
    downloadTripFile(trip, format);
};

const mapAppUrl = (app: TripMapApp) => (exportTrip.value ? getTripMapAppUrl(exportTrip.value, app) : '#');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
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
                        <Button as-child>
                            <Link :href="route('plans.index')">
                                <Plus class="size-4" />
                                Create
                            </Link>
                        </Button>
                    </div>
                </div>
                <div class="flex aspect-video flex-col justify-between gap-3 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs dark:border-sidebar-border">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-muted-foreground">Share your route</p>
                            <Globe2 class="size-5 text-muted-foreground" />
                        </div>
                        <h2 class="mt-2 text-xl font-semibold tracking-normal text-foreground">Publish a trip</h2>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">Choose one of your trips to share with other travelers.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedTripId"
                            aria-label="Trip to publish"
                            class="min-w-0 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option v-for="trip in myTrips" :key="trip.id" :value="trip.id">{{ trip.name }}</option>
                        </select>
                        <Button :variant="isSelectedTripPublished ? 'outline' : 'default'" :disabled="!selectedTrip" @click="togglePublished">
                            {{ isSelectedTripPublished ? 'Unpublish' : 'Publish' }}
                        </Button>
                    </div>
                    <p v-if="selectedTrip" class="text-xs text-muted-foreground">
                        {{ isSelectedTripPublished ? `${selectedTrip.name} is published` : 'Only the selected trip will be published.' }}
                    </p>
                </div>
                <div class="flex min-h-[220px] flex-col justify-between gap-3 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs dark:border-sidebar-border">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-muted-foreground">Take it with you</p>
                            <Download class="size-5 text-muted-foreground" />
                        </div>
                        <h2 class="mt-2 text-xl font-semibold tracking-normal text-foreground">Export a trip</h2>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">Download route stops or open them in your map app.</p>
                    </div>

                    <select
                        v-model="exportTripId"
                        aria-label="Trip to export"
                        class="min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option v-for="trip in myTrips" :key="trip.id" :value="trip.id">{{ trip.name }}</option>
                    </select>

                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" :disabled="!exportTrip" @click="downloadTrip('gpx')">GPX</Button>
                        <Button size="sm" variant="outline" :disabled="!exportTrip" @click="downloadTrip('kml')">KML</Button>
                    </div>

                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium text-muted-foreground">
                        <a :href="mapAppUrl('mapy')" target="_blank" rel="noopener noreferrer" class="hover:text-foreground">Mapy.com</a>
                        <a :href="mapAppUrl('google')" target="_blank" rel="noopener noreferrer" class="hover:text-foreground">Google Maps</a>
                        <a :href="mapAppUrl('apple')" target="_blank" rel="noopener noreferrer" class="hover:text-foreground">Apple Maps</a>
                    </div>
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

                <div class="grid gap-4 xl:grid-cols-3">
                    <article
                        v-for="trip in myTrips"
                        :key="trip.id"
                        class="grid min-h-[300px] overflow-hidden rounded-lg border border-sidebar-border/70 bg-card shadow-sm md:grid-cols-[minmax(0,1fr)_220px] xl:grid-cols-1 dark:border-sidebar-border"
                    >
                        <div class="flex flex-col gap-4 p-5">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-card-foreground">{{ trip.name }}</h2>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ trip.summary }}</p>
                            </div>

                            <div class="mt-auto flex flex-wrap gap-2 text-sm text-muted-foreground">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1 dark:border-sidebar-border"
                                >
                                    <Route class="size-4" />
                                    {{ trip.distance }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1 dark:border-sidebar-border"
                                >
                                    <MapPin class="size-4" />
                                    {{ trip.duration }}
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
                                <Button as-child>
                                    <Link :href="route('plans.index', { trip: trip.id })">Manage waypoints</Link>
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link :href="route('notes.index', { trip: trip.id })">Modify notes</Link>
                                </Button>
                            </div>
                        </div>

                        <div
                            class="relative min-h-[180px] border-t border-sidebar-border/70 md:border-t-0 md:border-l xl:border-t xl:border-l-0 dark:border-sidebar-border"
                        >
                            <MapComponentRecommendation :model-value="trip.points" height="100%" width="100%" />
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
