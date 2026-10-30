<?php
/**
 * Search text normalization and tokenization.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides stateless text processing for knowledge search.
 */
final class Bryvium_Search {

	/**
	 * Maximum input length in Unicode characters.
	 *
	 * @var int
	 */
	const MAX_INPUT_LENGTH = 10000;

	/**
	 * Return normalized, stemmed tokens without English stopwords.
	 *
	 * @param mixed $text Raw text to tokenize.
	 * @return string[]
	 */
	public static function tokenize( $text ) {
		return self::normalize_tokens( $text, true );
	}

	/**
	 * Find the best matching enabled knowledge entry.
	 *
	 * @param mixed $query Search query.
	 * @param mixed $entries Knowledge entries.
	 * @param float $threshold Minimum confidence required for an answer.
	 * @return array|WP_Error
	 */
	public static function search( $query, $entries, $threshold = 0.35 ) {
		if ( ! is_numeric( $threshold ) || (float) $threshold < 0 || (float) $threshold > 1 ) {
			return new WP_Error(
				'bryvium_wfc_search_invalid_threshold',
				__( 'The confidence threshold must be between 0 and 1.', 'bryvium-wp-file-chat' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $entries ) ) {
			return new WP_Error(
				'bryvium_wfc_search_invalid_entries',
				__( 'Knowledge entries must be provided as a list.', 'bryvium-wp-file-chat' ),
				array( 'status' => 400 )
			);
		}

		$query_tokens = self::tokenize( $query );
		if ( empty( $query_tokens ) ) {
			return self::empty_result();
		}
		$query_raw = self::tokenize_unstemmed( $query );

		$candidates = array();
		foreach ( $entries as $entry ) {
			if ( ! self::is_searchable_entry( $entry ) ) {
				continue;
			}

			$candidate = self::score_entry( $query_tokens, $query_raw, $entry );
			if ( $candidate['confidence'] > 0 ) {
				$candidates[] = $candidate;
			}
		}

		if ( empty( $candidates ) ) {
			return self::empty_result();
		}

		usort( $candidates, array( __CLASS__, 'compare_candidates' ) );

		$best        = $candidates[0];
		$is_match    = $best['confidence'] >= (float) $threshold;
		$suggestions = array();
		$start_index = $is_match ? 1 : 0;

		foreach ( array_slice( $candidates, $start_index, 2 ) as $candidate ) {
			$suggestions[] = self::format_suggestion( $candidate );
		}

		return array(
			'status'      => $is_match ? 'matched' : 'no_match',
			'id'          => $is_match ? $best['id'] : null,
			'answer'      => $is_match ? $best['answer'] : '',
			'confidence'  => $best['confidence'],
			'suggestions' => $suggestions,
		);
	}

	/**
	 * Normalize text and optionally apply stemming.
	 *
	 * @param mixed $text Raw text.
	 * @param bool  $apply_stemming Whether to stem tokens.
	 * @return string[]
	 */
	private static function normalize_tokens( $text, $apply_stemming ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return array();
		}

		$text = substr( $text, 0, self::MAX_INPUT_LENGTH * 4 );
		$text = self::make_valid_utf8( $text );
		$text = self::truncate_input( $text );

		$text = wp_strip_all_tags( $text, true );

		if ( function_exists( 'remove_accents' ) ) {
			$text = remove_accents( $text );
		} elseif ( function_exists( 'iconv' ) ) {
			$folded_text = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
			if ( false !== $folded_text ) {
				$text = $folded_text;
			}
		}

		if ( function_exists( 'mb_strtolower' ) ) {
			$text = mb_strtolower( $text, 'UTF-8' );
		} else {
			$text = strtolower( $text );
		}

		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		if ( null === $text || '' === trim( $text ) ) {
			return array();
		}

		$stopwords = require __DIR__ . '/data/stopwords-en.php';
		$tokens    = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$cleaned   = array();

		foreach ( $tokens as $token ) {
			if ( self::string_length( $token ) < 2 || in_array( $token, $stopwords, true ) ) {
				continue;
			}

			$stemmed_token = self::stem( $token );
			if ( self::string_length( $stemmed_token ) >= 2 ) {
				$cleaned[] = $apply_stemming ? $stemmed_token : $token;
			}
		}

		return $cleaned;
	}

	/**
	 * Return normalized tokens before stemming for typo comparisons.
	 *
	 * @param mixed $text Raw text.
	 * @return string[]
	 */
	private static function tokenize_unstemmed( $text ) {
		return self::normalize_tokens( $text, false );
	}

