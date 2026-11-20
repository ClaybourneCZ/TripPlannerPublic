<script setup lang="ts">
import FeatureAction from '@/components/FeatureAction.vue';
import MapComponent from '@/components/maps/MapComponent.vue';
import { Input } from '@/components/ui/input';
import { useFeatures } from '@/composables/useFeatures';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const { isEnabled } = useFeatures();

interface SavedTrip {
    id: number;
    name: string;
    notes: string | null;
    waypoints: { id: number; point_lat: number; point_lng: number; name: string | null }[];
    tags: string[];
}
const props = defineProps<{ trip: SavedTrip | null; notice?: string | null }>();
const points = ref<[number, number][]>(props.trip?.waypoints.map((point) => [point.point_lat, point.point_lng]) ?? []);
const name = ref(props.trip?.name ?? '');
const waypointNames = ref<string[]>(props.trip?.waypoints.map((point) => point.name ?? '') ?? []);
const waypointIds = ref<(number | null)[]>(props.trip?.waypoints.map((point) => point.id) ?? []);
const createForm = useForm({ name: '', waypoints: [] as { point_lat: number; point_lng: number; name: string | null }[] });
const updateForm = useForm({
    name: '',
    notes: '' as string | null,
    tags: [] as string[],
    waypoints: [] as { id?: number; point_lat: number; point_lng: number; name: string | null }[],
});
const deleteForm = useForm({});
const message = ref('');
const deleteError = ref('');
const snapshot = () => JSON.stringify({ name: name.value, points: points.value, waypointNames: waypointNames.value });
const savedSnapshot = ref(snapshot());
const isDirty = computed(() => savedSnapshot.value !== snapshot());
const markSaved = () => {
    savedSnapshot.value = snapshot();
};
const save = () => {
    if (!isEnabled('plan')) return;
    message.value = '';
    if (props.trip) {
        let succeeded = false;
        updateForm.name = name.value;
        updateForm.notes = props.trip.notes;
        updateForm.tags = props.trip.tags;
        updateForm.waypoints = points.value.map(([point_lat, point_lng], index) => {
            const id = waypointIds.value[index];
            return {
                ...(typeof id === 'number' ? { id } : {}),
                point_lat,
                point_lng,
                name: waypointNames.value[index]?.trim() || null,
            };
        });
        updateForm.patch(route('trips.update', props.trip.id), {
            preserveScroll: true,
            onSuccess: () => {
                succeeded = true;
                message.value = 'Trip updated.';
                waypointIds.value = props.trip?.waypoints.map((point) => point.id) ?? [];
                markSaved();
            },
            onFinish: () => {
                if (!succeeded && !Object.keys(updateForm.errors).length) message.value = 'Could not save. Your changes are still here.';
            },
        });
        return;
    }
    createForm.name = name.value;
    createForm.waypoints = points.value.map(([point_lat, point_lng], index) => ({
        point_lat,
        point_lng,
        name: waypointNames.value[index]?.trim() || null,
    }));
    let succeeded = false;
    createForm.post(route('trips.store'), {
        onSuccess: () => {
            succeeded = true;
            message.value = 'Trip saved.';
            markSaved();
        },
        onFinish: () => {
            if (!succeeded && !Object.keys(createForm.errors).length) message.value = 'Could not save. Your changes are still here.';
        },
    });
};
const addPoint = (updated: [number, number][]) => {
    if (updated.length > points.value.length) {
        waypointNames.value.push('');
        waypointIds.value.push(null);
    }
    points.value = updated;
};
const removePoint = (index: number) => {
    points.value = points.value.filter((_, pointIndex) => pointIndex !== index);
    waypointNames.value.splice(index, 1);
    waypointIds.value.splice(index, 1);
};
const remove = () => {
    if (!isEnabled('plan')) return;
    if (!props.trip || deleteForm.processing || updateForm.processing || !window.confirm(`Delete ${props.trip.name}?`)) return;
    deleteError.value = '';
    let succeeded = false;
    deleteForm.delete(route('trips.destroy', props.trip.id), {
        onSuccess: () => {
            succeeded = true;
        },
        onFinish: () => {
            if (!succeeded) deleteError.value = 'Could not delete the trip. Please try again.';
        },
    });
};
</script>

