# Bryvium WP File Chat: Full Project Documentation

**Document type:** Master plan, no code
**Version of this doc:** 1.0
**Plugin version target:** 1.0.0
**Status:** D1 complete, D2 delivered (verify with the D2 acceptance checks), D3 onward pending

---

## Table of Contents

1. Project Overview
2. Non-Negotiable Rules
3. Architecture
4. Final File and Folder Map
5. Data Specifications (Knowledge Entry, Settings, REST Endpoints)
6. Security Model
7. Search Engine Design
8. Coding Standards
9. Git Branching and Commit Guidelines
10. Definition of Done (Quality Gates)
11. Daily Development Schedule (D1 to D22)
12. How to Use the Daily Claude Prompts
13. Future Roadmap
14. Release and Deployment Checklist
15. Risks and Troubleshooting

---

## 1. Project Overview

**Bryvium WP File Chat** is a WordPress plugin that adds a chat widget to a website. Visitors type a question, and the plugin answers from a single local file named `knowledge.json`. There are no external API calls, no database tables, and no third-party services.

**Who it is for:** non-technical website owners who want a simple FAQ-style chatbot that is private, fast, and free of monthly fees.

**What "zero-dependency" means in this project**

| Layer | Rule |
|---|---|
| Runtime (what ships to the customer) | Pure PHP backend, one compiled JS file, one compiled CSS file, one JSON data file. No Composer packages, no CDN calls, no external APIs. |
| Build time (developer machine only) | React, Tailwind, Vite, PHPCS/WPCS are allowed. None of them ships in the release zip. |

**Success criteria for v1.0**

- Installs and activates in under one minute with no configuration required.
- Answers a question in under 200 ms server time for a 1,000-entry knowledge file.
- Owner can add, edit, delete, import, and export entries without touching code.
- Passes WordPress Coding Standards with zero errors.
- Zero outbound network requests (verifiable in the browser Network tab).

---

## 2. Non-Negotiable Rules

1. Local-first. All data stays on the owner's server.
2. No database tables. Settings use the options API (few small options only). Knowledge lives in the JSON file.
3. Everything is prefixed: PHP uses `bryvium_` / `Bryvium_`, constants use `BRYVIUM_WFC_`, CSS uses `bwfc-` / Tailwind prefix `bwfc:`, REST namespace is `bryvium/v1`.
4. Sanitize on input, escape on output, check capability and nonce on every state-changing action.
5. Build in small daily steps. Never request more than one day of code at once unless a day is explicitly split.
6. Simplicity first. If a feature needs a manual, simplify the feature.
7. The plugin requires PHP 8.5 or later. Keep the bootstrap parseable on older PHP versions where practical so unsupported hosts can receive a friendly requirements error instead of a syntax fatal.

---

## 3. Architecture

### 3.1 Components

| Component | Responsibility |
|---|---|
| Loader | Plugin lifecycle: requirements check, activation, deactivation, module registration |
| Storage | Create the protected data directory, read and write `knowledge.json` atomically with file locking, validate entries, cache parsed data |
| Search | Normalize text, tokenize, remove stopwords, score entries, rank, apply confidence threshold, fuzzy fallback |
| Rate Limiter | Per-visitor request throttling using transients |
| Public REST | The `/chat` endpoint used by the widget |
| Admin REST | Capability-gated CRUD, import, export endpoints used by the admin screens |
| Settings | Registration, defaults, sanitization of widget options |
| Admin UI | Settings page, knowledge manager, import/export tools |
| Widget (PHP side) | Enqueue built assets, expose config to JS, shortcode, optional auto-display |
| Widget (React side) | Launcher button, chat window, messages, input, API calls, accessibility, theming |

### 3.2 Data Flow (chat request)

1. Visitor types a question in the React widget.
2. Widget sends a request to the public REST endpoint with a nonce.
3. PHP checks the nonce, applies rate limiting, validates length, and sanitizes the text.
4. Storage returns the parsed knowledge array (served from cache when the file modification time has not changed).
5. Search scores every enabled entry and returns the best matches with a confidence value.
6. If confidence is below the threshold, the fallback message from Settings is returned.
7. Widget renders the answer as safe text (no raw HTML injection).

### 3.3 Storage Design

- Location: a dedicated folder inside the WordPress uploads directory.
- The folder is protected with an `.htaccess` deny rule and a blank `index.php`. The file is only ever served through REST, never by direct URL.
- Writes are atomic: write to a temporary file, then rename over the real file, while holding an exclusive lock.
- Reads use a shared lock and a cache keyed by the file modification time.
- Practical scale limit: about 1,000 entries or 2 MB. Larger sets are a roadmap item (sharding).

### 3.4 Frontend Design

- One React root mounted on a single element with id `bwfc-root`.
- Tailwind v4 with the `bwfc` prefix and **no preflight reset**, so the host theme is never restyled.
- Output is one IIFE bundle with a stable filename. PHP appends the file modification time as the cache-busting version.
- Theme colors come from CSS variables written by PHP from the saved settings.
- Chat history lives in memory and optionally in `sessionStorage` (cleared when the tab closes). No cookies.

### 3.5 Admin Design Decision

The admin screens use **plain PHP views plus a small vanilla JavaScript file**, not React. Reasons: smaller build, fewer moving parts, and it follows the WordPress admin look automatically. This is finalized at D9. If the owner later wants a React admin, it moves to the roadmap.

---

## 4. Final File and Folder Map

All paths are relative to the plugin root `bryvium-wp-file-chat/`.

