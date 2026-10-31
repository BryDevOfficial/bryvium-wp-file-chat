# Bryvium WP File Chat: v1.2.0 New Features Documentation

**Document type:** Feature plan and daily execution guide, no code
**Target plugin version:** 1.2.0
**Builds on:** v1.0.0 (stable release, tagged `v1.0.0`)
**Companion document:** `BRYVIUM-WP-FILE-CHAT-DOCS.md` (the v1.0 master doc stays untouched)

---

## Table of Contents

1. What v1.2.0 Adds
2. Scope Decision and Assumptions
3. Rules That Still Apply
4. Backward Compatibility Contract
5. Architecture Changes
6. Data Specifications (Schema v2, Settings, Routes, Unanswered Log)
7. Privacy and Security Additions
8. Separate Branching Model for v1.2
9. Commit Guidelines for v1.2
10. Definition of Done for v1.2
11. Daily Development Schedule (V12-D01 to V12-D19)
12. How to Use the Daily Claude Prompts
13. Release Checklist for v1.2.0
14. Risks and Mitigations
15. After v1.2.0 (Next Roadmap)

---

## 1. What v1.2.0 Adds

| # | Feature | Owner benefit | Visitor benefit |
|---|---|---|---|
| 1 | **Unanswered-question log** with a "turn into an answer" workflow | See what visitors ask that the bot cannot answer, then fix it in one click | Better answers over time |
| 2 | **Suggestion chips** (quick replies) managed from admin | Guide visitors to the most important topics | One tap instead of typing |
| 3 | **Bulk import from CSV and Markdown** with preview | Load hundreds of FAQs in minutes instead of typing them | None directly, but faster setup means better coverage |
| 4 | **Category filters in the widget** | Organize large knowledge bases | Narrow answers to one topic |
| 5 | **Multilingual stopwords and language-aware search** | Works well for non-English sites | Accurate answers in their own language |

Everything remains local-first: no external API calls, no database tables, no new runtime dependencies.

---

## 2. Scope Decision and Assumptions

- The original roadmap listed v1.1 (unanswered log, suggestion chips) and v1.2 (bulk import, category filters, multilingual stopwords) as separate releases. **v1.2.0 folds both into one release.** There will be no v1.1.0 tag.
- v1.0.0 must be released and tagged before V12-D01 starts, so v1.2 has a stable base.
- Hotfixes to v1.0.x continue on the v1 branches and are merged into the v1.2 integration branch regularly (see Section 8).
- Languages shipped in v1.2.0: English, Spanish, French, German, Portuguese, Italian, Filipino (Tagalog), and Cebuano. More can be added later by dropping in a new data file.
- Light stemming rules stay English-focused. Other languages get stopword filtering and accent folding, which gives most of the benefit without heavy per-language rules.

---

## 3. Rules That Still Apply

1. Zero runtime dependencies. Build tools stay dev-only.
2. No database tables. New data goes into flat files in the existing protected data folder or into the single settings option array.
3. Same prefixes: `bryvium_`, `BRYVIUM_WFC_`, `bwfc-`, Tailwind `bwfc:`, REST namespace `bryvium/v1`.
4. Sanitize in, escape out, capability and nonce on every state change.
5. One small daily task at a time. Split any day that grows too large.
6. Simplicity for non-technical owners. Every new feature ships with a safe default and a plain-language label.

---

## 4. Backward Compatibility Contract

These are promises v1.2.0 must keep. Any day that breaks one is not done.

- A `knowledge.json` written by v1.0.x loads and works in v1.2.0 with no manual steps.
- All new entry fields are optional. Missing fields use safe defaults.
- The chat response shape stays the same. New fields may only be added, never renamed or removed.
- Existing settings keep their meaning. New settings have defaults that preserve v1.0 behavior (for example the unanswered log is **off** by default).
- Existing shortcode and REST routes keep working.
- Upgrading creates an automatic backup of the knowledge file before any migration.
- Downgrading is not supported, but the backup allows manual recovery. This is stated in the readme.

---

## 5. Architecture Changes

### 5.1 New and Changed Components

| Component | Change |
|---|---|
| Upgrader (new) | Compares the stored plugin version with the current one, runs ordered migrations, takes a backup first, records the result |
| Storage | Supports schema v2 fields, category listing, optional migration hook |
| Search | Language-aware normalization and stopwords, optional category scope, language auto-detection |
| Unanswered Log (new) | Flat-file store for questions that fell below the confidence threshold, with dedupe, caps, retention, and redaction |
| REST (public) | Chat endpoint accepts an optional `category`; response may include `chips` |
| REST (admin) | New routes for the unanswered log and for import preview and commit |
| Importer | Becomes a router that hands work to the JSON, CSV, or Markdown parser |
| CSV Parser (new) | Strict, size-limited, delimiter-aware CSV reading |
| Markdown Parser (new) | Converts headings and body text into entries with optional keyword and category lines |
| Admin UI | New "Unanswered" screen, chips controls in the knowledge form, import preview flow, language setting |
| Widget | Category filter bar, suggestion chips component |

### 5.2 Data Flow Changes

**Chat request (v1.2):**
1. Same validation, nonce, and rate limiting as v1.0.
2. If the widget sent a category, search is limited to entries in that category.
3. Search picks the stopword list from the language setting. In auto mode it detects the language from the question.
4. If confidence is below the threshold and logging is on, the sanitized and redacted question is written to the unanswered log, then the fallback is returned together with chips.

**Import (v1.2):**
1. Owner uploads or pastes content and picks the format.
2. Server parses it in preview mode (nothing is saved) and returns a report: valid rows, duplicate rows, rejected rows with reasons.
3. Owner confirms. Server makes a backup, then writes in merge or replace mode.

### 5.3 File Locations

All data files live in the same protected folder created in v1.0.

| File | Purpose |
|---|---|
| `knowledge.json` | Knowledge entries (schema v2) |
| `unanswered.json` | Unanswered question log |
| `backups/` subfolder | Timestamped backups created before migration and before replace-imports |

The subfolder inherits the same protection (deny rule and blank index file).

---

## 6. Data Specifications

### 6.1 Knowledge Entry, Schema Version 2

Fields from v1.0 stay exactly the same. New optional fields:

| Field | Type | Rules |
|---|---|---|
| `featured` | boolean | Default false. When true, the entry may appear as a suggestion chip |
| `chip_label` | string | Optional, max 40 characters. Short label shown on the chip. Falls back to the title if empty |
| `chip_order` | integer | Optional, 0 to 999, lower shows first. Default 100 |

The file wrapper `schema_version` changes from 1 to 2 during migration.

Language is a **global setting**, not a per-entry field, to keep owner effort low.

### 6.2 Unanswered Log Entry

