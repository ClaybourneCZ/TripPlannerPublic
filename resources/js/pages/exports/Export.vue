<script setup lang="ts">
import FeatureAction from '@/components/FeatureAction.vue';
import { useFeatures } from '@/composables/useFeatures';
import { tripSamples } from '@/data/tripSamples';
import AppLayout from '@/layouts/AppLayout.vue';
import { downloadTripFile, getTripMapAppUrl, printTripAsPdf, type TripExportFormat, type TripMapApp } from '@/services/tripExport';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Download, ExternalLink, MapPin, Printer, Route } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const { isEnabled } = useFeatures();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', feature: 'dashboard', href: '/dashboard' },
    { title: 'Export trip', href: '/dashboard/export' },
];

const selectedTripId = ref(tripSamples[0]?.id ?? null);
const selectedTrip = computed(() => tripSamples.find((trip) => trip.id === selectedTripId.value));
const download = (format: TripExportFormat) => {
    if (isEnabled('export') && selectedTrip.value) downloadTripFile(selectedTrip.value, format);
};
const downloadPdf = () => {
    if (isEnabled('export') && selectedTrip.value) printTripAsPdf(selectedTrip.value);
};
const mapAppUrl = (app: TripMapApp) => (selectedTrip.value ? getTripMapAppUrl(selectedTrip.value, app) : '#');
</script>

<template>
    <Head title="Export trip" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-4 p-4 md:gap-4 md:p-5">
            <div>
                <FeatureAction feature="dashboard" variant="ghost" size="sm" class="mb-1 -ml-3" :href="route('dashboard')"
                    ><ArrowLeft class="size-4" />Back to dashboard</FeatureAction
                >
                <p class="text-xs font-medium text-muted-foreground">Take your itinerary with you</p>
                <h1 class="mt-0.5 text-2xl font-semibold tracking-tight">Export a trip</h1>
                <p class="mt-1 max-w-2xl text-sm leading-5 text-muted-foreground">
                    Pick a saved trip, download its waypoints, or open the route in your preferred map app.
                </p>
            </div>

            <section class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-xs dark:border-sidebar-border">
                    <div>
                        <label for="export-trip" class="text-sm font-semibold">Choose a trip</label>
                        <p class="mt-0.5 text-xs text-muted-foreground">Select which itinerary to include in your export.</p>
                        <select
                            id="export-trip"
                            v-model="selectedTripId"
                            class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option v-for="trip in tripSamples" :key="trip.id" :value="trip.id">{{ trip.name }}</option>
                        </select>
                    </div>

                    <div v-if="selectedTrip" class="rounded-lg bg-muted/50 p-3">
                        <h2 class="font-semibold">{{ selectedTrip.name }}</h2>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">{{ selectedTrip.notes }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 bg-background px-2.5 py-1.5 dark:border-sidebar-border"
                            >
                                <Route class="size-4" />{{ selectedTrip.distance }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 bg-background px-2.5 py-1.5 dark:border-sidebar-border"
                            >
                                <MapPin class="size-4" />{{ selectedTrip.waypoints.length }} stops
                            </span>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold">Download a file</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Save your trip for use in another navigation or mapping tool.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <div class="text-lg font-semibold">PDF</div>
                                <p class="mt-1 text-sm leading-5 text-muted-foreground">Print-ready itinerary. Save it as a PDF.</p>
                                <FeatureAction feature="export" class="mt-4 w-full" :disabled="!selectedTrip" @click="downloadPdf"
                                    ><Printer class="size-4" />Save as PDF</FeatureAction
                                >
                            </div>
                            <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <div class="text-lg font-semibold">GPX</div>
                                <p class="mt-1 text-sm leading-5 text-muted-foreground">Route stops and descriptions for GPS apps.</p>
                                <FeatureAction feature="export" class="mt-4 w-full" :disabled="!selectedTrip" @click="download('gpx')"
                                    ><Download class="size-4" />Download GPX</FeatureAction
                                >
                            </div>
                            <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <div class="text-lg font-semibold">KML</div>
                                <p class="mt-1 text-sm leading-5 text-muted-foreground">Route line and individual map stops.</p>
                                <FeatureAction
                                    feature="export"
                                    class="mt-4 w-full"
                                    variant="outline"
                                    :disabled="!selectedTrip"
                                    @click="download('kml')"
                                    ><Download class="size-4" />Download KML</FeatureAction
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-xs dark:border-sidebar-border">
                    <h2 class="font-semibold">Open in a map app</h2>
                    <p class="mt-1 text-sm leading-5 text-muted-foreground">
                        Continue with this route in a navigation app. The selected trip’s stops will be included.
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <a
                            :href="mapAppUrl('mapy')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center justify-between rounded-md border border-sidebar-border/70 px-3 py-3 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                        >
                            Mapy.com <ExternalLink class="size-4 text-muted-foreground" />
                        </a>
                        <a
                            :href="mapAppUrl('google')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center justify-between rounded-md border border-sidebar-border/70 px-3 py-3 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                        >
                            Google Maps <ExternalLink class="size-4 text-muted-foreground" />
                        </a>
                        <a
                            :href="mapAppUrl('apple')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center justify-between rounded-md border border-sidebar-border/70 px-3 py-3 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                        >
                            Apple Maps <ExternalLink class="size-4 text-muted-foreground" />
                        </a>
                    </div>
                </aside>
            </section>
        </main>
    </AppLayout>
</template>