| Path | Purpose | Created on |
|---|---|---|
| `bryvium-wp-file-chat.php` | Bootstrap, plugin header, constants, hooks | D1 |
| `index.php` (root, includes, admin, languages) | Directory listing protection | D1 |
| `includes/class-bryvium-loader.php` | Lifecycle and module registration | D1 |
| `.gitignore` | Ignore rules | D2 |
| `.editorconfig` | Editor consistency | D2 |
| `composer.json` | Dev-only PHPCS tooling | D2 |
| `phpcs.xml.dist` | Coding standards ruleset | D2 |
| `package.json` | Dev-only Node tooling | D2 |
| `vite.config.js` | Production build config | D2 |
| `src/index.css` | Tailwind entry (prefixed, no preflight) | D2 |
| `src/main.jsx` | React entry point | D2 |
| `includes/class-bryvium-storage.php` | JSON storage engine | D3 |
| `includes/class-bryvium-search.php` | Search engine | D4, D5 |
| `includes/data/stopwords-en.php` | English stopword list | D4 |
| `includes/class-bryvium-rest.php` | Public chat endpoint | D6 |
| `includes/class-bryvium-rate-limiter.php` | Throttling | D7 |
| `includes/class-bryvium-settings.php` | Settings registry and defaults | D8 |
| `includes/class-bryvium-admin.php` | Admin menu and page routing | D8 |
| `admin/views/page-settings.php` | Settings screen | D8 |
| `admin/views/page-knowledge.php` | Knowledge manager screen | D9 |
| `admin/css/bryvium-admin.css` | Admin styling | D9 |
| `admin/js/bryvium-admin.js` | Admin behavior | D9 |
| `includes/class-bryvium-rest-admin.php` | Admin CRUD endpoints | D10 |
| `includes/class-bryvium-importer.php` | Import and export logic | D11 |
| `admin/views/page-tools.php` | Import/export screen | D11 |
| `includes/class-bryvium-widget.php` | Enqueue, config, shortcode, auto-display | D12, D16 |
| `src/App.jsx` | Root component | D12 |
| `src/components/ChatLauncher.jsx` | Floating button | D13 |
| `src/components/ChatWindow.jsx` | Chat panel | D13 |
| `src/components/MessageList.jsx` | Message rendering | D13 |
| `src/components/MessageInput.jsx` | Input box | D13 |
| `src/api/client.js` | REST client | D14 |
| `src/hooks/useChat.js` | Chat state and logic | D14 |
| `uninstall.php` | Cleanup on delete | D19 |
| `languages/bryvium-wp-file-chat.pot` | Translation template | D19 |
| `docs/SECURITY-CHECKLIST.md` | Audit results | D17 |
| `docs/PERFORMANCE.md` | Benchmark notes | D18 |
| `docs/QA-MATRIX.md` | Test matrix results | D20 |
| `tools/generate-sample-knowledge.php` | Dev-only sample data generator (excluded from release) | D18 |
| `readme.txt` | WordPress.org readme | D21 |
| `CHANGELOG.md` | Version history | D21 |
| `scripts/build-zip.mjs` | Release packaging script | D21 |
| `docs/RELEASE-CHECKLIST.md` | Deployment steps | D22 |
| `build/bryvium-chat.js` and `build/bryvium-chat.css` | Compiled output (committed) | D2 onward |

---

## 5. Data Specifications

### 5.1 Knowledge Entry

| Field | Type | Rules |
|---|---|---|
| `id` | string | Unique, generated by the plugin, never edited by the user |
| `title` | string | Required, max 200 characters, plain text |
| `answer` | string | Required, max 4,000 characters, limited safe formatting only (line breaks, links) |
| `keywords` | array of strings | Optional, max 20 items, each max 50 characters |
| `category` | string | Optional, max 60 characters |
| `enabled` | boolean | Default true |
| `updated_at` | integer | Unix timestamp set by the plugin |

The file wrapper also stores a `schema_version` number so future versions can migrate data.

### 5.2 Settings (options API)

| Setting | Purpose | Default idea |
|---|---|---|
| `widget_title` | Header text of the chat window | "Chat with us" |
| `greeting` | First message shown | Friendly welcome line |
| `fallback_message` | Shown when no good match is found | "Sorry, I could not find an answer." |
| `placeholder` | Input placeholder | "Type your question" |
| `primary_color` | Brand color | A neutral blue |
| `position` | Bottom-right or bottom-left | Bottom-right |
| `auto_display` | Show on all pages automatically | Off until the owner enables it |
| `confidence_threshold` | Minimum score to accept an answer | A sensible tested default |
| `rate_limit_per_minute` | Max requests per visitor per minute | Moderate default |
| `save_history` | Keep history in sessionStorage | On |

All settings are stored together in one option array to keep the options table clean.

### 5.3 REST Endpoints (namespace `bryvium/v1`)

| Method and route | Access | Purpose |
|---|---|---|
| POST `/chat` | Public, nonce plus rate limit | Return an answer for a question |
| GET `/entries` | Admin only | List entries with search and pagination |
| POST `/entries` | Admin only | Create an entry |
| PUT `/entries/{id}` | Admin only | Update an entry |
| DELETE `/entries/{id}` | Admin only | Delete an entry |
| POST `/import` | Admin only | Import JSON with validation |
| GET `/export` | Admin only | Download the current knowledge file |

Admin routes require the `manage_options` capability and a valid REST nonce.

---

## 6. Security Model

| Threat | Defense |
|---|---|
| Direct download of `knowledge.json` | Protected folder, `.htaccess` deny, blank index file, served via REST only |
| Cross-site request forgery | Nonce on every state-changing request |
| Privilege escalation | Capability check on every admin route and screen |
| Injection through input | Sanitize every field, strict length limits, type validation |
| Cross-site scripting | Escape all output in PHP, render answers as text in React, restrict allowed tags for answers |
| Abuse and flooding | Rate limiting, payload size limits |
| File corruption from simultaneous writes | Exclusive locks and atomic rename |
| Malicious import file | Size cap, JSON decode checks, per-entry validation, reject unknown structure |
| Information leakage in errors | Generic error messages to visitors, details only in the debug log when enabled |
| Privacy | No external requests, no cookies, no personal data stored by default |

---

## 7. Search Engine Design

1. **Normalize:** lowercase, trim, collapse spaces, strip punctuation, handle basic accents.
2. **Tokenize:** split into words, drop very short tokens.
3. **Stopwords:** remove common words from the English list in `includes/data/stopwords-en.php`.
4. **Light stemming:** simple suffix handling (plural and common endings).
5. **Scoring:** weighted match of query tokens against title (highest weight), keywords (high), answer text (lower). Bonus for exact phrase matches and for matching a higher share of query tokens.
6. **Fuzzy fallback:** for tokens with no exact hit, allow small edit-distance matches on short words to tolerate typos.
7. **Ranking and threshold:** sort by score, return the top result and optionally up to two related suggestions. If the best score is below the confidence threshold, return the fallback.
8. **Determinism:** same input and same data always return the same output, which makes testing easy.

---

## 8. Coding Standards

- WordPress Coding Standards enforced by PHPCS. Run the lint script before every commit.
- Tabs for PHP, two spaces for JS, JSX, CSS, JSON, and Markdown (set in `.editorconfig`).
- Every PHP file starts with the direct-access guard.
- Classes are final unless extension is intended. One class per file, file names follow the `class-bryvium-*.php` pattern.
- All user-facing strings use the text domain `bryvium-wp-file-chat`.
- No inline CSS or JS in PHP views. Use enqueued files.
- React: functional components and hooks only. No external UI libraries. Tailwind classes always carry the `bwfc:` prefix.
- Comments explain why, not what. Keep them short.

---

## 9. Git Branching and Commit Guidelines

**Branches**

| Branch | Use |
|---|---|
| `main` | Always releasable, tagged with versions |
| `develop` | Integration of finished daily tasks |
| `feature/dNN-topic` | One branch per daily task, for example `feature/d03-json-storage` |
| `fix/issue-name` | Bug fixes |
| `release/x.y.z` | Release preparation |
| `hotfix/issue-name` | Urgent fixes from `main` |

**Daily Git routine**

