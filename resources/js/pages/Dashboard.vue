<script setup lang="ts">
import MapComponentRecommendation from '@/components/maps/MapComponentRecommendation.vue';
import { Button } from '@/components/ui/button';
import { tripSamples } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Map, MapPin, Plus, Route } from 'lucide-vue-next';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const myTrips = tripSamples.map((trip) => ({
    ...trip,
    points: trip.waypoints.map((waypoint) => waypoint.point),
    stops: trip.waypoints.map((waypoint) => waypoint.name ?? 'Unnamed stop'),
}));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative flex aspect-video flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xs transition hover:border-primary/40 hover:shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">New itinerary</p>
                            <h2 class="mt-2 text-xl font-semibold tracking-normal text-foreground">Create Trip</h2>
                            <p class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                                Start a route plan, add stops, and shape the trip on the map.
                            </p>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-md border border-sidebar-border/70 bg-muted text-muted-foreground"
                        >
                            <Route class="size-5" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Map class="size-4" />
                            <span>Route planner</span>
                        </div>
                        <Button as-child>
                            <Link :href="route('plans.index')">
                                <Plus class="size-4" />
                                Create
                            </Link>
                        </Button>
                    </div>
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="flex flex-1 flex-col gap-6 rounded-xl border border-sidebar-border/70 p-5 md:min-h-min dark:border-sidebar-border">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">My Trips</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-normal text-foreground">Your saved routes</h1>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Continue planning one of your saved routes with its stops and map preview ready to go.
                    </p>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <article
                        v-for="trip in myTrips"
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

                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="stop in trip.stops"
                                    :key="stop"
                                    class="rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ stop }}
                                </span>
                            </div>

                            <Button as-child>
                                <Link :href="route('plans.index')">Open trip</Link>
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
        </div>
    </AppLayout>
</template>
