<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { MapPin, Route, Trash2 } from 'lucide-vue-next';

// Types
type LatLngPoint = [number, number];

// Props and Emits
defineProps<{
    points: LatLngPoint[];
}>();

const emit = defineEmits<{
    focus: [point: LatLngPoint];
    clear: [];
}>();

const formatCoordinate = (point: LatLngPoint) => `${point[0].toFixed(4)}, ${point[1].toFixed(4)}`;

// Basic func
// - waypoints labeling
const getPointLabel = (index: number, total: number) => {
    if (index === 0) return 'Start';
    if (index === total - 1) return 'Destination';

    return `Stop ${index}`;
};

const getPointAccentClass = (index: number, total: number) => {
    if (index === 0) return 'bg-emerald-500';
    if (index === total - 1) return 'bg-blue-500';

    return 'bg-amber-500';
};
</script>

<template>
    <div class="flex h-full flex-col gap-5 border-l border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border">
        <div>
            <p class="text-sm font-medium text-muted-foreground">Current route</p>
            <h2 class="mt-1 text-xl font-semibold tracking-normal text-foreground">Waypoints</h2>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">Click a waypoint to focus it on the map.</p>
        </div>

        <div class="grid grid-cols-2 gap-2 text-sm text-muted-foreground">
            <div class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-2 dark:border-sidebar-border">
                <Route class="size-4" />
                {{ points.length }} points
            </div>
            <div class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-2 dark:border-sidebar-border">
                <MapPin class="size-4" />
                {{ points.length > 1 ? points.length - 1 : 0 }} stops
            </div>
        </div>

        <div
            v-if="points.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-5 text-sm leading-6 text-muted-foreground dark:border-sidebar-border"
        >
            Add points by clicking on the map. Your route list will appear here.
        </div>

        <ul v-else class="flex max-h-[60vh] flex-col gap-3 overflow-y-auto pr-1">
            <li v-for="(point, index) in points" :key="index">
                <button
                    type="button"
                    class="grid w-full grid-cols-[auto_minmax(0,1fr)] gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 text-left shadow-sm transition hover:border-foreground/25 hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:border-sidebar-border"
                    @click="emit('focus', point)"
                >
                    <span class="mt-1 size-3 rounded-full" :class="getPointAccentClass(index, points.length)" />

                    <span class="min-w-0">
                        <span class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-card-foreground">{{ getPointLabel(index, points.length) }}</span>
                            <span class="shrink-0 text-xs font-medium text-muted-foreground">#{{ index + 1 }}</span>
                        </span>
                        <span class="mt-1 block truncate text-sm text-muted-foreground">
                            {{ formatCoordinate(point) }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>

        <Button v-if="points.length > 0" variant="destructive" class="justify-start gap-2" @click="emit('clear')">
            <Trash2 class="size-4" />
            Clear route
        </Button>
    </div>
</template>
