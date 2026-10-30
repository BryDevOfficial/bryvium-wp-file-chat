# Day 3 Bugs and Deployment Notes

## Server configuration caveat

The storage class writes an Apache `.htaccess` deny rule and an `index.php`
guard. Nginx ignores `.htaccess`, so the host must deny direct HTTP access to
`/wp-content/uploads/bryvium-chat/` in its Nginx configuration. Direct URL
protection cannot be verified without the target web server.

## Open verification

- Run the CRUD and corrupted-JSON checks against a real WordPress install.
- Verify concurrent requests and direct URL denial on the target Apache or
  Nginx server.
