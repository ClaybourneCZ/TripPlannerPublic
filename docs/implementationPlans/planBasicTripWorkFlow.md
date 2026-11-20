> Update: trip descriptions now use only `notes`; the separate `summary` field
> from this original plan has been removed. See `docs/decisions.md`.

# Basic trip workflow implementation plan

This document describes planned implementation, not completed functionality.
It follows the [Basic trip workflow](../scenarions.md) scenario: a user creates
and names a trip, selects waypoints, saves it, finds it on the dashboard,
edits its name and notes, adds tags, and deletes the trip.

## Current state

- Migrations and models for `Trip`, `Waypoint`, and `WaypointTag`, along with
  the `User::trips()` relationship, already exist. `TripPersistenceTest`
  covers the persistence foundation.
- The trip editor is served by `TripController` at `/trips/create` and
  `/trips/{trip}/edit`; the legacy `/plans` endpoint has been removed.
- The dashboard now displays the authenticated user's saved trips.
- `NotesController` does not yet support saving notes.
- Waypoint tags remain separate from trip tags. Step 1 adds persistence for
  trip notes and tags; HTTP writes and editor integration are still pending.

## Scope and proposed rules

The first milestone is: **select a route on the map → save → refresh the page
→ the route remains available and appears on the dashboard**. The next
milestone completes the editing and deletion steps in the scenario.
Sharing, exporting saved routes, and recommendations are outside this plan.

Use the following proposed defaults for implementation. These are new decisions
for this workflow, not existing application constraints:

- A trip is private and belongs to the authenticated user.
- Its name contains 1–255 characters after trimming leading and trailing spaces.
- A saved trip contains 2–100 points. An empty editor represents work in
  progress that cannot yet be saved.
- Points contain finite numeric coordinates within latitude ±90 and longitude
  ±180. The server assigns their order from their position in the input array,
  starting at zero.
- Notes for the entire trip will be plain text in a new nullable `trips.notes`
  column, with a maximum of 10,000 characters. `summary` remains a brief
  overview; waypoint notes remain separate.
- Tags for the entire trip will use a new `trip_tags` table, a `TripTag` model,
  and a `Trip::tags()` relationship. They will neither replace `WaypointTag`
  nor require a shared tag catalog. Allow at most 20 tags per trip, each with
  a name of 1–50 characters after trimming spaces. Reject exactly matching
  names after trimming as duplicates.
- Distance and duration will remain `null` for this milestone. Calculating
  and storing them is not a prerequisite for successfully saving route points.

## 1. Prepare the data contract and database additions

- [x] Introduce trip and editor input types independent of `TripSample` and
  Leaflet, for example in `resources/js/types/trip.ts`.
- [x] Accept only a name and an array of points with coordinates when creating
  a trip. Accept the name, notes, and tag names when updating metadata.
  Do not accept ownership, sharing status, or IDs of other trips' tags.
- [x] Add new migrations for `trips.notes` and `trip_tags`, including a required
  `trip_id`, a foreign key with cascading deletion, and a unique constraint
  on `trip_id` + `name`. Add the model and relationships.
- [x] Do not modify previously applied migrations or reset an existing database.
  If earlier empty migrations have already run, prepare a separate upgrade
  for the actual database state, following the warning in `decisions.md`.
- [x] Record new decisions about notes, tags, and limits in `docs/decisions.md`
  when implementing them.

Step 1 defines input shapes only; runtime allowlists and validation belong to
step 2. The new migrations upgrade the implemented V2 schema. No existing
database has been reset or migrated during this change; installations that ran
the earlier empty migrations still need a schema-specific upgrade.

Outcome: an explicit contract and a schema capable of storing the full scenario.

### Waypoint tag selection follow-up

The notes prototype offers predefined waypoint tags from the database table
`waypoint_tag_presets`, populated by `WaypointTagPresetSeeder` (including
Restaurant and Lunch). Each waypoint can have multiple selections; waypoint #3
can have both Restaurant and Lunch without the user creating those options.
The controller reads the catalog and the UI still represents assignments as
names, separate from trip tags. Both selected preset names and custom names
must be saved as `waypoint_tags` rows for the owning waypoint. Custom names must
not be inserted into `waypoint_tag_presets`, and assignment changes must never
modify catalog entries. The existing models support this persistence.
Save notes currently keeps all assignments in browser local storage. Future
waypoint editing must connect both kinds to validated, authorized HTTP saving.
Assignment uses names rather than catalog IDs. The planned `TripWaypoint`
response includes all assigned names, including custom ones.