1. Start from an up-to-date `develop`.
2. Create the day's feature branch.
3. Do the work and run the quality gates.
4. Commit with the message provided for that day.
5. Merge into `develop` with a merge commit.
6. Delete the feature branch.

**Commit message format (Conventional Commits)**

The subject line is `type(scope): summary` in the imperative mood, 72 characters or fewer, with an optional body that explains why.

- Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `chore`, `security`
- Scopes: `storage`, `search`, `rest`, `admin`, `widget`, `build`, `i18n`

**Rules**

- Never commit `node_modules`, `vendor`, real `knowledge.json` data, or `.zip` files.
- Keep `CHANGELOG.md` updated using the Keep a Changelog style.
- Use semantic versioning. Stay on `0.x` until the first stable release.
- Commit the `build/` folder because customers install the compiled output.

---

## 10. Definition of Done (Quality Gates)

A daily task is done only when all of these are true:

1. The day's acceptance checks pass.
2. PHPCS reports zero errors on PHP changes.
3. With debug mode and debug log enabled, nothing new appears in the log.
4. No new outbound network requests.
5. Changes are committed with the day's commit message and merged to `develop`.
6. The handoff note for the next day is saved (see Section 12).

---

## 11. Daily Development Schedule

Each day lists the branch, goal, exact files, tasks, acceptance checks, commit message, and a ready-to-use **Claude Prompt**. Paste the prompt into a new chat together with the "Existing context" it asks for, and Claude will generate only that day's code.

---

### PHASE 1: FOUNDATION

#### D1: Bootstrap, Constants, Lifecycle (COMPLETE)

- **Branch:** `feature/d01-bootstrap`
- **Goal:** A plugin that activates cleanly, checks PHP and WordPress versions, and exposes a hook for later modules.
- **Files:** `bryvium-wp-file-chat.php`, `includes/class-bryvium-loader.php`, `index.php` in root, `includes/`, `admin/`, `languages/`
- **Tasks:** plugin header; constants for version, paths, URL, basename; requirements check; activation stores the installed version; deactivation is non-destructive; `bryvium_wfc_loaded` action fires when ready.
- **Acceptance:** activates without errors; the version option is saved; raising the minimum PHP version to an impossible number blocks activation with a friendly message; the debug log stays empty.
- **Commit:** `feat(build): add plugin bootstrap, constants and lifecycle hooks`
- **Handoff:** constants `BRYVIUM_WFC_VERSION`, `FILE`, `PATH`, `URL`, `BASENAME`; class `Bryvium_Loader`; action `bryvium_wfc_loaded`.

---

#### D2: Tooling Scaffold (DELIVERED, verify acceptance)

- **Branch:** `feature/d02-tooling`
- **Goal:** Dev-only tooling in place: coding standards, editor rules, ignore rules, and a working Vite + Tailwind build.
- **Files:** `.gitignore`, `.editorconfig`, `composer.json`, `phpcs.xml.dist`, `package.json`, `vite.config.js`, `src/index.css`, `src/main.jsx`
- **Tasks:** configure PHPCS with WordPress and PHP compatibility rules; set the text domain and prefix rules; configure the build to output a single IIFE script and a single CSS file with stable names; configure Tailwind with the `bwfc` prefix and no global reset; create a placeholder component to prove the pipeline.
- **Acceptance:** the build produces `build/bryvium-chat.js` and `build/bryvium-chat.css`; the CSS contains prefixed utilities and no global element resets; the PHP lint passes on D1 files; `git status` shows no `node_modules` or `vendor`.
- **Commit:** `build(tooling): add PHPCS, EditorConfig and Vite + Tailwind scaffold`
- **Troubleshooting:** if tools fail on paths with emoji or special characters, move the project to a plain ASCII folder path.
- **Handoff:** build outputs and the mount id `bwfc-root`; lint is the gate for every PHP day.

---

#### D3: Storage Engine

- **Branch:** `feature/d03-json-storage`
- **Goal:** A safe, atomic, validated way to read and write `knowledge.json`.
- **Files:** create `includes/class-bryvium-storage.php`; modify `includes/class-bryvium-loader.php` and `bryvium-wp-file-chat.php` (include the new class and call setup on activation).
- **Tasks:**
  1. Create the protected data directory in uploads with the deny rule and blank index file, on activation and lazily if missing.
  2. Create an empty valid knowledge file with the schema version on first use.
  3. Read with a shared lock; write with an exclusive lock, temp file, and atomic rename.
  4. Validate and sanitize entries against the schema in Section 5.1.
  5. Provide methods to get all, get one, add, update, delete, and count.
  6. Handle failures: missing file, unreadable file, corrupted JSON, no write permission. Return clear error objects, never fatal errors.
  7. Generate unique ids.
- **Acceptance:** the directory and protection files exist after activation; direct URL access to the JSON file is denied; add, update, delete work and persist; a manually corrupted JSON file does not crash the site and reports a recoverable error; two rapid writes do not corrupt the file.
- **Commit:** `feat(storage): add atomic JSON storage with locking and validation`
- **Handoff:** public method names and return shapes of the storage class.

> **Claude Prompt (D3)**
>
> You are my Senior WordPress Architect. Project: "Bryvium WP File Chat" (zero runtime dependencies, pure PHP, JSON flat-file, PHP 8.5+, WordPress 7.1.4+, WPCS-compliant, prefix `bryvium_`, text domain `bryvium-wp-file-chat`).
> Task D3: Generate the storage engine only.
> Create `includes/class-bryvium-storage.php` and give me only the minimal edits for `includes/class-bryvium-loader.php` and `bryvium-wp-file-chat.php`.
> Requirements: protected data directory inside uploads (deny rule plus blank index file); auto-create an empty valid knowledge file with `schema_version`; shared lock on read; exclusive lock plus temp file plus atomic rename on write; entry schema: id, title (max 200), answer (max 4000), keywords (max 20 items, 50 chars each), category (max 60), enabled, updated_at; methods for get all, get one, add, update, delete, count; return WP_Error on failure (missing file, corrupt JSON, no permission); sanitize and validate all input; generate unique ids.
> Existing context: constants `BRYVIUM_WFC_VERSION/FILE/PATH/URL/BASENAME`, class `Bryvium_Loader` with `activate()`, `deactivate()`, `init()` and action `bryvium_wfc_loaded`. [Paste only the current loader method signatures.]
> Output rules: code only for the listed files, short inline comments, no unrelated refactors. End with a 3-step manual test, a Conventional Commit message, and a 2-line handoff note for D4.

---

### PHASE 2: BACKEND CORE

#### D4: Search Engine Part 1 (Text Processing)

