<script setup lang="ts">
import FeatureAction from '@/components/FeatureAction.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Check, FileText, MapPin, Route, Save, Sparkles, Tags } from 'lucide-vue-next';
import { computed } from 'vue';

type LatLngPoint = [number, number];

export type WaypointDescription = {
    id: number | string;
    point: LatLngPoint;
    name?: string;
    tags?: string[];
    description: string;
};

const props = defineProps<{
    modelValue: WaypointDescription[];
    isSaved: boolean;
    processing?: boolean;
    tagPresets: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: WaypointDescription[]];
    focus: [point: LatLngPoint];
    save: [];
}>();

const describedCount = computed(() => props.modelValue.filter((waypoint) => waypoint.description.trim().length > 0).length);
const taggedCount = computed(() => props.modelValue.filter((waypoint) => (waypoint.tags ?? []).length > 0).length);

const formatCoordinate = (point: LatLngPoint) => `${point[0].toFixed(4)}, ${point[1].toFixed(4)}`;

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

const updateDescription = (id: WaypointDescription['id'], description: string) => {
    emit(
        'update:modelValue',
        props.modelValue.map((waypoint) => (waypoint.id === id ? { ...waypoint, description } : waypoint)),
    );
};

const updateName = (id: WaypointDescription['id'], name: string) => {
    emit(
        'update:modelValue',
        props.modelValue.map((waypoint) => (waypoint.id === id ? { ...waypoint, name } : waypoint)),
    );
};

const parseTags = (value: string) => [
    ...new Set(
        value
            .split(',')
            .map((tag) => tag.trim())
            .filter(Boolean),
    ),
];

const togglePreset = (id: WaypointDescription['id'], tag: string) => {
    emit(
        'update:modelValue',
        props.modelValue.map((waypoint) => {
            if (waypoint.id !== id) return waypoint;

            const tags = waypoint.tags ?? [];
            return { ...waypoint, tags: tags.includes(tag) ? tags.filter((value) => value !== tag) : [...tags, tag] };
        }),
    );
};

const updateTags = (id: WaypointDescription['id'], value: string) => {
    emit(
        'update:modelValue',
        props.modelValue.map((waypoint) => (waypoint.id === id ? { ...waypoint, tags: parseTags(value) } : waypoint)),
    );
};

const clearDescriptions = () => {
    emit(
        'update:modelValue',
        props.modelValue.map((waypoint) => ({ ...waypoint, description: '' })),
    );
};
</script>

<template>
    <section class="flex flex-col gap-5 border-t border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-muted-foreground">Route notes</p>
                <h2 class="mt-1 text-xl font-semibold tracking-normal text-foreground">Waypoint details</h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">Name each stop, tag it, and add the notes you need during the trip.</p>
            </div>
            <FeatureAction feature="notes" class="shrink-0 gap-2 px-4" :disabled="processing" type="button" @click="emit('save')">
                <Check v-if="isSaved" class="size-4" />
                <Save v-else class="size-4" />
                {{ processing ? 'Saving…' : 'Save' }}
            </FeatureAction>
        </div>

        <div class="grid gap-2 text-sm text-muted-foreground sm:grid-cols-3">
            <div class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-2 dark:border-sidebar-border">
                <Route class="size-4" />
                {{ modelValue.length }} points
            </div>
            <div class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-2 dark:border-sidebar-border">
                <FileText class="size-4" />
                {{ describedCount }} described
            </div>
            <div class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-2 dark:border-sidebar-border">
                <Tags class="size-4" />
                {{ taggedCount }} tagged
            </div>
        </div>

        <div
            v-if="modelValue.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-5 text-sm leading-6 text-muted-foreground dark:border-sidebar-border"
        >
            Choose or create a route to add waypoint descriptions.
        </div>

        <ul
            v-else
            tabindex="0"
            aria-label="Waypoint details"
            class="flex h-[24rem] min-h-0 flex-col gap-3 overflow-y-scroll overscroll-y-contain pr-1"
            @wheel.stop
        >
            <li v-for="(waypoint, index) in modelValue" :key="waypoint.id" class="shrink-0">
                <article class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                    <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-3">
                        <span class="mt-1 size-3 rounded-full" :class="getPointAccentClass(index, modelValue.length)" />

                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-sm font-semibold text-card-foreground">
                                    {{ waypoint.name || getPointLabel(index, modelValue.length) }}
                                </h3>
                                <span class="shrink-0 text-xs font-medium text-muted-foreground">#{{ index + 1 }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-muted-foreground">{{ formatCoordinate(waypoint.point) }}</p>
                        </div>

                        <Button variant="ghost" size="icon" class="relative size-8" type="button" @click="emit('focus', waypoint.point)">
                            <MapPin class="size-4" />
                            <span class="sr-only">Focus waypoint</span>
                        </Button>
                    </header>

                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(180px,0.75fr)]">
                        <label class="grid gap-1.5 text-sm font-medium text-card-foreground">
                            Name
                            <Input
                                :placeholder="getPointLabel(index, modelValue.length)"
                                :value="waypoint.name"
                                @input="updateName(waypoint.id, ($event.target as HTMLInputElement).value)"
                            />
                        </label>

                        <label class="grid gap-1.5 text-sm font-medium text-card-foreground">
                            Tags (comma-separated; custom names allowed)
                            <Input
                                placeholder="food, viewpoint"
                                :value="(waypoint.tags ?? []).join(', ')"
                                @change="updateTags(waypoint.id, ($event.target as HTMLInputElement).value)"
                            />
                        </label>
                    </div>

                    <fieldset v-if="tagPresets.length > 0" class="grid gap-2">
                        <legend class="text-sm font-medium text-card-foreground">Choose tags</legend>
                        <div class="flex flex-wrap gap-1.5">
                            <Button
                                v-for="tag in tagPresets"
                                :key="tag"
                                type="button"
                                size="sm"
                                :variant="waypoint.tags?.includes(tag) ? 'secondary' : 'outline'"
                                :aria-pressed="waypoint.tags?.includes(tag) ?? false"
                                @click="togglePreset(waypoint.id, tag)"
                            >
                                <Check v-if="waypoint.tags?.includes(tag)" class="size-4" aria-hidden="true" />
                                {{ tag }}
                            </Button>
                        </div>
                    </fieldset>

                    <div v-if="(waypoint.tags ?? []).length > 0" class="flex flex-wrap gap-1.5">
                        <span v-for="tag in waypoint.tags" :key="tag" class="rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                            {{ tag }}
                        </span>
                    </div>

                    <textarea
                        class="min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm leading-6 text-foreground shadow-xs transition placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        :placeholder="`Add notes for ${waypoint.name || getPointLabel(index, modelValue.length)}`"
                        :value="waypoint.description"
                        @input="updateDescription(waypoint.id, ($event.target as HTMLTextAreaElement).value)"
                    />
                </article>
            </li>
        </ul>

        <Button v-if="describedCount > 0" variant="outline" class="justify-start gap-2" type="button" @click="clearDescriptions">
            <Sparkles class="size-4" />
            Clear descriptions
        </Button>
    </section>
</template>
