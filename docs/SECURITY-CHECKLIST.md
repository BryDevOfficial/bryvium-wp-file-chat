# Security Audit Checklist

Day 17 review of the plugin's REST, admin, storage, import, and frontend
surfaces. The review found one deployment-dependent storage exposure. The
storage implementation now moves data outside the document root and removes
the public legacy files during migration.

## Review results

| Area | Result | Notes |
| --- | --- | --- |
| REST request validation and nonce checks | Pass | No actionable finding reported in the review. |
| Admin REST capabilities and nonce checks | Pass | No actionable finding reported in the review. |
| Admin settings and import validation | Pass | No actionable finding reported in the review. |
| Frontend rendering | Pass | No actionable finding reported in the review; message content is rendered as React text rather than raw HTML. |
| Storage directory access | Fixed in code; deployment verification pending | Data now lives outside the document root. Existing data, backups, and abandoned atomic-write files are migrated from uploads and removed only after destination verification. |
| Error responses and sensitive path disclosure | Pass | No actionable finding reported in the review. |
| PHP lint | Pass | `composer lint` passed during the security review. |

## Finding and remediation

### MEDIUM — Knowledge data could be publicly downloadable on Nginx

**Original evidence:** The legacy knowledge file was stored under the WordPress
uploads directory at `wp-content/uploads/bryvium-chat/knowledge.json`.
`.htaccess` does not apply on Nginx, and an `index.php` only prevents directory
listing, not direct requests for the JSON file.

**Remediation:** Storage now defaults to a dedicated directory alongside the
document root, validates that the resolved directory is outside the web root,
and applies private directory/file permissions. On the first request after
upgrade, existing knowledge, backup, and atomic-write temporary files are
copied under the legacy file lock, verified, and then removed from uploads.
The storage layout version ensures this migration runs for existing active
installations.
If the default parent is not writable, define `BRYVIUM_WFC_DATA_DIR` to an
absolute path whose existing parent is writable and outside the document root.
Migration failure does not delete legacy data and is reported to administrators.
For example, set the constant in `wp-config.php` before WordPress loads:

```php
define( 'BRYVIUM_WFC_DATA_DIR', '/var/private/bryvium-wp-file-chat' );
```

The Nginx rule below remains useful as defense in depth for the legacy
uploads directory, especially if migration cannot complete:

```nginx
location ^~ /wp-content/uploads/bryvium-chat/ {
    deny all;
    return 403;
}
```

Adapt the URL path to the site's uploads configuration and reload Nginx.

**Status:** Code remediation implemented; verify a real upgrade and direct
HTTP access on the target server before marking the finding fully closed.

## Validation still required

- Upgrade an installation with existing knowledge and backups; verify the
  files are preserved outside the document root and removed from uploads.
- Confirm direct HTTP requests cannot fetch the new knowledge file or backups
  on the target server.
- Verify the public REST chat endpoint and admin knowledge operations still
  work after migration.
- Confirm migration failure is visible to administrators and leaves legacy
  data intact.
- Confirm installations without a writable default parent can configure an
  absolute `BRYVIUM_WFC_DATA_DIR` whose parent already exists outside the
  document root.
- Retest after any custom uploads URL, reverse-proxy, or web-server changes.
