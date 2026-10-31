<?php
/**
 * Admin knowledge-entry REST endpoints.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers capability- and nonce-protected knowledge CRUD routes.
 */
final class Bryvium_Rest_Admin {

	/**
	 * Maximum number of entries returned by one request.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Register admin routes with the REST API.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register knowledge-entry routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'bryvium/v1',
			'/entries',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_entries' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'category' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => self::MAX_PER_PAGE,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_entry' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => self::entry_args( true ),
				),
				'schema' => array( __CLASS__, 'get_item_schema' ),
			)
		);

		register_rest_route(
			'bryvium/v1',
			'/entries/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'update_entry' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array_merge(
						array(
							'id' => array(
								'required' => true,
								'type'     => 'string',
							),
						),
						self::entry_args( true )
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_entry' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				'schema' => array( __CLASS__, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Require an authenticated administrator and a valid REST nonce.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public static function check_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'bryvium_wfc_admin_unauthenticated',
				__( 'You must be logged in to manage knowledge entries.', 'bryvium-wp-file-chat' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'bryvium_wfc_admin_forbidden',
				__( 'You do not have permission to manage knowledge entries.', 'bryvium-wp-file-chat' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'bryvium_wfc_admin_invalid_nonce',
				__( 'Request verification failed. Refresh the page and try again.', 'bryvium-wp-file-chat' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * List filtered entries and return pagination metadata.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_entries( $request ) {
		$entries = Bryvium_Storage::get_all();
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		$search   = strtolower( trim( (string) $request->get_param( 'search' ) ) );
		$category = strtolower( trim( (string) $request->get_param( 'category' ) ) );
		$filtered = array_values(
			array_filter(
				$entries,
				static function ( $entry ) use ( $search, $category ) {
					if ( '' !== $category && strtolower( $entry['category'] ) !== $category ) {
						return false;
					}

					if ( '' === $search ) {
						return true;
					}

					$haystack = strtolower(
						$entry['title'] . ' ' .
						$entry['answer'] . ' ' .
						implode( ' ', $entry['keywords'] ) . ' ' .
						$entry['category']
					);

					return false !== strpos( $haystack, $search );
				}
			)
		);

		$page        = max( 1, (int) $request->get_param( 'page' ) );
		$per_page    = min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$total       = count( $filtered );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );
		$page_items  = array_slice( $filtered, ( $page - 1 ) * $per_page, $per_page );

		$response = new WP_REST_Response(
			array(
				'entries'     => $page_items,
				'total'       => $total,
				'total_pages' => $total_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			),
			200
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Create a knowledge entry.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_entry( $request ) {
		$input = self::get_entry_input( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}

		$entry = Bryvium_Storage::add( $input );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		return new WP_REST_Response( array( 'entry' => $entry ), 201 );
	}

	/**
	 * Update a knowledge entry.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_entry( $request ) {
		$input = self::get_entry_input( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}

		$entry = Bryvium_Storage::update( (string) $request->get_param( 'id' ), $input );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		return new WP_REST_Response( array( 'entry' => $entry ), 200 );
	}

	/**
	 * Delete a knowledge entry.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_entry( $request ) {
		$id     = (string) $request->get_param( 'id' );
		$result = Bryvium_Storage::delete( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			),
			200
		);
	}

	/**
	 * Return the sanitized, schema-validated entry fields.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array|WP_Error
	 */
	private static function get_entry_input( $request ) {
		$input = array();
		foreach ( array( 'title', 'answer', 'keywords', 'category', 'enabled' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$input[ $key ] = $value;
			}
		}

		if ( ! array_key_exists( 'title', $input ) || ! array_key_exists( 'answer', $input ) ) {
			return new WP_Error(
				'bryvium_wfc_rest_required_fields',
				__( 'A title and answer are required for each knowledge entry.', 'bryvium-wp-file-chat' ),
				array(
					'status' => 400,
					'fields' => array( 'title', 'answer' ),
				)
			);
		}

		return $input;
	}

	/**
	 * Define the entry schema and request validation.
	 *
	 * @param bool $required Whether title and answer are required.
	 * @return array
	 */
	private static function entry_args( $required ) {
		return array(
			'title'    => array(
				'required'          => $required,
				'type'              => 'string',
				'minLength'         => 1,
				'maxLength'         => 200,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'answer'   => array(
				'required'          => $required,
				'type'              => 'string',
				'minLength'         => 1,
				'maxLength'         => 4000,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'keywords' => array(
				'type'     => 'array',
				'default'  => array(),
				'maxItems' => 20,
				'items'    => array(
					'type'      => 'string',
					'maxLength' => 50,
				),
			),
			'category' => array(
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 60,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'enabled'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
	}

	/**
	 * Describe the knowledge entry schema.
	 *
	 * @return array
	 */
	public static function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bryvium_wfc_entry',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'title'      => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'answer'     => array(
					'type'      => 'string',
					'maxLength' => 4000,
				),
				'keywords'   => array(
					'type'     => 'array',
					'maxItems' => 20,
					'items'    => array(
						'type'      => 'string',
						'maxLength' => 50,
					),
				),
				'category'   => array(
					'type'      => 'string',
					'maxLength' => 60,
				),
				'enabled'    => array(
					'type' => 'boolean',
				),
				'updated_at' => array(
					'type'     => 'integer',
					'readonly' => true,
				),
			),
		);
	}
}
