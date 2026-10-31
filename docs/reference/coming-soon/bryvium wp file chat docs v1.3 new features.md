# Bryvium WP File Chat: v1.3.0 New Features Documentation

**Document type:** Feature plan and daily execution guide, no code
**Target plugin version:** 1.3.0
**Builds on:** v1.2.0 (stable release, tagged `v1.2.0`)
**Theme of this release:** Scale. Make the plugin fast and safe for very large knowledge bases while keeping small sites exactly as simple as before.
**Companion documents:** `BRYVIUM-WP-FILE-CHAT-DOCS.md` (v1.0) and `bryvium wp file chat docs v1.2 new features.md` (v1.2). Both stay untouched.

---

## Table of Contents

1. What v1.3.0 Adds
2. Scope Decision and Assumptions
3. Rules That Still Apply
4. Backward Compatibility Contract
5. Architecture Changes
6. Data Specifications (Layout v3, Manifest, Index, Catalog, Settings, Routes)
7. Security and Safety Additions
8. Separate Branching Model for v1.3
9. Commit Guidelines for v1.3
10. Definition of Done for v1.3
11. Daily Development Schedule (V13-D01 to V13-D22)
12. How to Use the Daily Claude Prompts
13. Release Checklist for v1.3.0
14. Risks and Mitigations
15. After v1.3.0 (Next Roadmap)

---

## 1. What v1.3.0 Adds

| # | Feature | Owner benefit | Visitor benefit |
|---|---|---|---|
| 1 | **Sharded knowledge storage** ("large-site mode") | Knowledge bases of many thousands of entries stay fast and safe to edit | Answers stay quick no matter how big the site grows |
| 2 | **Prebuilt search index** (bucketed inverted index) | No more reading the whole knowledge base for each question | Faster replies |
| 3 | **Catalog** (lightweight listing file) | Admin list, search, categories, and chips load instantly even with huge data | Category bar and chips load fast |
| 4 | **Maintenance screen** (health, rebuild, verify, re-shard, consolidate) | One place to see storage health and fix problems without technical knowledge | Fewer broken searches |
| 5 | **Chunked large import** (up to 20,000 rows) with progress and resume | Load a whole help center in one go, even on slow hosts | Better coverage at launch |
| 6 | **Backup retention and restore** | Backups never fill the disk, and restoring takes one click | Fewer outages after mistakes |

Everything remains local-first: no external API calls, no database tables, no new runtime dependencies. Small sites do not need to do anything and notice no difference.

---

## 2. Scope Decision and Assumptions

- This release implements the planned "sharded JSON files" and "search index file" items from the roadmap and adds the supporting tools (catalog, maintenance, chunked import, backup retention) that make them safe for non-technical owners.
- The Gutenberg block, per-page visibility rules, and the analytics dashboard stay planned for v1.4.
- v1.2.0 must be released and tagged before V13-D01 starts.
- **Two storage modes exist after this release:**
  - **Simple mode** (default): the single `knowledge.json` file exactly as in v1.0 and v1.2. Best for most sites.
  - **Large-site mode**: sharded files plus index and catalog. Used when the site is big or the owner turns it on.
- Switching is automatic when the knowledge base crosses the thresholds below (setting `large_site_mode` set to `auto`), or manual from the Maintenance screen.

| Automatic switch trigger | Value |
|---|---|
| Number of entries | 1,000 or more |
| Size of `knowledge.json` | 1.5 MB or more |
| Either condition | Triggers an offer, or an automatic switch if the owner chose automatic |

- Tested and advertised upper limit for v1.3.0: **20,000 entries and about 20 MB** of knowledge text.
- Why two modes instead of always sharding: small sites keep the simplest possible data layout, and the risky migration only touches sites that actually need it.
- Language handling from v1.2 is unchanged. The index is built so changing the language setting never forces a rebuild.

---

## 3. Rules That Still Apply

1. Zero runtime dependencies. No new libraries, no `ZipArchive` requirement, no extension that is not part of a default PHP build.
2. No database tables. No large data in options or transients. Options and transients only hold small status values.
3. Same prefixes: `bryvium_`, `BRYVIUM_WFC_`, `bwfc-`, Tailwind `bwfc:`, REST namespace `bryvium/v1`.
4. Sanitize in, escape out, capability and nonce on every state change.
5. One small daily task at a time. Split any day that grows too large.
6. Simplicity for non-technical owners. Every new tool has a safe default, a plain-language label, and a way back.
7. **Never break search.** If the index, catalog, or a shard is missing or damaged, the plugin falls back to a slower but correct path and tells the admin, instead of failing.

---

## 4. Backward Compatibility Contract

These promises must hold. A day that breaks one is not done.

- Data written by v1.0.x or v1.2.x loads in v1.3.0 with no manual steps, including a direct upgrade from v1.0 to v1.3.
- Below the thresholds nothing is migrated. Simple mode behaves exactly like v1.2.
- Entry fields and meanings are unchanged. Chat, chips, categories, language search, unanswered log, and import keep their existing behavior and response shapes.
- All existing REST routes keep working. New routes are additive.
- Search results in large-site mode match simple-mode results on the same data (same top answer for the test question set; tiny ranking differences between lower results must be documented).
- Migration to large-site mode always creates a full backup first, verifies the result (counts and checksums), and only then switches. If anything fails, simple mode stays active and untouched.
- Returning from large-site mode to simple mode (consolidate) is available as long as the data fits the simple-mode limits.
- Downgrading the plugin to an older version after enabling large-site mode is not supported. The backup allows manual recovery. This is stated in the readme and on the Maintenance screen.

---

## 5. Architecture Changes

### 5.1 New and Changed Components

| Component | Change |
|---|---|
| Storage facade | The existing storage class becomes a thin front that selects a driver (simple or sharded). All other classes keep calling the same public methods |
| Storage driver interface (new) | One contract that both drivers implement |
| Simple driver (new, extracted) | The v1.2 single-file logic moved behind the interface with no behavior change |
| Sharded driver (new) | Reads and writes entries across shard files using a manifest |
| Filesystem helper (new) | Safe path building, protected folder creation, locking, atomic writes, checksums. Shared by every driver and by backups |
| Manifest (new) | Small file that describes the layout, shards, revision counter, and index state |
| Migrator (new) | Converts simple mode to large-site mode and back, with backup and verification |
| Catalog (new) | Compact listing of entries (id, title, category, flags, timestamps, shard) split into fixed-size chunks |
| Index store and builder (new) | Bucketed inverted index with full build and incremental updates |
| Search | Uses the index when it is fresh. Otherwise falls back to scanning shards. Scoring rules stay the same as v1.2 |
| Job runner (new) | Runs long tasks (rebuild, verify, re-shard, import) in small time-limited steps that resume safely |
| Maintenance REST and screen (new) | Status, rebuild, verify, re-shard, enable, consolidate, backups |
| Backup manager (new) | Create, list, prune by retention, and restore backups |
| Importer | Gains a chunked pipeline with a staging area and batch commits |
| Upgrader | Adds a migration step that records the new version and offers large-site mode when thresholds are met |

### 5.2 Data Flow Changes

**Chat request in large-site mode**
1. Validation, nonce, and rate limiting are unchanged.
2. The question is normalized and tokenized exactly as in v1.2.
3. Only the index buckets for the query tokens are read. Fuzzy matching consults the compact vocabulary buckets.
4. Candidate entry ids are scored using the stored postings and field weights, then ranked.
5. Only the shards that hold the top candidates are opened to fetch the answer text.
6. The response shape is the same as v1.2.
7. If the index is stale, missing, or damaged, the same question is answered by scanning shards, and the admin sees a notice suggesting a rebuild.

**Write (add, edit, delete) in large-site mode**
1. One global write lock is taken (readers are never blocked).
2. The shard that owns the entry is rewritten atomically.
3. The affected index buckets and catalog chunk are updated incrementally.
4. The manifest revision is increased last. Index and catalog record which revision they match.
5. If any step fails, the manifest still points to a consistent state and the index is marked stale for later repair.

**Large import**
1. File is stored in a protected staging area.
2. Parsing runs in small batches across several requests, with progress shown to the owner.
3. Entries are committed in batches. Index and catalog are updated once per batch group, not once per row.
4. The owner can stop and resume. A backup is taken first.

### 5.3 File Layout (inside the existing protected data folder)

| Path | Purpose | Mode |
|---|---|---|
| `knowledge.json` | Single knowledge file | Simple mode only |
| `unanswered.json` | Unanswered log from v1.2 | Both modes, unchanged |
| `manifest.json` | Layout, shard list, revision, index state | Large-site mode |
| `shards/shard-0000.json` and so on | Entries split by a fixed hash of the entry id | Large-site mode |
| `index/meta.json` | Index version, built-for revision, statistics | Large-site mode |
| `index/postings-00.json` to `postings-63.json` | Token to entry postings, bucketed by a hash of the token | Large-site mode |
| `index/vocab-00.json` to `vocab-63.json` | Compact token lists for fuzzy lookup | Large-site mode |
| `index/catalog-0000.json` and so on | Listing chunks of 500 entries | Large-site mode |
| `backups/` | Timestamped backup folders | Both modes |
| `staging/` | Temporary uploaded and parsed import data | Both modes |
| `jobs/` | Job state and lock files | Both modes |

