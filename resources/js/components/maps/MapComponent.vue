<script setup lang="ts">
import L from 'leaflet';
import 'leaflet-routing-machine';
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css';
import 'leaflet/dist/leaflet.css';
import { onMounted, onUnmounted, ref, watch } from 'vue';

// --- Types ---
type LatLngPoint = [number, number];

type Props = {
    modelValue?: LatLngPoint[];
    editable?: boolean;
    fitRoute?: boolean;
    height?: string;
    width?: string;
};

// --- Props & Emits ---

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    editable: false,
    fitRoute: false,
    height: '500px',
    width: '100%',
});

const emit = defineEmits<{
    'update:modelValue': [value: LatLngPoint[]];
}>();

// --- Variables ---
const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let routingControl: L.Routing.Control | null = null; // Router instance (for 2+ points)
let manualMarker: L.Marker | null = null; // Instance of the marker (for cases where there is only 1 point)

// --- Fix missing icons (Vite/Webpack) ---
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete (L.Icon.Default.prototype as L.Icon.Default & { _getIconUrl?: unknown })._getIconUrl;
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

    map = L.map(mapContainer.value).setView(startCenter, startZoom);

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

    // 3. Event Listener: Click on the map
    map.on('click', (e: L.LeafletMouseEvent) => {
        if (!props.editable) return;

        const newPoint: LatLngPoint = [e.latlng.lat, e.latlng.lng];
        const newRoute = [...props.modelValue, newPoint];

        emit('update:modelValue', newRoute);
    });
};

const fitRouteBounds = () => {
    if (!map || !props.fitRoute || props.modelValue.length < 2) return;

    map.fitBounds(L.latLngBounds(props.modelValue), {
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
            interactive: true,
            title: 'Start',
        }).addTo(map);

        manualMarker.on('click', () => {
            if (!props.editable) return;
            emit('update:modelValue', []); // Clear the point on click (only in editable mode)
        });

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

            // TODO: maybe add smthing like notes to each marker,
            // to write them when user clicks on marker and edit them,
            // also edit them later on the side with route points
            const marker = L.marker(wp.latLng, {
                draggable: false, // Disabled to maintain data consistency
                title: label,
            }).bindPopup(label); // After clicking on the point, a label will appear

            marker.on('click', () => {
                if (!props.editable) return;
                const newPoints = props.modelValue.filter((_, index) => index !== i);
                emit('update:modelValue', newPoints);
            });

            return marker;
        },

        show: false, // Hide itinerary
        addWaypoints: false, // Prohibiting adding points by dragging (we use clicking)
    }).addTo(map);

    fitRouteBounds();
};

/**
 * Public method for parents: Fly to coordinates
 */
const flyToCoordinate = (coords: LatLngPoint, zoom = 15) => {
    if (map) {
        map.flyTo(coords, zoom, { animate: true, duration: 2.5 });
    }
};

defineExpose({
    flyToCoordinate,
});

// --- Lifecycle ---

onMounted(() => {
    initMap();
    redrawMapElements();
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
