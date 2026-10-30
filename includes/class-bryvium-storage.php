<?php
/**
 * Protected JSON storage for knowledge entries.
 *
 * @package Bryvium_WP_File_Chat
 */

// Native file APIs are required for flock() and same-directory atomic rename.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.rename_rename

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's knowledge file.
 */
final class Bryvium_Storage {

	/**
	 * Current on-disk schema version.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Ensure the protected storage directory and initial data file exist.
	 *
	 * @return true|WP_Error
	 */
	public static function setup() {
		$paths = self::get_paths();
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		if ( ! wp_mkdir_p( $paths['directory'] ) ) {
			return self::error( 'directory_unavailable', __( 'The knowledge storage directory could not be created.', 'bryvium-wp-file-chat' ) );
		}

		$protection = self::write_protection_files( $paths );
		if ( is_wp_error( $protection ) ) {
			return $protection;
		}

		$lock = self::acquire_lock( $paths, LOCK_EX );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			if ( ! file_exists( $paths['data'] ) ) {
				$write_result = self::write_document( $paths, self::empty_document() );
				if ( is_wp_error( $write_result ) ) {
					return $write_result;
				}
			}
		} finally {
			self::release_lock( $lock );
		}

		return true;
	}

	/**
	 * Get all knowledge entries.
	 *
	 * @return array|WP_Error
	 */
	public static function get_all() {
		$document = self::read_document();
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return $document['entries'];
	}

	/**
	 * Get one knowledge entry by id.
	 *
	 * @param string $id Entry id.
	 * @return array|null|WP_Error
	 */
	public static function get_one( $id ) {
		$id = self::validate_id( $id );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$entries = self::get_all();
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		foreach ( $entries as $entry ) {
			if ( $entry['id'] === $id ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Add a knowledge entry.
	 *
	 * @param array $input Entry fields.
	 * @return array|WP_Error
	 */
	public static function add( $input ) {
		$entry = self::sanitize_entry( $input );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		$paths = self::prepare_storage();
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$lock = self::acquire_lock( $paths, LOCK_EX );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			$document = self::read_document_unlocked( $paths );
			if ( is_wp_error( $document ) ) {
				return $document;
			}

			$entry['id']           = self::generate_id( $document['entries'] );
			$entry['updated_at']   = time();
			$document['entries'][] = $entry;

			$write_result = self::write_document( $paths, $document );
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}
		} finally {
			self::release_lock( $lock );
		}

		return $entry;
	}

	/**
	 * Update a knowledge entry.
	 *
	 * @param string $id Entry id.
	 * @param array  $input Fields to update.
	 * @return array|WP_Error
	 */
	public static function update( $id, $input ) {
		$id = self::validate_id( $id );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! is_array( $input ) ) {
			return self::error( 'invalid_entry', __( 'The knowledge entry must be an object.', 'bryvium-wp-file-chat' ), 400 );
		}

		$paths = self::prepare_storage();
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$lock = self::acquire_lock( $paths, LOCK_EX );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			$document = self::read_document_unlocked( $paths );
			if ( is_wp_error( $document ) ) {
				return $document;
			}

			$entry_index = self::find_entry_index( $document['entries'], $id );
			if ( false === $entry_index ) {
				return self::error( 'entry_not_found', __( 'The requested knowledge entry was not found.', 'bryvium-wp-file-chat' ), 404 );
			}

			$merged_input = array_merge( $document['entries'][ $entry_index ], $input );
			unset( $merged_input['id'], $merged_input['updated_at'] );

			$entry = self::sanitize_entry( $merged_input, $document['entries'][ $entry_index ] );
			if ( is_wp_error( $entry ) ) {
				return $entry;
			}

			$entry['id']                         = $id;
			$entry['updated_at']                 = time();
			$document['entries'][ $entry_index ] = $entry;

			$write_result = self::write_document( $paths, $document );
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}
		} finally {
			self::release_lock( $lock );
		}

		return $entry;
	}

	/**
	 * Delete a knowledge entry.
	 *
	 * @param string $id Entry id.
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		$id = self::validate_id( $id );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$paths = self::prepare_storage();
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$lock = self::acquire_lock( $paths, LOCK_EX );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			$document = self::read_document_unlocked( $paths );
			if ( is_wp_error( $document ) ) {
				return $document;
			}

			$entry_index = self::find_entry_index( $document['entries'], $id );
			if ( false === $entry_index ) {
				return self::error( 'entry_not_found', __( 'The requested knowledge entry was not found.', 'bryvium-wp-file-chat' ), 404 );
			}

			array_splice( $document['entries'], $entry_index, 1 );
			$write_result = self::write_document( $paths, $document );
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}
		} finally {
			self::release_lock( $lock );
		}

		return true;
	}

	/**
	 * Count knowledge entries.
	 *
	 * @return int|WP_Error
	 */
	public static function count() {
		$entries = self::get_all();
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		return count( $entries );
	}

	/**
	 * Ensure storage exists and return its paths.
	 *
	 * @return array|WP_Error
	 */
	private static function prepare_storage() {
		$setup = self::setup();
		if ( is_wp_error( $setup ) ) {
			return $setup;
		}

		return self::get_paths();
	}

	/**
	 * Return paths for the data directory and files.
	 *
	 * @return array|WP_Error
	 */
	private static function get_paths() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return self::error( 'uploads_unavailable', __( 'The WordPress uploads directory is unavailable.', 'bryvium-wp-file-chat' ) );
		}

		$directory = trailingslashit( $uploads['basedir'] ) . 'bryvium-chat';

		return array(
			'directory' => $directory,
			'data'      => trailingslashit( $directory ) . 'knowledge.json',
			'lock'      => trailingslashit( $directory ) . '.knowledge.lock',
			'htaccess'  => trailingslashit( $directory ) . '.htaccess',
			'index'     => trailingslashit( $directory ) . 'index.php',
		);
	}

	/**
	 * Write or repair directory protection files.
	 *
	 * @param array $paths Storage paths.
	 * @return true|WP_Error
	 */
	private static function write_protection_files( $paths ) {
		$files = array(
			$paths['htaccess'] => "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			$paths['index']    => "<?php\n// Silence is golden.\n",
		);

		foreach ( $files as $path => $contents ) {
			$current = is_readable( $path ) ? file_get_contents( $path ) : false;
			if ( $current === $contents ) {
				continue;
			}

			$write_result = self::write_file_atomically(
				$paths['directory'],
				$path,
				$contents,
				'protection_file_failed',
				__( 'A storage directory protection file could not be written.', 'bryvium-wp-file-chat' )
			);
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}
		}

		return true;
	}

	/**
	 * Acquire a lock on the stable lock file.
	 *
	 * @param array $paths Storage paths.
	 * @param int   $mode Lock mode.
	 * @return resource|WP_Error
	 */
	private static function acquire_lock( $paths, $mode ) {
		$handle = @fopen( $paths['lock'], 'c' );
		if ( false === $handle ) {
			return self::error( 'lock_unavailable', __( 'The knowledge file could not be locked.', 'bryvium-wp-file-chat' ) );
		}

		if ( ! flock( $handle, $mode ) ) {
			fclose( $handle );
			return self::error( 'lock_unavailable', __( 'The knowledge file could not be locked.', 'bryvium-wp-file-chat' ) );
		}

		return $handle;
	}

	/**
	 * Release a file lock.
	 *
	 * @param resource $handle Locked file handle.
	 * @return void
	 */
	private static function release_lock( $handle ) {
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}

	/**
	 * Read a document under a shared lock.
	 *
	 * @return array|WP_Error
	 */
	private static function read_document() {
		$paths = self::prepare_storage();
		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$lock = self::acquire_lock( $paths, LOCK_SH );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			return self::read_document_unlocked( $paths );
		} finally {
			self::release_lock( $lock );
		}
	}

	/**
	 * Read and validate a document while the caller holds a lock.
	 *
	 * @param array $paths Storage paths.
	 * @return array|WP_Error
	 */
	private static function read_document_unlocked( $paths ) {
		if ( ! is_file( $paths['data'] ) ) {
			return self::error( 'file_missing', __( 'The knowledge file is missing.', 'bryvium-wp-file-chat' ) );
		}

		if ( ! is_readable( $paths['data'] ) ) {
			return self::error( 'file_unreadable', __( 'The knowledge file cannot be read.', 'bryvium-wp-file-chat' ) );
		}

		$contents = @file_get_contents( $paths['data'] );
		if ( false === $contents ) {
			return self::error( 'file_unreadable', __( 'The knowledge file cannot be read.', 'bryvium-wp-file-chat' ) );
		}

		$document = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! self::is_valid_document( $document ) ) {
			return self::error( 'invalid_json', __( 'The knowledge file contains invalid or unsupported data.', 'bryvium-wp-file-chat' ) );
		}

		return $document;
	}

	/**
	 * Check the persisted document and entry shapes.
	 *
	 * @param mixed $document Decoded JSON.
	 * @return bool
	 */
	private static function is_valid_document( $document ) {
		if ( ! is_array( $document )
			|| ! isset( $document['schema_version'], $document['entries'] )
			|| self::SCHEMA_VERSION !== $document['schema_version']
			|| ! is_array( $document['entries'] )
			|| ! array_is_list( $document['entries'] )
		) {
			return false;
		}

		$known_ids = array();
		foreach ( $document['entries'] as $entry ) {
			if ( ! is_array( $entry )
				|| ! isset( $entry['id'], $entry['title'], $entry['answer'], $entry['keywords'], $entry['category'], $entry['enabled'], $entry['updated_at'] )
				|| ! is_string( $entry['id'] )
				|| ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $entry['id'] )
				|| isset( $known_ids[ $entry['id'] ] )
				|| ! is_string( $entry['title'] )
				|| '' === $entry['title']
				|| self::string_length( $entry['title'] ) > 200
				|| ! is_string( $entry['answer'] )
				|| '' === $entry['answer']
				|| self::string_length( $entry['answer'] ) > 4000
				|| ! is_array( $entry['keywords'] )
				|| ! array_is_list( $entry['keywords'] )
				|| count( $entry['keywords'] ) > 20
				|| ! is_string( $entry['category'] )
				|| self::string_length( $entry['category'] ) > 60
				|| ! is_bool( $entry['enabled'] )
				|| ! is_int( $entry['updated_at'] )
				|| $entry['updated_at'] < 0
			) {
				return false;
			}

			$known_ids[ $entry['id'] ] = true;
			foreach ( $entry['keywords'] as $keyword ) {
				if ( ! is_string( $keyword ) || self::string_length( $keyword ) > 50 ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Write a document to a same-directory temporary file and rename it.
	 *
	 * @param array $paths Storage paths.
	 * @param array $document Data to persist.
	 * @return true|WP_Error
	 */
	private static function write_document( $paths, $document ) {
		$encoded = wp_json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			return self::error( 'encode_failed', __( 'The knowledge data could not be encoded.', 'bryvium-wp-file-chat' ) );
		}

		return self::write_file_atomically(
			$paths['directory'],
			$paths['data'],
			$encoded,
			'write_failed',
			__( 'The knowledge data could not be written.', 'bryvium-wp-file-chat' )
		);
	}

	/**
	 * Replace a file atomically using a temporary file in the same directory.
	 *
	 * @param string $directory Target directory.
	 * @param string $target Target file.
	 * @param string $contents File contents.
	 * @param string $error_code Error code suffix.
	 * @param string $error_message Error message.
	 * @return true|WP_Error
	 */
	private static function write_file_atomically( $directory, $target, $contents, $error_code, $error_message ) {
		$temp_file = @tempnam( $directory, '.bryvium-' );
		if ( false === $temp_file ) {
			return self::error( $error_code, $error_message );
		}

		$bytes_written = @file_put_contents( $temp_file, $contents );
		if ( false === $bytes_written || strlen( $contents ) !== $bytes_written ) {
			@unlink( $temp_file );
			return self::error( $error_code, $error_message );
		}

		@chmod( $temp_file, 0644 );
		if ( ! @rename( $temp_file, $target ) ) {
			@unlink( $temp_file );
			return self::error( $error_code, $error_message );
		}

		return true;
	}

	/**
	 * Return a valid empty document.
	 *
	 * @return array
	 */
	private static function empty_document() {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'entries'        => array(),
		);
	}

	/**
	 * Sanitize and validate entry fields.
	 *
	 * @param mixed $input Entry input.
	 * @param array $existing Existing entry for partial updates.
	 * @return array|WP_Error
	 */
	private static function sanitize_entry( $input, $existing = array() ) {
		if ( ! is_array( $input ) ) {
			return self::error( 'invalid_entry', __( 'The knowledge entry must be an object.', 'bryvium-wp-file-chat' ), 400 );
		}

		$title  = isset( $input['title'] ) && is_string( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$answer = isset( $input['answer'] ) && is_string( $input['answer'] ) ? sanitize_textarea_field( $input['answer'] ) : '';
		if ( '' === $title || '' === $answer ) {
			return self::error( 'required_fields_missing', __( 'A title and answer are required for each knowledge entry.', 'bryvium-wp-file-chat' ), 400 );
		}

		if ( self::string_length( $title ) > 200 || self::string_length( $answer ) > 4000 ) {
			return self::error( 'field_too_long', __( 'The title or answer exceeds its maximum length.', 'bryvium-wp-file-chat' ), 400 );
		}

		$raw_keywords = array_key_exists( 'keywords', $input ) ? $input['keywords'] : array();
		if ( ! is_array( $raw_keywords ) || count( $raw_keywords ) > 20 ) {
			return self::error( 'invalid_keywords', __( 'Keywords must be a list of no more than 20 items.', 'bryvium-wp-file-chat' ), 400 );
		}
		if ( ! array_is_list( $raw_keywords ) ) {
			return self::error( 'invalid_keywords', __( 'Keywords must be provided as a list.', 'bryvium-wp-file-chat' ), 400 );
		}

		$keywords = array();
		foreach ( $raw_keywords as $keyword ) {
			if ( ! is_string( $keyword ) ) {
				return self::error( 'invalid_keywords', __( 'Each keyword must be text.', 'bryvium-wp-file-chat' ), 400 );
			}

			$keyword = sanitize_text_field( $keyword );
			if ( '' === $keyword ) {
				continue;
			}
			if ( self::string_length( $keyword ) > 50 ) {
				return self::error( 'keyword_too_long', __( 'Each keyword must be no more than 50 characters.', 'bryvium-wp-file-chat' ), 400 );
			}

			$keywords[] = $keyword;
		}

		if ( array_key_exists( 'category', $input ) && ! is_string( $input['category'] ) ) {
			return self::error( 'invalid_category', __( 'The category must be text.', 'bryvium-wp-file-chat' ), 400 );
		}

		$category = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
		if ( self::string_length( $category ) > 60 ) {
			return self::error( 'category_too_long', __( 'The category must be no more than 60 characters.', 'bryvium-wp-file-chat' ), 400 );
		}

		$enabled = array_key_exists( 'enabled', $input )
			? self::parse_boolean( $input['enabled'] )
			: ( isset( $existing['enabled'] ) ? $existing['enabled'] : true );
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		return array(
			'title'    => $title,
			'answer'   => $answer,
			'keywords' => $keywords,
			'category' => $category,
			'enabled'  => $enabled,
		);
	}

	/**
	 * Parse supported boolean values without PHP's loose truthiness.
	 *
	 * @param mixed $value Boolean input.
	 * @return bool|WP_Error
	 */
	private static function parse_boolean( $value ) {
		if ( true === $value || 1 === $value || '1' === $value || 'true' === $value ) {
			return true;
		}
		if ( false === $value || 0 === $value || '0' === $value || 'false' === $value ) {
			return false;
		}

		return self::error( 'invalid_enabled', __( 'The enabled value must be a boolean.', 'bryvium-wp-file-chat' ), 400 );
	}

	/**
	 * Validate an entry identifier.
	 *
	 * @param mixed $id Entry id.
	 * @return string|WP_Error
	 */
	private static function validate_id( $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $id ) ) {
			return self::error( 'invalid_id', __( 'The knowledge entry id is invalid.', 'bryvium-wp-file-chat' ), 400 );
		}

		return $id;
	}

	/**
	 * Generate a unique entry id.
	 *
	 * @param array $entries Current entries.
	 * @return string
	 */
	private static function generate_id( $entries ) {
		do {
			$id = 'entry_' . str_replace( '-', '', wp_generate_uuid4() );
		} while ( false !== self::find_entry_index( $entries, $id ) );

		return $id;
	}

	/**
	 * Find an entry index by id.
	 *
	 * @param array  $entries Entries to search.
	 * @param string $id Entry id.
	 * @return int|false
	 */
	private static function find_entry_index( $entries, $id ) {
		foreach ( $entries as $index => $entry ) {
			if ( $entry['id'] === $id ) {
				return $index;
			}
		}

		return false;
	}

	/**
	 * Count Unicode characters, with a UTF-8 regex fallback.
	 *
	 * @param string $value Text to count.
	 * @return int
	 */
	private static function string_length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}

		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}

	/**
	 * Create a WP_Error with a stable plugin-specific code.
	 *
	 * @param string $code Error code suffix.
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status = 500 ) {
		return new WP_Error(
			'bryvium_wfc_storage_' . $code,
			$message,
			array( 'status' => $status )
		);
	}
}