Every subfolder gets the same deny rule and blank index file as the main folder. File names are always generated by the plugin from strict patterns. User input never becomes part of a path.

---

## 6. Data Specifications

### 6.1 Manifest

| Field | Type | Rules |
|---|---|---|
| `layout_version` | integer | 3 for large-site mode |
| `bucket_count` | integer | Number of shards, a power of two between 2 and 256, chosen from the entry count so that an average shard holds roughly 200 to 500 entries |
| `shards` | list | Each item: file name, entry count, byte size, checksum |
| `total_entries` | integer | Kept in sync with the shards |
| `data_revision` | integer | Increases on every successful write |
| `index_state` | string | `fresh`, `stale`, `building`, or `missing` |
| `index_built_revision` | integer | The `data_revision` the index was built for |
| `catalog_revision` | integer | The `data_revision` the catalog matches |
| `created_at`, `updated_at` | integer | Unix timestamps |

**Shard assignment:** an entry belongs to the shard computed from a stable hash of its id and `bucket_count`. No lookup table is needed. Re-sharding (changing `bucket_count`) is a maintenance task that rewrites shards.

### 6.2 Search Index

| Part | Description |
|---|---|
| Tokens | Normalized, accent-folded tokens from title, keywords, and answer. Stopwords are **not** stored. They are removed from the query at search time using the active language list, so the index does not depend on the language setting |
| Stem aliases | English stems are stored as alias keys so English stemming keeps working |
| Posting | For a token: entry id, field weights found (title, keywords, answer), and occurrence count |
| Buckets | A token goes to one of 64 posting buckets by a hash of the token. A query reads only the buckets it needs |
| Vocabulary | Compact token lists per bucket for fuzzy matching |
| Meta | Index version, built-for revision, entry count, average lengths if scoring needs them |
| States | `fresh` (usable), `stale` (do not use, fallback scan), `building` (do not use), `missing` |

**Parity rule:** the index must reproduce the v1.2 scoring. The index only changes how candidates are found, not how they are scored.

### 6.3 Catalog

| Field per row | Description |
|---|---|
| `id`, `title`, `category`, `enabled`, `featured`, `chip_label`, `chip_order`, `updated_at`, `shard` | Everything the admin list, category counts, and chips need, without reading answers |

Chunks hold 500 rows ordered by creation. The admin list pages map to chunks. Category counts and featured chips come from the catalog, not from scanning shards.

### 6.4 New Settings (in the existing single option array)

| Setting | Purpose | Default |
|---|---|---|
| `large_site_mode` | `auto`, `on`, or `off` | `auto` |
| `use_search_index` | Use the index in large-site mode. Turning it off forces scanning (troubleshooting) | On |
| `backup_keep_count` | Number of backups to keep | 5 (range 1 to 30) |
| `backup_before_import` | Create a backup before every import | On and not changeable for replace mode |

Technical constants such as shard target size, postings bucket count, and catalog chunk size are internal and are not exposed as settings.

### 6.5 New Routes (admin only: `manage_options` and a valid REST nonce)

| Route | Purpose |
|---|---|
| GET `/maintenance/status` | Mode, counts, sizes, index state, last backup, warnings |
| POST `/maintenance/index/rebuild` | Start or continue a chunked index and catalog rebuild |
| POST `/maintenance/verify` | Start or continue an integrity check (counts, checksums, orphaned files, index and catalog match) |
| POST `/maintenance/reshard` | Start or continue re-sharding to a recommended bucket count |
| POST `/maintenance/enable-large-site` | Start or continue migration to large-site mode |
| POST `/maintenance/consolidate` | Start or continue return to simple mode (only if limits allow) |
| GET `/maintenance/job` | Current job progress |
| POST `/maintenance/job/cancel` | Cancel a running job safely |
| GET `/backups` | List backups with date, size, entry count, reason |
| POST `/backups` | Create a backup now |
| POST `/backups/{id}/restore` | Restore a backup (confirmation flag required) |
| DELETE `/backups/{id}` | Delete one backup |
| POST `/import/stage` | Upload a large file to staging and return a stage id |
| POST `/import/stage/{id}/step` | Process the next batch of a staged import |
| GET `/import/stage/{id}` | Staged import progress |

The v1.2 import preview and commit routes keep working for small imports (up to the v1.2 limits). Larger files use the staged routes automatically.

---

## 7. Security and Safety Additions

| Concern | Rule |
|---|---|
| Path traversal | Paths are built only from strict generated patterns (for example `shard-` plus four digits). No user-supplied path or file name is ever used |
| Folder exposure | Every new subfolder gets a deny rule and a blank index file. Direct URL access to shards, index, backups, staging, and jobs must be refused |
| Data integrity | Each shard has a checksum in the manifest. The Verify tool detects missing, extra, or altered files |
| Crash safety | Atomic rename for every file, manifest updated last, index marked stale on any partial failure |
| Concurrency | One global write lock for writers, lock-free readers, job runner uses its own lock and a heartbeat so a dead job can be taken over safely |
| Long tasks | Each request has a time budget and a memory budget. State is saved after every step so a timeout never loses work |
| Staging data | Staged uploads are protected, size-limited (10 MB), expire automatically, and are deleted after commit or cancel |
| Restore | Admin only, confirmation required, automatic safety backup of the current data before restoring |
| Backup storage | Count-based retention so backups cannot fill the disk. Free-space check before large backups |
| Information leaks | Admin error messages are helpful but never show full server paths to non-admin contexts. Visitors get generic messages |
| Privacy | No new data about visitors is collected. Everything stays on the server |
| Uninstall | Removes the manifest, shards, index, catalog, staging, jobs, and backups together with the data folder only when "remove data on uninstall" is on |

---

## 8. Separate Branching Model for v1.3

v1.3 uses its own branch family. It never mixes with the v1.0 branches (`develop`, `feature/dNN-...`) or the v1.2 branches (`v12-...`).

| Branch | Role |
|---|---|
| `main` | Stable. Receives merges only at release time |
| `develop` | v1.0 maintenance line (frozen for features) |
| `v12-develop` | v1.2 maintenance line (frozen for features) |
| `v13-develop` | **Integration branch for all v1.3 work.** Created from `main` at tag `v1.2.0` (or the latest v1.2.x) |
| `v13-feature/dNN-topic` | One branch per daily task, for example `v13-feature/d06-catalog` |
| `v13-fix/issue-name` | Bug fixes found during v1.3 work |
| `v13-release/1.3.0` | Release preparation, created from `v13-develop` |
| `hotfix/issue-name` | Urgent fixes from `main` (unchanged) |

**Tags**
- Release candidates: `v1.3.0-rc.1`, `v1.3.0-rc.2`
- Final: `v1.3.0`

**Daily Git routine**
1. Make sure `v13-develop` is up to date.
2. Create today's `v13-feature/dNN-topic` branch from it.
3. Do the work and run the quality gates.
4. Commit with the day's commit message.
5. Merge into `v13-develop` with a merge commit, then delete the feature branch.

**Keeping fixes in sync**
- After any `hotfix/...` lands on `main`, merge `main` into `v13-develop` the same day.
- Never merge `v13-develop` into `develop` or `v12-develop` before release.
- Never branch v1.3 work from `v12-develop`.

**Release routine**
1. Create `v13-release/1.3.0` from `v13-develop`.
2. Only bug fixes, version bumps, and release assets go on this branch.
3. Tag release candidates from it for testing.
4. Merge `v13-release/1.3.0` into `main` and tag `v1.3.0`.
5. Merge `main` back into `v13-develop`, `v12-develop`, and `develop` so every line contains the release.

---

## 9. Commit Guidelines for v1.3

Same Conventional Commits format as before. Additional scopes for v1.3:

| Scope | Use |
|---|---|
| `shard` | Sharded storage and drivers |
| `index` | Search index |
| `catalog` | Catalog |
| `maint` | Maintenance tools and screen |
| `bulk` | Chunked large import |
| `backup` | Backup retention and restore |
| `fs` | Filesystem helper |

Existing scopes still apply. Each daily commit body includes the line `v1.3 scale release` so history is easy to filter.

---

## 10. Definition of Done for v1.3

A v1.3 day is done only when:

1. The day's acceptance checks pass.
2. PHPCS reports zero errors.
3. Debug mode and debug log show nothing new.
4. No new outbound network requests.
5. The Backward Compatibility Contract (Section 4) still holds. Quick check: load a real v1.0 file and a real v1.2 file, run a chat in each.
6. For storage and index days: run the sample data set at small and large size and compare answers to simple mode.
7. The commit is merged into `v13-develop`.
8. The handoff note is saved.

---

## 11. Daily Development Schedule

Day ids use the `V13-` prefix so they are never confused with v1.0 or v1.2 days.

---

### PHASE A: STORAGE FOUNDATION

#### V13-D01: Storage Driver Interface and Facade

