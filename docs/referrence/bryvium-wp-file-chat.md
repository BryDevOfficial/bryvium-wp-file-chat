# Acknowledged

Understood: no code yet. This is the foundation for **Bryvium WP File Chat**, built in small daily steps.

One clarification on "zero-dependency" that I'll treat as a rule: **zero runtime dependencies** (no external APIs, no Composer packages, no DB tables, no CDN calls). React, Tailwind, and Vite are **build-time only**, and the shipped plugin contains just one compiled JS and one CSS file.

---

## 1. Project Documentation Plan

### 1.1 Architecture

**Folder structure (target)**

```
bryvium-wp-file-chat/
├── bryvium-wp-file-chat.php     # Bootstrap, headers, constants
├── uninstall.php                # Cleanup
├── includes/
│   ├── class-bryvium-loader.php       # Hooks registration
│   ├── class-bryvium-storage.php      # JSON read/write, locking, validation
│   ├── class-bryvium-search.php       # Tokenizer + scoring engine
│   ├── class-bryvium-rest.php         # REST routes (/bryvium/v1/...)
│   ├── class-bryvium-admin.php        # Admin menu + settings page
│   └── class-bryvium-widget.php       # Frontend enqueue + shortcode
├── admin/                       # Admin UI (React or plain PHP forms; decided Day 9)
├── src/                         # React + Tailwind source (dev only)
├── build/                       # Vite output (committed for release)
├── languages/                   # i18n .pot
└── docs/                        # Project docs (this plan)
```

**Data flow**

```
Visitor types → React widget → POST /wp-json/bryvium/v1/chat (nonce + rate limit)
→ PHP sanitizes → Search engine reads cached knowledge.json
→ Scores entries → Returns top match(es) + confidence → Widget renders
```

**Key architectural decisions**

| Concern     | Decision                                                                                                                                                                     |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Storage     | `knowledge.json` in `wp-content/uploads/bryvium-chat/`, protected by `.htaccess` + `index.php`, and served only via REST                                                     |
| Concurrency | `flock()` exclusive lock on writes, atomic write (temp file + rename)                                                                                                        |
| Caching     | Parsed JSON cached in a transient/object cache keyed by file `filemtime`                                                                                                     |
| Search      | Pure PHP: lowercase, strip stopwords, tokenize, weighted scoring (title > keywords > body), simple stemming, fuzzy fallback via `similar_text`/`levenshtein` on short tokens |
| Security    | Nonces, capability checks (`manage_options`), `sanitize_*`/`wp_kses`, escaped output, REST `permission_callback`, per-IP rate limiting via transients, max payload sizes     |
| Privacy     | No external calls, no cookies required, no personal data stored by default (chat logs are opt-in and local)                                                                  |
| Frontend    | Single React root mounted by shortcode/footer hook, Tailwind prefixed (`bwfc-`) and scoped to avoid theme collisions, lazy-loaded                                            |
| Standards   | WPCS (PHPCS ruleset), i18n-ready, `bryvium_` prefix, PHP 7.4+, WP 6.0+                                                                                                       |
| Scale limit | Designed for roughly 1,000 entries / 2 MB JSON. Beyond that, the roadmap adds sharded files                                                                                  |

**Knowledge entry schema (draft)**
`id`, `title`, `answer`, `keywords[]`, `category`, `enabled`, `updated_at`

### 1.2 Git Branching & Commit Guidelines

**Branches**

- `main`: always releasable, tagged with versions
- `develop`: integration branch
- `feature/<day-or-topic>`: e.g. `feature/d03-json-storage`
- `fix/<issue>`, `release/x.y.z`, `hotfix/<issue>`

**Workflow:** one branch per daily task, merged to `develop` via squash or PR. `develop` merges to `main` only at release.

**Commit format (Conventional Commits)**

