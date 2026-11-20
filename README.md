# TripPlanner

A Laravel and Vue application for planning trips on a map, built as a personal
student portfolio and learning project. Version **0.5** connects the trip editor,
dashboard, and Notes page to the database, with private saved trips, waypoints,
notes, and tags.

## Live demo

TBA

## Tech stack

- Laravel 12 and PHP 8.2+
- Vue 3 and TypeScript
- Inertia.js
- Tailwind CSS
- Leaflet and OpenStreetMap
- SQLite by default; database connection can be configured for other supported
  relational databases

## About this repository

This public repository presents selected parts and snapshots of the TripPlanner
project for my student portfolio. The production application is developed in a
private repository. Some modules, infrastructure code, and production-specific
implementation details are intentionally not published. The public repository
may be updated periodically with selected project work.

## Featured implementation

- Laravel session-based registration, login, email verification, and account
  settings
- Inertia-powered pages connecting Laravel with Vue
- Route editor with interactive map points and route display
- Waypoint names, tags, and descriptions in the current browser-based prototype
- Database-backed waypoint tag suggestions and support for custom waypoint tags
- Shared Vue layouts and reusable interface components

The trip editor creates and reloads private database trips. Users can update
trip names, notes, and tags, or delete their own trips. The dashboard shows
the current user's saved trips, with map previews and pagination. Groups, chat,
sharing, exports, and place recommendations are ideas for future development,
not features included in this version.

## Waypoint tag catalog setup

After configuring an existing V2 database, apply migrations and seed the shared
suggestions without creating a demo user:

```sh
php artisan migrate
php artisan db:seed --class=WaypointTagPresetSeeder
```

Both selected catalog names and custom names belong in `waypoint_tags` when
saving a waypoint. Custom names never extend the shared catalog. The Notes page loads the signed-in user’s database trips and saves trip notes,
trip tags, waypoint names, descriptions, and tags through an authenticated endpoint.

## Architecture

See [docs/architecture.md](docs/architecture.md) for an introduction to the
project structure and current implementation.

## Page availability

Set these server-side variables independently in each environment (all default
to `enabled`):

```dotenv
FEATURE_DASHBOARD_STATE=enabled
FEATURE_PLAN_STATE=enabled
FEATURE_NOTES_STATE=enabled
FEATURE_RECOMMENDATIONS_STATE=enabled
FEATURE_EXPORT_STATE=enabled
FEATURE_PUBLISH_STATE=enabled
```

Each accepts `enabled`, `maintenance`, or `hidden`. Maintenance keeps navigation
visible and replaces the page with **Under maintenance**; writes return 503.
Hidden removes navigation and returns 404. In both unavailable states, related
action buttons remain visible but disabled. Existing data is preserved.
Plan controls trip creation, editing and deletion; Notes controls its own page
and saving endpoint. Export and Publish are independent of Dashboard. Turning
off Notes does not remove stored notes or change the trip editor's metadata
contract. Authentication and account settings remain available.

After changing local configuration, run `php artisan config:clear` if cached.
For production, rebuild configuration using `php artisan config:cache` on all
instances and reload long-running application processes. Changing a state needs
no migration or frontend rebuild. An already open tab refreshes its navigation
on its next response; the server enforces the current state on every request.

To add a feature, register its state, label and optional parameterless entry
route in `config/features.php`, document its environment variable, and attach
`feature:key,page` to page GET routes and `feature:key` to writes or data/download
routes. Keep authentication and authorization middleware. Add `feature: 'key'`
to navigation items and use `FeatureAction` for related actions. Add behavioral
tests. Unknown states and keys are treated as hidden. No middleware changes are
needed for ordinary new pages; new access middleware needs explicit support in
the available-page resolver before automatic redirects may select that page.

Run backend tests with `composer test` and the Vue rendering tests with
`node tests/frontend/run.mjs`. The latter uses the installed Vite/Vue tooling
without additional dependencies; it verifies rendered buttons and navigation,
not browser keyboard interaction.
