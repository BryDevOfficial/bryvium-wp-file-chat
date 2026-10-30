# Day 4 Notes — Search Text Processing

## Implemented

- Added the stateless `Bryvium_Search::tokenize( $text )` entry point.
- Normalizes case, accents, punctuation, and whitespace; ignores malformed
  UTF-8 safely and caps processing at 10,000 Unicode characters.
- Removes English stopwords, drops tokens shorter than two characters, and
  applies conservative plural/common-suffix stemming.
- Added the English stopword list in `includes/data/stopwords-en.php`.
- No loader registration is needed yet; D5 will extend the search class.

## Example output

```php
Bryvium_Search::tokenize( 'The CAFÉS are serving fresh pastries!' );
// array( 'cafe', 'serv', 'fresh', 'pastry' )

Bryvium_Search::tokenize( 'Running, runners, and deliveries.' );
// array( 'run', 'runner', 'delivery' )

Bryvium_Search::tokenize( '!!!' );
// array()
```

## Verification

- PHP lint and `composer lint` pass.
- Focused PHP CLI checks cover punctuation-only and empty input, stopwords,
  stemming, accents, non-English Unicode, invalid UTF-8, and long input caps.

## Handoff

Call `Bryvium_Search::tokenize( $text )` with a raw string. It returns an
ordered `string[]` of normalized, stemmed tokens with English stopwords
removed.
