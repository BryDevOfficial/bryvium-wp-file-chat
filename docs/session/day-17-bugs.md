# Day 17 Bugs and Security Findings

## Remediated; deployment verification pending

- **Medium — Nginx may expose knowledge JSON:** the storage folder is within
  uploads and relied on `.htaccess` plus an index file. Day 17 now migrates
  knowledge, backup, and temporary files to private storage outside the document root,
  verifies destination data, and removes legacy copies. Confirm migration and
  direct URL denial on a live server.

See [the security checklist](../SECURITY-CHECKLIST.md) for evidence,
remediation, and verification steps.
