# Day 9 Notes — Knowledge Manager UI

## Implemented

- Added a Knowledge submenu restricted to `manage_options`.
- Added a searchable, category-filterable, paginated entries table and a
  responsive form for adding or editing title, answer, comma-separated
  keywords, category, and enabled state.
- Added edit and confirmed delete actions using safe DOM text rendering.
- Added REST requests with the WordPress REST nonce for GET/POST/PUT/DELETE
  `/wp-json/bryvium/v1/entries` routes.
- Enqueued versioned admin CSS and JavaScript only on the Knowledge screen.
- Added validation for required fields and the maximum 20 keywords.

## Verification

- `composer lint` and PHP syntax checks pass.
- JavaScript syntax plus simulated create, edit, delete, and required-field
  checks pass; focused admin-hook and rendered-view smoke checks pass.
- The D10 admin REST routes are not present yet; the screen displays API
  errors gracefully until those routes are implemented.
- No live WordPress installation was available for browser and mobile checks.

## Handoff

The JavaScript uses `#bwfc-knowledge-*` IDs, config global
`BryviumAdminConfig`, and REST URL `bryvium/v1/entries`. GET sends `search`,
`category`, `page`, and `per_page`; POST/PUT send `title`, `answer`,
`keywords` (array), `category`, and `enabled`; DELETE uses the entry ID path.
List responses should provide an `entries` array and `total_pages` or `pages`,
or set the `X-WP-TotalPages` header.
