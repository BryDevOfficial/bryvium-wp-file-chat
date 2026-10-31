# Day 10 Notes — Admin REST Endpoints

## Implemented

- Added `Bryvium_Rest_Admin` routes in the `bryvium/v1` namespace:
  `GET` and `POST /entries`, plus `PUT` and `DELETE /entries/{id}`.
- Required a logged-in user with `manage_options` and a valid `X-WP-Nonce`
  on every route.
- Added REST argument schemas matching entry limits: title 200 characters,
  answer 4,000, up to 20 keywords of 50 characters each, category 60, and
  boolean enabled state.
- Added search across title, answer, keywords, and category; exact
  case-insensitive category filter; and bounded pagination (1–100 per page).
- Routed changes through `Bryvium_Storage`, preserving its validation,
  locking, atomic writes, and cache invalidation.
- Wired endpoint registration into the loader and plugin bootstrap.
- Updated the Knowledge UI with loading, mutation states, and inline field
  validation feedback.

## Response shapes

- List: `{ "entries": [], "total": 0, "total_pages": 1, "page": 1, "per_page": 10 }`
  with `X-WP-Total` and `X-WP-TotalPages` headers.
- Create/update: `{ "entry": { ... } }`; create returns HTTP 201 and update
  returns HTTP 200.
- Delete: `{ "deleted": true, "id": "..." }` with HTTP 200.
- Errors use `WP_Error` with a stable code, message, and HTTP status; required
  field errors also identify the affected `fields`.

## Verification

- `composer lint`, PHP syntax checks, JavaScript syntax check, and
  `git diff --check` pass.
- Focused PHP tests cover route registration, authentication/capability/nonce
  denial, list filters/pagination, validation errors, and CRUD response shapes.
- Simulated JavaScript checks cover required-field feedback and the create,
  update, and delete request flow.
- No live WordPress installation was available for end-to-end REST testing.

## Handoff for Day 11

`Bryvium_Rest_Admin` exposes the four routes above; all require the REST nonce
and `manage_options`. The list response includes pagination fields and headers;
mutations return an `entry` wrapper (or a `deleted`/`id` result).