- **Branch:** `v13-feature/d01-storage-driver`
- **Goal:** Make storage swappable without changing any caller.
- **Files:** create `includes/storage/interface-bryvium-storage-driver.php`, `includes/storage/class-bryvium-storage-single.php`; modify `includes/class-bryvium-storage.php`, `includes/class-bryvium-loader.php`, `bryvium-wp-file-chat.php` (version constants to `1.3.0-dev`), `CHANGELOG.md` (unreleased section).
- **Tasks:**
  1. Create `v13-develop` from the v1.2.0 tag.
  2. Define the driver contract covering everything the current storage class offers (get all, get one, add, update, delete, count, categories, featured chips, validation hook).
  3. Move the existing single-file logic into the simple driver with **no behavior change**.
  4. Turn `Bryvium_Storage` into a facade that picks the simple driver for now and keeps every public method name and return shape.
  5. Keep the modification-time cache working through the simple driver.
- **Acceptance:** every v1.2 feature works exactly as before (chat, admin CRUD, import, chips, categories, unanswered log); PHPCS passes; no performance regression on a 1,000-entry file.
- **Commit:** `refactor(shard): extract storage driver interface and simple driver` with body line `v1.3 scale release`.
- **Handoff:** the interface method list and return shapes.

> **Claude Prompt (V13-D01)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat, building v1.3.0 on top of v1.2.0. Rules: zero runtime dependencies, pure PHP 7.4+ (syntax parseable by 7.0 for the requirement notice), JSON flat-file, WPCS, prefix `bryvium_`, text domain `bryvium-wp-file-chat`.
> Task V13-D01: Storage driver interface and facade. Create `includes/storage/interface-bryvium-storage-driver.php` and `includes/storage/class-bryvium-storage-single.php`; changed parts only for `includes/class-bryvium-storage.php`, `includes/class-bryvium-loader.php`, `bryvium-wp-file-chat.php`.
> Requirements: the interface mirrors every public method of the current storage class (get all, get one, add, update, delete, count, category list with counts, featured chip list, entry validation); the simple driver contains the existing single-file logic with locking, atomic writes, and modification-time cache and must behave identically; the existing `Bryvium_Storage` becomes a facade that selects a driver (only the simple driver for now) and keeps all public method names and return shapes; no behavior change visible to REST, admin, search, importer, or the unanswered log.
> Existing context: [Paste the current storage class public method signatures with return shapes, the loader registration lines, and the version constants.]
> Output rules: code only for listed files, changed parts only for existing files, short comments. End with 4 manual tests (chat, admin CRUD, import, chips and categories), the Conventional Commit message, and a 2-line handoff for V13-D02.

---

#### V13-D02: Filesystem Helper

- **Branch:** `v13-feature/d02-fs-helper`
- **Goal:** One safe place for paths, locks, atomic writes, and checksums.
- **Files:** create `includes/class-bryvium-fs.php`; modify `includes/storage/class-bryvium-storage-single.php`, `includes/class-bryvium-unanswered.php`, `includes/class-bryvium-upgrader.php` (use the helper for backups).
- **Tasks:** strict path builder that only accepts known generated patterns (shard names, index names, catalog names, backup ids, stage ids); protected-folder creation for any subfolder (deny rule plus blank index file); global write lock with timeout and stale-lock takeover; atomic write (temp file plus rename) with fsync-like care; read helper with size sanity checks; checksum helper; free disk space check; safe delete and safe recursive delete restricted to the data folder; consistent error objects; refactor the simple driver, unanswered log, and upgrader backup to use the helper.
- **Acceptance:** all v1.2 features still pass; a path with traversal characters is rejected; a stale lock is recovered; protected files exist in every subfolder.
- **Commit:** `feat(fs): add filesystem helper for safe paths, locks and atomic writes`
- **Handoff:** helper method names, error object shape, lock behavior.

> **Claude Prompt (V13-D02)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0 (zero dependencies, WPCS).
> Task V13-D02: Filesystem helper. Create `includes/class-bryvium-fs.php`; changed parts only for `includes/storage/class-bryvium-storage-single.php`, `includes/class-bryvium-unanswered.php`, `includes/class-bryvium-upgrader.php`.
> Requirements: path builder accepting only generated patterns (`shard-` + 4 digits `.json`, `postings-`/`vocab-` + 2 digits, `catalog-` + 4 digits, backup ids as timestamp plus short random suffix, stage ids as random hex) and rejecting anything else; create protected subfolder (deny rule plus blank index file); global write lock with timeout and safe takeover of stale locks; atomic write helper (temp file in same folder then rename); safe read helper with size cap; checksum helper (built-in hash functions only); free-space check; recursive delete restricted to inside the data folder; WP_Error returns; refactor the listed files to use it with no behavior change.
> Existing context: [Paste the current locking and atomic write code summary, the data directory helper name, and the backup creation method signature.]
> Output rules: code only for listed files, changed parts only for existing files. End with 4 manual tests (traversal rejected, stale lock recovered, protection files present, v1.2 features still work), the commit message, and a 2-line handoff for V13-D03.

---

#### V13-D03: Sharded Driver, Part 1 (Manifest and Reads)

- **Branch:** `v13-feature/d03-sharded-read`
- **Goal:** Read entries from a sharded layout.
- **Files:** create `includes/storage/class-bryvium-manifest.php`, `includes/storage/class-bryvium-storage-sharded.php` (read methods only).
- **Tasks:** manifest read, validate, and write with checksum fields (write path used by later days); stable hash-to-shard function; get one entry by id by opening only its shard; iterate all entries shard by shard without loading everything at once (a generator-style approach that works on PHP 7.4); count from the manifest; per-shard cache keyed by file modification time with a small in-request cache limit; tolerate a missing or damaged shard by returning a clear error object and flagging the manifest state; keep the same entry shape as simple mode.
- **Acceptance:** with a hand-made sample layout, get one returns the right entry from the right shard; iterating all returns every entry exactly once; a deleted shard file produces a clear error and no fatal error; memory stays flat while iterating.
- **Commit:** `feat(shard): add manifest and sharded read paths`
- **Handoff:** manifest accessors, the hash rule, iteration method.

> **Claude Prompt (V13-D03)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D03: Sharded driver read side. Create `includes/storage/class-bryvium-manifest.php` and `includes/storage/class-bryvium-storage-sharded.php` (read methods only; leave marked space for writes in V13-D04). The sharded driver implements the driver interface from V13-D01 and uses the filesystem helper from V13-D02.
> Requirements: manifest fields `layout_version`, `bucket_count` (power of two 2 to 256), `shards` (file, entry_count, bytes, checksum), `total_entries`, `data_revision`, `index_state`, `index_built_revision`, `catalog_revision`, `created_at`, `updated_at`; stable hash of entry id to shard index using only built-in functions; get one opens only the owning shard; iterate all entries shard by shard with low memory (generator or callback style); count from the manifest; per-shard cache by filemtime with a small in-request limit; a missing or damaged shard returns WP_Error and flags the manifest state without fatal errors; entry shape identical to simple mode.
> Existing context: [Paste the driver interface method list from V13-D01 and the filesystem helper method names from V13-D02.]
> Output rules: code only for the two files. End with 4 manual tests using a hand-made layout, the commit message, and a 2-line handoff for V13-D04.

---

#### V13-D04: Sharded Driver, Part 2 (Writes)

- **Branch:** `v13-feature/d04-sharded-write`
- **Goal:** Add, update, and delete entries safely across shards.
- **Files:** modify `includes/storage/class-bryvium-storage-sharded.php`, `includes/storage/class-bryvium-manifest.php`.
- **Tasks:** write methods take the global write lock; add places the entry in its hashed shard and rewrites only that shard atomically; update and delete rewrite only the owning shard; manifest is updated last with the new shard checksum, entry count, total, and an increased `data_revision`; any index and catalog follow-up is signaled through hook points (actions) that later days use, and the manifest index state is set to `stale` until those hooks confirm; bulk write method for batches (one lock, many entries, one manifest update) for imports; enforce the same entry validation as simple mode; roll back temp files on failure; detect an oversized shard and recommend re-sharding without blocking the write.
- **Acceptance:** CRUD round-trips; two simultaneous writes do not corrupt; killing a write between steps leaves a readable, consistent previous state; revision increases only on success; a batch write of 500 entries is one manifest update.
- **Commit:** `feat(shard): add atomic sharded writes with manifest revisions`
- **Handoff:** write method list, hook names fired, revision rules.

> **Claude Prompt (V13-D04)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D04: Sharded driver write side. Changed parts only for `includes/storage/class-bryvium-storage-sharded.php` and `includes/storage/class-bryvium-manifest.php`.
> Requirements: all writes take the global write lock; add, update, delete rewrite only the owning shard atomically; the manifest is written last with new shard checksum, entry count, total, and `data_revision` plus one; fire actions after a successful write (`bryvium_wfc_entries_written` with changed ids and the new revision, `bryvium_wfc_entry_deleted`) and set `index_state` to `stale` until a listener confirms; a batch write method (one lock, many entries, one manifest update); same validation as simple mode; clean up temp files on failure; if a shard exceeds the recommended size, add a manifest warning but still succeed; no partial visible state if a step fails.
> Existing context: [Paste manifest accessor names and the read-side method list from V13-D03, plus the lock and atomic write helper signatures.]
> Output rules: changed parts only. End with 5 manual tests (CRUD, concurrent writes, interrupted write, revision rule, batch), the commit message, and a 2-line handoff for V13-D05.

