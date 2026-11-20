import type { TripSample } from '@/data/tripSamples';

export type TripExportFormat = 'gpx' | 'kml';
export type TripMapApp = 'mapy' | 'google' | 'apple';

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

const escapeHtml = (value: string) => value.replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
})[character] as string);

// Opens a print-ready itinerary. The browser's print dialog can save it as PDF.
export const printTripAsPdf = (trip: TripSample) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return false;

    const stops = trip.waypoints.map((waypoint, index) => `
        <article class="stop">
            <div class="stop-number">${index + 1}</div>
            <div>
                <h2>${escapeHtml(waypoint.name ?? 'Unnamed stop')}</h2>
                <p>${escapeHtml(waypoint.description)}</p>
                ${waypoint.tags?.length ? `<div class="tags">${waypoint.tags.map((tag) => `<span>${escapeHtml(tag)}</span>`).join('')}</div>` : ''}
                <small>${waypoint.point[0].toFixed(4)}, ${waypoint.point[1].toFixed(4)}</small>
            </div>
        </article>`).join('');

    printWindow.document.write(`<!doctype html>
        <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">
        <title>${escapeHtml(trip.name)} — TripPlanner</title>
        <style>
            * { box-sizing: border-box; } body { margin: 0; color: #17211d; font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
            main { max-width: 760px; margin: 48px auto; padding: 0 32px; } .eyebrow { color: #537461; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
            h1 { margin: 8px 0; font-size: 34px; line-height: 1.15; } .notes { color: #53615a; font-size: 16px; }
            .facts { display: flex; gap: 24px; margin: 24px 0 32px; padding: 14px 0; border-block: 1px solid #dce4df; font-weight: 600; }
            .stop { display: grid; grid-template-columns: 34px 1fr; gap: 14px; padding: 18px 0; border-bottom: 1px solid #e5ebe7; break-inside: avoid; }
            .stop-number { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 50%; background: #eaf2ec; color: #315c43; font-weight: 700; }
            h2 { margin: 2px 0 4px; font-size: 18px; } .stop p { margin: 0 0 8px; color: #53615a; }
            small { color: #758078; } .tags { display: flex; gap: 6px; margin-bottom: 8px; } .tags span { border-radius: 12px; background: #f0f4f1; padding: 2px 9px; color: #53615a; font-size: 11px; }
            footer { margin-top: 28px; color: #758078; font-size: 11px; }
            @media print { main { margin: 0 auto; padding: 18mm 12mm; } }
        </style></head><body><main>
        <div class="eyebrow">TripPlanner · Itinerary</div><h1>${escapeHtml(trip.name)}</h1>
        <p class="notes">${escapeHtml(trip.notes)}</p>
        <div class="facts"><span>${escapeHtml(trip.distance)}</span><span>${escapeHtml(trip.duration)}</span><span>${trip.waypoints.length} stops</span></div>
        <section>${stops}</section><footer>Prepared with TripPlanner</footer>
        </main><script>window.addEventListener('load', () => window.print());<\/script></body></html>`);
    printWindow.document.close();
    return true;
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
