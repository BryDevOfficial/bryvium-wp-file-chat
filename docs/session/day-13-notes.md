# Day 13 Notes — Chat UI

## Implemented

- Added a floating launcher with open/close state, accessible labels, and
  keyboard Escape handling.
- Added a responsive chat window with header, close action, scrollable message
  log, and message input.
- Added visually distinct visitor and assistant bubbles with explicit light
  and dark palettes; the widget remains isolated under `#bwfc-root`.
- Added smooth scrolling to the newest message and a static local reply so the
  interface can be exercised before the D14 API integration.
- Kept the fixed widget above page content with a bounded z-index and no
  effect on document layout.
- Kept message state in `App`; the component tree receives props and emits
  send/close events.
- All Tailwind utility classes use the `bwfc:` prefix.

## Verification

- Production Vite build and compiled JavaScript syntax check pass.
- `composer lint` and `git diff --check` pass.
- No live WordPress theme/browser session was available for visual checks.

## Handoff for Day 14

`App` owns `messages` and passes them to `ChatWindow`, which passes them to
`MessageList`. `MessageInput` calls `onSend( text )`; D14 can replace the
temporary response in `App` with the chat hook while keeping this UI contract.
