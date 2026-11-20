<script setup lang="ts">
import FeatureAction from '@/components/FeatureAction.vue';
import MapComponentNote from '@/components/maps/MapComponentNote.vue';
import { Input } from '@/components/ui/input';
import WaypointsDescriptions, { type WaypointDescription } from '@/components/waypoints/WaypointsDescriptions.vue';
import { useFeatures } from '@/composables/useFeatures';
import type { NotesTripCard, Trip } from '@/types/trip';
import { router, useForm } from '@inertiajs/vue3';
import { MapPin, Route } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const { isEnabled } = useFeatures();

type LatLngPoint = [number, number];
const props = defineProps<{
    trips: NotesTripCard[];
    trip: Trip | null;
    waypointTagPresets: string[];
}>();
const activeTrip = computed(() => props.trip);
const activeTripId = computed(() => props.trip?.id);
const routeWaypoints = ref<WaypointDescription[]>(
    (props.trip?.waypoints ?? []).map((point) => ({
        id: point.id,
        point: [point.point_lat, point.point_lng],
        name: point.name ?? '',
        description: point.description ?? '',
        tags: [...point.tags],
    })),
);
const tripNotes = ref(props.trip?.notes ?? '');
const tripTagsText = ref(props.trip?.tags.join(', ') ?? '');
const form = useForm({
    notes: '',
    tags: [] as string[],
    waypoints: [] as { id: number | string; name: string; description: string; tags: string[] }[],
});
const metadataError = computed(() => Object.values(form.errors).join(' '));
const metadataMessage = ref('');
const snapshot = () => JSON.stringify({ waypoints: routeWaypoints.value, notes: tripNotes.value, tags: tripTagsText.value });
const savedSnapshot = ref(snapshot());
const isSaved = computed(() => savedSnapshot.value === snapshot());
const routePoints = computed(() => (props.trip?.waypoints ?? []).map((point): LatLngPoint => [point.point_lat, point.point_lng]));
const mapComponentRef = ref<{ flyToCoordinate: (point: LatLngPoint, zoom?: number) => void } | null>(null);
const mapSectionRef = ref<HTMLElement | null>(null);
const selectTrip = (trip: { id: number }) => {
    if (!isEnabled('notes') || form.processing || trip.id === activeTripId.value) return;
    if (!isSaved.value && !window.confirm('Discard unsaved notes and load another trip?')) return;
    router.get(route('notes.index', { trip: trip.id }));
};
const saveNotes = () => {
    if (!isEnabled('notes') || !props.trip || form.processing) return;
    metadataMessage.value = '';
    form.notes = tripNotes.value;
    form.tags = tripTagsText.value
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean);
    form.waypoints = routeWaypoints.value.map((point) => ({
        id: point.id,
        name: point.name ?? '',
        description: point.description,
        tags: point.tags ?? [],
    }));
    const submittedSnapshot = snapshot();
    form.patch(route('notes.update', props.trip.id), {
        preserveScroll: true,
        onSuccess: () => {
            savedSnapshot.value = submittedSnapshot;
            metadataMessage.value = 'Trip and waypoint notes saved.';
        },
    });
};
const focusOnMap = (point: LatLngPoint) => {
    mapSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    mapComponentRef.value?.flyToCoordinate(point, 14);
};
</script>

<template>
    <section id="notes" aria-label="Notes" class="flex flex-col gap-6 p-4 md:p-6">
        <div class="max-w-3xl">
            <p class="text-sm font-medium text-muted-foreground">Your routes</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-normal text-foreground">Choose a saved route</h2>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">Choose a route first, then add descriptions to its waypoints below.</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <article
                v-for="trip in trips"
                :key="trip.id"
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
            >
                <div>
                    <h3 class="text-base font-semibold tracking-normal text-card-foreground">{{ trip.name }}</h3>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ trip.notes }}</p>
                </div>

                <div class="flex flex-wrap gap-2 text-sm text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1 dark:border-sidebar-border">
                        <Route class="size-4" />
                        {{ trip.distance === null ? 'Unknown distance' : `${(trip.distance / 1000).toFixed(1)} km` }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1 dark:border-sidebar-border">
                        <MapPin class="size-4" />
                        {{ trip.duration === null ? 'Unknown duration' : `${Math.round(trip.duration / 60)} min` }}
                    </span>
                </div>

                <div class="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap">
                    <span
                        v-for="(stop, index) in trip.stops.slice(0, 3)"
                        :key="index"
                        class="min-w-0 truncate rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        {{ stop }}
                    </span>
                    <span v-if="trip.stops.length > 3" class="shrink-0 text-xs font-medium text-muted-foreground">
                        +{{ trip.stops.length - 3 }} stops
                    </span>
                </div>

                <FeatureAction
                    feature="notes"
                    type="button"
                    :variant="trip.id === activeTripId ? 'secondary' : 'outline'"
                    class="justify-start"
                    @click="selectTrip(trip)"
                >
                    {{ trip.id === activeTripId ? 'Loaded' : 'Load route' }}
                </FeatureAction>
            </article>
        </div>

        <div v-if="!activeTrip" class="text-sm text-muted-foreground">
            No saved trips yet. <FeatureAction feature="plan" variant="link" :href="route('trips.create')">Create a trip</FeatureAction>
        </div>
        <template v-if="activeTrip">
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

                <div class="grid gap-4 border-t border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <label class="grid gap-1.5 text-sm font-medium text-card-foreground">
                        Trip description
                        <textarea
                            v-model="tripNotes"
                            maxlength="10000"
                            class="min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm leading-6 text-foreground shadow-xs transition placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            placeholder="Add notes for the whole trip"
                        />
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium text-card-foreground">
                        Trip tags (comma-separated)
                        <Input v-model="tripTagsText" placeholder="weekend, culture, family" aria-describedby="trip-tags-hint" />
                    </label>
                    <p id="trip-tags-hint" class="text-sm text-muted-foreground">
                        Up to 20 tags, 50 characters each. Saving updates this trip and its waypoint notes.
                    </p>
                    <p v-if="metadataError" role="alert" class="text-sm text-destructive">{{ metadataError }}</p>
                    <p v-else-if="!isSaved" role="status" class="text-sm text-muted-foreground">Unsaved changes</p>
                    <p v-else-if="metadataMessage" role="status" class="text-sm text-muted-foreground">{{ metadataMessage }}</p>
                </div>

                <WaypointsDescriptions
                    v-model="routeWaypoints"
                    :is-saved="isSaved"
                    :processing="form.processing"
                    :tag-presets="waypointTagPresets"
                    @focus="focusOnMap"
                    @save="saveNotes"
                />
            </div>
        </template>
    </section>
</template>
