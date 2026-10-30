# Day 1 Bugs

## Resolved during review

1. **Plugin bootstrap filename was incorrect.** The plugin header and
   lifecycle code were in the root `index.php`, which WordPress does not use
   as the plugin entry file. Added `bryvium-wp-file-chat.php` as the actual
   plugin entry and restored `index.php` as a directory-listing guard.
2. **Declared minimum versions conflicted.** The plugin header claimed PHP
   8.4 and WordPress 7.0, while the runtime constants required PHP 7.4 and
   WordPress 6.0. Aligned the header and setup guide with the runtime checks:
   PHP 7.4 and WordPress 6.0.
3. **Setup instructions mixed shell syntaxes.** Rewrote the Day 1 scaffold
   commands as PowerShell so they are usable in the documented Windows
   workflow.

## Open bugs

No known open Day 1 bugs from the source and documentation review.

## Verification limitation

The PHP syntax and bootstrap smoke checks pass locally, but activation and
deactivation have not yet been exercised in a running WordPress installation.
