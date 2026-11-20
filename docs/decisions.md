# Database decisions

## Trip schema V2

The migrations and Eloquent models implement `db/tripplannerV2.drawio`.
The diagram leaves types, nullability, ownership keys, and deletion behavior
unspecified; the implementation uses these conventions:

- `User` has many `Trip` records through required `trips.user_id`.
- `Trip` has many `Waypoint` records through required `waypoints.trip_id`
  (`tripId` in the diagram). `Trip::waypoints()` returns them by `order`.
- `Waypoint` has many `WaypointTag` records through required `waypoint_id`.
  The table is `waypoint_tags`, following the prepared migration and Eloquent
  naming convention (`waypoints_tags` in the diagram). Tags belong to individual
  waypoints; these assignment records have no pivot table. A separate preset
  catalog was added later (see below).
- All three tables retain IDs and Laravel timestamps. Foreign keys cascade
  deletion from user to trips, waypoints, and tags.
- Trip names and tag names are required strings (up to 255 characters).
  Trip summaries, waypoint names, and waypoint descriptions are nullable.
- Distance is a nullable integer in meters; duration is a nullable integer in
  seconds. Null means the route has not been calculated yet. These values differ
  from the formatted strings in the current frontend sample data.
- Trips default to private (`is_public = false`). Ownership foreign keys and
  `is_public` are excluded from mass assignment. Create children through their
  parent's relationship; any future visibility change requires an explicit,
  authorized assignment on the server.
- Coordinates use decimal columns with seven fractional digits and float model
  casts. Model saves reject missing, nonnumeric, nonfinite, or out-of-range
  coordinates (latitude ±90, longitude ±180). Query builder bulk writes bypass
  this model validation and should not be used for unvalidated coordinates.
- Waypoint `order` is an explicit zero-based integer, unique within a trip.
  Reordering must account for that unique constraint; positions need not be
  contiguous.

This change completes the three prepared create-table migrations. On a database
where those earlier empty migrations have already run, editing them does not
alter existing tables: a separate, data-aware upgrade migration is needed.
Do not reset a database containing data to apply this change.

Only persistence is implemented. The UI still uses local state and sample data;
there are no new endpoints or public sharing features. Future HTTP writes must
add Form Requests, authorization, input limits, and validation of nonnegative
distance, duration, and order (SQLite does not enforce unsigned integer types).


## Basic trip workflow: contract and metadata persistence

Step 1 adds `resources/js/types/trip.ts` without dependencies on sample data,
Vue, or Leaflet. Creation uses `{ name, waypoints }`, where each waypoint has
named `point_lat` and `point_lng` coordinates. Array position defines order.
Metadata updates use `{ name, notes, tags }`, with tags represented as names.
Neither input includes ownership, visibility, waypoint IDs, or tag IDs.
The editor response explicitly describes trip fields and ordered waypoints;
it is not automatic serialization of an Eloquent model. Distance is in meters
and duration in seconds, with `null` representing an unknown value.

New additive migrations extend the implemented V2 schema:

- `trips.notes` is nullable plain text, separate from `summary` and waypoint
  descriptions. Existing trips receive `null` notes.
- `TripTag` uses `trip_tags` with IDs, timestamps, a required `trip_id`, and
  a name column of length 50. `Trip::tags()` and `TripTag::trip()` expose the
  relationships. Only `name` is mass assignable on a tag; create tags through
  the owning trip. There is no shared catalog or pivot table.
- The database enforces a unique `(trip_id, name)` pair and cascades deletion
  from trip to tags, including when the owner is deleted. Waypoint tags remain
  unchanged. String comparison follows the database collation; SQLite's default
  comparison is case-sensitive. Check collation before switching databases.
- Rolling back these additions removes trip notes and trip tags but preserves
  the original trip and waypoint data. It necessarily discards metadata stored
  in the removed column and table.

The workflow adopts the following limits for the upcoming Form Requests:
trimmed trip names of 1–255 characters, 2–100 waypoints, finite coordinates
within latitude ±90 and longitude ±180, nullable notes up to 10,000 characters,
and at most 20 tags with trimmed names of 1–50 characters. Exactly matching
trimmed tag names must be rejected as duplicates. Ownership and privacy are
server-controlled; order comes from array position. Distance and duration
remain `null` for this workflow's first milestone.

These input shapes and limits are a contract, not runtime validation. TypeScript
does not validate requests, and SQLite does not enforce declared string lengths.
Form Requests, trimming, authorization, input allowlists, and UI wiring remain
steps 2 onward. No new endpoint is exposed by this change.

The new migrations assume the original V2 tables exist. They do not repair
installations where the earlier empty migrations were already marked as run.
Such installations require an upgrade based on their actual schema and data;
do not reset them or mark missing schema as successfully migrated.

