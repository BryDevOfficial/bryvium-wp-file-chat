# Day 7 Bugs and Deployment Notes

## Known limitations

- Rate limiting depends on the WordPress transient backend; verify that the
  production object cache/database can persist transients.
- The limiter trusts only the direct `REMOTE_ADDR` connection. Sites behind a
  reverse proxy may share one bucket unless the web server sets a trustworthy
  client address. `X-Forwarded-For` is deliberately not trusted.
- File cache invalidation covers all writes made through this storage class.
  External file edits are detected by `filemtime` and size; an external edit
  that preserves both metadata values may remain cached until expiration.

No known open Day 7 defects from the focused checks.
