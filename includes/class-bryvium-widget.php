<?php
/**
 * Frontend widget asset loading and mount point.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues and mounts the frontend chat widget when enabled.
 */
final class Bryvium_Widget {

	/**
	 * Whether the mount element has already been printed.
	 *
	 * @var bool
	 */
	private static $mount_printed = false;

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_mount' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_build_notice' ) );
	}

	/**
	 * Enqueue built assets only when auto display is enabled.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}

		$script_path = BRYVIUM_WFC_PATH . 'build/bryvium-chat.js';
		$style_path  = BRYVIUM_WFC_PATH . 'build/bryvium-chat.css';
		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) ) {
			return;
		}

		$settings = Bryvium_Settings::get();
		wp_enqueue_style(
			'bryvium-wfc-widget',
			BRYVIUM_WFC_URL . 'build/bryvium-chat.css',
			array(),
			(string) filemtime( $style_path )
		);
		wp_enqueue_script(
			'bryvium-wfc-widget',
			BRYVIUM_WFC_URL . 'build/bryvium-chat.js',
			array(),
			(string) filemtime( $script_path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'bryvium-wfc-widget',
			'BryviumChatConfig',
			array(
				'restUrl'      => esc_url_raw( rest_url() ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'widgetTitle'  => $settings['widget_title'],
				'greeting'     => $settings['greeting'],
				'placeholder'  => $settings['placeholder'],
				'position'     => $settings['position'],
				'saveHistory'  => (bool) $settings['save_history'],
				'primaryColor' => $settings['primary_color'],
			)
		);
	}

	/**
	 * Print exactly one widget mount element in the frontend footer.
	 *
	 * @return void
	 */
	public static function render_mount() {
		if ( is_admin() || ! self::is_enabled() || self::$mount_printed ) {
			return;
		}

		$script_path = BRYVIUM_WFC_PATH . 'build/bryvium-chat.js';
		$style_path  = BRYVIUM_WFC_PATH . 'build/bryvium-chat.css';
		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) ) {
			return;
		}

		self::$mount_printed = true;
		echo '<div id="bwfc-root"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Show administrators a notice when a production build is unavailable.
	 *
	 * @return void
	 */
	public static function render_build_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_enabled() ) {
			return;
		}

		$script_path = BRYVIUM_WFC_PATH . 'build/bryvium-chat.js';
		$style_path  = BRYVIUM_WFC_PATH . 'build/bryvium-chat.css';
		if ( is_readable( $script_path ) && is_readable( $style_path ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Bryvium WP File Chat is enabled, but its frontend build files are missing. Run “npm run build” to generate the widget assets.', 'bryvium-wp-file-chat' )
		);
	}

	/**
	 * Check whether the automatic frontend widget is enabled.
	 *
	 * @return bool
	 */
	private static function is_enabled() {
		$settings = Bryvium_Settings::get();
		return ! empty( $settings['auto_display'] );
	}
}
