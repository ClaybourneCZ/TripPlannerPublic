<script setup lang="ts">
import NotesSection from '@/components/waypoints/NotesSection.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { NotesTripCard, Trip } from '@/types/trip';
import { Head } from '@inertiajs/vue3';

defineProps<{ trips: NotesTripCard[]; trip: Trip | null; waypointTagPresets: string[] }>();
</script>

<template>
    <AppLayout>
        <Head title="Notes" />
        <NotesSection :key="trip?.id" :trips="trips" :trip="trip" :waypoint-tag-presets="waypointTagPresets" />
    </AppLayout>
</template>