- [ ] When connecting waypoint saving, persist preset and custom tag names in
  the same transaction through the authorized waypoint's `tags()` relationship.
- [ ] Verify through HTTP tests that both kinds survive reloading, custom names
  do not appear in the catalog, and users cannot change another user's tags.

## 2. Validate inputs and protect access

- [x] Add `StoreTripRequest` and `UpdateTripRequest` with explicit allowlists
  of fields, including keys within individual points.
- [x] Validate the rules above on the server. Return standard Laravel validation
  responses so that Inertia can display errors next to the relevant fields.
- [x] Protect all new routes with `auth` middleware; preserve the existing
  `verified` requirement on the dashboard.
- [x] Scope reads, updates, and deletions through
  `$request->user()->trips()`. Return 404 for both nonexistent IDs and trips
  owned by another user. Route model binding alone is not authorization.
- [x] Derive ownership from the session when creating a trip. Client-supplied
  `user_id` and `is_public` must not change the trip's owner or privacy.

The Form Requests and their behavior are covered by HTTP tests using routes
registered only in the test environment. `StoreTripRequest` accepts only a
trimmed name and 2–100 points with explicit latitude/longitude keys.
`UpdateTripRequest` accepts the trimmed name, nullable notes and up to 20
unique trimmed tag names, and checks trip ownership through the current user.
The existing dashboard keeps its `verified` middleware. Production trip routes now use these requests; ownership and authentication are
checked for read, update and delete, and the store action derives the owner
from the session.

Outcome: validated input contracts and authenticated, owner-scoped trip operations.

## 3. Implement trip creation and loading

- [x] Add a `TripController` with thin HTTP actions and an
  `app/Application/Trips/CreateTrip` use case that accepts explicit typed
  input and the authenticated user's ID, rather than an HTTP request.
- [x] Create the trip through its owner's relationship, then create its
  waypoints, all within one transaction. A failed waypoint write must also
  roll back the creation of the trip.
- [x] Use Eloquent for this simple CRUD functionality; do not introduce generic
  repositories or empty domain layers. If separate business rules emerge,
  isolate them from the framework in `app/Domain/Trips`.
- [x] After creation, redirect to the saved trip's editor using its database
  ID and show a success confirmation. Reopening the editor must load data
  from the database.
- [x] Explicitly select the required fields and ordered waypoints for Inertia
  props; do not automatically expose the entire model or the owner's details.