| Field | Type | Rules |
|---|---|---|
| `id` | string | Generated, unique |
| `question` | string | Normalized and redacted text, max 300 characters |
| `count` | integer | How many times this normalized question was asked |
| `first_seen` | integer | Unix timestamp |
| `last_seen` | integer | Unix timestamp |
| `best_score` | number | Highest confidence reached, helps spot "almost matched" questions |
| `status` | string | `open`, `dismissed`, or `resolved` |
| `category_hint` | string | Optional, the category the visitor had selected, if any |

**What is never stored:** IP address, user id, cookies, user agent, page URL, or the raw unredacted text.

### 6.3 New Settings (added to the existing single option array)

| Setting | Purpose | Default |
|---|---|---|
| `search_language` | `auto` or a specific language code | `auto` |
| `log_unanswered` | Turn the unanswered log on or off | Off |
| `unanswered_max_entries` | Cap on stored questions | 500 |
| `unanswered_retention_days` | Auto-delete entries older than this | 90 |
| `show_category_filter` | Show the category bar in the widget | Off |
| `show_chips` | Show suggestion chips | On, but only if at least one entry is featured |
| `chips_max` | Maximum chips shown at once | 4 |

### 6.4 Routes

**Public (namespace `bryvium/v1`)**

| Route | Change |
|---|---|
| POST `/chat` | New optional `category` argument; response may include `chips` (list of id and label) |

Categories for the filter bar are delivered through the widget config printed by PHP, not through an extra request, to avoid another public endpoint.

**Admin (all require `manage_options` and a valid REST nonce)**

| Route | Purpose |
|---|---|
| GET `/unanswered` | List with status filter, search, and pagination |
| POST `/unanswered/{id}/dismiss` | Mark as dismissed |
| POST `/unanswered/{id}/resolve` | Mark as resolved (used after creating an entry) |
| DELETE `/unanswered/{id}` | Remove one |
| DELETE `/unanswered` | Clear all, with a confirmation flag |
| POST `/import/preview` | Parse CSV or Markdown and return a dry-run report |
| POST `/import/commit` | Apply a previewed import in merge or replace mode |

The v1.0 JSON `/import` and `/export` routes keep working.

### 6.5 Import Formats

**CSV rules**
- UTF-8 only (a leading byte-order mark is tolerated and removed).
- First row is a header. Recognized columns: `title`, `answer`, `keywords`, `category`, `enabled`, `featured`, `chip_label`. `title` and `answer` are required.
- Keywords in one cell are separated by a semicolon or a pipe.
- Delimiter is detected among comma, semicolon, and tab.
- Limits: 1 MB file size, 1,000 rows per import, cell length follows the entry schema.

**Markdown rules**
- Each level-2 heading starts a new entry. The heading text is the title.
- The body under the heading is the answer.
- Optional lines at the very top of a body: `Keywords:` followed by a comma-separated list, and `Category:` followed by a name. These lines are removed from the answer.
- Limits: 1 MB file size, 1,000 entries.
- Raw HTML in the Markdown is stripped. Only plain text, line breaks, and links survive.

---

## 7. Privacy and Security Additions

| Concern | Rule |
|---|---|
| Unanswered log is opt-in | Off by default. The setting screen explains exactly what is stored |
| Redaction before logging | Email-like strings, long digit sequences (phone or card-like), and URLs with credentials are removed or masked before anything is written |
| Retention | Automatic pruning by age and by maximum count, run lazily on write and on a scheduled cleanup |
| Admin-only access | Every unanswered route and screen needs `manage_options` and a nonce |
| Log file protection | Same protected folder, same deny rule, never served directly |
| Import hardening | Size and row caps, strict parsing, no execution of uploaded content, HTML stripped, preview before commit, backup before replace |
| CSV injection | Cells that begin with formula characters are neutralized on export and on import display |
| Chips and categories | Output escaped in PHP, rendered as text in React |
| Uninstall | Log and backups are removed together with the data folder only when the owner enabled "remove data on uninstall" |
| Privacy statement | readme and settings screen state clearly that nothing leaves the server |

---

## 8. Separate Branching Model for v1.2

v1.2 uses its own branch family so it never mixes with the v1.0 branches (`develop`, `feature/dNN-...`).

| Branch | Role |
|---|---|
| `main` | Stable. Only receives merges at release time (v1.0.x hotfix releases and v1.2.0) |
| `develop` | v1.0 integration branch. Frozen for new features. Used only for v1.0.x maintenance |
| `v12-develop` | **Integration branch for all v1.2 work.** Created from `main` at tag `v1.0.0` (or the latest v1.0.x) |
| `v12-feature/dNN-topic` | One branch per daily task, for example `v12-feature/d04-category-backend` |
| `v12-fix/issue-name` | Bug fixes found during v1.2 work |
| `v12-release/1.2.0` | Release preparation, created from `v12-develop` |
| `hotfix/issue-name` | Urgent v1.0.x fixes from `main` (unchanged from v1) |

**Why the names look like this:** the `v12-` prefix keeps these branches visually and logically separate from every v1.0 branch, and avoids Git conflicts that would happen with names like `develop/v1.2`.

**Tags**
- Release candidates: `v1.2.0-rc.1`, `v1.2.0-rc.2`
- Final: `v1.2.0`

**Daily Git routine**
1. Make sure `v12-develop` is up to date.
2. Create today's `v12-feature/dNN-topic` branch from it.
3. Do the work and run the quality gates.
4. Commit with the day's commit message.
5. Merge into `v12-develop` with a merge commit, then delete the feature branch.

**Keeping v1.0 fixes in sync**
- After any `hotfix/...` lands on `main`, merge `main` into `v12-develop` the same day so the fix is never lost.
- Never merge `v12-develop` into `develop`. The v1.0 line stays clean.

**Release routine**
1. Create `v12-release/1.2.0` from `v12-develop`.
2. Only bug fixes, version bumps, and release assets go on this branch.
3. Tag release candidates from it for testing.
4. Merge `v12-release/1.2.0` into `main`, tag `v1.2.0`.
5. Merge `main` back into `v12-develop` and `develop` so all lines contain the release.

---

## 9. Commit Guidelines for v1.2

Same Conventional Commits format as v1.0. Additional scopes for v1.2:

| Scope | Use |
|---|---|
| `upgrade` | Version migration and backup logic |
| `unanswered` | Unanswered log |
| `chips` | Suggestion chips |
| `import` | CSV and Markdown import |
| `category` | Category filters |
| `lang` | Multilingual search |

Existing scopes (`storage`, `search`, `rest`, `admin`, `widget`, `build`, `i18n`) still apply.

Each daily commit message in Section 11 includes the v1.2 marker in the body line so history is easy to filter.

---

## 10. Definition of Done for v1.2

A v1.2 day is done only when:

1. The day's acceptance checks pass.
2. PHPCS reports zero errors.
3. Debug mode and debug log show nothing new.
4. No new outbound network requests.
5. The Backward Compatibility Contract (Section 4) still holds. The quickest check is to load a real v1.0 `knowledge.json` and run a chat.
6. The commit is made and merged into `v12-develop`.
7. The handoff note is saved.

---

## 11. Daily Development Schedule

