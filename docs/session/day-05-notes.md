# Day 5 Notes — Search Scoring

## Implemented

- Added `Bryvium_Search::search( $query, $entries, $threshold = 0.35 )`.
- Scores title matches above keywords, and keywords above answer text;
  includes query coverage and exact-phrase bonuses.
- Adds conservative Levenshtein matching for short ASCII tokens. Fuzzy
  matches are discounted and compare unstemmed tokens so stemming does not
  hide common typos.
- Skips entries that are disabled or do not have the required fields.
- Sorts deterministically by confidence and then entry ID.
- Returns a consistent result with `status`, `id`, `answer`, `confidence`,
  and up to two `suggestions` (`id`, `title`, `answer`, `confidence`).
- Validates the confidence threshold between 0 and 1; empty or unrelated
  searches return `no_match` with an empty answer.

## Verification

- `composer lint` and PHP syntax checks pass.
- Focused PHP tests pass for exact title matches, typo fallback, unrelated
  queries, disabled entries, empty data, invalid thresholds, limited
  suggestions, and deterministic repeated results.

## Handoff

Use `Bryvium_Search::search( $query, $entries, $threshold )`. It returns
`status`, `id`, `answer`, `confidence` (0–1), and `suggestions`; invalid
thresholds or entry collections return `WP_Error`.