- **Branch:** `feature/d04-search-text`
- **Goal:** Reliable text normalization and tokenization.
- **Files:** create `includes/class-bryvium-search.php`, `includes/data/stopwords-en.php`; modify `includes/class-bryvium-loader.php` only if registration is needed.
- **Tasks:** normalization rules (lowercase, punctuation, whitespace, accents); tokenizer with minimum token length; stopword filtering from the data file; light stemming; a public method that turns a raw string into a clean token list; keep the class stateless so it is easy to test.
- **Acceptance:** a set of sample sentences produces expected token lists; empty and punctuation-only input returns an empty list; very long input is truncated safely; non-English characters do not cause errors.
- **Commit:** `feat(search): add text normalization, tokenizer and stopwords`
- **Handoff:** the tokenizer method name, its input, and its output shape.

> **Claude Prompt (D4)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat (pure PHP 8.5+, WordPress 7.1.4+, WPCS, prefix `bryvium_`).
> Task D4: Generate search engine part 1 only: text processing.
> Files: `includes/class-bryvium-search.php` (text processing methods only; leave clearly marked space for scoring in D5) and `includes/data/stopwords-en.php` (returns an array).
> Requirements: normalize (lowercase, strip punctuation, collapse whitespace, basic accent folding, UTF-8 safe using mb functions with fallback); tokenizer with min token length of 2; stopword removal; light stemming (plural and common suffixes); a public method that returns a clean token array from a raw string; cap input length; stateless and deterministic.
> Existing context: [Paste D3 storage method signatures only if useful, otherwise none.]
> Output rules: code only for the listed files, short comments. End with 3 manual test sentences with expected token output, the commit message, and a 2-line handoff for D5.

---

#### D5: Search Engine Part 2 (Scoring and Ranking)

- **Branch:** `feature/d05-search-scoring`
- **Goal:** Return the best matching entries with a confidence value.
- **Files:** modify `includes/class-bryvium-search.php`.
- **Tasks:** weighted scoring (title, keywords, answer); exact-phrase bonus; token coverage bonus; fuzzy fallback for unmatched short tokens; ranking; confidence normalized to a 0 to 1 scale; confidence threshold parameter; skip disabled entries; return the top result plus up to two related suggestions; handle zero entries gracefully.
- **Acceptance:** exact title questions rank first; a typo still finds the right entry; unrelated questions fall below the threshold; disabled entries never appear; results are identical on repeated runs.
- **Commit:** `feat(search): add weighted scoring, ranking and fuzzy fallback`
- **Handoff:** the search method name, parameters, and result array shape (answer, id, confidence, suggestions).

> **Claude Prompt (D5)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D5: Add scoring and ranking to the existing search class. Modify only `includes/class-bryvium-search.php`.
> Requirements: weights title > keywords > answer; exact-phrase bonus; token coverage bonus; fuzzy fallback using edit distance for unmatched short tokens; confidence normalized 0 to 1; threshold parameter; ignore disabled entries; return best match plus up to two related suggestions; deterministic; handle empty knowledge safely.
> Existing context: [Paste the D4 handoff note and the tokenizer method signature.] Entry schema: id, title, answer, keywords[], category, enabled, updated_at.
> Output rules: show only the new or changed methods, short comments. End with 5 manual test queries (exact, typo, unrelated, disabled entry, empty data), the commit message, and a 2-line handoff for D6.

---

#### D6: Public REST Chat Endpoint

- **Branch:** `feature/d06-rest-chat`
- **Goal:** A secure endpoint the widget can call.
- **Files:** create `includes/class-bryvium-rest.php`; modify `includes/class-bryvium-loader.php` to register it.
- **Tasks:** register the `bryvium/v1/chat` POST route; argument schema with sanitize and validate callbacks; nonce verification; permission callback; maximum question length; consistent response shape (answer, confidence, suggestions, status); safe error responses with proper HTTP status codes; no sensitive data in responses.
- **Acceptance:** valid request returns an answer; missing or invalid nonce is rejected; empty and over-length questions are rejected with clear messages; the response contains no file paths or internal details.
- **Commit:** `feat(rest): add public chat endpoint with validation and nonce check`
- **Handoff:** route, request fields, response shape, error codes.

> **Claude Prompt (D6)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D6: Public REST chat endpoint. Create `includes/class-bryvium-rest.php`; give minimal edits for `includes/class-bryvium-loader.php`.
> Requirements: namespace `bryvium/v1`, POST `/chat`; args schema with sanitize and validate callbacks; nonce check via the REST nonce header; max question length 300 chars; call the storage and search classes; response shape: `status`, `answer`, `confidence`, `suggestions`; proper HTTP codes; generic error messages; never leak paths or internals.
> Existing context: [Paste the storage method signatures and the search method signature plus result shape from the D5 handoff.]
> Output rules: only the listed files, short comments. End with 3 manual tests (valid, bad nonce, empty or long question), the commit message, and a 2-line handoff for D7.

---

#### D7: Rate Limiting, Caching, Input Limits

- **Branch:** `feature/d07-limits-cache`
- **Goal:** Protect the site from abuse and make repeated reads fast.
- **Files:** create `includes/class-bryvium-rate-limiter.php`; modify `includes/class-bryvium-storage.php`, `includes/class-bryvium-rest.php`.
- **Tasks:** per-visitor throttling using transients with a hashed visitor key (no raw IP stored); configurable limit; proper 429 response with a retry hint; cache the parsed knowledge keyed by file modification time; invalidate on every write; enforce payload size limits; respect proxies carefully without trusting spoofable headers blindly.
- **Acceptance:** exceeding the limit returns 429; limit resets after the window; a second read does not re-parse the file; editing an entry refreshes the cache immediately.
- **Commit:** `feat(rest): add rate limiting and modification-time caching`
- **Handoff:** limiter method names, cache invalidation hook points.

> **Claude Prompt (D7)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D7: Rate limiting and caching. Create `includes/class-bryvium-rate-limiter.php`; modify `includes/class-bryvium-storage.php` and `includes/class-bryvium-rest.php`.
> Requirements: transient-based per-visitor limiter with a hashed key (salted, no raw IP stored); configurable per-minute limit with a safe default; 429 plus retry-after hint; cache parsed knowledge keyed by `filemtime` and invalidate on every write; payload size limit; careful client IP detection.
> Existing context: [Paste the relevant method signatures from the storage and REST classes.]
> Output rules: only changed or new methods, short comments. End with 3 manual tests, the commit message, and a 2-line handoff for D8.

---

### PHASE 3: ADMIN EXPERIENCE

#### D8: Admin Menu and Settings

- **Branch:** `feature/d08-admin-settings`
- **Goal:** Owners can configure the widget from a settings page.
- **Files:** create `includes/class-bryvium-settings.php`, `includes/class-bryvium-admin.php`, `admin/views/page-settings.php`; modify `includes/class-bryvium-loader.php`.
- **Tasks:** top-level or Settings submenu entry with a clear label; capability check; a single option array with defaults; sanitization per field (text, color, select, checkbox, number with min and max); settings screen with nonce-protected save; success and error notices; escape everything in the view; add a Settings link on the Plugins list row.
- **Acceptance:** defaults appear on first load; saving persists; invalid colors or numbers are corrected; non-admin users cannot access the page.
- **Commit:** `feat(admin): add settings page with sanitized options`
- **Handoff:** settings getter method and the list of setting keys.