---

#### V13-D05: Migration to Large-Site Mode and Back

- **Branch:** `v13-feature/d05-migrator`
- **Goal:** Safely move between simple and large-site mode.
- **Files:** create `includes/storage/class-bryvium-storage-migrator.php`; modify `includes/class-bryvium-upgrader.php`, `includes/class-bryvium-storage.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`.
- **Tasks:**
  1. Add the `large_site_mode` setting (`auto`, `on`, `off`) with a plain-language description.
  2. Migrator steps for simple to sharded: lock, full backup, compute bucket count from the entry count, write all shards, write the manifest, verify (entry count equals, every id present, checksums match), then atomically switch the active mode and move the old file into the backup folder.
  3. Consolidate (sharded to simple) with the same safety steps and a limit check (entries and size must fit simple-mode limits).
  4. The facade selects the driver based on the presence and validity of the manifest.
  5. Threshold detection (1,000 entries or 1.5 MB). In `auto` mode the upgrader and the first admin load after crossing the threshold queue the migration to run as a resumable job (job runner arrives on V13-D11, so until then expose a synchronous, time-limited method used for tests and small sets); in other modes show a notice offering the switch.
  6. On any failure the active mode stays unchanged and a clear admin notice explains what happened.
  7. Add the upgrader step that records the new version and runs the threshold check without migrating anything automatically on the upgrade request itself.
- **Acceptance:** a 1,500-entry v1.2 file migrates, answers match before and after, and the old file sits in the backup; consolidate returns to a single file with identical data; a simulated failure at each step leaves the original mode working.
- **Commit:** `feat(shard): add verified migration between simple and large-site modes`
- **Handoff:** migrator method names, threshold rules, mode detection rule.

> **Claude Prompt (V13-D05)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D05: Migration between simple and large-site mode. Create `includes/storage/class-bryvium-storage-migrator.php`; changed parts only for `includes/class-bryvium-upgrader.php`, `includes/class-bryvium-storage.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`.
> Requirements: setting `large_site_mode` (`auto`, `on`, `off`, default `auto`); simple to sharded: lock, full backup, choose `bucket_count` so shards average roughly 200 to 500 entries, write shards and manifest, verify counts, ids, and checksums, then switch and move the old `knowledge.json` into the backup folder; consolidate back with the same safety and a check that data fits simple-mode limits; facade picks the driver from a valid manifest; thresholds 1,000 entries or 1.5 MB; `auto` queues a resumable job (job runner comes in V13-D11, so provide a synchronous time-limited method for now), other modes show an admin notice offering the switch; any failure leaves the active mode untouched with a clear notice; the upgrader step records the new version and only checks thresholds, it never migrates inside the upgrade request.
> Existing context: [Paste the facade driver-selection code summary, the sharded driver write method names, the backup helper signature, and the settings class method list.]
> Output rules: code only for the new file, changed parts only for existing files. End with 5 manual tests (migrate, answers match, consolidate, failure injection, threshold notice), the commit message, and a 2-line handoff for V13-D06.

---

### PHASE B: CATALOG AND SEARCH INDEX

#### V13-D06: Catalog

- **Branch:** `v13-feature/d06-catalog`
- **Goal:** A lightweight listing that makes admin screens and categories instant.
- **Files:** create `includes/index/class-bryvium-catalog.php`; modify `includes/storage/class-bryvium-storage-sharded.php`.
- **Tasks:** build catalog chunks (500 rows each) from shards; a method to list a page with search by title or category and filters for enabled and featured; category list with counts computed from the catalog; featured chip list from the catalog; incremental update for add, update, delete using the write hooks from V13-D04 (touch only the affected chunk); record `catalog_revision` in the manifest; if the catalog is missing or behind the data revision, return a clear signal so callers can fall back to scanning shards; no answer text inside the catalog.
- **Acceptance:** the catalog lists exactly the same entries as the shards; edits show up immediately; the category list matches a full scan; a missing catalog triggers fallback instead of an error.
- **Commit:** `feat(catalog): add chunked catalog with incremental updates`
- **Handoff:** catalog methods and the fallback signal.

> **Claude Prompt (V13-D06)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D06: Catalog. Create `includes/index/class-bryvium-catalog.php`; changed parts only for `includes/storage/class-bryvium-storage-sharded.php`.
> Requirements: chunk files `index/catalog-0000.json` and so on, 500 rows each, row fields id, title, category, enabled, featured, chip_label, chip_order, updated_at, shard (no answers); full build from shards in low memory; list page method with search (title and category), enabled and featured filters, pagination; category counts for enabled entries; featured chip list sorted by order then title; incremental update on write hooks touching only the affected chunk; keep `catalog_revision` in the manifest; if missing or behind the data revision return a clear fallback signal; use the filesystem helper for all writes.
> Existing context: [Paste the write hook names and payloads from V13-D04, the manifest accessor names, and the filesystem helper signatures.]
> Output rules: code only for the new file, changed parts only for the existing file. End with 4 manual tests, the commit message, and a 2-line handoff for V13-D07.

---

#### V13-D07: Index Builder, Part 1 (Full Build)

- **Branch:** `v13-feature/d07-index-build`
- **Goal:** Build the bucketed inverted index from all entries.
- **Files:** create `includes/index/class-bryvium-index-store.php`, `includes/index/class-bryvium-index-builder.php`; modify `includes/class-bryvium-search.php` (expose the existing normalization and tokenization for reuse without changing results).
- **Tasks:** reuse the v1.2 normalization and tokenization (no stopword removal at index time) plus English stem aliases; posting structure with field weights and counts; 64 posting buckets chosen by a hash of the token; vocabulary buckets for fuzzy lookup; index meta with version and built-for revision; the builder works on a list or range of shards so it can be driven in steps later; atomic writes for each bucket file; low memory by flushing buckets per shard group; state transitions `building` then `fresh` only after the final step succeeds.
- **Acceptance:** a full build on 1,000 sample entries produces the expected files; the same token always lands in the same bucket; peak memory stays within the documented budget; a build interrupted halfway leaves the state `building` or `stale`, never `fresh`.
- **Commit:** `feat(index): add bucketed inverted index store and full builder`
- **Handoff:** posting format, bucket rule, builder step interface.

> **Claude Prompt (V13-D07)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D07: Search index full build. Create `includes/index/class-bryvium-index-store.php` and `includes/index/class-bryvium-index-builder.php`; changed parts only for `includes/class-bryvium-search.php` (expose normalization and tokenization for reuse; results must not change).
> Requirements: index tokens are normalized and accent-folded, stopwords are NOT removed at index time, English stem aliases are added; posting per token: entry id, per-field presence (title, keywords, answer), and occurrence count; 64 posting buckets by a hash of the token and 64 vocabulary buckets for fuzzy lookup; `index/meta.json` with index version, built-for revision, entry count; builder processes a given list or range of shards so it can be driven in steps; flush buckets per shard group to keep memory low; atomic writes via the filesystem helper; state moves `building` to `fresh` only after the last step succeeds; an interrupted build never leaves `fresh`.
> Existing context: [Paste the search class tokenizer and normalizer signatures, the manifest accessors, the sharded iteration method, and the filesystem helper signatures.]
> Output rules: code only for the two new files, changed parts only for the search class. End with 4 manual tests (build, bucket determinism, memory, interrupted build), the commit message, and a 2-line handoff for V13-D08.

---

#### V13-D08: Index Builder, Part 2 (Incremental Updates and State)

- **Branch:** `v13-feature/d08-index-incremental`
- **Goal:** Keep the index fresh after every edit without rebuilding.
- **Files:** modify `includes/index/class-bryvium-index-builder.php`, `includes/index/class-bryvium-index-store.php`, `includes/class-bryvium-loader.php`.
- **Tasks:** on the write hooks, remove the entry's old postings and add the new ones, touching only the affected buckets; batch updates for the bulk write hook (collect touched buckets, write each once); update `index_built_revision` and set the state back to `fresh` only when the whole update finishes; if an incremental update fails, mark the index `stale` and log to the debug log, never failing the user's save; add a cheap freshness check (`index_built_revision` equals `data_revision` and the state is `fresh`) for the search layer; changing the language setting must not mark the index stale; provide a method to drop postings of deleted entries safely.
- **Acceptance:** add, edit, and delete are reflected in search immediately; a batch of 500 updates writes each touched bucket once; a forced failure marks `stale` and the save still succeeds; the freshness check is correct in all four states.
- **Commit:** `feat(index): add incremental index updates and freshness tracking`
- **Handoff:** the freshness method and the stale conditions.

