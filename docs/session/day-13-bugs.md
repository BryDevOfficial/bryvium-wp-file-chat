# Day 13 Bugs and Integration Notes

## Fixed during verification

- Corrected Tailwind v4 prefixed variant ordering (`bwfc:hover:...`,
  `bwfc:focus:...`, and related states). The first production build omitted
  those interaction-state utilities; the rebuilt CSS contains the expected
  hover, focus-visible, placeholder, disabled, dark-mode, and layout utilities.

## Resolved in Day 14

- Replaced the local placeholder reply with the WordPress chat REST integration.

No known open defects from the production build and static checks.

## Remaining verification

- Test the widget on light and dark WordPress themes and on narrow mobile
  viewports.
