import type { LatLng, LatLngExpression, Control as LeafletControl, Marker, PathOptions } from 'leaflet';

declare module 'leaflet' {
    namespace Routing {
        interface Waypoint {
            latLng: LatLng;
        }

        interface LineOptions {
            styles?: PathOptions[];
        }

        interface ControlOptions {
            waypoints?: LatLngExpression[];
            routeWhileDragging?: boolean;
            fitSelectedRoutes?: boolean | 'smart';
            lineOptions?: LineOptions;
            createMarker?: (index: number, waypoint: Waypoint, totalWaypoints: number) => Marker | null;
            show?: boolean;
            addWaypoints?: boolean;
        }

        interface Control extends LeafletControl {
            on(event: 'routingerror', handler: () => void): this;
        }

        function control(options: ControlOptions): Control;
    }
}

declare module 'leaflet-routing-machine';
