<script setup lang="ts">
import MapComponent from '@/components/maps/MapComponent.vue';
import WaypointsPlan from '@/components/waypoints/WaypointsPlan.vue';
import { starterRoutePoints, type LatLngPoint } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const routePoints = ref<LatLngPoint[]>([...starterRoutePoints]);

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

        <div class="grid h-screen grid-rows-[minmax(360px,1fr)_auto] overflow-hidden lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)] lg:grid-rows-1">
            <div class="relative min-h-[360px]">
                <MapComponent ref="mapComponentRef" v-model="routePoints" :editable="true" height="100%" width="100%" />
            </div>

            <div class="min-h-0">
                <WaypointsPlan :points="routePoints" @focus="focusOnMap" @clear="routePoints = []" />
            </div>
        </div>
    </AppLayout>
</template>