	/**
	 * Check the required fields for a searchable entry.
	 *
	 * @param mixed $entry Knowledge entry.
	 * @return bool
	 */
	private static function is_searchable_entry( $entry ) {
		return is_array( $entry )
			&& isset( $entry['id'], $entry['title'], $entry['answer'], $entry['keywords'], $entry['enabled'] )
			&& is_string( $entry['id'] )
			&& is_string( $entry['title'] )
			&& is_string( $entry['answer'] )
			&& is_array( $entry['keywords'] )
			&& true === $entry['enabled'];
	}

	/**
	 * Score one entry using field priority, query coverage and phrase matches.
	 *
	 * @param string[] $query_tokens Normalized query tokens.
	 * @param string[] $query_raw Unstemmed query tokens.
	 * @param array    $entry Knowledge entry.
	 * @return array
	 */
	private static function score_entry( $query_tokens, $query_raw, $entry ) {
		$title_tokens   = self::tokenize( $entry['title'] );
		$answer_tokens  = self::tokenize( $entry['answer'] );
		$title_raw      = self::tokenize_unstemmed( $entry['title'] );
		$answer_raw     = self::tokenize_unstemmed( $entry['answer'] );
		$keyword_tokens = array();
		$keyword_raw    = array();

		foreach ( $entry['keywords'] as $keyword ) {
			if ( is_string( $keyword ) ) {
				$keyword_tokens = array_merge( $keyword_tokens, self::tokenize( $keyword ) );
				$keyword_raw    = array_merge( $keyword_raw, self::tokenize_unstemmed( $keyword ) );
			}
		}

		$matched_tokens = 0;
		$weighted_score = 0.0;
		foreach ( $query_tokens as $query_index => $query_token ) {
			$field_score = 0.0;

			$title_strength = self::token_match_strength( $query_token, $title_tokens, $query_raw[ $query_index ], $title_raw );
			if ( $title_strength > 0 ) {
				$field_score = $title_strength;
			} else {
				$keyword_strength = self::token_match_strength( $query_token, $keyword_tokens, $query_raw[ $query_index ], $keyword_raw );
				if ( $keyword_strength > 0 ) {
					$field_score = 0.8 * $keyword_strength;
				} else {
					$answer_strength = self::token_match_strength( $query_token, $answer_tokens, $query_raw[ $query_index ], $answer_raw );
					if ( $answer_strength > 0 ) {
						$field_score = 0.45 * $answer_strength;
					}
				}
			}

			if ( $field_score > 0 ) {
				++$matched_tokens;
				$weighted_score += $field_score;
			}
		}

		$query_count = count( $query_tokens );
		$coverage    = $matched_tokens / $query_count;
		$score       = ( 0.65 * ( $weighted_score / $query_count ) ) + ( 0.15 * $coverage );

		if ( self::contains_phrase( $title_tokens, $query_tokens ) ) {
			$score += 0.20;
		} elseif ( self::contains_phrase( $keyword_tokens, $query_tokens ) ) {
			$score += 0.12;
		} elseif ( self::contains_phrase( $answer_tokens, $query_tokens ) ) {
			$score += 0.08;
		}

		return array(
			'id'         => $entry['id'],
			'title'      => $entry['title'],
			'answer'     => $entry['answer'],
			'confidence' => round( min( 1.0, $score ), 4 ),
		);
	}

	/**
	 * Match a token exactly or with a conservative short-token typo fallback.
	 *
	 * @param string   $query_token Query token.
	 * @param string[] $field_tokens Entry field tokens.
	 * @param string   $raw_query_token Unstemmed query token.
	 * @param string[] $raw_field_tokens Unstemmed entry field tokens.
	 * @return float Match strength from 0 to 1.
	 */
	private static function token_match_strength( $query_token, $field_tokens, $raw_query_token, $raw_field_tokens ) {
		if ( in_array( $query_token, $field_tokens, true ) ) {
			return 1.0;
		}

		if ( ! preg_match( '/^[a-z0-9]+$/D', $raw_query_token ) ) {
			return 0.0;
		}

		$query_length = strlen( $raw_query_token );
		if ( $query_length < 4 || $query_length > 8 ) {
			return 0.0;
		}

		$max_distance = $query_length < 7 ? 1 : 2;
		foreach ( $raw_field_tokens as $field_token ) {
			if ( ! preg_match( '/^[a-z0-9]+$/D', $field_token )
				|| abs( strlen( $field_token ) - $query_length ) > $max_distance
			) {
				continue;
			}

			if ( levenshtein( $raw_query_token, $field_token ) <= $max_distance ) {
				return 0.6;
			}
		}

		return 0.0;
	}

