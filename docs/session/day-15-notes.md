# Day 15 Notes — Accessibility and Mobile

## Implemented

- Made the chat a labeled modal dialog; opening moves focus inside, Tab and
  Shift+Tab stay within the chat, Escape closes it, and closing restores focus
  to the launcher.
- Kept new replies in the polite chat log and exposed the typing indicator as
  a status message.
- Added a consistent high-contrast visible focus indicator.
- Selects black or white foreground text for the configured primary color
  using relative luminance, maintaining readable contrast on header, launcher,
  and visitor messages.
- Increased close and suggestion controls to at least 44px touch targets.
- Made the chat full-screen below the small-screen breakpoint, hid the
  launcher while that view is open, and applied safe-area insets.
- Respects `prefers-reduced-motion` for transitions, animations, and scrolling.

## Verification

- Production Vite build, bundled JavaScript syntax check, and
  `git diff --check` pass.
- `composer lint` passes.
- Compiled CSS includes mobile safe-area rules, reduced-motion handling, and
  focus-visible styles.
- No live WordPress/browser session was available for hands-on keyboard,
  screen reader, or device testing.

## Manual checklist

- Keyboard: open the launcher, confirm focus enters the chat, Tab and
  Shift+Tab wrap inside it, Escape closes it, and focus returns to the launcher.
- Screen reader: confirm dialog name, message-log updates, typing status, and
  suggestion button labels are announced.
- Mobile: test at 320px width and with safe-area insets; confirm full-screen
  layout with no horizontal scrolling.
- Motion/contrast: enable reduced motion and test light/dark primary colors.

## Handoff for Day 16

The widget still uses `--bwfc-primary-color` and now derives
`--bwfc-primary-text-color` for readable foreground text. Day 16 can centralize
these variables with the settings-driven theme implementation.

Commit message: `feat(widget): improve accessibility and mobile layout`
