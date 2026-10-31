<?php
/**
 * Validated knowledge document import and export.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates imported JSON before passing it to protected storage.
 */
final class Bryvium_Importer {

	/**
	 * Maximum JSON import size in bytes.
	 *
	 * @var int
	 */
	const MAX_IMPORT_BYTES = 1048576;

	/**
	 * Build an export document from the current knowledge entries.
	 *
	 * @return array|WP_Error
	 */
	public static function export_document() {
		$entries = Bryvium_Storage::get_all();
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		return array(
			'schema_version' => Bryvium_Storage::SCHEMA_VERSION,
			'entries'        => $entries,
		);
	}

	/**
	 * Validate and import a knowledge JSON document.
	 *
	 * @param string $contents JSON document.
	 * @param string $mode Import mode.
	 * @return array|WP_Error
	 */
	public static function import( $contents, $mode ) {
		if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
			return self::error( 'empty_import', __( 'Choose a JSON file or paste a knowledge document first.', 'bryvium-wp-file-chat' ) );
		}

		if ( strlen( $contents ) > self::MAX_IMPORT_BYTES ) {
			return self::error( 'import_too_large', __( 'The import file must be no larger than 1 MB.', 'bryvium-wp-file-chat' ), 413 );
		}

		if ( ! in_array( $mode, array( 'merge', 'replace' ), true ) ) {
			return self::error( 'invalid_import_mode', __( 'Choose either merge or replace mode.', 'bryvium-wp-file-chat' ) );
		}

		$document = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return self::error( 'invalid_import_json', __( 'The import is not valid JSON. Check the file and try again.', 'bryvium-wp-file-chat' ) );
		}

		$entries = self::validate_document( $document );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		return Bryvium_Storage::import_entries( $entries, $mode );
	}

	/**
	 * Validate a full document and normalize its entry text fields.
	 *
	 * @param mixed $document Decoded JSON.
	 * @return array|WP_Error
	 */
	private static function validate_document( $document ) {
		if ( ! is_array( $document )
			|| 2 !== count( $document )
			|| ! array_key_exists( 'schema_version', $document )
			|| ! array_key_exists( 'entries', $document )
			|| Bryvium_Storage::SCHEMA_VERSION !== $document['schema_version']
			|| ! is_array( $document['entries'] )
			|| ! array_is_list( $document['entries'] )
		) {
			return self::error(
				'invalid_import_structure',
				__( 'The JSON must contain a supported schema_version and an entries list.', 'bryvium-wp-file-chat' )
			);
		}

		$required_keys = array( 'id', 'title', 'answer', 'keywords', 'category', 'enabled', 'updated_at' );
		$known_ids     = array();
		$entries       = array();
		$errors        = array();

		foreach ( $document['entries'] as $index => $entry ) {
			$entry_number = $index + 1;
			if ( ! is_array( $entry )
				|| count( $entry ) !== count( $required_keys )
				|| array_diff( $required_keys, array_keys( $entry ) )
				|| array_diff( array_keys( $entry ), $required_keys )
			) {
				$errors[] = sprintf(
					/* translators: %d: entry number in the imported file. */
					__( 'Entry %d must contain exactly the supported entry fields.', 'bryvium-wp-file-chat' ),
					$entry_number
				);
				continue;
			}

			$entry = self::sanitize_entry( $entry, $entry_number, $errors );
			if ( is_wp_error( $entry ) ) {
				continue;
			}

			if ( isset( $known_ids[ $entry['id'] ] ) ) {
				$errors[] = sprintf(
					/* translators: %d: entry number in the imported file. */
					__( 'Entry %d has a duplicate id.', 'bryvium-wp-file-chat' ),
					$entry_number
				);
				continue;
			}

			$known_ids[ $entry['id'] ] = true;
			$entries[]                 = $entry;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'bryvium_wfc_import_invalid_entries',
				__( 'The import contains invalid entries. No data was changed.', 'bryvium-wp-file-chat' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		return $entries;
	}

	/**
	 * Validate and normalize a single imported entry.
	 *
	 * @param array $entry Raw entry.
	 * @param int   $entry_number Entry number.
	 * @param array $errors Collected validation errors.
	 * @return array|WP_Error
	 */
	private static function sanitize_entry( $entry, $entry_number, &$errors ) {
		$valid = is_string( $entry['id'] )
			&& 1 === preg_match( '/^[a-zA-Z0-9_-]+$/D', $entry['id'] )
			&& is_string( $entry['title'] )
			&& is_string( $entry['answer'] )
			&& is_array( $entry['keywords'] )
			&& array_is_list( $entry['keywords'] )
			&& is_string( $entry['category'] )
			&& is_bool( $entry['enabled'] )
			&& is_int( $entry['updated_at'] )
			&& $entry['updated_at'] >= 0
			&& count( $entry['keywords'] ) <= 20;

		if ( ! $valid ) {
			$errors[] = sprintf(
				/* translators: %d: entry number in the imported file. */
				__( 'Entry %d has an invalid field type, id, timestamp, or keyword list.', 'bryvium-wp-file-chat' ),
				$entry_number
			);
			return new WP_Error( 'bryvium_wfc_import_invalid_entry' );
		}

		$title    = sanitize_text_field( $entry['title'] );
		$answer   = sanitize_textarea_field( $entry['answer'] );
		$category = sanitize_text_field( $entry['category'] );
		if ( '' === trim( $title )
			|| '' === trim( $answer )
			|| self::string_length( $title ) > 200
			|| self::string_length( $answer ) > 4000
			|| self::string_length( $category ) > 60
		) {
			$errors[] = sprintf(
				/* translators: %d: entry number in the imported file. */
				__( 'Entry %d has an empty or overlong title, answer, or category.', 'bryvium-wp-file-chat' ),
				$entry_number
			);
			return new WP_Error( 'bryvium_wfc_import_invalid_entry' );
		}

		$keywords = array();
		foreach ( $entry['keywords'] as $keyword ) {
			if ( ! is_string( $keyword ) ) {
				$errors[] = sprintf(
					/* translators: %d: entry number in the imported file. */
					__( 'Entry %d contains a keyword that is not text.', 'bryvium-wp-file-chat' ),
					$entry_number
				);
				return new WP_Error( 'bryvium_wfc_import_invalid_entry' );
			}

			$keyword = sanitize_text_field( $keyword );
			if ( self::string_length( $keyword ) > 50 ) {
				$errors[] = sprintf(
					/* translators: %d: entry number in the imported file. */
					__( 'Entry %d contains a keyword longer than 50 characters.', 'bryvium-wp-file-chat' ),
					$entry_number
				);
				return new WP_Error( 'bryvium_wfc_import_invalid_entry' );
			}

			$keywords[] = $keyword;
		}

		return array(
			'id'         => $entry['id'],
			'title'      => $title,
			'answer'     => $answer,
			'keywords'   => $keywords,
			'category'   => $category,
			'enabled'    => $entry['enabled'],
			'updated_at' => $entry['updated_at'],
		);
	}

	/**
	 * Create a plugin-specific import error.
	 *
	 * @param string $code Error code suffix.
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status = 400 ) {
		return new WP_Error(
			'bryvium_wfc_import_' . $code,
			$message,
			array( 'status' => $status )
		);
	}

	/**
	 * Count Unicode characters, with a safe fallback.
	 *
	 * @param string $text Text to count.
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
