# Bryvium WP File Chat

Bryvium WP File Chat is a WordPress plugin project for a private, local FAQ
chat widget. Its planned answers come from a JSON knowledge file stored on the
site owner's server. The project is designed to avoid external APIs and custom
database tables.

> **Development status:** This project is being built in daily stages. The
> plugin bootstrap, build tooling, JSON storage, search processing/ranking,
> REST rate limiting and caching, admin settings, and the knowledge manager
> interface are implemented. The chat interface and admin REST API are still
> in development.

## Requirements

- WordPress 7.1.4 or later
- PHP 8.5 or later
- For development builds: Node.js 20 or later and npm
- For PHP coding-standard checks: Composer

## Development

Install the JavaScript build dependencies and compile the frontend assets:

```powershell
npm install
npm run build
```

The production build writes `build/bryvium-chat.js` and
`build/bryvium-chat.css`. To rebuild when source files change:

```powershell
npm run dev
```

Install the PHP development tools and run WordPress Coding Standards checks:

```powershell
composer install
composer lint
```

To apply automatic PHPCS fixes:

```powershell
composer fix
```

## Project layout

- `bryvium-wp-file-chat.php` — WordPress plugin entry point and constants.
- `includes/` — PHP lifecycle, JSON storage, search processing/ranking, and
  planned backend modules.
- `src/` — React and Tailwind source for the frontend.
- `build/` — Compiled frontend assets.
- `admin/` — WordPress admin settings and knowledge manager interfaces.
- `languages/` — Translation files.
- `docs/` — Project reference and development-session documentation.

## Design goals

- Keep all knowledge data on the WordPress site owner's server.
- Use a protected JSON file for knowledge storage; do not create custom
  database tables.
- Keep build tools out of the shipped plugin runtime.
- Follow WordPress Coding Standards and escape/sanitize data appropriately.
- Prefix Tailwind utilities with `bwfc:` and avoid its global preflight reset
  to reduce styling conflicts with site themes.

See [`docs/referrence/BRYVIUM-WP-FILE-CHAT-DOCS.md`](docs/referrence/BRYVIUM-WP-FILE-CHAT-DOCS.md)
for the detailed architecture and development roadmap.
