<script setup lang="ts">
import MapComponentRecommendation from '@/components/maps/MapComponentRecommendation.vue';
import { tripSamples } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, Route } from 'lucide-vue-next';
import { Button } from '@/components/ui/button'; 

const recommendedTrips = tripSamples.map((trip) => ({
    ...trip,
    points: trip.waypoints.map((waypoint) => waypoint.point),
    stops: trip.waypoints.map((waypoint) => waypoint.name ?? 'Unnamed stop'),
}));
</script>

<template>
    <AppLayout>
        <Head title="Recommendations" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-muted-foreground">Recommended trips</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal text-foreground">Pick a ready-made route</h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Three starter ideas with route previews, useful trip details, and the main stops already mapped out.
                </p>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <article
                    v-for="trip in recommendedTrips"
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

                        <Button >
                            <Link :href="route('dashboard')">
                                <Plus class="size-4" />
                                Add to My Trips
                            </Link>
                        </Button>
                    </div>

                    <div
                        class="relative min-h-[180px] border-t border-sidebar-border/70 md:border-t-0 md:border-l xl:border-t xl:border-l-0 dark:border-sidebar-border"
                    >
                        <MapComponentRecommendation :model-value="trip.points" height="100%" width="100%" />
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