> **Claude Prompt (D8)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D8: Admin menu and settings page. Create `includes/class-bryvium-settings.php`, `includes/class-bryvium-admin.php`, `admin/views/page-settings.php`; minimal edits to `includes/class-bryvium-loader.php`.
> Requirements: settings keys: widget_title, greeting, fallback_message, placeholder, primary_color, position, auto_display, confidence_threshold, rate_limit_per_minute, save_history; stored as one option array with defaults; use the WordPress Settings API or a nonce-protected handler; per-field sanitization; capability `manage_options`; escape all output; add a "Settings" link in the plugin row; simple clean layout for non-technical owners.
> Existing context: constants and loader signatures. [Paste D7 handoff if relevant.]
> Output rules: only the listed files, short comments. End with 3 manual tests, the commit message, and a 2-line handoff for D9.

---

#### D9: Knowledge Manager UI

- **Branch:** `feature/d09-knowledge-ui`
- **Goal:** Owners can list, search, add, edit, and delete entries from the admin screen.
- **Files:** create `admin/views/page-knowledge.php`, `admin/css/bryvium-admin.css`, `admin/js/bryvium-admin.js`; modify `includes/class-bryvium-admin.php`.
- **Tasks:** admin submenu "Knowledge"; table with search box, category filter, and pagination; add and edit form (title, answer, keywords, category, enabled); delete with confirmation; enqueue admin assets only on this plugin's screens; JS talks to the admin REST routes built in D10 (build the UI with clear placeholders and wire it fully on D10); empty-state message with a friendly hint; mobile-friendly layout.
- **Acceptance:** screen loads for admins only; assets load only on plugin screens; the form validates required fields before submit; layout works on a narrow screen.
- **Commit:** `feat(admin): add knowledge manager screen`
- **Handoff:** DOM ids and data attributes the JS uses; expected REST payloads.

> **Claude Prompt (D9)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D9: Knowledge manager UI. Create `admin/views/page-knowledge.php`, `admin/css/bryvium-admin.css`, `admin/js/bryvium-admin.js` (vanilla JS, no libraries); minimal edits to `includes/class-bryvium-admin.php`.
> Requirements: submenu "Knowledge"; list table with search, category filter, pagination; add/edit form for title, answer, keywords (comma separated), category, enabled; delete with confirm; enqueue assets only on plugin screens; all output escaped; the JS calls these REST routes with the REST nonce: GET/POST `/entries`, PUT/DELETE `/entries/{id}` (backend arrives on D10, so stub gracefully); friendly empty state; responsive.
> Existing context: [Paste the admin class signatures from D8 and the settings key list.]
> Output rules: only the listed files, short comments. End with 3 manual tests, the commit message, and a 2-line handoff for D10.

---

#### D10: Admin REST Endpoints

- **Branch:** `feature/d10-admin-rest`
- **Goal:** Secure CRUD that powers the knowledge manager.
- **Files:** create `includes/class-bryvium-rest-admin.php`; modify `includes/class-bryvium-loader.php`, `admin/js/bryvium-admin.js` (wire to real endpoints).
- **Tasks:** routes for list (search, category, page, per-page), create, update, delete; permission callback with `manage_options`; nonce checks; validation matching the schema; consistent JSON responses and errors; pagination headers or fields; JS wiring with loading, success, and error states.
- **Acceptance:** non-admin requests get 401 or 403; full create, edit, delete cycle works from the UI; validation errors appear next to the form; the chat endpoint immediately reflects changes.
- **Commit:** `feat(rest): add capability-gated entries CRUD`
- **Handoff:** route list and response shapes.

> **Claude Prompt (D10)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D10: Admin REST CRUD. Create `includes/class-bryvium-rest-admin.php`; minimal edits to `includes/class-bryvium-loader.php`; update `admin/js/bryvium-admin.js` to use the real endpoints.
> Requirements: namespace `bryvium/v1`; GET `/entries` (search, category, page, per_page), POST `/entries`, PUT `/entries/{id}`, DELETE `/entries/{id}`; permission callback requires `manage_options`; nonce verification; args schema and sanitization matching the entry schema; consistent responses and errors; UI states for loading, success, error.
> Existing context: [Paste the storage method signatures and the D9 DOM ids handoff.]
> Output rules: only the listed files, changed methods only for existing files. End with 3 manual tests, the commit message, and a 2-line handoff for D11.

---

#### D11: Import and Export

- **Branch:** `feature/d11-import-export`
- **Goal:** Owners can back up and restore knowledge safely.
- **Files:** create `includes/class-bryvium-importer.php`, `admin/views/page-tools.php`; modify `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-admin.php`, `admin/js/bryvium-admin.js`.
- **Tasks:** export as a downloadable JSON file; import via file upload or pasted JSON; size cap; strict structure and per-entry validation; two modes (merge or replace) with a confirmation step for replace; automatic backup of the current file before replace; clear summary after import (added, updated, skipped, errors).
- **Acceptance:** export then import reproduces the same data; a malformed file is rejected with a helpful message; replace mode always creates a backup first; oversized files are refused.
- **Commit:** `feat(admin): add validated JSON import and export tools`
- **Handoff:** importer method names and result shape.

> **Claude Prompt (D11)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat.
> Task D11: Import and export. Create `includes/class-bryvium-importer.php` and `admin/views/page-tools.php`; changed methods only for `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-admin.php`, `admin/js/bryvium-admin.js`.
> Requirements: export current knowledge as a JSON download; import by upload or paste; 1 MB size cap; strict structure and per-entry validation using existing schema rules; merge and replace modes; replace creates a timestamped backup first; result summary (added, updated, skipped, errors); admin only, nonce protected; no execution of any uploaded content.
> Existing context: [Paste storage method signatures and the D10 route list.]
> Output rules: only the listed files, short comments. End with 3 manual tests, the commit message, and a 2-line handoff for D12.

---

### PHASE 4: FRONTEND WIDGET

#### D12: React Skeleton, Enqueue, Build Wiring

- **Branch:** `feature/d12-react-skeleton`
- **Goal:** The built widget loads on the site front end with config from PHP.
- **Files:** create `includes/class-bryvium-widget.php`, `src/App.jsx`; modify `src/main.jsx`, `vite.config.js` (only if needed), `includes/class-bryvium-loader.php`.
- **Tasks:** enqueue the built JS and CSS only on the front end and only when the widget is going to display; version assets by file modification time; load script in the footer with the defer strategy; pass config to JS (REST URL, nonce, title, greeting, placeholder, position, history flag) through a safely encoded data object; print the mount element once; bail out cleanly if build files are missing; React entry reads the config and mounts `App`.
- **Acceptance:** assets load only where intended; no console errors; config values reach the page; the mount element appears exactly once; missing build files produce an admin notice, not a fatal error.
- **Commit:** `feat(widget): enqueue built assets and mount React app`
- **Handoff:** the config object keys and the mount id.