## Database catalog for waypoint tag suggestions

The shared offer is stored in `waypoint_tag_presets`, represented by
`WaypointTagPreset`. It has IDs, timestamps, and unique names of up to 50
characters. `WaypointTagPresetSeeder` supplies Restaurant, Lunch, Cafe,
Accommodation, Viewpoint, and Parking. It can run repeatedly without duplicating
entries or replacing existing rows. `Lunch` means the meal. The former config
list has been removed; the database is the source of truth for the offer.

The authenticated notes page reads names in alphabetical order and passes only
those names through Inertia. Reading the page never inserts defaults. An empty
catalog produces an empty offer while custom name input remains available.
There is currently no user-facing endpoint to create or edit catalog entries.

Each waypoint can select multiple suggestions or accept custom names. Both
kinds are persisted as `WaypointTag` rows in `waypoint_tags`, with the waypoint's
ID and the assigned name. For example, waypoint #3 can store Restaurant, Lunch,
and a custom Birthday stop. Only Restaurant and Lunch come from the shared
catalog; Birthday stop is saved on the waypoint but never added to the catalog.
Saving or deleting assignments must not insert, update, or delete catalog rows.

This persistence is already supported by the models and covered by tests. The
current Save notes UI still stores all assignments in browser local storage;
authorized HTTP saving has not yet been implemented. That temporary UI limitation
applies equally to preset and custom tags, not just to custom names.

This change adds a catalog for suggestions only, not persistent assignment by
catalog ID. Existing `waypoint_tags` records and their ownership are preserved.
The prototype continues to represent selected tags as names; changing a catalog
name does not rename existing assignments. `TripWaypoint.tags` follows that
contract. Trip tags remain separate. Persistent catalog references and private
user-owned catalog entries are future work, not part of this implementation.

Apply the additive migration and populate the catalog on an existing V2 database:

```sh
php artisan migrate
php artisan db:seed --class=WaypointTagPresetSeeder
```

The dedicated seeder does not create a demo user. `DatabaseSeeder` also calls it
for new development installations. Do not reset an existing database. Rolling
back this migration drops only the preset catalog and leaves existing waypoint
assignments intact. Authorized HTTP writes, validation of custom names and
limits, and ownership checks remain prerequisites for future database saving.


## Trip write request validation

`StoreTripRequest` and `UpdateTripRequest` define the initial HTTP input
contracts. The create request allows only `name` and a list of 2–100
waypoints; each point has only `point_lat` and `point_lng`. Latitude must be
between −90 and 90, longitude between −180 and 180. The update request
allows `name`, nullable `notes`, and a list of at most 20 distinct tag names.
Names and tags use Laravel's trimmed input, with maximum lengths of 255 and
50 respectively; notes have a 10,000 character maximum. Neither contract
accepts owner, visibility, IDs, or distance. Unknown top-level fields are
excluded from validated output and unknown point keys are rejected.

The update request scopes the trip to the current user's relationship and
returns 404 for foreign or missing IDs. Authentication is required by the
request and by the future production routes. Feature tests exercise these
requests over HTTP with test-only routes. Production create/update routes and
persistence are still part of the following implementation step.


## Authenticated trip creation and editing

`/trips/create`, POST `/trips`, GET `/trips/{trip}/edit`, PATCH `/trips/{trip}`
and DELETE `/trips/{trip}` require authentication. Reads, edits and deletes
select the trip through the current user's relationship and return 404 for
foreign or missing IDs. Creation takes the owner from the session. The create
use case writes the trip and ordered waypoints in one transaction. Metadata and
trip tags update together in a transaction; waypoint positions remain unchanged.
The editor receives selected trip fields, not the Eloquent model or owner data.
The legacy sample planner remains separate until the dashboard is connected to
real trips in step 4.


## Saved trip dashboard and route editor

The dashboard loads only the current user's trips, ordered by update time and
ID, paginated 12 at a time. Waypoints are eager-loaded in route order for map
previews. Unknown distance and duration remain null and show as unknown.
The empty state links to the trip creation page. Saved trip links open the
owner-scoped editor. Sample trip IDs are not treated as database IDs without
an ownership check.

The new editor starts with no points and initializes saved trips from explicit
Inertia props. It converts Leaflet `[lat, lng]` tuples to named database
coordinates on submission. It uses Inertia forms and preserves values on
validation failure. Duplicate submissions are disabled while processing; a
success state appears only after server confirmation. Saved route points are
read-only until a route update use case exists. When routing fails, the map
displays a dashed direct line between points without changing their stored
coordinates. Local draft restoration is not part of this workflow.


## Metadata updates and deletion

The trip editor has one metadata form for the name, notes, and trip tag names.
The dashboard's Modify notes link targets that form. Updating these values
replaces the trip tag set in one transaction and does not change waypoint
positions or waypoint tags. Values are read back through an owner-scoped GET.

