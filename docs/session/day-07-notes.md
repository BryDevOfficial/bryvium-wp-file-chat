# Day 7 Notes — Rate Limiting and Cache

## Implemented

- Added `Bryvium_Rate_Limiter::check( $limit = null )`, a per-client,
  60-second transient counter. The default is 20 requests/minute; the future
  `bryvium_wfc_settings.rate_limit_per_minute` option can override it, clamped
  to 1–120.
- Rate-limit keys are HMAC hashes of the direct `REMOTE_ADDR` using WordPress
  salts; raw IP addresses are not stored. Forwarded-IP headers are ignored.
- The REST endpoint rejects raw bodies larger than 8 KiB with HTTP 413.
- Rate-limited REST responses use HTTP 429 and include a `Retry-After` header
  plus a `retry_after` JSON field.
- Storage caches parsed JSON in transients keyed by file path, `filemtime`,
  and file size. Successful atomic writes invalidate the cached document.
- The limiter class is loaded by the plugin entry point.

## Verification

- PHP lint and `composer lint` pass.
- Focused PHP tests cover transient limits/window reset, hashed client keys,
  spoofed proxy headers, payload rejection, 429 headers, storage cache hits,
  and invalidation after writes.

## Handoff

Use `Bryvium_Rate_Limiter::check( $limit = null )`; it returns `true` or
`WP_Error` with `status` and (for 429) `retry_after`. Storage cache
invalidation occurs after every successful `write_document()` replacement.