> **Claude Prompt (D12)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat (React + Tailwind v4 prefixed `bwfc:`, no preflight, Vite IIFE build to `build/bryvium-chat.js` and `.css`, mount id `bwfc-root`).
> Task D12: Enqueue and mount. Create `includes/class-bryvium-widget.php` and `src/App.jsx`; give changed lines only for `src/main.jsx` and `includes/class-bryvium-loader.php`.
> Requirements: front-end only enqueue; version by `filemtime`; footer and defer; localized config (REST root URL, REST nonce, widget_title, greeting, placeholder, position, save_history); single mount element in `wp_footer`; skip silently with an admin notice if build files are missing; App reads config and renders a placeholder.
> Existing context: settings getter and keys from D8; loader signatures.
> Output rules: only the listed files, short comments. End with 3 manual tests, the commit message, and a 2-line handoff for D13.

---

#### D13: Chat UI Components

- **Branch:** `feature/d13-chat-ui`
- **Goal:** The full visual chat experience with static behavior.
- **Files:** create `src/components/ChatLauncher.jsx`, `src/components/ChatWindow.jsx`, `src/components/MessageList.jsx`, `src/components/MessageInput.jsx`; modify `src/App.jsx`, `src/index.css`.
- **Tasks:** floating launcher with open and close toggle; chat window with header, scrollable message list, input area; distinct visitor and bot message styles; auto-scroll to the newest message; position left or right from config; every utility class uses the `bwfc:` prefix; no layout shifts on the host page; z-index high enough but not absurd.
- **Acceptance:** opens and closes smoothly; looks correct on a default WordPress theme and on a dark theme; host page styles are unchanged; messages scroll correctly.
- **Commit:** `feat(widget): add launcher, window, message list and input`
- **Handoff:** component props and state ownership (which component owns messages).

> **Claude Prompt (D13)**
>
> Senior React and Tailwind v4 engineer. Project: Bryvium WP File Chat. All Tailwind classes must use the `bwfc:` prefix; no preflight; no external UI libraries.
> Task D13: Chat UI components (static, no API yet). Create `src/components/ChatLauncher.jsx`, `ChatWindow.jsx`, `MessageList.jsx`, `MessageInput.jsx`; changed parts only for `src/App.jsx` and `src/index.css`.
> Requirements: floating launcher toggle; window with header (title), scrollable message list, input with send button; user and bot message styles; auto-scroll; left or right position from config; props-driven; App owns the messages state.
> Existing context: config keys from the D12 handoff.
> Output rules: only the listed files, short comments. End with 3 manual visual tests, the commit message, and a 2-line handoff for D14.

---

#### D14: API Integration and Chat Logic

- **Branch:** `feature/d14-chat-api`
- **Goal:** The widget talks to the backend.
- **Files:** create `src/api/client.js`, `src/hooks/useChat.js`; modify `src/App.jsx`, `src/components/MessageList.jsx`, `src/components/MessageInput.jsx`.
- **Tasks:** REST client sending the nonce header; timeout and abort handling; typing indicator while waiting; friendly error messages for network failure, rate limit (429), and server errors; prevent sending while a request is in flight; trim and length-check input before sending; render suggestions as clickable follow-ups; history kept in memory and optionally in `sessionStorage` wrapped in safe try and catch; render answers as text (convert line breaks and detected links safely, never raw HTML injection); greeting shown as the first bot message.
- **Acceptance:** a real question returns a real answer; offline mode shows a friendly error; spamming triggers the rate-limit message; history survives a page navigation within the same tab when enabled and is gone in a new tab.
- **Commit:** `feat(widget): connect chat UI to REST API with history`
- **Handoff:** hook return values and client function names.

> **Claude Prompt (D14)**
>
> Senior React engineer. Project: Bryvium WP File Chat (no external libraries, `bwfc:` Tailwind prefix).
> Task D14: API integration and chat logic. Create `src/api/client.js` and `src/hooks/useChat.js`; changed parts only for `src/App.jsx`, `MessageList.jsx`, `MessageInput.jsx`.
> Requirements: POST to the chat route with the REST nonce header; timeout via AbortController; typing indicator; friendly errors for network, 429, and 5xx; block double sends; input trim and 300-char limit; clickable suggestion chips; optional `sessionStorage` history in try and catch; render answers safely as text with safe link detection (no `dangerouslySetInnerHTML`); greeting as first message.
> Existing context: chat response shape `status, answer, confidence, suggestions`; config keys; D13 component props.
> Output rules: only listed files, short comments. End with 4 manual tests (success, offline, rate limit, history), the commit message, and a 2-line handoff for D15.

---

#### D15: Accessibility and Responsiveness

- **Branch:** `feature/d15-a11y-mobile`
- **Goal:** Usable by everyone on every screen size.
- **Files:** modify `src/components/ChatLauncher.jsx`, `ChatWindow.jsx`, `MessageList.jsx`, `MessageInput.jsx`, `src/App.jsx`, `src/index.css`.
- **Tasks:** proper roles and labels; live region announcing new bot messages; focus moves into the window on open and returns to the launcher on close; Escape closes the window; focus is contained while open; visible focus rings; sufficient color contrast; touch targets large enough; full-screen layout on small screens with safe-area handling; respect reduced-motion preference.
- **Acceptance:** fully keyboard operable; a screen reader announces replies; layout works at 320 px width; no horizontal scroll caused by the widget.
- **Commit:** `feat(widget): improve accessibility and mobile layout`
- **Handoff:** list of ARIA roles and keyboard shortcuts added.

> **Claude Prompt (D15)**
>
> Senior React and accessibility engineer. Project: Bryvium WP File Chat.
> Task D15: Accessibility and responsiveness. Modify the existing components and `src/index.css`; show only changed parts per file.
> Requirements: roles and labels; live region for new bot messages; focus management (move in, return on close, trap while open); Escape to close; visible focus; AA contrast; 44px touch targets; full-screen on small screens with safe-area padding; `prefers-reduced-motion` support. Keep the `bwfc:` prefix.
> Existing context: [Paste the component prop lists from D13 and D14.]
> Output rules: changed parts only, short comments. End with a keyboard and screen reader test checklist, the commit message, and a 2-line handoff for D16.

---

#### D16: Theming, Shortcode, Auto-Display

