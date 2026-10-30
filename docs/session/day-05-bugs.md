# Day 5 Bugs and Limitations

## Known limitations

- Confidence weights and the default `0.35` threshold are initial heuristics;
  tune them against a representative FAQ set before release.
- Fuzzy matching is intentionally limited to ASCII tokens of 4–8 characters.
- Related suggestions may be returned on a `no_match` result to support
  follow-up choices; the REST/API layer should preserve that result shape.

No known open Day 5 defects from the focused search tests.
