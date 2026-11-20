prepare proper export for all formats/maps and pdf, text, markdown etc

why not make api, so the whole backend could be used for mobile app?

how hard it would be to create app for mobile phone? android


## Deployment plan: self-hosted OSRM in Docker

This is a future deployment proposal, not an implemented configuration. The map components currently use the default public OSRM demo server. The console warning indicates that this server is not intended for production use.

TripPlanner and a self-hosted OSRM server can run on the same VM, each in a separate Docker container. OSRM provides an official Docker image. [OSRM documentation](https://github.com/Project-OSRM/osrm-backend#readme)

Proposed setup:

```text
VM with Docker Compose
├── Nginx / Caddy – HTTPS and request forwarding
├── TripPlanner – Laravel application
└── OSRM – route calculation + map data in a volume
```

The browser would call an address such as `https://example.com/routing/route/v1`. Nginx would forward the request to OSRM over the internal Docker network. Set this public HTTPS address as `serviceUrl` in Leaflet Routing Machine; the browser cannot use the internal container address `http://osrm:5000`.

The reverse proxy would strip the `/routing` prefix when forwarding requests so that OSRM receives the path `/route/v1/...`. The OSRM port does not need to be exposed directly to the internet.

Before starting OSRM, download OpenStreetMap data for the required region and prepare it for route calculation. The official workflow uses `osrm-extract`, `osrm-partition`, and `osrm-customize`, followed by the `osrm-routed` server. Data updates are the responsibility of the deployment operator. [Docker setup guide](https://github.com/Project-OSRM/osrm-backend#quick-start)

**VM sizing will mainly depend on the geographical coverage and request volume.** Start with a limited region, such as Czechia, and choose RAM and disk capacity based on measurements. Data preparation can be resource-intensive; it can run on a separate machine, with the prepared files transferred to the VM.

**OSRM calculates routes; it does not provide map tiles.** The project's OpenStreetMap base map would continue to load separately.
