# Day 3 Bugs and Deployment Notes

## Resolved in Day 17

- Active knowledge data is now kept outside the web root, and legacy files are
  migrated and removed after verification. A server-level Nginx deny rule for
  the old uploads directory remains defense in depth.

## Open verification

- Run the CRUD and corrupted-JSON checks against a real WordPress install.
- Verify migration, concurrent requests, and direct URL denial on the target
  server.