Day ids use the `V12-` prefix so they never get confused with v1.0 days.

---

### PHASE A: FOUNDATION AND LANGUAGE

#### V12-D01: Version Setup, Upgrader, Schema v2

- **Branch:** `v12-feature/d01-upgrader-schema`
- **Goal:** A safe upgrade path from v1.0 data to schema v2.
- **Files:** create `includes/class-bryvium-upgrader.php`; modify `bryvium-wp-file-chat.php` (version constants to `1.2.0-dev`), `includes/class-bryvium-loader.php`, `includes/class-bryvium-storage.php`; create `CHANGELOG.md` entry for the unreleased version (file already exists from v1.0, only add a section).
- **Tasks:**
  1. Create `v12-develop` from the v1.0.0 tag.
  2. Add the upgrader that compares the stored version option with the current constant on every admin load (cheap check).
  3. Add ordered migration steps. The first step upgrades the knowledge file from schema 1 to 2 by adding default values for the new fields.
  4. Create a timestamped backup in the `backups/` subfolder, with protection files, before any migration.
  5. Make migration idempotent and lock-protected so two simultaneous requests cannot run it twice.
  6. If migration fails, keep the old file untouched, show an admin notice, and keep the plugin working in read-only compatibility mode.
  7. Storage accepts and validates the new optional fields.
  8. Record the new version only after success.
- **Acceptance:** a v1.0 file upgrades and still answers questions; a backup file exists; running the upgrade twice changes nothing the second time; a simulated failure leaves the original file intact and shows a notice.
- **Commit:** `feat(upgrade): add upgrader, schema v2 and pre-migration backup` with body line `v1.2 foundation`.
- **Handoff:** upgrader method names, backup folder path rules, new entry fields accepted by storage.

> **Claude Prompt (V12-D01)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat, now building v1.2.0 on top of v1.0.0. Rules: zero runtime dependencies, pure PHP 7.4+, JSON flat-file, WPCS, prefix `bryvium_`, text domain `bryvium-wp-file-chat`.
> Task V12-D01: Upgrader and schema v2. Create `includes/class-bryvium-upgrader.php`; give changed parts only for `bryvium-wp-file-chat.php`, `includes/class-bryvium-loader.php`, `includes/class-bryvium-storage.php`.
> Requirements: compare stored version option to current constant on admin load; ordered, idempotent migrations guarded by a lock (transient or lock file); step 1 migrates `knowledge.json` from schema_version 1 to 2 adding optional fields `featured` (bool, default false), `chip_label` (string max 40), `chip_order` (int 0 to 999, default 100); timestamped backup in a protected `backups/` subfolder of the existing data directory (deny rule plus blank index file) before migrating; on failure keep the old file untouched, show an admin notice, keep working; save the new version only on success; storage validates and sanitizes the new fields; v1.0 files must keep working with no manual steps.
> Existing context: [Paste the storage class public method signatures, the loader method list, the data directory helper name, and the version option name.]
> Output rules: code only for the listed files, short comments. End with 4 manual tests (upgrade success, double run, simulated failure, v1.0 file still answers), the Conventional Commit message, and a 2-line handoff for V12-D02.

---

#### V12-D02: Multilingual Stopword Data and Language Setting

- **Branch:** `v12-feature/d02-lang-data-setting`
- **Goal:** Stopword lists for each language and an owner-friendly language setting.
- **Files:** create `includes/data/stopwords-es.php`, `stopwords-fr.php`, `stopwords-de.php`, `stopwords-pt.php`, `stopwords-it.php`, `stopwords-tl.php`, `stopwords-ceb.php` (all inside `includes/data/`); modify `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`.
- **Tasks:** each data file returns a plain list of lowercase, accent-folded stopwords; keep lists focused (common function words only, no content words that could hide real answers); add the `search_language` setting with a whitelist of codes plus `auto`; add a clear dropdown on the settings page with the language names written in their own language; validate that the chosen code has a data file, otherwise fall back to English.
- **Acceptance:** each file loads without errors and has no duplicates; the setting saves and rejects unknown codes; the dropdown works.
- **Commit:** `feat(lang): add multilingual stopword lists and language setting`
- **Handoff:** the list of supported codes and the file naming rule.

> **Claude Prompt (V12-D02)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D02: Multilingual stopwords and language setting.
> Create `includes/data/stopwords-es.php`, `-fr.php`, `-de.php`, `-pt.php`, `-it.php`, `-tl.php` (Filipino/Tagalog), `-ceb.php` (Cebuano). Each returns an array of lowercase, accent-folded common function words only (articles, pronouns, prepositions, conjunctions, common auxiliary verbs, question words). No content words. No duplicates. Match the format of the existing `stopwords-en.php`.
> Also give changed parts only for `includes/class-bryvium-settings.php` and `admin/views/page-settings.php`: new setting `search_language` (`auto` or one of `en, es, fr, de, pt, it, tl, ceb`), whitelist sanitization, dropdown with each language shown in its own language, fallback to `en` if invalid.
> Existing context: [Paste the format of the existing English stopword file (first 5 lines) and the settings class method list.]
> Output rules: code only for listed files, short comments. End with 3 manual tests, the commit message, and a 2-line handoff for V12-D03.

---

#### V12-D03: Language-Aware Search and Auto-Detection

- **Branch:** `v12-feature/d03-lang-search`
- **Goal:** Search uses the right stopwords and handles accents per language.
- **Files:** modify `includes/class-bryvium-search.php`, `includes/class-bryvium-rest.php` (pass language through if needed).
- **Tasks:** load the correct stopword list based on the setting; implement lightweight auto-detection by comparing how many query words appear in each language's list and picking the highest ratio above a minimum, otherwise English; cache loaded lists in memory for the request; keep English stemming only for English; accent folding works for every supported language; make sure mixed-language questions still work; keep scoring unchanged so v1.0 results are identical for English sites.
- **Acceptance:** Spanish, French, and Cebuano sample questions find the right entry; auto mode picks the right language for clear sentences and falls back to English for ambiguous ones; English results are identical to v1.0 on the same data.
- **Commit:** `feat(lang): add language-aware search with auto-detection`
- **Handoff:** the method that resolves the active language and its return value.

> **Claude Prompt (V12-D03)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D03: Language-aware search. Modify `includes/class-bryvium-search.php` (and `includes/class-bryvium-rest.php` only if needed). Show changed methods only.
> Requirements: choose the stopword list from the `search_language` setting; for `auto`, detect language by the share of query tokens found in each language's stopword list, pick the best above a minimum ratio, else English; load each list once per request; English-only stemming stays English-only; accent folding for all languages; English results must be identical to v1.0 (no scoring changes); mixed-language input must not error.
> Existing context: [Paste the search class method signatures, the stopword file naming rule, and the supported codes from the V12-D02 handoff.]
> Output rules: changed methods only, short comments. End with 5 manual test questions (English, Spanish, French, Cebuano, ambiguous), the commit message, and a 2-line handoff for V12-D04.

---

