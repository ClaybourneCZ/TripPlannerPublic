<script setup lang="ts">
import L from 'leaflet';
import 'leaflet-routing-machine';
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css';
import 'leaflet/dist/leaflet.css';
import { onMounted, onUnmounted, ref, watch } from 'vue';

type LatLngPoint = [number, number];
type MapOptionsWithTap = L.MapOptions & { tap: boolean };

type Props = {
    modelValue?: LatLngPoint[];
    height?: string;
    width?: string;
    interactive?: boolean;
};

// --- Props ---
const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    height: '220px',
    width: '100%',
    interactive: false,
});

// --- Variables ---
const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let routingControl: L.Routing.Control | null = null; // Router instance (for 2+ points)
let manualMarker: L.Marker | null = null; // Instance of the marker (for cases where there is only 1 point)

// --- Fix missing icons (Vite/Webpack) ---
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

(delete (L.Icon.Default.prototype as L.Icon.Default & { _getIconUrl?: unknown })._getIconUrl);
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

// --- Main logic ---

const initMap = () => {
    // 1. Creating a map
    const defaultCenter: LatLngPoint = [49.8175, 15.473];
    const startCenter = props.modelValue.length > 0 ? props.modelValue[0] : defaultCenter;
    const startZoom = props.modelValue.length > 0 ? 13 : 7;

    if (!mapContainer.value) return;

    const mapOptions: MapOptionsWithTap = {
        zoomControl: props.interactive,
        dragging: props.interactive,
        scrollWheelZoom: false,
        doubleClickZoom: props.interactive,
        boxZoom: props.interactive,
        keyboard: props.interactive,
        tap: props.interactive,
    };

    map = L.map(mapContainer.value, mapOptions).setView(startCenter, startZoom);

    // 2. Tiles (CartoDB Voyager)
    
    /*
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        maxZoom: 20,
    }).addTo(map);
    */

    L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }
    ).addTo(map);
};

const fitTripBounds = () => {
    if (!map || props.modelValue.length === 0) return;

    const points = props.modelValue.map((p) => L.latLng(p[0], p[1]));

    if (points.length === 1) {
        map.setView(points[0], 13);
        return;
    }

    map.fitBounds(L.latLngBounds(points), {
        padding: [24, 24],
        maxZoom: 13,
    });
};

const redrawMapElements = () => {
    if (!map) return;

    // 1. CLEANUP: Removal of previous elements
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;
    }
    if (manualMarker) {
        map.removeLayer(manualMarker);
        manualMarker = null;
    }

    const points = props.modelValue;

    // SCENARIO 2: No point
    if (points.length === 0) return;

    // 3. SCENARIO: Only 1 point (Start)
    // The router needs at least 2 points, so we draw the first point manually.
    if (points.length === 1) {
        manualMarker = L.marker(points[0], {
            interactive: props.interactive,
            title: 'Start',
        }).addTo(map);

        fitTripBounds();
        return;
    }

    // 4. SCENARIO: 2 or more points (Full route with stops)
    // Convert all points to Leaflet format
    const waypoints = points.map((p) => L.latLng(p[0], p[1]));

    routingControl = L.Routing.control({
        waypoints: waypoints, // Here we pass ALL points
        routeWhileDragging: false,

        // Line style
        lineOptions: {
            styles: [{ color: '#3b82f6', opacity: 0.8, weight: 6 }],
        },

        // Logic for markers (Start vs Stop vs Destination)
        createMarker: function (i: number, wp: L.Routing.Waypoint, nWps: number) {
            let label = '';

            if (i === 0) {
                label = 'Start';
            } else if (i === nWps - 1) {
                label = 'Destination';
            } else {
                label = `Stop no. ${i}`;
            }

            return L.marker(wp.latLng, {
                draggable: false, // Disabled to maintain data consistency
                interactive: props.interactive,
                title: label,
            }).bindPopup(label); // After clicking on the point, a label will appear
        },

        show: false, // Hide itinerary
        addWaypoints: false, // Prohibiting adding points by dragging (we use clicking)
    }).addTo(map);

    fitTripBounds();
};

// --- Lifecycle ---

onMounted(() => {
    initMap();
    redrawMapElements();
    setTimeout(() => {
        map?.invalidateSize();
        fitTripBounds();
    }, 0);
});

watch(
    () => props.modelValue,
    () => {
        redrawMapElements();
    },
    { deep: true },
);

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});



/*
:deep(.leaflet-routing-error) {
    display: none !important;
}
 */

</script>

<style scoped>
:deep(.leaflet-routing-container) {
    display: none !important;
}
:deep(.leaflet-routing-error) {
    display: none !important;
}
:deep(.leaflet-control-attribution) {
    font-size: 10px;
    opacity: 0.7;
}
</style>

<template>
    <div ref="mapContainer" class="z-0" :style="{ height: height, width: width }"></div>
</template>
