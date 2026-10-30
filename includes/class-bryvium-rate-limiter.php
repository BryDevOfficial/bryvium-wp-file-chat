<?php
/**
 * Transient-based public chat rate limiter.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limits requests per client without persisting raw IP addresses.
 */
final class Bryvium_Rate_Limiter {

	/**
	 * Request window length in seconds.
	 *
	 * @var int
	 */
	const WINDOW_SECONDS = 60;

	/**
	 * Default requests allowed in one window.
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 20;

	/**
	 * Check and record one request.
	 *
	 * @param int|null $limit Maximum requests for the window.
	 * @return true|WP_Error
	 */
	public static function check( $limit = null ) {
		$limit = self::get_limit( $limit );
		$key   = self::get_transient_key();
		$state = get_transient( $key );

		if ( ! is_array( $state )
			|| ! isset( $state['started_at'], $state['count'] )
			|| ! is_int( $state['started_at'] )
			|| ! is_int( $state['count'] )
			|| $state['started_at'] > time()
			|| $state['count'] < 0
		) {
			$state = array(
				'started_at' => time(),
				'count'      => 0,
			);
		}

		$elapsed = time() - $state['started_at'];
		if ( $elapsed >= self::WINDOW_SECONDS ) {
			$state   = array(
				'started_at' => time(),
				'count'      => 0,
			);
			$elapsed = 0;
		}

		if ( $state['count'] >= $limit ) {
			$retry_after = max( 1, self::WINDOW_SECONDS - $elapsed );

			return new WP_Error(
				'bryvium_wfc_rate_limited',
				__( 'Too many chat requests. Please wait before trying again.', 'bryvium-wp-file-chat' ),
				array(
					'status'      => 429,
					'retry_after' => $retry_after,
				)
			);
		}

		++$state['count'];
		if ( ! set_transient( $key, $state, self::WINDOW_SECONDS ) ) {
			return new WP_Error(
				'bryvium_wfc_rate_limit_unavailable',
				__( 'The chat service is temporarily unavailable.', 'bryvium-wp-file-chat' ),
				array( 'status' => 503 )
			);
		}

		return true;
	}

	/**
	 * Resolve a configured limit, falling back safely when unset or invalid.
	 *
	 * @param int|null $limit Optional explicit limit.
	 * @return int
	 */
	private static function get_limit( $limit ) {
		if ( null === $limit ) {
			$settings = get_option( 'bryvium_wfc_settings', array() );
			$limit    = is_array( $settings ) && isset( $settings['rate_limit_per_minute'] )
				? $settings['rate_limit_per_minute']
				: self::DEFAULT_LIMIT;
		}

		if ( ! is_numeric( $limit ) ) {
			return self::DEFAULT_LIMIT;
		}

		return min( 120, max( 1, (int) $limit ) );
	}

	/**
	 * Build a keyed transient identifier from the direct client address.
	 *
	 * Proxy headers are deliberately ignored unless a trusted-proxy policy
	 * is introduced; REMOTE_ADDR is supplied by the web server connection.
	 *
	 * @return string
	 */
	private static function get_transient_key() {
		$client_ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( false === filter_var( $client_ip, FILTER_VALIDATE_IP ) ) {
			$client_ip = 'unknown-client';
		}

		$salt = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		$hash = hash_hmac( 'sha256', $client_ip, $salt );

		return 'bryvium_wfc_rate_' . substr( $hash, 0, 40 );
	}
}