### PHASE B: CATEGORY FILTERS

#### V12-D04: Category Filter Backend

- **Branch:** `v12-feature/d04-category-backend`
- **Goal:** Search can be limited to one category and the widget can learn which categories exist.
- **Files:** modify `includes/class-bryvium-search.php`, `includes/class-bryvium-storage.php`, `includes/class-bryvium-rest.php`.
- **Tasks:** add an optional category parameter to search that restricts candidates to enabled entries of that category (case-insensitive exact match on the stored category); add a storage method that returns the list of categories with counts, counting only enabled entries, cached with the same modification-time cache; the chat endpoint accepts an optional `category` argument, sanitized and length-limited; unknown categories behave as no filter and are reported safely; no change to the response shape except optional additions.
- **Acceptance:** a question with a category only returns entries from that category; without a category behavior equals v1.0; the category list excludes disabled entries and empty names; the cache refreshes after an edit.
- **Commit:** `feat(category): add category-scoped search and category listing`
- **Handoff:** the category list return shape and the new REST argument.

> **Claude Prompt (V12-D04)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D04: Category filter backend. Modify `includes/class-bryvium-search.php`, `includes/class-bryvium-storage.php`, `includes/class-bryvium-rest.php`. Show changed or new methods only.
> Requirements: optional category parameter in search (case-insensitive exact match, enabled entries only); storage method returning categories with counts for enabled entries (skip empty names), cached via the existing modification-time cache; POST `/chat` accepts optional `category` (sanitized, max 60 chars), unknown category behaves as no filter; keep the response shape and only add optional fields.
> Existing context: [Paste search method signature, storage signatures, REST args schema.]
> Output rules: changed parts only. End with 4 manual tests (with category, without, disabled entries, cache refresh), the commit message, and a 2-line handoff for V12-D05.

---

#### V12-D05: Category Filter in the Widget

- **Branch:** `v12-feature/d05-category-widget`
- **Goal:** Visitors can pick a category before or while chatting.
- **Files:** create `src/components/CategoryFilter.jsx`; modify `src/components/ChatWindow.jsx`, `src/App.jsx`, `src/hooks/useChat.js`, `src/api/client.js`, `includes/class-bryvium-widget.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`.
- **Tasks:** add the `show_category_filter` setting (default off) with a plain label; PHP passes the category list in the widget config only when the setting is on and at least two categories exist; filter bar sits under the header, scrolls horizontally on small screens, with an "All" option selected by default; selected category is sent with each question and kept in session history; accessible (proper roles, keyboard arrows, visible focus, announced selection); all classes use the `bwfc:` prefix.
- **Acceptance:** with the setting off nothing changes; with it on the bar appears and questions are scoped; selection survives within the session; keyboard and screen reader work.
- **Commit:** `feat(category): add category filter bar to the widget`
- **Handoff:** config key names and component props.

