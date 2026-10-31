# Day 16 Notes — Theming and Shortcode

## Implemented

- Kept the saved primary color and luminance-based readable foreground color
  exposed as `--bwfc-primary-color` and `--bwfc-primary-text-color`; added
  CSS defaults as a fallback.
- Registered the `[bryvium_chat]` shortcode. It renders the widget at the
  shortcode location when automatic display is disabled.
- Enqueued widget assets when automatic display is enabled or the current
  queried post contains the shortcode.
- Kept automatic footer mounting controlled by the auto-display setting.
  Shortcode and footer rendering share a single-mount guard, including pages
  where both are enabled.
- Added settings-page guidance for shortcode-only display and automatic text
  contrast.

## Verification

- Production Vite build, JavaScript syntax check, PHP lint, and
  `git diff --check` pass.
- PHP behavior checks cover shortcode asset enqueueing with auto-display off,
  footer mounting with auto-display on, and duplicate-mount prevention.
- Live WordPress page rendering and visual color changes remain to be checked.

## Manual tests

1. Disable automatic display, add `[bryvium_chat]` to a page, and confirm the
   widget loads on that page only.
2. Enable automatic display and include the shortcode on the same page;
   confirm only one `#bwfc-root` exists.
3. Change the primary color to light and dark colors; confirm the widget
   updates its surfaces and retains readable foreground text.

## Handoff for Day 17

The public shortcode is `[bryvium_chat]`; shortcode detection on the queried
post triggers enqueues before rendering. CSS variables are
`--bwfc-primary-color` and `--bwfc-primary-text-color`.

Commit message: `feat(widget): add CSS-variable theming, shortcode and display rules`
