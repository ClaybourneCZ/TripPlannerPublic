<script setup lang="ts">
import MapComponentNote from '@/components/maps/MapComponentNote.vue';
import { Button } from '@/components/ui/button';
import WaypointsDescriptions from '@/components/waypoints/WaypointsDescriptions.vue';
import { cloneWaypointDescriptions, tripSamples, type LatLngPoint, type TripSample, type WaypointDescription } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { MapPin, Route } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const recommendedTrips = tripSamples;

interface MapRef {
    flyToCoordinate: (coords: LatLngPoint, zoom?: number) => void;
}

const activeTripId = ref(recommendedTrips[0].id);

const routeWaypoints = ref<WaypointDescription[]>(cloneWaypointDescriptions(recommendedTrips[0].waypoints));
const routePoints = ref<LatLngPoint[]>(routeWaypoints.value.map((waypoint) => waypoint.point));
const mapComponentRef = ref<MapRef | null>(null);
const mapSectionRef = ref<HTMLElement | null>(null);

const activeTrip = computed(() => recommendedTrips.find((trip) => trip.id === activeTripId.value) ?? recommendedTrips[0]);

const selectTrip = async (trip: TripSample) => {
    activeTripId.value = trip.id;
    routeWaypoints.value = cloneWaypointDescriptions(trip.waypoints);
    routePoints.value = routeWaypoints.value.map((waypoint) => waypoint.point);

    await nextTick();
    mapSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const focusOnMap = (point: LatLngPoint) => {
    mapSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    mapComponentRef.value?.flyToCoordinate(point, 14);
};
</script>

<template>
    <AppLayout>
        <Head title="Notes" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-muted-foreground">Test routes</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal text-foreground">Load route sample</h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">Choose a route first, then add descriptions to its waypoints below.</p>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <article
                    v-for="trip in recommendedTrips"
                    :key="trip.id"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div>
                        <h3 class="text-base font-semibold tracking-normal text-card-foreground">{{ trip.name }}</h3>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ trip.summary }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 text-sm text-muted-foreground">
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

                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="waypoint in trip.waypoints"
                            :key="waypoint.id"
                            class="rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ waypoint.name }}
                        </span>
                    </div>

                    <Button
                        type="button"
                        :variant="trip.id === activeTripId ? 'secondary' : 'outline'"
                        class="justify-start"
                        @click="selectTrip(trip)"
                    >
                        {{ trip.id === activeTripId ? 'Loaded' : 'Load route' }}
                    </Button>
                </article>
            </div>

            <div class="max-w-3xl">
                <p class="text-sm font-medium text-muted-foreground">Plan notes</p>
                <h2 class="mt-1 text-lg font-semibold tracking-normal text-foreground">{{ activeTrip.name }}</h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Add descriptions to each waypoint so the route carries the details you need during the trip.
                </p>
            </div>

            <div
                ref="mapSectionRef"
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div class="relative h-[260px] md:h-[320px]">
                    <MapComponentNote ref="mapComponentRef" :model-value="routePoints" height="100%" width="100%" />
                </div>

                <WaypointsDescriptions v-model="routeWaypoints" @focus="focusOnMap" />
            </div>
        </div>
    </AppLayout>
</template>