> **Claude Prompt (V12-D05)**
>
> Senior React and WordPress engineer. Project: Bryvium WP File Chat v1.2.0 (Tailwind v4 with `bwfc:` prefix, no preflight, no external UI libraries).
> Task V12-D05: Category filter bar. Create `src/components/CategoryFilter.jsx`; changed parts only for `src/components/ChatWindow.jsx`, `src/App.jsx`, `src/hooks/useChat.js`, `src/api/client.js`, `includes/class-bryvium-widget.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`.
> Requirements: setting `show_category_filter` (default off); PHP adds `categories` (name and count) to the widget config only if the setting is on and at least two categories exist; horizontal scrollable bar with an "All" default; selected category sent with each chat request and preserved in session history; keyboard arrow navigation, ARIA roles, visible focus, announced selection; text rendered safely.
> Existing context: [Paste the D04 category list shape, the config keys, ChatWindow props, and the useChat return values.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D06.

---

### PHASE C: SUGGESTION CHIPS

#### V12-D06: Chips Management (Backend and Admin)

- **Branch:** `v12-feature/d06-chips-admin`
- **Goal:** Owners choose which entries appear as chips.
- **Files:** modify `admin/views/page-knowledge.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`, `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`, `includes/class-bryvium-storage.php` (chip list method).
- **Tasks:** add "Show as quick reply" toggle, chip label field, and chip order field to the entry form with a character counter; show a star or badge in the entries list for featured entries and a filter "Featured only"; add `show_chips` and `chips_max` settings with sane limits (1 to 8); storage method returns featured and enabled entries sorted by order, trimmed to the maximum, as id and label pairs; REST admin create and update accept the new fields with validation.
- **Acceptance:** featured entries are saved and sorted correctly; label falls back to the title when empty; invalid values are corrected; the list filter works.
- **Commit:** `feat(chips): add chip fields, ordering and settings in admin`
- **Handoff:** chip list method name and return shape.

> **Claude Prompt (V12-D06)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0 (admin is plain PHP views plus vanilla JS).
> Task V12-D06: Chips management in admin. Changed parts only for `admin/views/page-knowledge.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`, `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`, `includes/class-bryvium-storage.php`.
> Requirements: entry form gets a "Show as quick reply" checkbox (`featured`), `chip_label` (max 40, live counter), `chip_order` (0 to 999); entries list shows a badge for featured and a "Featured only" filter; settings `show_chips` (default on) and `chips_max` (1 to 8, default 4); storage method returns enabled and featured entries sorted by `chip_order` then title, limited to `chips_max`, as id and label (label falls back to title); admin REST validates and saves the new fields.
> Existing context: [Paste the entry schema v2 fields, the storage signatures, the admin JS entry form ids, and the admin REST arg schema.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D07.

---

#### V12-D07: Chips in the Widget

- **Branch:** `v12-feature/d07-chips-widget`
- **Goal:** Visitors see quick replies at the start and after a failed answer.
- **Files:** create `src/components/SuggestionChips.jsx`; modify `src/components/MessageList.jsx`, `src/hooks/useChat.js`, `src/App.jsx`, `includes/class-bryvium-widget.php`, `includes/class-bryvium-rest.php`.
- **Tasks:** PHP passes the starting chips in the config when `show_chips` is on; the chat endpoint adds `chips` to the fallback response (same list) so visitors always have a next step; tapping a chip sends its label as the question and, to avoid ambiguity, sends the entry id so the exact entry answers (add a safe optional `entry_id` argument that returns that entry directly if it exists, is enabled, and is featured); chips are wrap-friendly buttons with proper focus and accessible names; chips disappear after the first user message and reappear only after a fallback.
- **Acceptance:** chips show initially; tapping one returns the exact entry answer; fallback replies show chips; disabling `show_chips` hides everything; a disabled or deleted entry id returns a safe fallback instead of an error.
- **Commit:** `feat(chips): show quick replies in the widget and on fallback`
- **Handoff:** the `entry_id` argument rules and the `chips` response field.

> **Claude Prompt (V12-D07)**
>
> Senior React and WordPress engineer. Project: Bryvium WP File Chat v1.2.0 (`bwfc:` Tailwind prefix, no external libraries).
> Task V12-D07: Suggestion chips in the widget. Create `src/components/SuggestionChips.jsx`; changed parts only for `src/components/MessageList.jsx`, `src/hooks/useChat.js`, `src/App.jsx`, `includes/class-bryvium-widget.php`, `includes/class-bryvium-rest.php`.
> Requirements: config carries `chips` (id and label) when `show_chips` is on; fallback response adds the same `chips`; optional `entry_id` argument on POST `/chat` that returns that exact entry only if it exists, is enabled, and is featured, otherwise a safe fallback; chips are accessible buttons that wrap on small screens; hidden after the first user message, shown again after a fallback; text rendered safely.
> Existing context: [Paste the chip list shape from the V12-D06 handoff, the REST args schema, MessageList props, useChat return values.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D08.

---

### PHASE D: UNANSWERED QUESTION LOG

#### V12-D08: Log Storage and Capture

- **Branch:** `v12-feature/d08-unanswered-store`
- **Goal:** Safely record questions the bot could not answer, only when the owner opted in.
- **Files:** create `includes/class-bryvium-unanswered.php`; modify `includes/class-bryvium-rest.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`, `includes/class-bryvium-loader.php`.
- **Tasks:**
  1. Create the log class using the same locking and atomic write approach as the knowledge storage, writing to `unanswered.json` in the protected folder.
  2. Add the settings `log_unanswered` (default off), `unanswered_max_entries` (default 500, range 50 to 2,000), and `unanswered_retention_days` (default 90, range 7 to 365), with a plain-language privacy explanation on the settings page.
  3. Redact emails, long digit runs, and credentialed URLs, then normalize and truncate to 300 characters before storing.
  4. Deduplicate by normalized text: increase the count, update last seen, keep the highest score.
  5. Enforce the maximum by dropping the oldest dismissed or resolved items first, then the oldest open ones.
  6. Prune by retention lazily on write and through a once-daily scheduled cleanup registered on activation and removed on deactivation.
  7. The chat endpoint calls the logger only when the answer fell below the threshold and the setting is on. Logging must never slow down or break the reply: failures are swallowed and written to the debug log only.
- **Acceptance:** with the setting off nothing is written; with it on a fallback question appears once and repeats increase its count; email and phone-like text never reaches the file; caps and retention work; a read-only folder does not break chat.
- **Commit:** `feat(unanswered): add opt-in unanswered question log with redaction and retention`
- **Handoff:** the log class method names and the entry shape.

> **Claude Prompt (V12-D08)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0 (zero dependencies, flat-file, WPCS, PHP 7.4+).
> Task V12-D08: Unanswered log storage and capture. Create `includes/class-bryvium-unanswered.php`; changed parts only for `includes/class-bryvium-rest.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`, `includes/class-bryvium-loader.php`.
> Requirements: `unanswered.json` in the existing protected data folder with shared and exclusive locking and atomic rename like the knowledge storage; entry fields: id, question (normalized and redacted, max 300), count, first_seen, last_seen, best_score, status (`open`, `dismissed`, `resolved`), category_hint; redact emails, long digit runs, and URLs with credentials before saving; dedupe by normalized text; settings `log_unanswered` (default off), `unanswered_max_entries` (default 500, range 50 to 2000), `unanswered_retention_days` (default 90, range 7 to 365); enforce the cap by dropping the oldest dismissed or resolved first; prune by retention on write and with a daily scheduled event registered on activation and cleared on deactivation; the chat endpoint logs only on a below-threshold answer when enabled; logging failures never affect the reply; never store IP, user id, user agent, or URL.
> Existing context: [Paste the storage class locking and data directory helper signatures, the REST chat handler fallback branch description, and the settings class method list.]
> Output rules: code only for listed files, short comments. End with 5 manual tests (off, on, dedupe, redaction, read-only folder), the commit message, and a 2-line handoff for V12-D09.

---

#### V12-D09: Unanswered Admin REST

- **Branch:** `v12-feature/d09-unanswered-rest`
- **Goal:** Secure admin endpoints to manage the log.
- **Files:** modify `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-unanswered.php`.
- **Tasks:** implement the routes listed in Section 6.4 for the unanswered log; list supports status filter, text search, sorting by count or last seen, and pagination; clear-all requires an explicit confirmation flag; all routes need `manage_options` and a nonce; consistent response and error shapes matching the existing admin routes; resolve and dismiss are idempotent.
- **Acceptance:** non-admins are rejected; list, filter, sort, dismiss, resolve, delete, and clear-all behave correctly; clear-all without the flag is refused.
- **Commit:** `feat(rest): add admin routes for the unanswered log`
- **Handoff:** route list and response shapes.

> **Claude Prompt (V12-D09)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D09: Unanswered admin REST. Changed parts only for `includes/class-bryvium-rest-admin.php` and `includes/class-bryvium-unanswered.php`.
> Requirements: GET `/unanswered` (status, search, orderby count or last_seen, order, page, per_page), POST `/unanswered/{id}/dismiss`, POST `/unanswered/{id}/resolve`, DELETE `/unanswered/{id}`, DELETE `/unanswered` requiring a `confirm` flag; permission callback `manage_options`; nonce; args schema and sanitization; response and error shapes consistent with the existing entries routes; dismiss and resolve are idempotent.
> Existing context: [Paste the existing admin REST registration pattern, a sample response shape for entries, and the unanswered class method names.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D10.

---

#### V12-D10: Unanswered Admin Screen and "Turn Into Answer"

- **Branch:** `v12-feature/d10-unanswered-ui`
- **Goal:** Owners review unanswered questions and convert them into knowledge entries in one click.
- **Files:** create `admin/views/page-unanswered.php`; modify `includes/class-bryvium-admin.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`, `admin/views/page-knowledge.php`.
- **Tasks:** add the "Unanswered" submenu with a count badge of open items; table with question, times asked, last seen, closest score, and actions; filters for open, dismissed, resolved; sort by most asked; "Turn into answer" opens the knowledge form with the title prefilled from the question and a note; after saving the new entry the original item is marked resolved automatically; dismiss and delete with confirmation; friendly empty state that explains the log is off if it is off, with a button to the setting; "Clear all" with a confirmation step; assets loaded only on plugin screens.
- **Acceptance:** the badge shows the right number; converting creates an entry and resolves the item; dismissed items leave the open list; the empty state is correct for both "log off" and "nothing yet".
- **Commit:** `feat(unanswered): add admin screen and turn-into-answer workflow`
- **Handoff:** DOM ids and the prefill mechanism used between screens.

> **Claude Prompt (V12-D10)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0 (admin is plain PHP views plus vanilla JS).
> Task V12-D10: Unanswered screen. Create `admin/views/page-unanswered.php`; changed parts only for `includes/class-bryvium-admin.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`, `admin/views/page-knowledge.php`.
> Requirements: "Unanswered" submenu with a badge of open items; table with question, count, last seen, best score, actions; status filter and sort by count; "Turn into answer" opens the knowledge form with the title prefilled and, on save, marks the original item resolved; dismiss, delete, and clear-all with confirmations; empty state differentiating "log is off" (link to settings) from "nothing yet"; all output escaped; responsive.
> Existing context: [Paste the D09 route list and response shapes, the knowledge form DOM ids, and how admin assets are enqueued.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D11.

---

### PHASE E: BULK IMPORT

#### V12-D11: CSV Parser

- **Branch:** `v12-feature/d11-import-csv`
- **Goal:** Read CSV safely into candidate entries.
- **Files:** create `includes/class-bryvium-import-csv.php`.
- **Tasks:** UTF-8 only, remove a leading byte-order mark, reject other encodings with a clear message; detect delimiter among comma, semicolon, and tab; header row mapping (case-insensitive, trimmed, accepts the recognized columns in Section 6.5); required columns `title` and `answer`; keyword cell split on semicolon or pipe; boolean columns accept yes, no, true, false, 1, 0; per-row validation using the existing entry schema; return a structured result with valid rows, rejected rows (row number and reason), and warnings; limits of 1 MB and 1,000 rows enforced before heavy work; handle quoted cells, embedded line breaks, and empty lines; neutralize cells starting with formula characters for any display or export use.
- **Acceptance:** a clean CSV yields all rows; a messy CSV reports precise row errors; wrong delimiter styles are detected; oversized input is refused early; a file with no required columns is refused with guidance.
- **Commit:** `feat(import): add strict CSV parser with row-level validation`
- **Handoff:** parser method and result shape.

> **Claude Prompt (V12-D11)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0 (zero dependencies; use only PHP built-ins).
> Task V12-D11: CSV parser. Create `includes/class-bryvium-import-csv.php` only.
> Requirements: input is a string; UTF-8 only with BOM removal; detect delimiter (comma, semicolon, tab); header mapping case-insensitive for `title`, `answer`, `keywords`, `category`, `enabled`, `featured`, `chip_label` (title and answer required); keywords split on `;` or `|`; booleans accept yes/no/true/false/1/0; validate each row with the entry schema rules (title max 200, answer max 4000, keywords max 20 of 50 chars, category max 60, chip_label max 40); size cap 1 MB and 1000 rows checked early; handle quoted cells and embedded newlines; neutralize cells beginning with `=`, `+`, `-`, `@` for display; return a result array: `valid` (rows), `rejected` (row number and reason), `warnings`, `totals`.
> Existing context: [Paste the existing entry validation method signature from the storage class.]
> Output rules: code only for the listed file, short comments. End with 5 manual test CSVs described in words (clean, semicolon, bad rows, oversized, missing columns), the commit message, and a 2-line handoff for V12-D12.

---

#### V12-D12: Markdown Parser and Import Router

- **Branch:** `v12-feature/d12-import-markdown`
- **Goal:** Read Markdown FAQ files and route all formats through one entry point.
- **Files:** create `includes/class-bryvium-import-markdown.php`; modify `includes/class-bryvium-importer.php`.
- **Tasks:** level-2 headings become titles; body becomes the answer; optional `Keywords:` and `Category:` lines at the top of the body are extracted and removed; strip raw HTML; keep line breaks and safe links only; ignore content before the first level-2 heading; per-entry validation with the same schema; same result shape as the CSV parser; the importer gains a single method that accepts the format and content, applies size limits, calls the right parser, and returns the unified report; JSON stays supported through the v1.0 path.
- **Acceptance:** a typical FAQ Markdown file imports cleanly; HTML tags are removed; entries without a body are rejected with a reason; the router returns the same report shape for all three formats.
- **Commit:** `feat(import): add Markdown parser and unified import router`
- **Handoff:** router method name and report shape.

> **Claude Prompt (V12-D12)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D12: Markdown parser and import router. Create `includes/class-bryvium-import-markdown.php`; changed parts only for `includes/class-bryvium-importer.php`.
> Requirements: each `##` heading starts an entry (title); body is the answer; optional first lines `Keywords:` (comma separated) and `Category:` are extracted and removed from the answer; strip raw HTML; keep line breaks and safe links; ignore text before the first `##`; validate each entry with the existing schema; result shape identical to the CSV parser (`valid`, `rejected`, `warnings`, `totals`); the importer gets one method taking format (`json`, `csv`, `md`) plus content, applying the 1 MB cap and returning the unified report; keep the v1.0 JSON path working.
> Existing context: [Paste the CSV parser result shape from V12-D11, the current importer method list, and the entry validation signature.]
> Output rules: code only for listed files (changed methods only for the importer), short comments. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D13.

---

#### V12-D13: Import Preview and Commit Flow

- **Branch:** `v12-feature/d13-import-preview-ui`
- **Goal:** A safe two-step import: preview first, commit second.
- **Files:** modify `admin/views/page-tools.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`, `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-importer.php`.
- **Tasks:** format selector (JSON, CSV, Markdown) with short guidance and a downloadable sample file link for CSV and Markdown (generated text, no external hosting); upload or paste; the preview route returns the report and a short-lived token that references the parsed result stored temporarily (a transient) so the commit step does not re-upload the file; the preview screen shows counts, a sample of valid rows, and a clear list of rejected rows with reasons; duplicates detected by normalized title are flagged, with the choice to skip or update; mode selector merge or replace; replace requires typing a confirmation word; commit makes a backup first, writes atomically, then shows a summary (added, updated, skipped, rejected); tokens expire after a short time and are single-use; the existing export button can now also export CSV.
- **Acceptance:** preview never changes data; commit applies exactly what was previewed; expired or reused tokens are rejected; replace without confirmation is refused; a backup file exists after each replace; export then CSV re-import round-trips.
- **Commit:** `feat(import): add preview and commit flow with backup and duplicate handling`
- **Handoff:** route payloads and token rules.

> **Claude Prompt (V12-D13)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0 (plain PHP views and vanilla JS admin).
> Task V12-D13: Import preview and commit. Changed parts only for `admin/views/page-tools.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`, `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-importer.php`.
> Requirements: format selector (json, csv, md) with short guidance and an on-page sample content block for csv and md; upload or paste; POST `/import/preview` parses via the router, stores the parsed result in a transient with a random single-use token that expires in 15 minutes, and returns the report plus the token; preview UI shows totals, a sample of valid rows, rejected rows with reasons, and duplicates (matched by normalized title) with a skip or update choice; mode merge or replace; replace requires typing a confirmation word; POST `/import/commit` takes the token and options, creates a backup first, writes atomically, and returns a summary (added, updated, skipped, rejected); invalid, expired, or reused tokens are rejected; add CSV export alongside JSON export with formula-character neutralization; admin only and nonce protected.
> Existing context: [Paste the router method and report shape from V12-D12, the backup helper name from V12-D01, the existing tools page ids, and the admin REST registration pattern.]
> Output rules: changed parts only. End with 5 manual tests, the commit message, and a 2-line handoff for V12-D14.

---

### PHASE F: HARDENING AND RELEASE

#### V12-D14: i18n Update, Uninstall Update, Scheduled Events

- **Branch:** `v12-feature/d14-i18n-uninstall`
- **Goal:** All new strings translatable and clean removal for the new data.
- **Files:** modify `uninstall.php`, `includes/class-bryvium-loader.php`, `languages/bryvium-wp-file-chat.pot`; modify any file with missed strings.
- **Tasks:** make sure every new admin and widget string is translatable, including strings passed to React through the config; regenerate the translation template; uninstall also removes the scheduled cleanup event, new transients (import tokens), and, only when "remove data on uninstall" is on, `unanswered.json` and the backups folder; deactivation clears the scheduled event; multisite aware.
- **Acceptance:** the template includes the new strings; deactivate removes the scheduled event; uninstall with the option on leaves no plugin data; uninstall with the option off keeps the data folder.
- **Commit:** `feat(i18n): update translations and uninstall cleanup for v1.2 data`
- **Handoff:** list of new option names, transients, and scheduled hooks.

> **Claude Prompt (V12-D14)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D14: i18n and uninstall updates. Changed parts only for `uninstall.php` and `includes/class-bryvium-loader.php`; list exact steps to regenerate `languages/bryvium-wp-file-chat.pot`.
> Requirements: uninstall also clears the daily cleanup scheduled event, import-token transients, and (only when "remove data on uninstall" is on) `unanswered.json` and the `backups/` folder; deactivation clears the scheduled event; multisite aware; all new strings use the text domain.
> Existing context: [Paste the current uninstall steps, the list of new option names, transient prefixes, and scheduled hook name from V12-D08 and V12-D13.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V12-D15.

---

#### V12-D15: Security Audit (v1.2 Delta)

- **Branch:** `v12-feature/d15-security-delta`
- **Goal:** Audit only what is new or changed in v1.2.
- **Files:** create `docs/SECURITY-CHECKLIST-v1.2.md`; modify any PHP or JS file where an issue is found.
- **Tasks:** review every new route for capability, nonce, argument schema, and error handling; review the import parsers with hostile inputs (huge files, deeply odd encodings, formula cells, HTML, script tags, extremely long cells); verify redaction cannot be bypassed with simple tricks; verify the log and backups cannot be fetched by direct URL; verify the import token cannot be guessed or reused; verify chips, categories, and unanswered text are escaped or rendered as text everywhere; confirm the log is truly off by default; record pass or fixed for each item.
- **Acceptance:** every item in the v1.2 checklist is pass or fixed; PHPCS security sniffs show no errors.
- **Commit:** `security: audit v1.2 features and fix findings`
- **Handoff:** open issues, if any.

> **Claude Prompt (V12-D15)**
>
> Senior WordPress security reviewer. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D15: Security audit of v1.2 changes only. I will paste files one at a time. For each, review for missing sanitization or escaping, missing capability or nonce checks, route argument gaps, path traversal, unsafe file handling, redaction bypasses, token weaknesses, CSV or Markdown injection, XSS in React, information leaks. Output a findings table (severity, location, issue, fix) and corrected snippets only for problems found. Also draft `docs/SECURITY-CHECKLIST-v1.2.md` as a pass or fail checklist covering unanswered log, chips, categories, language, and import.
> Files pasted in this session: [list files].
> Output rules: no rewrites of safe code. End with the commit message.

---

#### V12-D16: Performance (v1.2 Delta)

- **Branch:** `v12-feature/d16-performance-delta`
- **Goal:** Make sure the new features did not slow the plugin down.
- **Files:** create `docs/PERFORMANCE-v1.2.md`; modify code only where a bottleneck is found; extend `tools/generate-sample-knowledge.php` to produce categories, featured entries, and multi-language samples.
- **Tasks:** measure search time on 1,000 entries for English and for each other language; measure the cost of language auto-detection; measure the cost of category-scoped search; measure the unanswered log write time at its maximum size and confirm it stays small; measure import time and peak memory for 1,000 CSV rows and 1,000 Markdown entries; compare widget bundle size against v1.0; confirm assets still load only where intended; record all numbers next to the v1.0 baseline.
- **Acceptance:** search stays under 200 ms; log writes stay well under the search budget; the bundle grows by a small, documented amount; imports of 1,000 rows complete without memory problems on a default host configuration.
- **Commit:** `perf: benchmark v1.2 features and fix regressions`
- **Handoff:** advertised limits for v1.2.

> **Claude Prompt (V12-D16)**
>
> Senior WordPress performance engineer. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D16: Performance delta. Draft `docs/PERFORMANCE-v1.2.md` as a fill-in template comparing v1.0 and v1.2 for: search time per language, auto-detection overhead, category-scoped search, unanswered log write at max size, CSV and Markdown import time and peak memory at 1,000 items, and bundle size. Also give changed parts only for `tools/generate-sample-knowledge.php` so it can generate categories, featured entries, and Spanish, French, and Cebuano samples. Add a measurement procedure and suggest likely hot spots to check.
> Existing context: [Paste the v1.0 baseline numbers from `docs/PERFORMANCE.md` and the generator's current options.]
> Output rules: short comments. End with the commit message and a 2-line handoff for V12-D17.

---

#### V12-D17: QA Matrix (v1.2 Delta and Upgrade Path)

- **Branch:** `v12-feature/d17-qa-delta`
- **Goal:** Verify v1.2 on real environments, especially the upgrade from v1.0.
- **Files:** create `docs/QA-MATRIX-v1.2.md`; modify code only for bugs found.
- **Tasks:** upgrade tests from v1.0.0 with an empty file, a small file, a 1,000-entry file, and a file with unusual characters; upgrade with a read-only folder; upgrade on multisite; fresh install of v1.2; every new feature on PHP 7.4, 8.1, and 8.3; chips and category bar on at least three themes and on mobile browsers; unanswered log with page caching plugins active; imports with files exported from common spreadsheet tools; language auto-detection on real sample questions; accessibility checks for the new components; record pass or fail with notes.
- **Acceptance:** matrix complete; blocking bugs fixed; known limitations written down.
- **Commit:** `test: complete v1.2 QA matrix and upgrade-path verification`
- **Handoff:** known limitations list.

> **Claude Prompt (V12-D17)**
>
> Senior QA lead for WordPress plugins. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D17: Produce the full content of `docs/QA-MATRIX-v1.2.md` as a ready-to-fill checklist table. Cover: upgrade path from v1.0.0 (empty, small, large, unusual characters, read-only folder, multisite), fresh install, PHP 7.4, 8.1, and 8.3, themes, mobile browsers, caching plugins, spreadsheet-exported CSVs, Markdown files from common editors, language detection samples (English, Spanish, French, Cebuano, Filipino), accessibility of chips and category bar, privacy checks for the unanswered log, and security smoke tests. Each row needs steps and expected result. I will report failures and you propose fixes one at a time.
> Existing context: [Paste the v1.2 feature list from the documentation.]
> Output rules: Markdown only, no code. End with the commit message.

---

#### V12-D18: Release Assets and Version Bump

- **Branch:** `v12-feature/d18-release-assets`
- **Goal:** Everything needed to publish v1.2.0.
- **Files:** modify `readme.txt`, `CHANGELOG.md`, `bryvium-wp-file-chat.php`, `package.json`, `scripts/build-zip.mjs`; add new screenshots to `.wordpress-org/`.
- **Tasks:** bump the version from `1.2.0-dev` to `1.2.0` everywhere (plugin header, constant, readme stable tag, package file) and verify they match; update the readme description, FAQ, screenshots list, upgrade notice (mention the automatic backup and that downgrading is unsupported), and the privacy section covering the opt-in unanswered log; write the changelog entry grouped by feature; make sure the zip script excludes new dev-only files and docs but includes the new data files; build and install the zip on a clean site and on a site upgraded from v1.0.0.
- **Acceptance:** versions match everywhere; the zip contains only runtime files; both clean install and upgrade work from the zip.
- **Commit:** `build: prepare release assets for 1.2.0`
- **Handoff:** the zip name and contents list.

> **Claude Prompt (V12-D18)**
>
> Senior WordPress plugin release engineer. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D18: Release assets. Write the updated `readme.txt` sections (description, FAQ additions, screenshots list, changelog, upgrade notice stating an automatic backup is created and downgrading is unsupported, privacy section covering the opt-in unanswered log), a `CHANGELOG.md` entry for 1.2.0 grouped by feature (unanswered log, suggestion chips, bulk import, category filters, multilingual search, upgrader), and the changes to `scripts/build-zip.mjs` so it excludes dev-only files and docs but includes `includes/data/*`. Give a version-consistency checklist.
> Existing context: [Paste the current readme headings, the current changelog top section, and the zip script exclusion list.]
> Output rules: changed parts only, short comments. End with the commit message and a 2-line handoff for V12-D19.

---

#### V12-D19: Release Candidate, Tag, and Deploy

- **Branch:** `v12-release/1.2.0`
- **Goal:** Ship v1.2.0 safely.
- **Files:** create `docs/RELEASE-CHECKLIST-v1.2.md`.
- **Tasks:** create `v12-release/1.2.0` from `v12-develop`; tag `v1.2.0-rc.1`, test the packaged zip on clean and upgraded sites; fix only blocking bugs on the release branch and tag further release candidates if needed; merge the release branch into `main` and tag `v1.2.0`; merge `main` back into `v12-develop` and `develop`; publish the release zip; if listed on WordPress.org, submit the update; monitor support feedback for the first two weeks; keep a list of requests for the next release; keep the rollback plan ready (the previous release zip and the restore-from-backup steps).
- **Acceptance:** `v1.2.0` tag exists on `main`; the zip installs and upgrades cleanly; all branches contain the release; the checklist is signed off.
- **Commit:** `chore(release): prepare 1.2.0`
- **Handoff:** feedback log and next backlog.

> **Claude Prompt (V12-D19)**
>
> Senior WordPress plugin release manager. Project: Bryvium WP File Chat v1.2.0.
> Task V12-D19: Draft `docs/RELEASE-CHECKLIST-v1.2.md` with pre-release checks, the exact branch flow (`v12-develop` to `v12-release/1.2.0` to `main`, release candidate tags `v1.2.0-rc.N`, final tag `v1.2.0`, merge `main` back into `v12-develop` and `develop`), packaging and install verification on clean and upgraded sites, publishing steps (GitHub release and optional WordPress.org update), a two-week monitoring plan, and a rollback plan using the previous zip and the automatic backups. Markdown only, no code.
> Existing context: the v1.2 branching model from the documentation.
> Output rules: concise checklist. End with the final commit message.

---

## 12. How to Use the Daily Claude Prompts

1. Open this document and find today's `V12-D` day.
2. Create the listed `v12-feature/...` branch from `v12-develop`.
3. Copy that day's **Claude Prompt** into a new chat.
4. Replace every bracketed placeholder with the real context. Paste only signatures and short notes, never whole files.
5. Review the generated code and place it in the exact files listed.
6. Run the acceptance checks and the quality gates in Section 10, including loading a real v1.0 `knowledge.json`.
7. Commit with the day's commit message and merge into `v12-develop`.
8. Save the handoff note Claude gives you. It becomes the next day's "Existing context".

**Token-saving habits**
- Paste function signatures and result shapes, not files.
- Ask for changed methods only when editing existing files.
- Split heavy days (for example V12-D13 into preview first, commit second).
- Keep a running handoff log file for yourself.

**Order matters:** the days are ordered by dependency. Chips (D06 to D07) need the schema from D01. The unanswered screen (D10) needs the knowledge form from v1.0 and the routes from D09. Import preview (D13) needs the parsers from D11 and D12 and the backup helper from D01.

---

## 13. Release Checklist for v1.2.0 (Summary)

- All 19 `V12-D` days merged into `v12-develop` and passing the quality gates
- Upgrade from v1.0.0 verified with real data, backup confirmed
- v1.0 files work with no manual steps
- Security checklist for v1.2 fully passed
- Performance numbers documented against the v1.0 baseline
- QA matrix complete
- Version numbers identical in the plugin header, constant, `readme.txt`, and `package.json`
- Zip contains runtime files only
- `v12-release/1.2.0` merged into `main` and tagged `v1.2.0`
- `main` merged back into `v12-develop` and `develop`
- Rollback plan and support routine ready

---

## 14. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Migration corrupts an owner's data | Automatic timestamped backup, idempotent migration, lock protection, original file untouched on failure |
| Unanswered log raises privacy concerns | Off by default, clear explanation, redaction, retention limits, no IP or user data, removable on uninstall |
| Log grows without bound | Maximum entries, retention days, scheduled cleanup |
| Hostile or huge import files | Size and row limits, strict parsing, HTML stripped, preview before commit, single-use expiring token |
| Spreadsheet formula injection | Neutralize formula-starting cells on import display and export |
| Wrong language detected | Owners can set a fixed language; auto mode falls back to English when unsure |
| Weak stopword lists hide real answers | Lists contain function words only; owners can review sample results; lists can be edited as data files |
| Category bar clutters small screens | Horizontal scroll, off by default, shown only with two or more categories |
| Chips shown for a deleted or disabled entry | `entry_id` is re-validated on every request and falls back safely |
| v1.0 hotfix lost during v1.2 development | Merge `main` into `v12-develop` the same day any hotfix lands |
| Free-tier token limits | Daily split, signature-only context, changed-methods-only output |

---

## 15. After v1.2.0 (Next Roadmap)

| Version | Focus |
|---|---|
| v1.3 | Sharded knowledge files for very large sites and a prebuilt search index file |
| v1.4 | Gutenberg block, per-page visibility rules, local analytics dashboard (including trends from the unanswered log) |
| v2.0 | Optional, opt-in provider interface that is off by default and never required |

---

*End of document.*
