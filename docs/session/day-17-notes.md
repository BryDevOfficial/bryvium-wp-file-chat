# Day 17 Notes — Security Audit

## Reviewed

- Public REST request validation, nonce and rate-limit handling.
- Admin REST permissions and request handling.
- Settings and import validation.
- Knowledge storage protections and frontend message rendering.

## Results

- The security review reported one medium-severity, deployment-dependent
  exposure: the Apache `.htaccess` protection does not apply on Nginx, so
  uploads-hosted knowledge data could be fetched directly.
- No other actionable findings were reported by the review.
- `composer lint` passed during the review.
- Implemented private storage outside the document root, a verified migration
  for legacy knowledge, backups, and atomic-write temporary files, and
  administrator-visible migration errors.
- Added [the security checklist](../SECURITY-CHECKLIST.md), including upgrade
  and direct-access verification steps.

## Verification

- `composer lint` and PHP syntax checks pass.
- Focused PHP tests pass for out-of-root storage, knowledge/backup/temp-file
  migration, legacy cleanup after verification, readable migrated data,
  repeat migration, and preservation of legacy data on conflicts.
- A configured storage path inside the document root is rejected.
- A live server upgrade and direct HTTP check remain outstanding.

## Handoff

Code remediation is implemented; verify data migration and direct HTTP denial
on the target WordPress and web-server setup before closing the finding.