> **Claude Prompt (V13-D08)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D08: Incremental index updates. Changed parts only for `includes/index/class-bryvium-index-builder.php`, `includes/index/class-bryvium-index-store.php`, `includes/class-bryvium-loader.php`.
> Requirements: listen to `bryvium_wfc_entries_written` and `bryvium_wfc_entry_deleted`; for each changed id remove old postings and add new ones, touching only affected buckets; batch updates write each touched bucket once; set `index_built_revision` and state `fresh` only after full success; on failure mark `stale`, write to the debug log only, never fail the user's save; provide a cheap `is_fresh()` check (state `fresh` and built revision equals data revision); language setting changes must not mark the index stale; safe removal of postings for deleted entries.
> Existing context: [Paste the hook payload shapes from V13-D04, builder and store method names from V13-D07, and the manifest accessors.]
> Output rules: changed parts only. End with 5 manual tests (add, edit, delete, batch, forced failure), the commit message, and a 2-line handoff for V13-D09.

---

#### V13-D09: Indexed Search with Safe Fallback

- **Branch:** `v13-feature/d09-indexed-search`
- **Goal:** Answer questions using the index, with identical results to a full scan.
- **Files:** modify `includes/class-bryvium-search.php`, `includes/index/class-bryvium-index-store.php`.
- **Tasks:** when the driver is sharded, `use_search_index` is on, and the index is fresh, find candidates by reading only the postings buckets of the query tokens (after stopword removal for the active language); fuzzy fallback uses vocabulary buckets and the same edit-distance rules as v1.2; apply the category scope using the catalog; score candidates with the exact v1.2 scoring (title, keywords, answer weights, phrase bonus, coverage bonus); fetch answer text only for the top results through their shards; related suggestions use the same path; whenever the index is not fresh, missing, or damaged, use the v1.2 full scan over shards and flag a one-time admin notice; add a parity test routine (developer tool, not shipped to customers) that runs a question set both ways and reports differences.
- **Acceptance:** the top answer matches the full scan for the entire sample question set; fuzzy and category queries work; with the index deleted, chat still answers using the fallback and a notice appears; the language setting changes results correctly without a rebuild.
- **Commit:** `feat(index): add indexed search with parity-checked fallback`
- **Handoff:** the search entry method behavior per state and the parity tool usage.

> **Claude Prompt (V13-D09)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D09: Indexed search. Changed parts only for `includes/class-bryvium-search.php` and `includes/index/class-bryvium-index-store.php`.
> Requirements: if the driver is sharded, `use_search_index` is on, and `is_fresh()` is true, retrieve candidates by reading only the postings buckets for query tokens (stopwords removed using the active language list at query time); fuzzy matching via vocabulary buckets with the v1.2 edit-distance rules; category scope via the catalog; scoring must be the exact v1.2 scoring so results match a full scan; load answer text only for top results through their shards; otherwise run the v1.2 full scan over shards and raise a one-time admin notice; response shape unchanged; provide a dev-only parity routine (excluded from the release zip) that runs a question list both ways and reports differences.
> Existing context: [Paste the current search scoring method signatures, the index store read methods, the catalog category scoping method, and the language resolution method from v1.2.]
> Output rules: changed parts only, short comments. End with 5 manual tests (exact, typo, category, index deleted fallback, language change), the commit message, and a 2-line handoff for V13-D10.

---

#### V13-D10: Wire Catalog Into Admin, REST, and Widget Config

- **Branch:** `v13-feature/d10-catalog-wiring`
- **Goal:** Admin lists, categories, and chips use the catalog in large-site mode.
- **Files:** modify `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-rest.php`, `includes/class-bryvium-widget.php`, `includes/storage/class-bryvium-storage-sharded.php`.
- **Tasks:** the admin entries list route reads pages from the catalog (search, category filter, featured filter, pagination) and loads full entries only when the edit form needs one; the category list and featured chip list for the widget config come from the catalog; `entry_id` chip answers fetch a single entry through its shard; the widget config result is cached with a modification-time style key so it does not rebuild on every page view; a missing or stale catalog falls back to shard scanning with a notice; simple mode continues to use the v1.2 paths untouched.
- **Acceptance:** with 10,000 sample entries the admin list page loads quickly and paging works; category bar and chips appear correctly; editing an entry updates the list immediately; simple mode behaves as in v1.2.
- **Commit:** `feat(catalog): use catalog for admin lists, categories and chips`
- **Handoff:** which methods switch behavior by mode.

