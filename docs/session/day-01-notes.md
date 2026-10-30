# Day 1 Notes — Repository Setup

## Result

Day 1 bootstrap is in place. WordPress can identify the plugin from
`bryvium-wp-file-chat.php`; the root `index.php` is reserved for the
directory-listing guard.

## Implemented

- Plugin header, constants, and lifecycle hook registration.
- PHP 7.4 and WordPress 6.0 requirements are consistent between the plugin
  header and runtime checks.
- Activation records `bryvium_wfc_version`; deactivation preserves user data
  and fires `bryvium_wfc_deactivated`.
- Runtime initialization fires `bryvium_wfc_loaded` when requirements pass.
- The Day 1 setup guide and its PowerShell setup commands are aligned with the
  implementation.

## Repository state

- Branch: `feature/d01-bootstrap`.
- Baseline commit: `a169446` (`feat(build): add plugin bootstrap, constants and lifecycle hooks`).
- Changes made during this review are not committed.

## Verification

- PHP CLI is available at PHP 8.5.10.
- `php -l` checks and a focused bootstrap smoke test passed after the review
  changes.
- Full activation/deactivation behavior still needs verification in a
  WordPress installation with PHP 7.4+ and WordPress 6.0+.

## Handoff

The loader class and `bryvium_wfc_loaded` action are ready for later modules.
Continue with Day 2 tooling setup after saving or committing these changes as
desired.
