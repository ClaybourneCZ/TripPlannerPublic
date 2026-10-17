<script setup lang="ts">
import MapComponent from '@/components/maps/MapComponent.vue';
import WaypointsPlan from '@/components/waypoints/WaypointsPlan.vue';
import { starterRoutePoints, tripSamples, type LatLngPoint } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ tripId?: number | string | null }>();

const selectedTrip = tripSamples.find((trip) => trip.id === Number(props.tripId));
const routePoints = ref<LatLngPoint[]>(selectedTrip ? selectedTrip.waypoints.map((waypoint) => waypoint.point) : [...starterRoutePoints]);

// Action after clicking on an item in the list
// 1. Define what the component can do (Interface)
// error with ts not knowing the flyToCoordinate
interface MapRef {
    flyToCoordinate: (coords: LatLngPoint, zoom?: number) => void;
}

// Reference to the map component instance
// 2. Tell ref that it can contain either MapRef or null
const mapComponentRef = ref<MapRef | null>(null);

// Now TS won't complain anymore, because it knows that the function may exist there.
const focusOnMap = (point: LatLngPoint) => {
    // The question mark (?.) is "safe navigation" - it is only called if mapComponentRef.value is not null.
    mapComponentRef.value?.flyToCoordinate(point, 16);
};
</script>

<template>
    <AppLayout>
        <Head title="Plan" />

        <div class="grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)_minmax(160px,32vh)] overflow-hidden lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)] lg:grid-rows-1">
            <div class="relative min-h-0">
                <MapComponent ref="mapComponentRef" v-model="routePoints" :editable="true" height="100%" width="100%" />
            </div>

            <div class="min-h-0">
                <WaypointsPlan :points="routePoints" @focus="focusOnMap" @clear="routePoints = []" />
            </div>
        </div>
    </AppLayout>
</template>
