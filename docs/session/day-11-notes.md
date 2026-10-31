# Day 11 Notes — Knowledge Import and Export

## Implemented

- Added an administrator-only Import / Export screen with JSON download,
  file-upload and paste import, merge and replace modes, a replace
  confirmation, and an import summary.
- Added authenticated `GET /wp-json/bryvium/v1/export` and
  `POST /wp-json/bryvium/v1/import` routes; both use the existing capability
  and REST nonce check.
- Capped imports at 1 MiB and strictly validated schema version, document
  shape, entry IDs, types, lengths, keyword count, timestamps, and duplicate
  IDs. Invalid input is rejected without changing stored data.
- Added atomic merge/replace support in `Bryvium_Storage`. Merge matches by ID
  and skips unchanged entries. Replace writes a timestamped backup while
  holding the storage lock, then atomically replaces the data document.
- Export uses the same `schema_version` and `entries` document format as
  protected storage, preserving entry IDs for restore.

## Response shape

Successful import returns `{ "summary": { "added": 0, "updated": 0,
"skipped": 0, "errors": [], "backup": "" } }`. Replace returns the backup
filename. Validation errors include a helpful message and, for invalid
entries, an `errors` list. Export downloads `bryvium-knowledge.json`.

## Verification

- `composer lint`, PHP syntax checks, JavaScript syntax check, and
  `git diff --check` pass.
- Focused PHP tests cover export/restore, idempotent merge, merge add/update,
  strict validation, the 1 MiB limit, atomic replace, and pre-replace backup.
- REST route permission and the UI paste/replace-confirmation flows were
  separately smoke-tested with request stubs.
- No live WordPress installation was available for end-to-end file upload and
  download testing.

## Handoff for Day 12

`Bryvium_Importer::export_document()` produces the schema-versioned document;
`Bryvium_Importer::import( $contents, $mode )` validates it and returns the
summary above. Admin routes are GET `/export` and POST `/import`.
