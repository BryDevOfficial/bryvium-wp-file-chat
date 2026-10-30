# Day 6 Bugs and Integration Notes

## Integration requirement

The route requires a valid WordPress REST nonce in `X-WP-Nonce`. The frontend
must receive and send that nonce; the widget enqueue/config work is scheduled
for a later day.

## Open verification

- Exercise the route through a running WordPress REST API with valid and
  invalid nonce headers.
- Confirm the deployed site's cache/CDN does not serve a stale nonce.

No known open Day 6 defects from the focused endpoint tests.