<template>
    <AppLayout class="lg:h-dvh lg:overflow-hidden">
        <Head :title="trip ? 'Edit trip' : 'Create trip'" />
        <div class="grid gap-4 p-4 lg:min-h-0 lg:flex-1 lg:grid-cols-[2fr_1fr] lg:grid-rows-[minmax(0,1fr)] lg:overflow-hidden">
            <div class="h-[60dvh] min-h-0 lg:h-full">
                <MapComponent
                    :model-value="points"
                    :editable="isEnabled('plan')"
                    :fit-route="trip !== null"
                    height="100%"
                    width="100%"
                    @update:model-value="addPoint"
                    @remove-point="removePoint"
                />
            </div>
            <form id="metadata" class="flex min-h-0 flex-col gap-3" @submit.prevent="save">
                <h1 class="text-xl font-semibold">{{ trip ? 'Edit trip' : 'Create trip' }}</h1>
                <label class="grid gap-1">Name <Input v-model="name" maxlength="255" /></label>
                <p class="text-sm">Click the map to add waypoints. Click a marker to remove it.</p>
                <div class="flex min-h-0 flex-col gap-2 lg:flex-1">
                    <h2 class="text-lg font-semibold">Waypoints</h2>
                    <p v-if="points.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Click the map to add a waypoint. It will appear here.
                    </p>
                    <div
                        v-if="points.length"
                        role="region"
                        aria-label="Waypoints"
                        tabindex="0"
                        class="flex max-h-[37rem] min-h-0 flex-col gap-2 overflow-y-auto pr-1 lg:max-h-full"
                    >
                        <div v-for="(point, index) in points" :key="index" class="grid h-28 shrink-0 gap-1 rounded-md border p-3">
                            <div class="flex items-center justify-between gap-2">
                                <label :for="`waypoint-${index}`" class="text-sm font-medium">Waypoint {{ index + 1 }}</label>
                                <button
                                    type="button"
                                    :disabled="!isEnabled('plan')"
                                    :aria-label="`Remove waypoint ${index + 1}`"
                                    :title="`Remove waypoint ${index + 1}`"
                                    class="rounded p-1 text-destructive hover:bg-destructive/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                    @click="removePoint(index)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </button>
                            </div>
                            <Input :id="`waypoint-${index}`" v-model="waypointNames[index]" maxlength="255" placeholder="Name this waypoint" />
                            <p class="text-xs text-muted-foreground">{{ point[0].toFixed(4) }}, {{ point[1].toFixed(4) }}</p>
                        </div>
                    </div>
                    <FeatureAction
                        feature="plan"
                        type="submit"
                        class="shrink-0"
                        :disabled="createForm.processing || updateForm.processing || deleteForm.processing"
                    >
                        {{ createForm.processing || updateForm.processing ? 'Saving…' : 'Save trip' }}
                    </FeatureAction>
                    <FeatureAction
                        feature="plan"
                        v-if="trip"
                        type="button"
                        variant="destructive"
                        class="shrink-0"
                        :disabled="updateForm.processing || deleteForm.processing"
                        @click="remove"
                    >
                        {{ deleteForm.processing ? 'Deleting…' : 'Delete trip' }}
                    </FeatureAction>
                    <p v-if="deleteError" role="alert" class="text-sm text-destructive">{{ deleteError }}</p>
                    <p v-if="message.startsWith('Could not save')" role="alert" class="text-sm text-destructive">{{ message }}</p>
                    <p v-else-if="isDirty" role="status" class="text-sm text-muted-foreground">Unsaved changes</p>
                    <p v-else-if="message || notice" role="status">{{ message || notice }}</p>
                </div>
                <ul v-if="Object.keys(trip ? updateForm.errors : createForm.errors).length" role="alert" class="text-sm text-destructive">
                    <li v-for="(error, key) in trip ? updateForm.errors : createForm.errors" :key="key">{{ error }}</li>
                </ul>
            </form>
        </div>
    </AppLayout>
</template>