Deletion requires a confirmation naming the trip and uses an authenticated
DELETE request. The server scopes it to the owner. Database foreign keys cascade
to waypoints, waypoint tags, and trip tags. Successful deletion redirects to
the dashboard with a one-time notice; a failed request keeps the editor visible
and permits retrying. Backend tests cover transaction rollback, data reload,
foreign access, cascading deletion, and isolation of other users' trips.

## Waypoint names in trip creation

The create request accepts an optional name of up to 255 characters for each
waypoint. Names are saved in route order with their coordinates. The trip editor
shows the points beside the map, with naming available during creation. The
sample Notes content is on its own page. Trip notes and trip tag fields are
removed from the Plan form; existing metadata remains stored when a trip name
is updated.

## Saved route editing

The owner can add, remove, rename, and reorder waypoints through the trip editor.
The update request validates 2–100 points and checks that supplied waypoint IDs
belong to the trip. Existing IDs preserve descriptions and waypoint tags; removed
points and their tags are deleted. Changes to metadata and route share a database
transaction. Updates that omit `waypoints` keep the current route.

## Trip editor location

The trip editor Inertia page is `trips/Editor` and trip creation opens at
`/trips/create`.
Saved trip editing continues at `/trips/{trip}/edit`.

## Consolidate trip text in notes

`notes` is the only trip-level description field. The upgrade migration copies
nonempty legacy `summary` text into empty notes, or appends it after existing
notes with a blank line when the texts differ, before dropping `summary`.
Identical values are not duplicated. Existing notes and waypoint descriptions
are preserved. Dashboard responses and sample trip consumers now use `notes`.
The editor still preserves notes without offering a notes input.

Apply this upgrade with `php artisan migrate`. Rollback recreates a nullable
`summary` column but leaves merged text in `notes`; the original split cannot
be reconstructed. Earlier schema sections and diagrams describe history.

## Sample trip metadata in Notes

The sample Notes page keeps its existing route cards, map, and waypoint editor.
Plan notes additionally edits the selected sample's description and trip tags.
Save notes stores trip metadata under `trip-planner:trip-metadata:<sample-id>`
in localStorage; existing waypoint arrays and their storage keys are unchanged.
The sample description is the initial value and trip tags initially start empty.
These changes do not update database trips or change the shared sample data.
Trip text is limited to 10,000 characters and 20 tags of 50 characters each.
Stored metadata is validated when loaded; storage errors are shown in the UI.


## Database-backed Notes page

Notes now replaces the sample/localStorage workflow described above. GET `/notes`
lists the owner's trips and loads the selected trip (`?trip=<id>`) with ordered
waypoints and tags. Without a selection it opens the most recently updated trip;
empty accounts receive an empty state. Foreign or missing selections return 404.
Only the selected trip's waypoint details are loaded.

PATCH `/notes/{trip}` validates and atomically saves trip notes/tags and submitted
waypoint names/descriptions/tags. Waypoint IDs must belong to that trip. Route
coordinates, order, ownership, privacy, and catalog presets cannot be modified
through this endpoint. Limits are 10,000 characters per description, 255 per
waypoint name, 100 waypoints, and 20 tags of 50 characters per trip or waypoint.
The UI prevents duplicate saves, retains edits on validation errors, and confirms
discarding unsaved changes when switching trips. Browser-local sample drafts
are not imported into database trips because their IDs have no shared identity.

## Feature availability states

Page availability uses one `enabled | maintenance | hidden` value per feature,
not independent boolean and presentation settings. Existing features default
to enabled. Invalid configured values and unknown keys fail closed as hidden.
Routes stay registered so route caching and Ziggy names are stable.

Maintenance page GET/HEAD requests return a normal 200 Inertia placeholder;
this is navigation, not successful execution of the original operation. Writes
and data/download requests return 503; hidden routes return 404. Authentication,
verification and CSRF remain in force. The maintenance response never includes
original page props or object data. Multiple feature requirements must all be
enabled to execute a controller; hidden takes precedence over maintenance.

Dashboard controls only the overview. Plan controls all trip CRUD routes;
Notes controls only its own editor endpoints, preserving existing metadata
contracts elsewhere. Export and Publish remain independent sample features.
Action buttons are visible but disabled for either unavailable state, while
navigation retains maintenance pages and omits hidden pages. No permissions
are granted by a feature state. Switching states neither deletes data nor
cancels requests already running. Client pages are not updated by push.

System redirects choose enabled pages only, or home when none is accessible.
Intended destinations are restricted to same-origin entry/settings pages;
unknown access middleware and parameterized routes use the fallback. Verified
middleware follows Laravel's MustVerifyEmail contract (the current User model
does not implement that contract); this change does not alter email policy.
