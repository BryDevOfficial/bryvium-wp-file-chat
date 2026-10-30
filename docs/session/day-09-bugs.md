# Day 9 Bugs and Integration Notes

No known open defects from static checks and focused admin-hook checks.

## Dependency and remaining verification

- Entry listing and mutations require the D10 admin REST routes; until then,
  the screen reports the REST API error and does not simulate successful saves.
- In WordPress, verify the Knowledge menu and asset loading only on that
  screen, administrator access, REST nonce requests, and narrow-screen layout.