	/**
	 * Check whether one token list contains another as an ordered phrase.
	 *
	 * @param string[] $field_tokens Entry field tokens.
	 * @param string[] $query_tokens Query tokens.
	 * @return bool
	 */
	private static function contains_phrase( $field_tokens, $query_tokens ) {
		$phrase_length = count( $query_tokens );
		if ( $phrase_length > count( $field_tokens ) ) {
			return false;
		}

		$last_offset = count( $field_tokens ) - $phrase_length;
		for ( $offset = 0; $offset <= $last_offset; $offset++ ) {
			if ( array_slice( $field_tokens, $offset, $phrase_length ) === $query_tokens ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sort by descending confidence, then stable ID.
	 *
	 * @param array $left First candidate.
	 * @param array $right Second candidate.
	 * @return int
	 */
	private static function compare_candidates( $left, $right ) {
		if ( $left['confidence'] !== $right['confidence'] ) {
			return $left['confidence'] > $right['confidence'] ? -1 : 1;
		}

		return strcmp( $left['id'], $right['id'] );
	}

	/**
	 * Format a related result without exposing internal ranking fields.
	 *
	 * @param array $candidate Search candidate.
	 * @return array
	 */
	private static function format_suggestion( $candidate ) {
		return array(
			'id'         => $candidate['id'],
			'title'      => $candidate['title'],
			'answer'     => $candidate['answer'],
			'confidence' => $candidate['confidence'],
		);
	}

	/**
	 * Return the consistent no-result response.
	 *
	 * @return array
	 */
	private static function empty_result() {
		return array(
			'status'      => 'no_match',
			'id'          => null,
			'answer'      => '',
			'confidence'  => 0.0,
			'suggestions' => array(),
		);
	}

	/**
	 * Remove malformed UTF-8 bytes before Unicode-aware operations.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function make_valid_utf8( $text ) {
		if ( 1 === preg_match( '//u', $text ) ) {
			return $text;
		}

		if ( function_exists( 'iconv' ) ) {
			$valid_text = iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
			if ( false !== $valid_text ) {
				return $valid_text;
			}
		}

		return preg_replace( '/[\x80-\xFF]/', ' ', $text );
	}

	/**
	 * Truncate input without splitting a UTF-8 character.
	 *
	 * @param string $text Valid UTF-8 text.
	 * @return string
	 */
	private static function truncate_input( $text ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, self::MAX_INPUT_LENGTH, 'UTF-8' );
		}

		$matched = preg_match( '/^.{0,' . self::MAX_INPUT_LENGTH . '}/us', $text, $matches );
		if ( 1 === $matched ) {
			return $matches[0];
		}

		return substr( $text, 0, self::MAX_INPUT_LENGTH );
	}

	/**
	 * Apply conservative plural and common suffix stemming.
	 *
	 * @param string $token Normalized token.
	 * @return string
	 */
	private static function stem( $token ) {
		$length = strlen( $token );

		if ( $length > 4 && str_ends_with( $token, 'ies' ) ) {
			return substr( $token, 0, -3 ) . 'y';
		}

		if ( $length > 5 && str_ends_with( $token, 'sses' ) ) {
			return substr( $token, 0, -2 );
		}

		if ( $length > 5 && str_ends_with( $token, 'ing' ) ) {
			$token = substr( $token, 0, -3 );
			return self::remove_doubled_ending( $token );
		}

		if ( $length > 4 && str_ends_with( $token, 'ed' ) ) {
			$token = substr( $token, 0, -2 );
			return self::remove_doubled_ending( $token );
		}

		if ( $length > 4 && str_ends_with( $token, 'ly' ) ) {
			return substr( $token, 0, -2 );
		}

		if ( $length > 5
			&& (
				str_ends_with( $token, 'ches' )
				|| str_ends_with( $token, 'shes' )
				|| str_ends_with( $token, 'xes' )
				|| str_ends_with( $token, 'zes' )
			)
		) {
			return substr( $token, 0, -2 );
		}

		if ( $length > 3
			&& str_ends_with( $token, 's' )
			&& ! str_ends_with( $token, 'ss' )
			&& ! str_ends_with( $token, 'us' )
			&& ! str_ends_with( $token, 'is' )
		) {
			return substr( $token, 0, -1 );
		}

		return $token;
	}

	/**
	 * Reduce a doubled final consonant after removing ed or ing.
	 *
	 * @param string $token Stemmed token.
	 * @return string
	 */
	private static function remove_doubled_ending( $token ) {
		$length = strlen( $token );
		if ( $length > 2 && $token[ $length - 1 ] === $token[ $length - 2 ] ) {
			return substr( $token, 0, -1 );
		}

		return $token;
	}

	/**
	 * Count Unicode characters, with a safe fallback.
	 *
	 * @param string $text UTF-8 text.
	 * @return int
	 */
	private static function string_length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text, 'UTF-8' );
		}

		$length = preg_match_all( '/./us', $text, $matches );
		return false === $length ? strlen( $text ) : $length;
	}
}