- **Branch:** `feature/d16-theme-shortcode`
- **Goal:** Owners control appearance and where the widget appears.
- **Files:** modify `includes/class-bryvium-widget.php`, `src/index.css`, `src/App.jsx`, `admin/views/page-settings.php`, `includes/class-bryvium-settings.php`.
- **Tasks:** output CSS variables from the saved primary color with a computed readable text color; shortcode that renders the widget on specific pages even when auto-display is off; auto-display toggle respected; optional simple visibility rules (hide on selected page ids) if it stays simple; make sure assets are enqueued whenever the shortcode is present; avoid double mounting when both auto-display and shortcode exist.
- **Acceptance:** changing the color in settings changes the widget; shortcode works on a page with auto-display off; only one widget instance ever appears.
- **Commit:** `feat(widget): add CSS-variable theming, shortcode and display rules`
- **Handoff:** variable names and shortcode usage.

> **Claude Prompt (D16)**
>
> Senior WordPress and React engineer. Project: Bryvium WP File Chat.
> Task D16: Theming, shortcode, display rules. Modify only the listed files and show changed parts only: `includes/class-bryvium-widget.php`, `src/index.css`, `src/App.jsx`, `admin/views/page-settings.php`, `includes/class-bryvium-settings.php`.
> Requirements: CSS variables from `primary_color` with an automatic contrast text color; a shortcode `[bryvium_chat]`; respect `auto_display`; ensure assets enqueue when the shortcode is used; guarantee a single mount; optional hide-on-page-ids setting (keep it simple).
> Existing context: settings keys from D8; widget class signatures from D12.
> Output rules: changed parts only. End with 3 manual tests, the commit message, and a 2-line handoff for D17.

---

### PHASE 5: HARDENING

#### D17: Security Audit

- **Branch:** `feature/d17-security-audit`
- **Goal:** Find and fix security gaps before release.
- **Files:** create `docs/SECURITY-CHECKLIST.md`; modify any PHP file where an issue is found.
- **Tasks:** review every input for sanitization and every output for escaping; confirm every route and admin screen has capability and nonce checks; confirm direct file access guards in every PHP file; confirm the data folder protections work on Apache and note the Nginx requirement for owners; test import with hostile files; confirm no secrets or paths leak in errors; confirm the front-end never injects raw HTML; record results and fixes in the checklist document.
- **Acceptance:** every checklist item is marked pass or fixed; PHPCS security-related sniffs show no errors.
- **Commit:** `security: complete audit and fix findings`
- **Handoff:** open issues, if any.

> **Claude Prompt (D17)**
>
> Senior WordPress security reviewer. Project: Bryvium WP File Chat.
> Task D17: Security audit. I will paste specific files one at a time. For each file, review for: missing sanitization or escaping, missing capability or nonce checks, direct access, path traversal, unsafe file handling, information leaks, XSS in React. Give me only a findings table (severity, location, issue, fix) and the corrected snippets. Also draft the content of `docs/SECURITY-CHECKLIST.md` as a pass/fail checklist.
> Files pasted in this session: [list the files you are pasting].
> Output rules: no rewrites of safe code, short explanations. End with the commit message.

---

#### D18: Performance Pass

- **Branch:** `feature/d18-performance`
- **Goal:** Confirm speed targets and reduce bundle size.
- **Files:** create `tools/generate-sample-knowledge.php`, `docs/PERFORMANCE.md`; modify `vite.config.js`, `src/` files only where size can be reduced.
- **Tasks:** generate a realistic 1,000-entry sample file; time the search and record results; profile memory use; check the cache is hit on repeated requests; inspect the bundle size and remove waste; confirm assets are not loaded on admin or unrelated screens; record all numbers in the performance document; set the practical limits you will advertise.
- **Acceptance:** search under 200 ms on 1,000 entries; bundle size recorded and within the target you set; results documented.
- **Commit:** `perf: benchmark search and reduce bundle size`
- **Handoff:** advertised limits.

> **Claude Prompt (D18)**
>
> Senior WordPress performance engineer. Project: Bryvium WP File Chat.
> Task D18: Performance. Create `tools/generate-sample-knowledge.php` (dev-only CLI script that writes a realistic 1,000-entry sample file with varied titles, answers, keywords) and draft `docs/PERFORMANCE.md` as a template with tables for search time, memory, bundle size, and cache behavior. Also suggest safe bundle-size reductions for my Vite config.
> Existing context: [Paste vite.config.js and the search method signature.]
> Output rules: short comments. End with a measurement procedure, the commit message, and a 2-line handoff for D19.

---

#### D19: i18n, Uninstall, Edge Cases

- **Branch:** `feature/d19-i18n-uninstall`
- **Goal:** Translation-ready plugin with clean removal and graceful failures.
- **Files:** create `uninstall.php`, `languages/bryvium-wp-file-chat.pot`; modify PHP files for any missed strings and edge cases.
- **Tasks:** load the text domain; verify every visible string is translatable, including strings passed to React; generate the translation template; uninstall removes options, transients, and (by an owner-controlled setting, default keep) the data folder; handle empty file, corrupted JSON, unwritable folder, and very large file with clear admin notices; add a one-click "reset to empty knowledge" safety action only if it stays simple.
- **Acceptance:** the template contains all strings; deleting the plugin removes options and transients; each edge case shows a helpful notice rather than a fatal error.
- **Commit:** `feat(i18n): add translations support, uninstall cleanup and edge-case handling`
- **Handoff:** list of edge cases covered.

> **Claude Prompt (D19)**
>
> Senior WordPress engineer. Project: Bryvium WP File Chat.
> Task D19: i18n, uninstall, edge cases. Create `uninstall.php`; list the exact steps to generate `languages/bryvium-wp-file-chat.pot`; give changed parts only for any file needing text-domain loading or edge-case notices.
> Requirements: uninstall guard (`WP_UNINSTALL_PLUGIN`); delete the settings option, version option, and plugin transients; delete the data folder only if a setting "remove data on uninstall" is on (default off); multisite aware; admin notices for empty, corrupted, unwritable, and oversized data file; strings translatable; JS strings passed through config.
> Existing context: [Paste option names and transient prefixes used so far.]
> Output rules: short comments. End with 5 edge-case tests, the commit message, and a 2-line handoff for D20.

---

#### D20: Manual QA Matrix

- **Branch:** `feature/d20-qa`
- **Goal:** Verified compatibility.
- **Files:** create `docs/QA-MATRIX.md`; modify code only for bugs found.
- **Tasks:** test on PHP 8.5 and later supported releases, and the latest WordPress; test on at least three themes (default block theme, a classic theme, a heavily styled theme); test with common caching plugins and with a page cache purge after edits; test multisite; test Apache and Nginx notes for folder protection; test browsers (Chrome, Firefox, Safari, mobile Safari, mobile Chrome); test with a conflicting plugin that loads React; record pass or fail with notes.
- **Acceptance:** matrix complete; blocking bugs fixed; known limitations written down.
- **Commit:** `test: complete QA matrix and fix findings`
- **Handoff:** known limitations list.

