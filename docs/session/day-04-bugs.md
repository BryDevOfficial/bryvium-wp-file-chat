# Day 4 Bugs and Limitations

## Known limitations

- Stopword removal is English-only; multilingual stopword support is a later
  roadmap item.
- Stemming is deliberately lightweight and is not a linguistic stemmer.
- When `mbstring` is unavailable, lowercasing of non-Latin scripts is limited
  by PHP's native `strtolower()`. UTF-8 input is still handled without regex
  errors.

No known open Day 4 defects from the focused tokenizer checks.
