<?php
/**
 * Public chat REST endpoint.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the public chat route and validates requests.
 */
final class Bryvium_Rest {

	/**
	 * Maximum accepted question length.
	 *
	 * @var int
	 */
	const MAX_QUESTION_LENGTH = 300;

	/**
	 * Register this module with the WordPress REST API.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register chat route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'bryvium/v1',
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_chat' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'question' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( __CLASS__, 'sanitize_question' ),
						'validate_callback' => array( __CLASS__, 'validate_question' ),
					),
				),
			)
		);
	}

	/**
	 * Check the WordPress REST nonce sent in X-WP-Nonce.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public static function check_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( is_string( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		return new WP_Error(
			'bryvium_wfc_rest_invalid_nonce',
			__( 'Request verification failed. Refresh the page and try again.', 'bryvium-wp-file-chat' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Sanitize a chat question.
	 *
	 * @param mixed $question Raw question.
	 * @return string
	 */
	public static function sanitize_question( $question ) {
		if ( ! is_string( $question ) ) {
			return '';
		}

		return sanitize_textarea_field( $question );
	}

	/**
	 * Validate that a question contains text within the allowed length.
	 *
	 * @param mixed           $question Sanitized question.
	 * @param WP_REST_Request $request REST request.
	 * @param string          $key Argument name.
	 * @return bool|WP_Error
	 */
	public static function validate_question( $question, $request, $key ) {
		if ( ! ( $request instanceof WP_REST_Request ) || 'question' !== $key ) {
			return new WP_Error(
				'bryvium_wfc_rest_invalid_argument',
				__( 'The request contains an invalid question.', 'bryvium-wp-file-chat' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_string( $question ) || '' === trim( $question ) ) {
			return new WP_Error(
				'bryvium_wfc_rest_empty_question',
				__( 'Enter a question before sending.', 'bryvium-wp-file-chat' ),
				array( 'status' => 400 )
			);
		}

		if ( self::string_length( $question ) > self::MAX_QUESTION_LENGTH ) {
			return new WP_Error(
				'bryvium_wfc_rest_question_too_long',
				__( 'Your question must be no more than 300 characters.', 'bryvium-wp-file-chat' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Find an answer for a validated question.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_chat( $request ) {
		$question = $request->get_param( 'question' );
		if ( ! is_string( $question ) || '' === trim( $question ) || self::string_length( $question ) > self::MAX_QUESTION_LENGTH ) {
			return new WP_Error(
				'bryvium_wfc_rest_invalid_question',
				__( 'The question is invalid. Enter up to 300 characters and try again.', 'bryvium-wp-file-chat' ),
				array( 'status' => 400 )
			);
		}

		$entries = Bryvium_Storage::get_all();
		if ( is_wp_error( $entries ) ) {
			return new WP_Error(
				'bryvium_wfc_rest_storage_unavailable',
				__( 'The chat service is temporarily unavailable.', 'bryvium-wp-file-chat' ),
				array( 'status' => 500 )
			);
		}

		$result = Bryvium_Search::search( $question, $entries );
		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'bryvium_wfc_rest_search_failed',
				__( 'The chat service could not process your question.', 'bryvium-wp-file-chat' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'status'      => $result['status'],
				'answer'      => $result['answer'],
				'confidence'  => $result['confidence'],
				'suggestions' => $result['suggestions'],
			),
			200
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
