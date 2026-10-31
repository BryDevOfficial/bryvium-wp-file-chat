# Day 12 Notes — React Widget Skeleton

## Implemented

- Added `Bryvium_Widget` frontend hooks. It enqueues assets only when
  `auto_display` is enabled and only outside the admin.
- Assets are versioned using `filemtime`; the script uses WordPress's footer
  and deferred loading strategy.
- Localized `BryviumChatConfig` with the REST root URL, REST nonce, widget
  title, greeting, placeholder, position, history setting, and primary color.
- Added a footer mount callback that emits `#bwfc-root` once when both build
  files are available.
- Added a capability-gated admin warning when the widget is enabled but the
  JS or CSS build asset is missing. Frontend rendering remains non-fatal.
- Replaced the build placeholder with an initial React component that reads
  localized config and renders a positioned greeting.
- Scoped Tailwind source scanning to `src/` so example utility classes in
  project documentation are not included in the production stylesheet.

## Verification

- `npm run build`, `composer lint`, PHP and JavaScript syntax checks pass.
- Focused PHP smoke checks cover auto-display gating, frontend-only enqueue,
  localized config, deferred footer script, single mount element, and missing
  build notices.
- No live WordPress theme was available for browser-level footer/theme tests.

## Handoff for Day 13

The config global is `BryviumChatConfig` with `restUrl`, `restNonce`,
`widgetTitle`, `greeting`, `placeholder`, `position`, `saveHistory`, and
`primaryColor`. The React app mounts at `#bwfc-root`.
