# Day 8 Notes — Admin Menu and Settings

## Implemented

- Added the `bryvium_wfc_settings` option with defaults for all ten widget and
  protection settings.
- Registered the option through the WordPress Settings API and added
  sanitization for text, color, position, checkboxes, confidence threshold,
  and request limit.
- Added a `manage_options`-restricted submenu under Settings and a Settings
  shortcut on the plugin row.
- Created an escaped settings form with the Settings API nonce and hidden
  checkbox values so unchecked options can be saved as off.
- Wired settings and admin classes into the plugin bootstrap and loader.

## Verification

- `composer lint` passes.
- PHP syntax checks and focused PHP smoke tests pass.
- No live WordPress installation was available for browser-based save and
  capability checks.

## Handoff

Use `Bryvium_Settings::get()` for defaults and stored values. The option keys
are `widget_title`, `greeting`, `fallback_message`, `placeholder`,
`primary_color`, `position`, `auto_display`, `confidence_threshold`,
`rate_limit_per_minute`, and `save_history`.