> **Claude Prompt (D20)**
>
> Senior QA lead for WordPress plugins. Project: Bryvium WP File Chat.
> Task D20: Produce the full content of `docs/QA-MATRIX.md` as a ready-to-fill checklist table covering PHP versions, WordPress versions, themes, caching plugins, multisite, browsers, devices, server types, accessibility checks, and security smoke tests, each with steps and expected results. I will report failures and you will propose fixes one at a time.
> Existing context: [Paste the feature list built so far.]
> Output rules: Markdown only, no code. End with the commit message.

---

### PHASE 6: RELEASE

#### D21: Release Assets and Packaging

- **Branch:** `feature/d21-release-assets`
- **Goal:** Everything needed to publish.
- **Files:** create `readme.txt`, `CHANGELOG.md`, `scripts/build-zip.mjs`; add screenshots to a `.wordpress-org/` folder.
- **Tasks:** write the WordPress.org-format readme (description, installation, FAQ, screenshots list, changelog, upgrade notice, privacy statement that explains no external calls); write the changelog; packaging script that produces a clean zip containing only runtime files (no tooling, no sources, no dev scripts, no sample data); verify the zip installs on a clean site; set stable tag and version numbers consistently across the plugin header, constants, readme, and package file.
- **Acceptance:** the zip contains only what customers need; installs and activates on a clean WordPress site; version numbers match everywhere.
- **Commit:** `build: add readme, changelog and release packaging`
- **Handoff:** the release zip name and contents list.

> **Claude Prompt (D21)**
>
> Senior WordPress plugin release engineer. Project: Bryvium WP File Chat.
> Task D21: Release assets. Write the full `readme.txt` in WordPress.org format (short description, description, installation, FAQ, screenshots list, changelog, upgrade notice, privacy section stating no external calls and no personal data stored), a `CHANGELOG.md`, and `scripts/build-zip.mjs` (Node, no dependencies) that builds a zip containing only runtime files and excludes tooling, sources, docs, tools, and tests.
> Existing context: plugin name, version, feature list, minimum PHP and WP versions, and the file map from the documentation.
> Output rules: short comments. End with a version-consistency checklist, the commit message, and a 2-line handoff for D22.

---

#### D22: Tag, Release, Deploy

- **Branch:** `release/1.0.0`
- **Goal:** Ship v1.0.0.
- **Files:** create `docs/RELEASE-CHECKLIST.md`.
- **Tasks:** final full test on a clean site; merge `develop` into `main`; tag `v1.0.0`; build the final zip; publish the release; (optional) submit to WordPress.org; set up a simple support routine; keep a list of feedback for v1.1.
- **Acceptance:** tagged release exists; the zip is downloadable and installs cleanly; checklist signed off.
- **Commit:** `chore(release): prepare 1.0.0`
- **Handoff:** feedback log and v1.1 backlog.

> **Claude Prompt (D22)**
>
> Senior WordPress plugin release manager. Project: Bryvium WP File Chat.
> Task D22: Draft `docs/RELEASE-CHECKLIST.md` with pre-release checks, git merge and tag steps, packaging steps, publishing steps (GitHub release and optional WordPress.org submission), post-release monitoring for the first two weeks, and a rollback plan. Markdown only, no code.
> Existing context: branch model and versioning rules from the project documentation.
> Output rules: concise checklist. End with the final commit message.

---

## 12. How to Use the Daily Claude Prompts

**Every day, follow this routine:**

1. Open this document and find today's day.
2. Create the feature branch listed for that day.
3. Copy that day's **Claude Prompt** into a new chat with Claude.
4. Replace every bracketed placeholder with the real context. Paste only signatures and short notes, never entire files, to save tokens.
5. Review the generated code, paste it into the exact files listed for the day.
6. Run the acceptance checks and the quality gates from Section 10.
7. Commit with the day's commit message and merge into `develop`.
8. Save the **handoff note** Claude gives you at the end. It becomes the "Existing context" for the next day.

**Token-saving habits**

- Paste function signatures and result shapes, not whole files.
- If a day is too large, split it (for example D5a scoring and D5b fuzzy fallback).
- Ask for changed methods only when editing existing files.
- Keep one running "handoff log" file for yourself with all the notes.

**Standard header for any extra request (optional)**

> Project: Bryvium WP File Chat. Zero runtime dependencies, pure PHP 8.5+, WordPress 7.1.4+, JSON flat-file, WPCS, prefix `bryvium_`, text domain `bryvium-wp-file-chat`, React plus Tailwind v4 (`bwfc:` prefix, no preflight) built with Vite. Task: [what you need]. Files: [exact paths]. Context: [signatures only]. Output: code only for those files, short comments, then a 3-step test, a commit message, and a 2-line handoff.

---

## 13. Future Roadmap

| Version | Focus |
|---|---|
| v1.0 | Core chat, admin CRUD, import and export, rate limiting, basic theming |
| v1.1 | Local log of unanswered questions with a "turn into an answer" workflow; quick-reply suggestion chips managed in admin |
| v1.2 | Bulk import from CSV and Markdown; category filters in the widget; multilingual stopword lists |
| v1.3 | Sharded knowledge files for very large sites; a prebuilt search index file |
| v1.4 | Gutenberg block; per-page visibility rules; local analytics dashboard |
| v2.0 | Optional, opt-in provider interface that is off by default and never required |

---

## 14. Release and Deployment Checklist (Summary)

- All 22 daily tasks merged and passing quality gates
- Security checklist fully passed
- Performance numbers documented and within targets
- QA matrix complete
- Version numbers identical in the plugin header, constants, `readme.txt`, and `package.json`
- Zip contains runtime files only and installs on a clean site
- `build/` is current and committed
- `main` merged, tagged `v1.0.0`
- Backup and rollback plan written
- Support and feedback routine ready

---

## 15. Risks and Troubleshooting

| Risk | Mitigation |
|---|---|
| Tools fail on paths with emoji or special characters | Keep the project in a plain ASCII folder path |
| Nginx servers ignore `.htaccess` | Document a server rule for owners and keep the blank index file; REST-only access is the real protection |
| Page caching serves a stale nonce | Use short-lived nonces carefully and document cache exclusion for the REST route; handle expired nonce with a friendly retry |
| Theme CSS collides with the widget | Prefixed utilities, no global reset, scoped box-sizing, high-specificity root |
| Large knowledge files slow search | Cache by modification time, advertise limits, shard in v1.3 |
| Concurrent edits corrupt data | Exclusive locking plus atomic rename |
| Another plugin loads a different React | The widget bundles its own React inside an IIFE, so it does not share globals |
| Running out of free-tier tokens mid-day | Split the day, paste signatures only, continue next session with the handoff note |
| Host runs an old PHP version | Syntax kept compatible so the requirement notice displays instead of a fatal error |

---

*End of document.*