Proposed new routes:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/trips/create` | Empty editor for a new trip |
| POST | `/trips` | Save a new trip and its points |
| GET | `/trips/{trip}/edit` | Load the user's own trip into the editor |
| PATCH | `/trips/{trip}` | Update the name, notes, and tags |
| DELETE | `/trips/{trip}` | Delete the user's own trip |

The dashboard Create link opens the new database-backed editor. Existing
`plans` routes and sample links still open the browser-only prototype; replacing
those sample flows belongs to the dashboard integration in step 4.

## 4. Display real trips on the dashboard

- [x] Use a controller to load only the authenticated user's trips, sorted by
  their most recent update and paginated, for example with 12 items per page.
- [x] Select the data needed for cards and map previews. Eager-load the required
  relationships to avoid a separate query for each card.
- [x] Replace `tripSamples` in `Dashboard.vue` with typed Inertia props.
  Display unknown distance and duration as unavailable values.
- [x] Add an empty state with a link to create a trip, and links from the cards
  to the editor for the actual saved trip.

Outcome: a newly saved trip appears on the dashboard even after refreshing,
and another user cannot see it in their own list.

## 5. Connect the Vue editor to persistence

- [x] Use an Inertia form and replace the current `saveTrip()` implementation,
  which only saves to `localStorage`, with a database write.
- [x] Start a new editor empty; initialize an existing trip from props.
  Old `localStorage` data does not overwrite a saved trip. Treat any future
  support for local drafts as a separate feature.
- [x] Explicitly convert coordinates between the contract and Leaflet's
  `[lat, lng]` format. Keep state in the editor and communicate component
  changes through props and emits.
- [x] Prevent duplicate submissions while a request is in progress. Show success
  only after server confirmation. Preserve the name and selected points on error.
- [x] Display validation errors, saving progress, unsaved changes, and network
  errors. After the first save, switch to the existing ID so that subsequent
  edits do not create another trip.
- [x] A failure of the external routing service must not prevent valid points
  from being saved. Prevent stale routing responses from overwriting a newer
  route when loading data.

The database editor now handles new and saved trip links. Trip
points can be edited after the first save; metadata editing is separate.
Map routing errors draw a direct dashed line between saved points. The map
service can still fail to draw a routed line, but the point data remains saved.

This completes the first milestone: creating, saving, and reloading a trip.

## 6. Edit the name, notes, and tags

- [x] Add a form for the trip's name, notes, and tags to the editor.
  Point the dashboard's “Modify notes” link to this section of the editor;
  keep it distinct from the existing waypoint notes prototype.
- [x] Implement metadata and tag updates in a single transaction.
  Create tags only through the authorized trip's relationship.
- [x] After saving, display confirmation and the loaded values; show the updated
  name when returning to the dashboard. Render notes and tag names as text.
- [x] Leave waypoints unchanged when updating metadata. Changing points after
  the first save is outside this scenario, so display saved trip points
  without editing controls until a separate route update use case is added.

Outcome: changes to the name, notes, and tags survive a page refresh.

## 7. Delete a trip

- [x] Add a delete button with a confirmation that includes the trip's name.
- [x] Process `DELETE` after authorization; never delete through GET.
- [x] Use database cascades for waypoints, their tags, and the new trip tags.
- [x] On success, redirect to the dashboard with confirmation. On error, keep
  the trip visible and allow retrying; disable further submissions during
  the request.

Outcome: the trip disappears from the dashboard and its original URL returns 404.

## 8. Tests and completion checks

Add tests alongside each implementation step. Preserve the existing
`TripPersistenceTest` and add feature tests for the HTTP workflow:

- [x] Create a trip with the correct owner, privacy, and point order, then
  load the same values through Inertia.
- [x] Reject empty names, invalid coordinates, exceeded limits, and invalid
  tags without leaving partially saved records.
- [x] Roll back the entire operation if a waypoint or tag write fails.
- [x] An unauthenticated user cannot create, load, edit, or delete a trip.
- [x] Another user cannot see the trip in their list or load, edit, or delete
  it; forged ownership or public visibility values have no effect.
- [x] Name, note, and tag updates survive reloading and do not change waypoints.
- [x] Deletion removes all child records and preserves other users' trips.
- [x] The dashboard correctly displays the user's own trips, pagination,
  and the empty state.

Automated checks passed for the changed code. Full-repository Pint and
Prettier checks report existing style issues in unrelated files; the changed
files pass their scoped checks. Manual browser acceptance remains outstanding.

Run backend tests against SQLite in memory. Replace external map services at
their boundary in tests instead of making live calls. Run the affected tests
first, then `composer test` and `vendor/bin/pint --test`. For the frontend, run
`npx eslint .`, `npm run format:check`, `npx vue-tsc --noEmit`, and
`npm run build`; also run `npm run build:ssr` when SSR changes. Distinguish
existing check failures from new regressions.

Manual acceptance walkthrough:

1. Sign in, open a new editor, enter a name, and select at least two points.
2. Save, refresh the page, and verify the name, coordinates, and point order.
3. Open the dashboard and return to the saved trip.
4. Change the name, add notes and tags, save, and refresh again.
5. Trigger a validation error and a network error: work in progress remains intact.
6. Use a second account to verify that the trip is inaccessible, even by its URL.
7. Delete the trip from the original account, then check the dashboard and
   confirm that the original URL is no longer accessible.

After completion, update `README.md` and `docs/architecture.md` to reflect the
implemented behavior. A successful build complements tests and the acceptance
walkthrough; it does not establish correctness of the entire workflow on its own.
