import type { TripSample } from '@/data/tripSamples';

export type TripExportFormat = 'gpx' | 'kml';
export type TripMapApp = 'mapy' | 'google' | 'apple';

// later prepare a pdf export too

const escapeXml = (value: string) => value.replace(/[<>&"']/g, (character) => ({
    '<': '&lt;',
    '>': '&gt;',
    '&': '&amp;',
    '"': '&quot;',
    "'": '&apos;',
})[character] as string);

export const prepareTripFile = (trip: TripSample, format: TripExportFormat) => {
    const contents = format === 'gpx'
        ? `<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="TripPlanner" xmlns="http://www.topografix.com/GPX/1/1"><rte><name>${escapeXml(trip.name)}</name>${trip.waypoints.map((waypoint) => `<rtept lat="${waypoint.point[0]}" lon="${waypoint.point[1]}"><name>${escapeXml(waypoint.name ?? 'Unnamed stop')}</name><desc>${escapeXml(waypoint.description)}</desc></rtept>`).join('')}</rte></gpx>`
        : `<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>${escapeXml(trip.name)}</name><Placemark><name>${escapeXml(trip.name)} route</name><LineString><tessellate>1</tessellate><coordinates>${trip.waypoints.map((waypoint) => `${waypoint.point[1]},${waypoint.point[0]},0`).join(' ')}</coordinates></LineString></Placemark>${trip.waypoints.map((waypoint) => `<Placemark><name>${escapeXml(waypoint.name ?? 'Unnamed stop')}</name><description>${escapeXml(waypoint.description)}</description><Point><coordinates>${waypoint.point[1]},${waypoint.point[0]},0</coordinates></Point></Placemark>`).join('')}</Document></kml>`;

    return {
        contents,
        mimeType: format === 'gpx' ? 'application/gpx+xml' : 'application/vnd.google-earth.kml+xml',
        filename: `${trip.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.${format}`,
    };
};

export const downloadTripFile = (trip: TripSample, format: TripExportFormat) => {
    const { contents, mimeType, filename } = prepareTripFile(trip, format);
    const fileUrl = URL.createObjectURL(new Blob([contents], { type: mimeType }));
    const link = document.createElement('a');
    link.href = fileUrl;
    link.download = filename;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(fileUrl), 1000);
};

export const getTripMapAppUrl = (trip: TripSample, app: TripMapApp) => {
    const points = trip.waypoints;
    if (points.length === 0) return '#';
    const coords = (index: number) => `${points[index].point[1]},${points[index].point[0]}`;

    if (app === 'mapy') {
        const parameters = new URLSearchParams({ start: coords(0), end: coords(points.length - 1), routeType: 'car_fast' });
        if (points.length > 2) parameters.set('waypoints', points.slice(1, -1).map((_, index) => coords(index + 1)).join(';'));
        return `https://mapy.com/fnc/v1/route?${parameters.toString()}`;
    }

    if (app === 'google') {
        const parameters = new URLSearchParams({ api: '1', origin: coords(0), destination: coords(points.length - 1), travelmode: 'driving' });
        if (points.length > 2) parameters.set('waypoints', points.slice(1, -1).map((_, index) => coords(index + 1)).join('|'));
        return `https://www.google.com/maps/dir/?${parameters.toString()}`;
    }

    const parameters = new URLSearchParams({ source: coords(0), destination: coords(points.length - 1), mode: 'driving' });
    points.slice(1, -1).forEach((_, index) => parameters.append('waypoint', coords(index + 1)));
    return `https://maps.apple.com/directions?${parameters.toString()}`;
};
