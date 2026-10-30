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

			$token = self::stem( $token );
			if ( self::string_length( $token ) >= 2 ) {
				$cleaned[] = $token;
			}
		}

		return $cleaned;
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
