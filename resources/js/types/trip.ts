/** Named coordinates avoid coupling the contract to Leaflet's tuple format. */
export interface TripPoint {
    point_lat: number;
    point_lng: number;
}

/** Array position defines waypoint order; ownership is supplied by the server. */
export interface CreateTripInput {
    name: string;
    waypoints: (TripPoint & { name?: string | null })[];
}

export interface UpdateTripInput {
    name: string;
    notes: string | null;
    tags: string[];
}

export interface TripWaypoint extends TripPoint {
    id: number;
    order: number;
    name: string | null;
    description: string | null;
    /** Preset selections and custom tags are stored as names on this waypoint. */
    tags: string[];
}

/** Editor response contract, not a serialized Eloquent model. */
export interface Trip {
    id: number;
    name: string;
    notes: string | null;
    distance: number | null;
    duration: number | null;
    waypoints: TripWaypoint[];
    tags: string[];
}

export interface NotesTripCard extends Pick<Trip, 'id' | 'name' | 'notes' | 'distance' | 'duration'> {
    stops: string[];
}
