# Day 6 Notes — Public Chat REST Endpoint

## Implemented

- Added `Bryvium_Rest`, registered through the loader on `rest_api_init`.
- Registers `POST /wp-json/bryvium/v1/chat`.
- Requires a valid `X-WP-Nonce` for the `wp_rest` action.
- Validates and sanitizes the required `question` string; rejects empty
  questions and questions longer than 300 characters.
- Calls `Bryvium_Storage::get_all()` and `Bryvium_Search::search()`.
- Returns a consistent response with `status`, `answer`, `confidence`, and
  `suggestions`. Storage/search failures are replaced with generic errors;
  internal paths and details are not returned.

## Verification

- `composer lint` and PHP syntax checks pass.
- Focused PHP tests cover route registration, valid and invalid nonce,
  question validation, successful/no-match responses, and generic storage
  errors.
- Validate the nonce in a running WordPress request once the frontend
  provides the REST nonce.

## Handoff

Route: `POST /wp-json/bryvium/v1/chat`, with JSON `{ "question": "..." }`
and an `X-WP-Nonce` header. Response fields are `status`, `answer`,
`confidence`, and `suggestions`; errors use stable `bryvium_wfc_rest_*`
codes with HTTP 400, 403, or 500 status.
