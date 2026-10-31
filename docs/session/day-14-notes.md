# Day 14 Notes — API Integration and Chat Logic

## Implemented

- Added `src/api/client.js` to POST questions to `bryvium/v1/chat` with the
  WordPress REST nonce, a 15-second abort timeout, response validation, and
  friendly network, rate-limit, timeout, and server errors.
- Added `src/hooks/useChat.js` to own conversation state, prevent concurrent
  sends, show API answers and no-match responses, and optionally restore/save
  conversation history in `sessionStorage`.
- Wired `App` to the hook and passed the loading state through the chat window.
- Reused the configured admin fallback message for no-match responses.
- Added a typing indicator, disabled input during requests, Unicode-aware
  300-character enforcement, and clickable related-question suggestions.
- Rendered detected HTTP(S) links as safe text-based anchors; no raw HTML is
  rendered.
- Updated the project status and this session index.

## Verification

- Production Vite build and bundled JavaScript syntax check pass.
- `composer lint` and `git diff --check` pass.
- Mocked REST-client checks pass for URL, nonce, request body, success, HTTP
  429, offline network, and timeout behavior.
- Live WordPress/theme checks remain outstanding.

## Handoff for Day 15

`useChat()` returns `{ messages, isLoading, sendMessage }`; `sendMessage(text)`
returns `false` when blocked and starts a request otherwise. Assistant message
objects may include `suggestions` with `id` and `title` fields.