```
<type>(<scope>): <imperative summary, ≤72 chars>

[optional body: why, not what]
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `chore`, `security`.
Scopes: `storage`, `search`, `rest`, `admin`, `widget`, `build`, `i18n`.
Example: `feat(search): add weighted keyword scoring`

**Rules:** commit at the end of each daily task, never commit `node_modules/` or real `knowledge.json` data, keep a `CHANGELOG.md` (Keep a Changelog format), and use SemVer (`0.x` until the first stable release).

### 1.3 Future Roadmap

| Version  | Focus                                                                               |
| -------- | ----------------------------------------------------------------------------------- |
| **v1.0** | Core chat, admin CRUD, JSON import/export, rate limiting, theming basics            |
| **v1.1** | Unanswered-question log and "suggest answer" workflow, quick-reply suggestion chips |
| **v1.2** | Bulk import (CSV/Markdown/FAQ), category filters, multilingual stopwords            |
| **v1.3** | Sharded JSON files for large knowledge bases, search index file                     |
| **v1.4** | Gutenberg block, per-page widget visibility rules, analytics dashboard (local)      |
| **v2.0** | Optional pluggable provider interface (opt-in only, off by default, never required) |

---

## 2. Daily Development Schedule

**Phase 1: Foundation (Days 1–3)**

- **D1:** Repo setup, plugin bootstrap file, constants, activation/deactivation hooks, folder skeleton
- **D2:** PHPCS/WPCS config, `.gitignore`, `.editorconfig`, Vite + Tailwind scaffold (dev tooling only)
- **D3:** Storage class: protected directory creation, atomic read/write with locking, schema validation

**Phase 2: Backend Core (Days 4–7)**

- **D4:** Search engine part 1: normalization, tokenizer, stopwords
- **D5:** Search engine part 2: scoring, ranking, confidence threshold, fuzzy fallback
- **D6:** REST API: `/chat` endpoint with sanitization, nonce, and response format
- **D7:** Rate limiting, caching by `filemtime`, input size limits

**Phase 3: Admin Experience (Days 8–11)**

- **D8:** Admin menu + settings storage (widget title, greeting, fallback message, colors)
- **D9:** Knowledge manager UI: list/search/add/edit/delete entries
- **D10:** REST admin endpoints (capability-gated CRUD) and wiring to UI
- **D11:** JSON import/export with validation and size checks

**Phase 4: Frontend Widget (Days 12–16)**

- **D12:** React app skeleton, mount logic, Vite production build config, enqueue
- **D13:** Chat UI: launcher button, window, message list, input (Tailwind, prefixed)
- **D14:** API integration: fetch, loading/typing state, error handling, session history (in-memory/sessionStorage)
- **D15:** Accessibility (ARIA, focus management, keyboard), mobile responsiveness
- **D16:** Theming via CSS variables from admin settings, shortcode + auto-display option

**Phase 5: Hardening (Days 17–20)**

- **D17:** Security audit pass (escaping, capabilities, REST permissions, file access)
- **D18:** Performance pass: bundle size, lazy loading, search benchmarks on 1k entries
- **D19:** i18n (`.pot`), `uninstall.php`, edge cases (empty file, corrupted JSON, permissions)
- **D20:** Manual QA matrix (themes, PHP versions, multisite, caching plugins)

**Phase 6: Release (Days 21–22)**

- **D21:** `readme.txt` (WP.org format), screenshots, changelog, build script for zip packaging
- **D22:** Tag v1.0.0, release zip, deployment checklist, post-launch monitoring notes

---

## 3. Daily Coding Request Template

Copy, fill in the brackets, and paste:

```
## DAILY CODING REQUEST
**Project:** Bryvium WP File Chat
**Day / Task:** [e.g., D3: Storage class]
**Branch:** [e.g., feature/d03-json-storage]

**Goal (1–2 sentences):**
[What must work by end of today]

**Files to create/modify (only these):**
- [path/file-1]
- [path/file-2]

**Existing context (paste ONLY what's needed):**
[Relevant function signatures / class names / schema. Not whole files.]

**Constraints:**
- Zero runtime dependencies, WPCS-compliant, PHP 7.4+
- Secure by default (sanitize in, escape out, capability + nonce checks)
- [Any task-specific constraint]

**Output rules:**
- Code ONLY for the files listed. No unrelated refactors.
- Short inline comments, no long explanations.
- End with: (a) a 3-step manual test, (b) the Conventional Commit message,
  (c) a 2-line handoff note of what Day [N+1] will need from this code.
```

**Token-saving habits**

- Paste function signatures, not entire files, as context.
- Keep the handoff note from each day. It becomes your next day's "Existing context."
- If a day's task overflows, split it ("D5a: scoring," "D5b: fuzzy fallback") rather than compressing the code.

---