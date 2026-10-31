# Day 11 Bugs and Integration Notes

No known open defects from the focused PHP and JavaScript checks.

## Remaining verification

- On a WordPress site, test an exported download and restore it from both an
  uploaded `.json` file and pasted JSON.
- Confirm replace creates a backup in the protected `bryvium-chat` storage
  directory before changing the active document.
- Verify the web server denies direct access to backup files; Nginx needs an
  explicit deny rule because it does not read `.htaccess`.
- Confirm hosting upload limits permit the advertised 1 MiB file size.