> **Claude Prompt (V13-D10)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D10: Catalog wiring. Changed parts only for `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-rest.php`, `includes/class-bryvium-widget.php`, `includes/storage/class-bryvium-storage-sharded.php`.
> Requirements: in large-site mode the admin entries list reads from the catalog (search, category filter, featured filter, pagination) and loads a full entry only for the edit form; widget config categories and chips come from the catalog and are cached with a key that changes when the catalog revision changes; the chip `entry_id` path loads one entry through its shard; a missing or stale catalog falls back to shard scanning and shows a notice; simple mode keeps the v1.2 paths with no changes; response shapes unchanged.
> Existing context: [Paste the catalog method signatures from V13-D06, the existing admin list handler summary, and the widget config builder summary.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V13-D11.

---

### PHASE C: JOBS, MAINTENANCE, AND BACKUPS

#### V13-D11: Resumable Job Runner

- **Branch:** `v13-feature/d11-job-runner`
- **Goal:** Run long tasks safely on slow shared hosting.
- **Files:** create `includes/class-bryvium-job-runner.php`; modify `includes/storage/class-bryvium-storage-migrator.php`, `includes/index/class-bryvium-index-builder.php`.
- **Tasks:** one job at a time, tracked in a small state file inside `jobs/` (not in options); job types: migrate, consolidate, rebuild index, rebuild catalog, verify, reshard, large import; each step has a time budget (default 8 seconds, adjusted down if the host max execution time is low) and a memory guard; state saved after every step (job type, step number, total steps, last processed shard or item, errors, started and heartbeat times); a dead job (heartbeat too old) can be resumed or cancelled safely; cancel leaves data in a consistent state; steps are idempotent so a repeated step is harmless; progress returned as a simple percentage plus a plain-language message; adapt the migrator and the index builder to run as steps.
- **Acceptance:** a rebuild of a 10,000-entry index completes across several requests; closing the browser mid-job and returning later resumes; cancel works at any point; starting a second job while one runs is refused politely.
- **Commit:** `feat(maint): add resumable time-budgeted job runner`
- **Handoff:** job interface, state fields, step contract.

> **Claude Prompt (V13-D11)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D11: Resumable job runner. Create `includes/class-bryvium-job-runner.php`; changed parts only for `includes/storage/class-bryvium-storage-migrator.php` and `includes/index/class-bryvium-index-builder.php`.
> Requirements: one active job at a time with state in `jobs/state.json` (never in options); job types `migrate`, `consolidate`, `rebuild_index`, `rebuild_catalog`, `verify`, `reshard`, `import`; `step()` method with a time budget (default 8 seconds, reduced when host max execution time is low) and memory guard; state saved after each step (type, step, total, cursor, errors, started_at, heartbeat); stale-heartbeat jobs can be resumed or cancelled; cancel leaves data consistent; steps are idempotent; progress as percentage plus plain-language message; a second start while running is refused with a friendly error; adapt the migrator and the index builder to expose step-based interfaces.
> Existing context: [Paste the migrator method list, the builder method list, and the filesystem helper lock and write signatures.]
> Output rules: code only for the new file, changed parts only for existing files. End with 5 manual tests (multi-request job, resume after closing, cancel, second job refused, stale heartbeat), the commit message, and a 2-line handoff for V13-D12.

---

#### V13-D12: Maintenance REST Routes

- **Branch:** `v13-feature/d12-maintenance-rest`
- **Goal:** Secure endpoints for all maintenance actions.
- **Files:** create `includes/class-bryvium-rest-maintenance.php`; modify `includes/class-bryvium-loader.php`.
- **Tasks:** implement the routes in Section 6.5 for status, rebuild, verify, reshard, enable large-site mode, consolidate, job progress, and job cancel; status returns mode, entry count, shard count, bytes, index state and age, catalog state, last backup, warnings, and recommendations (for example "re-shard recommended: average shard holds more than X entries"); verify checks counts, checksums, orphaned shard files, missing shards, index and catalog revision match; destructive actions need a confirmation flag; everything requires `manage_options` and a nonce; consistent response and error shapes with existing admin routes; routes only start jobs through the job runner.
- **Acceptance:** non-admins are rejected; status is accurate in both modes; verify detects a deleted shard, an extra file, and a stale index; destructive actions without the flag are refused.
- **Commit:** `feat(maint): add maintenance REST routes`
- **Handoff:** route payloads and status field list.

> **Claude Prompt (V13-D12)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D12: Maintenance REST. Create `includes/class-bryvium-rest-maintenance.php`; changed parts only for `includes/class-bryvium-loader.php`.
> Requirements: namespace `bryvium/v1`; GET `/maintenance/status`; POST `/maintenance/index/rebuild`, `/maintenance/verify`, `/maintenance/reshard`, `/maintenance/enable-large-site`, `/maintenance/consolidate` (each starts or continues a job via the job runner); GET `/maintenance/job`; POST `/maintenance/job/cancel`; status fields: mode, entry count, shard count, bytes, index state and age, catalog state, last backup, warnings, recommendations; verify checks counts, checksums, orphaned or missing shard files, index and catalog revision match; destructive actions require a `confirm` flag; permission `manage_options`; nonce; args schema and sanitization; response and error shapes consistent with existing admin routes.
> Existing context: [Paste the job runner interface from V13-D11, the manifest accessors, the admin REST registration pattern, and the backup helper name.]
> Output rules: code only for the new file, changed parts only for the loader. End with 5 manual tests, the commit message, and a 2-line handoff for V13-D13.

---

#### V13-D13: Maintenance Admin Screen

- **Branch:** `v13-feature/d13-maintenance-ui`
- **Goal:** A friendly health and repair screen for non-technical owners.
- **Files:** create `admin/views/page-maintenance.php`; modify `includes/class-bryvium-admin.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`.
- **Tasks:** "Maintenance" submenu with a warning badge when something needs attention; status cards (mode, entries, size, index, catalog, last backup) with simple green, amber, red states and plain explanations; action buttons (Rebuild search index, Verify data, Switch to large-site mode, Return to simple mode, Re-shard) each with a one-sentence description and a confirmation where needed; live progress bar that polls the job route, shows the plain-language step message, survives page reloads by checking the current job on load, and offers Cancel; clear success and error summaries; show the "downgrading is not supported after large-site mode" notice; explain when large-site mode is recommended; assets only on plugin screens; mobile friendly.
- **Acceptance:** status cards match the REST status; a rebuild shows progress and finishes; reloading the page mid-job resumes the progress display; buttons are disabled while a job runs.
- **Commit:** `feat(maint): add maintenance admin screen with job progress`
- **Handoff:** DOM ids and polling behavior.

> **Claude Prompt (V13-D13)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0 (plain PHP views and vanilla JS admin).
> Task V13-D13: Maintenance screen. Create `admin/views/page-maintenance.php`; changed parts only for `includes/class-bryvium-admin.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`.
> Requirements: submenu "Maintenance" with a warning badge when status has warnings; status cards (mode, entries, size, index, catalog, last backup) with green, amber, red states and one-line explanations; buttons for Rebuild search index, Verify data, Switch to large-site mode, Return to simple mode, Re-shard, each with a description and confirmation where destructive; progress bar polling GET `/maintenance/job`, plain-language messages, resumes after reload by checking the current job, Cancel button; buttons disabled during a job; notice that downgrading after large-site mode is unsupported; guidance on when large-site mode is recommended; all output escaped; responsive.
> Existing context: [Paste the maintenance route list and status fields from V13-D12 and how admin assets are enqueued.]
> Output rules: code only for the new file, changed parts only for existing files. End with 4 manual tests, the commit message, and a 2-line handoff for V13-D14.

---

#### V13-D14: Backup Manager (Retention and Restore)

- **Branch:** `v13-feature/d14-backup-manager`
- **Goal:** Centralized backups that never fill the disk and can be restored in one click.
- **Files:** create `includes/class-bryvium-backup.php`, `admin/views/page-backups.php` (or a section inside the maintenance page if you prefer one screen); modify `includes/class-bryvium-rest-maintenance.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`, `includes/class-bryvium-upgrader.php`, `includes/storage/class-bryvium-storage-migrator.php`, `includes/class-bryvium-importer.php`, `admin/js/bryvium-admin.js`.
- **Tasks:** one backup manager used by upgrade, migration, import, restore, and the manual "Back up now" button; each backup is a timestamped folder with a small info file (reason, date, mode, entry count, plugin version, checksums) and copies of the data files for the active mode; setting `backup_keep_count` with pruning after each new backup (oldest first, never delete the only backup, never delete a backup being restored); free-space check before creating; list backups with date, size, entries, and reason; delete one backup; restore with a confirmation: automatic safety backup of the current data, restore files, verify, switch mode if needed, mark the index and catalog stale and queue a rebuild; the v1.2 backup folders created by earlier versions are recognized and listed.
- **Acceptance:** retention keeps exactly the configured number; restore of a simple-mode backup into a site now in large-site mode ends in a consistent state with working search; a restore failure leaves the current data intact.
- **Commit:** `feat(backup): add backup manager with retention and restore`
- **Handoff:** backup manager method names and the info file fields.

> **Claude Prompt (V13-D14)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D14: Backup manager. Create `includes/class-bryvium-backup.php` and `admin/views/page-backups.php`; changed parts only for `includes/class-bryvium-rest-maintenance.php`, `includes/class-bryvium-settings.php`, `admin/views/page-settings.php`, `includes/class-bryvium-upgrader.php`, `includes/storage/class-bryvium-storage-migrator.php`, `includes/class-bryvium-importer.php`, `admin/js/bryvium-admin.js`.
> Requirements: a single backup manager used by upgrade, migration, import, restore, and a "Back up now" button; each backup is a timestamped folder with an info file (reason, date, mode, entry count, plugin version, checksums) plus copies of the active mode's data files (no ZipArchive); setting `backup_keep_count` (default 5, range 1 to 30) with pruning oldest first, never removing the only backup or one in use; free-space check; list with date, size, entries, reason; delete one; restore with confirmation that first creates a safety backup, restores files, verifies, switches mode if needed, marks index and catalog stale and queues a rebuild; recognize v1.2-style backup folders; routes GET `/backups`, POST `/backups`, POST `/backups/{id}/restore`, DELETE `/backups/{id}`.
> Existing context: [Paste the existing backup helper signature from v1.2, the migrator and importer call sites, the settings class method list, and the job runner interface.]
> Output rules: code only for the new files, changed parts only for existing files. End with 5 manual tests (retention, restore same mode, restore across modes, failed restore, v1.2 backups listed), the commit message, and a 2-line handoff for V13-D15.

---

### PHASE D: LARGE IMPORT

#### V13-D15: Chunked Import Engine

- **Branch:** `v13-feature/d15-chunked-import`
- **Goal:** Import tens of thousands of rows safely in small steps.
- **Files:** modify `includes/class-bryvium-importer.php`, `includes/class-bryvium-import-csv.php`, `includes/class-bryvium-import-markdown.php`, `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-job-runner.php`.
- **Tasks:**
  1. Raise limits for the staged path to 10 MB and 20,000 rows, while the old one-shot path keeps its v1.2 limits.
  2. Staging: store the uploaded content in the protected `staging/` folder under a random stage id with an expiry; delete after commit, cancel, or expiry.
  3. Make the CSV and Markdown parsers able to continue from an offset (a cursor) and return the next batch of parsed rows, with line and entry numbers that stay correct across batches.
  4. The staged import runs as a job: backup first, then batches of about 500 rows, each batch validated, deduplicated against existing titles (using the catalog in large-site mode), and committed through the sharded batch write.
  5. Index and catalog updates are applied per batch group; at the end the manifest is confirmed fresh.
  6. Report totals (added, updated, skipped, rejected) and keep a downloadable list of rejected rows with reasons.
  7. A failed batch stops the import with a clear message and the ability to resume or cancel. Already committed batches stay valid.
  8. Simple mode also supports staged imports (writes go through the simple driver in batches), with the practical simple-mode size limits explained.
- **Acceptance:** a 15,000-row CSV imports across multiple requests; interrupting and resuming works; duplicates and bad rows are reported correctly; memory stays flat; the one-shot v1.2 path still works for small files.
- **Commit:** `feat(bulk): add staged chunked import for large files`
- **Handoff:** stage lifecycle, batch size, cursor format.

> **Claude Prompt (V13-D15)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D15: Chunked import engine. Changed parts only for `includes/class-bryvium-importer.php`, `includes/class-bryvium-import-csv.php`, `includes/class-bryvium-import-markdown.php`, `includes/class-bryvium-rest-admin.php`, `includes/class-bryvium-job-runner.php`.
> Requirements: staged path limits 10 MB and 20,000 rows (one-shot path keeps v1.2 limits); upload goes to protected `staging/` with a random stage id and expiry, deleted after commit, cancel, or expiry; CSV and Markdown parsers accept a cursor and return the next batch with correct row or entry numbers; the import runs as a job type `import`: backup first, then batches of about 500 rows, each validated, deduplicated by normalized title (via the catalog in large-site mode), committed with the sharded batch write; index and catalog updates per batch group; final totals (added, updated, skipped, rejected) and a downloadable rejected-rows list; a failed batch stops with a clear message and can resume or cancel while committed batches stay valid; simple mode supports staged imports through the simple driver with its limits explained; routes POST `/import/stage`, POST `/import/stage/{id}/step`, GET `/import/stage/{id}`.
> Existing context: [Paste the router method and report shape from v1.2, parser signatures, the job runner interface, the batch write method from V13-D04, and the backup manager signature from V13-D14.]
> Output rules: changed parts only, short comments. End with 5 manual tests (large CSV, interrupt and resume, duplicates and bad rows, memory, small file one-shot), the commit message, and a 2-line handoff for V13-D16.

---

#### V13-D16: Large Import Screen

- **Branch:** `v13-feature/d16-large-import-ui`
- **Goal:** A clear upload, progress, and results experience.
- **Files:** modify `admin/views/page-tools.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`.
- **Tasks:** automatically choose the staged path when the file is larger than the v1.2 limits and explain why; the upload sends the file once and shows a preview of the first rows and a summary of detected format, delimiter, and column mapping before the owner presses Start; progress bar with counts of processed, added, updated, skipped, rejected, and estimated time left; Pause (cancel safely) and Resume; after finishing show the summary and a link to download the rejected rows; show a reminder that a backup was created and how to restore it; handle closing the tab: returning to the Tools page shows the unfinished import with Resume or Discard; friendly guidance on file preparation (encoding, columns) with downloadable sample content; mobile friendly.
- **Acceptance:** a large file import completes with accurate counters; reload mid-import offers Resume; Discard removes the staged file; errors are readable.
- **Commit:** `feat(bulk): add large import screen with progress and resume`
- **Handoff:** DOM ids and the resume mechanism.

> **Claude Prompt (V13-D16)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0 (plain PHP views and vanilla JS admin).
> Task V13-D16: Large import screen. Changed parts only for `admin/views/page-tools.php`, `admin/js/bryvium-admin.js`, `admin/css/bryvium-admin.css`.
> Requirements: choose the staged path automatically for files above v1.2 limits with a plain explanation; after upload show a first-rows preview plus detected format, delimiter, and column mapping, then a Start button; progress bar with processed, added, updated, skipped, rejected counts and time left estimate; Pause (safe cancel) and Resume; final summary with a rejected-rows download link and a reminder about the backup and how to restore it; on page load detect an unfinished staged import and offer Resume or Discard; file preparation guidance with sample content; all output escaped; responsive.
> Existing context: [Paste the staged route list from V13-D15, existing tools page ids, and the report shapes.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V13-D17.

---

### PHASE E: HARDENING AND RELEASE

#### V13-D17: i18n, Uninstall, Scheduled Housekeeping

- **Branch:** `v13-feature/d17-i18n-uninstall`
- **Goal:** All new strings translatable and complete cleanup.
- **Files:** modify `uninstall.php`, `includes/class-bryvium-loader.php`, `languages/bryvium-wp-file-chat.pot`; modify any file with missed strings.
- **Tasks:** make every new admin string and maintenance message translatable; regenerate the template; the existing daily cleanup event also prunes expired staging files, dead job state, and old backups by retention, and performs a light freshness check (flags a stale index so the admin sees a notice); uninstall removes manifest, shards, index, catalog, staging, jobs, and backups only when "remove data on uninstall" is on; deactivation clears scheduled events and leaves data; multisite aware.
- **Acceptance:** the template includes the new strings; housekeeping removes expired staging files; uninstall with the option on leaves no plugin data; with the option off the data folder stays.
- **Commit:** `feat(i18n): update translations, housekeeping and uninstall for v1.3 data`
- **Handoff:** list of new options, folders, and scheduled hooks.

> **Claude Prompt (V13-D17)**
>
> Senior WordPress Architect mode. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D17: i18n, uninstall, housekeeping. Changed parts only for `uninstall.php` and `includes/class-bryvium-loader.php`; list exact steps to regenerate `languages/bryvium-wp-file-chat.pot`.
> Requirements: the daily cleanup event also deletes expired staging files, clears dead job state, prunes backups by retention, and runs a light index freshness check that flags a stale index; uninstall removes `manifest.json`, `shards/`, `index/`, `staging/`, `jobs/`, and `backups/` only when "remove data on uninstall" is on; deactivation clears scheduled events and keeps data; multisite aware; all new strings use the text domain.
> Existing context: [Paste the current uninstall steps, scheduled hook name, and the new folder names.]
> Output rules: changed parts only. End with 4 manual tests, the commit message, and a 2-line handoff for V13-D18.

---

#### V13-D18: Security Audit (v1.3 Delta)

- **Branch:** `v13-feature/d18-security-delta`
- **Goal:** Audit only what is new or changed in v1.3.
- **Files:** create `docs/SECURITY-CHECKLIST-v1.3.md`; modify any file where an issue is found.
- **Tasks:** review every new route for capability, nonce, argument schema, and error handling; try path traversal against every place a name or id is accepted (backup id, stage id, shard names); confirm direct URL access is denied for shards, index, catalog, backups, staging, and jobs on Apache and document the Nginx rule; test hostile large imports (decompression-like bloat is not applicable, but test huge cells, malformed encodings, endless lines, and mixed delimiters); verify restore and consolidate cannot be triggered without the confirmation flag; verify staged files and expired stage ids cannot be reused; verify job state cannot be hijacked by a non-admin; confirm no server paths leak in visitor-facing errors; record pass or fixed for each item.
- **Acceptance:** every item is pass or fixed; PHPCS security sniffs show no errors.
- **Commit:** `security: audit v1.3 features and fix findings`
- **Handoff:** open issues, if any.

> **Claude Prompt (V13-D18)**
>
> Senior WordPress security reviewer. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D18: Security audit of v1.3 changes only. I will paste files one at a time. For each, review for missing capability or nonce checks, route argument gaps, path traversal through backup ids, stage ids, shard or index names, unsafe file handling and lock races, restore and consolidate safety flags, job state tampering, staging reuse, resource exhaustion in chunked import, information leaks, and XSS in admin output. Output a findings table (severity, location, issue, fix) and corrected snippets only for problems found. Also draft `docs/SECURITY-CHECKLIST-v1.3.md` as a pass or fail checklist covering storage layout, index, catalog, maintenance, backups, and chunked import, including the Nginx rule note.
> Files pasted in this session: [list files].
> Output rules: no rewrites of safe code. End with the commit message.

---

#### V13-D19: Performance and Scale Benchmarks

- **Branch:** `v13-feature/d19-performance-scale`
- **Goal:** Prove the scale targets and document honest limits.
- **Files:** create `docs/PERFORMANCE-v1.3.md`; modify `tools/generate-sample-knowledge.php` (sizes 1,000, 5,000, 10,000, 20,000 with categories, featured entries, and multi-language text); modify code only where a bottleneck is found.
- **Tasks:** measure cold and warm search time at each size in simple mode (where it still works) and in large-site mode with and without the index; measure memory per chat request; measure admin list load time and category list time; measure index full build time (steps and total), incremental update time, and migration time; measure chunked import throughput and peak memory at 20,000 rows; measure shard count and size distribution and test re-shard; compare widget bundle size against v1.2 (should be unchanged); record numbers beside the v1.2 baseline.

| Target | Value |
|---|---|
| Chat reply server time at 10,000 entries (cold, no persistent object cache) | Under 150 ms |
| Chat reply server time at 10,000 entries (warm) | Under 50 ms |
| Peak memory per chat request at 20,000 entries | Under 32 MB |
| Admin list page at 10,000 entries | Under 300 ms |
| Each job step | Under the configured time budget |
| Chunked import of 20,000 rows | Completes with flat memory |

- **Acceptance:** targets met or the real numbers documented with the advertised limit adjusted honestly.
- **Commit:** `perf: benchmark v1.3 scale features and tune hot spots`
- **Handoff:** advertised limits for v1.3.

> **Claude Prompt (V13-D19)**
>
> Senior WordPress performance engineer. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D19: Scale benchmarks. Draft `docs/PERFORMANCE-v1.3.md` as a fill-in template comparing v1.2 and v1.3 across 1,000, 5,000, 10,000, and 20,000 entries for: chat time cold and warm (simple mode, large-site mode without index, large-site mode with index), memory per chat request, admin list load, category list, index full build, incremental update, migration, re-shard, chunked import throughput and peak memory, shard size distribution, bundle size. Include the target table (chat under 150 ms cold and under 50 ms warm at 10,000 entries, under 32 MB peak memory at 20,000, admin list under 300 ms at 10,000). Also give changed parts only for `tools/generate-sample-knowledge.php` to generate those sizes with categories, featured entries, and English, Spanish, and Cebuano text. Add a measurement procedure and likely hot spots.
> Existing context: [Paste v1.2 baseline numbers and the generator's current options.]
> Output rules: short comments. End with the commit message and a 2-line handoff for V13-D20.

---

#### V13-D20: QA Matrix (v1.3 Delta and Upgrade Paths)

- **Branch:** `v13-feature/d20-qa-delta`
- **Goal:** Verify v1.3 on real environments, especially upgrades and failures.
- **Files:** create `docs/QA-MATRIX-v1.3.md`; modify code only for bugs found.
- **Tasks:** upgrade paths: v1.0 to v1.3 and v1.2 to v1.3 with empty, small, near-threshold, and above-threshold data; automatic and manual migration; consolidate back; failure injection (read-only folder, low disk space, deleted shard, deleted index, corrupted manifest, killed request in the middle of a write, killed job in the middle of a rebuild); restore across modes; concurrent admin edits while a job runs; chunked import with interruption; PHP 7.4, 8.1, 8.3; multisite; shared hosting with short execution time; Apache and Nginx protection checks; caching plugins; accessibility of the Maintenance and Tools screens; chat behavior parity between modes on the same questions; record pass or fail with notes.
- **Acceptance:** matrix complete; blocking bugs fixed; known limitations written down.
- **Commit:** `test: complete v1.3 QA matrix with failure-injection and upgrade paths`
- **Handoff:** known limitations list.

> **Claude Prompt (V13-D20)**
>
> Senior QA lead for WordPress plugins. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D20: Produce the full content of `docs/QA-MATRIX-v1.3.md` as a ready-to-fill checklist table. Cover: upgrade from v1.0 and v1.2 (empty, small, near threshold, above threshold), automatic and manual migration, consolidate, failure injection (read-only folder, low disk, deleted shard, deleted index, corrupted manifest, killed write, killed job), restore across modes, concurrent edits during a job, interrupted chunked import, PHP 7.4, 8.1, 8.3, multisite, short execution time hosting, Apache and Nginx protection, caching plugins, accessibility of Maintenance and Tools screens, and chat parity between modes. Each row needs steps and expected result. I will report failures and you propose fixes one at a time.
> Existing context: [Paste the v1.3 feature list from the documentation.]
> Output rules: Markdown only, no code. End with the commit message.

---

#### V13-D21: Release Assets and Version Bump

- **Branch:** `v13-feature/d21-release-assets`
- **Goal:** Everything needed to publish v1.3.0.
- **Files:** modify `readme.txt`, `CHANGELOG.md`, `bryvium-wp-file-chat.php`, `package.json`, `scripts/build-zip.mjs`; add new screenshots to `.wordpress-org/`.
- **Tasks:** bump the version from `1.3.0-dev` to `1.3.0` everywhere (plugin header, constant, readme stable tag, package file) and verify they match; update the readme description, FAQ (When do I need large-site mode? How do I go back? What happens to my backups?), screenshots list (Maintenance, Backups, Large import), upgrade notice (automatic backup, large-site mode is optional, downgrading after large-site mode is unsupported), and privacy statement; changelog grouped by feature; make sure the zip includes the new `includes/storage/` and `includes/index/` folders and excludes dev-only tools including the parity routine and the sample data generator; build the zip and install it on a clean site and on a site upgraded from v1.2.0 and from v1.0.0.
- **Acceptance:** versions match; zip contains only runtime files; clean install and both upgrade paths work from the zip.
- **Commit:** `build: prepare release assets for 1.3.0`
- **Handoff:** the zip name and contents list.

> **Claude Prompt (V13-D21)**
>
> Senior WordPress plugin release engineer. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D21: Release assets. Write the updated `readme.txt` sections (description, FAQ additions: when do I need large-site mode, how do I go back, what happens to backups; screenshots list including Maintenance, Backups, Large import; upgrade notice stating an automatic backup is created, large-site mode is optional, and downgrading after large-site mode is unsupported; privacy section confirming nothing leaves the server), a `CHANGELOG.md` entry for 1.3.0 grouped by feature (sharded storage, search index, catalog, maintenance, chunked import, backups), and the changes to `scripts/build-zip.mjs` so it includes `includes/storage/` and `includes/index/` and excludes dev-only tools and docs. Give a version-consistency checklist.
> Existing context: [Paste the current readme headings, the changelog top section, and the zip script exclusion list.]
> Output rules: changed parts only, short comments. End with the commit message and a 2-line handoff for V13-D22.

---

#### V13-D22: Release Candidate, Tag, and Deploy

- **Branch:** `v13-release/1.3.0`
- **Goal:** Ship v1.3.0 safely.
- **Files:** create `docs/RELEASE-CHECKLIST-v1.3.md`.
- **Tasks:** create `v13-release/1.3.0` from `v13-develop`; tag `v1.3.0-rc.1`; test the packaged zip on clean sites and on upgraded sites (from v1.0.0 and v1.2.0) with real-sized data; fix only blocking bugs on the release branch and tag further candidates if needed; merge the release branch into `main` and tag `v1.3.0`; merge `main` back into `v13-develop`, `v12-develop`, and `develop`; publish the zip; submit the update to WordPress.org if listed; monitor feedback for two weeks, paying special attention to migration and maintenance issues; keep the rollback plan ready (previous release zip plus restore-from-backup steps); keep a list of requests for v1.4.
- **Acceptance:** `v1.3.0` tag exists on `main`; zip installs and upgrades cleanly; all branches contain the release; checklist signed off.
- **Commit:** `chore(release): prepare 1.3.0`
- **Handoff:** feedback log and v1.4 backlog.

> **Claude Prompt (V13-D22)**
>
> Senior WordPress plugin release manager. Project: Bryvium WP File Chat v1.3.0.
> Task V13-D22: Draft `docs/RELEASE-CHECKLIST-v1.3.md` with pre-release checks, the exact branch flow (`v13-develop` to `v13-release/1.3.0` to `main`, release candidate tags `v1.3.0-rc.N`, final tag `v1.3.0`, merge `main` back into `v13-develop`, `v12-develop`, and `develop`), packaging and install verification on clean sites and sites upgraded from v1.0.0 and v1.2.0 with real-sized data, publishing steps (GitHub release and optional WordPress.org update), a two-week monitoring plan focused on migration and maintenance reports, and a rollback plan using the previous zip and the backups. Markdown only, no code.
> Existing context: the v1.3 branching model from the documentation.
> Output rules: concise checklist. End with the final commit message.

---

## 12. How to Use the Daily Claude Prompts

1. Open this document and find today's `V13-D` day.
2. Create the listed `v13-feature/...` branch from `v13-develop`.
3. Copy that day's **Claude Prompt** into a new chat.
4. Replace every bracketed placeholder with the real context. Paste only signatures and short notes, never whole files.
5. Review the generated code and place it in the exact files listed.
6. Run the acceptance checks and the quality gates in Section 10. For storage and index days, always compare answers against simple mode on the same sample data.
7. Commit with the day's commit message and merge into `v13-develop`.
8. Save the handoff note Claude gives you. It becomes the next day's "Existing context".

**Token-saving habits**
- Paste function signatures and result shapes, not files.
- Ask for changed methods only when editing existing files.
- Split heavy days (for example V13-D04 into add and update first, delete and batch second; V13-D15 into staging first, batches second).
- Keep a running handoff log file for yourself.

**Order matters (dependencies)**
- D01 and D02 must come first. Every later storage day depends on the driver interface and the filesystem helper.
- D03 and D04 (sharded driver) come before D05 (migration) because the migrator writes through the sharded driver.
- D06 (catalog) and D07 to D09 (index) need the write hooks from D04.
- D10 needs D06.
- D11 (job runner) is needed by D12 to D16. Until D11 exists, migration uses its synchronous time-limited method.
- D14 (backups) is used by D15 (chunked import).
- D17 to D22 come last.

---

## 13. Release Checklist for v1.3.0 (Summary)

- All 22 `V13-D` days merged into `v13-develop` and passing the quality gates
- Simple-mode sites verified unchanged (v1.0 and v1.2 data, no migration without consent)
- Large-site mode verified at 1,000, 5,000, 10,000, and 20,000 entries with parity against simple mode
- Migration, consolidate, and restore verified including failure injection
- Index and catalog fallback verified (deleted index, deleted catalog, damaged shard)
- Chunked import verified with interruption and resume
- Security checklist for v1.3 fully passed (including path traversal and direct URL access)
- Performance numbers documented against the v1.2 baseline and targets
- QA matrix complete
- Version numbers identical in the plugin header, constant, `readme.txt`, and `package.json`
- Zip contains runtime files only and installs on clean and upgraded sites
- `v13-release/1.3.0` merged into `main` and tagged `v1.3.0`
- `main` merged back into `v13-develop`, `v12-develop`, and `develop`
- Rollback plan and support routine ready

---

## 14. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Migration to large-site mode corrupts data | Mandatory backup, verified counts and checksums, switch only after verification, original file moved (not deleted) into the backup |
| Index disagrees with data | Revision tracking, stale state, automatic fallback to a full scan, Verify and Rebuild tools |
| Search results differ between modes | Parity rule, dev-only parity routine, same scoring code |
| Long tasks time out on cheap hosting | Time-budgeted, resumable, idempotent job steps with state saved after each step |
| Two admins start conflicting jobs | One active job at a time, friendly refusal, stale-heartbeat takeover |
| Shards become too big or too small | Bucket count chosen at migration, warnings and a re-shard tool |
| Disk fills with backups | Count-based retention, free-space check before backups, easy delete |
| Restore overwrites good data by mistake | Confirmation required, automatic safety backup before restore |
| Path traversal through ids or names | Strict generated name patterns only, no user input in paths |
| Nginx ignores `.htaccess` | Document a server rule, keep blank index files, serve data only through REST |
| Hostile or huge import | Size and row caps, staged storage with expiry, batch validation, rejected-rows report |
| Owners confused by two modes | Small sites never see it; Maintenance screen explains in plain language when large-site mode helps |
| Hotfix on v1.2 lost during v1.3 work | Merge `main` into `v13-develop` the same day any hotfix lands |
| Free-tier token limits | Daily split, signature-only context, changed-methods-only output |

---

## 15. After v1.3.0 (Next Roadmap)

| Version | Focus |
|---|---|
| v1.4 | Gutenberg block, per-page visibility rules, local analytics dashboard (including trends from the unanswered log) |
| v1.5 (idea) | WP-CLI commands for maintenance, scheduled automatic backups, export of the index health report |
| v2.0 | Optional, opt-in provider interface that is off by default and never required |

---

*End of document.*
