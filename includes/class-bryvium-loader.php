<?php
/**
 * Plugin lifecycle and bootstrap.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation, deactivation and runtime initialisation.
 */
final class Bryvium_Loader {

	/**
	 * Check PHP and WordPress minimum versions.
	 *
	 * @return bool
	 */
	public static function requirements_met() {
		global $wp_version;

		return version_compare( PHP_VERSION, BRYVIUM_WFC_MIN_PHP, '>=' )
			&& version_compare( $wp_version, BRYVIUM_WFC_MIN_WP, '>=' );
	}

	/**
	 * Human-readable requirements error.
	 *
	 * @return string
	 */
	public static function requirements_message() {
		return sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version. */
			__( 'Bryvium WP File Chat requires PHP %1$s+ and WordPress %2$s+.', 'bryvium-wp-file-chat' ),
			BRYVIUM_WFC_MIN_PHP,
			BRYVIUM_WFC_MIN_WP
		);
	}

	/**
	 * Activation: verify requirements, record installed version.
	 * Initialize the storage directory before recording activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! self::requirements_met() ) {
			deactivate_plugins( BRYVIUM_WFC_BASENAME );
			wp_die(
				esc_html( self::requirements_message() ),
				esc_html__( 'Plugin activation error', 'bryvium-wp-file-chat' ),
				array( 'back_link' => true )
			);
		}

		$storage_result = Bryvium_Storage::setup();
		if ( is_wp_error( $storage_result ) ) {
			deactivate_plugins( BRYVIUM_WFC_BASENAME );
			wp_die(
				esc_html( $storage_result->get_error_message() ),
				esc_html__( 'Plugin activation error', 'bryvium-wp-file-chat' ),
				array( 'back_link' => true )
			);
		}

		update_option( 'bryvium_wfc_version', BRYVIUM_WFC_VERSION, false );
	}

	/**
	 * Deactivation: non-destructive. User data (knowledge.json, settings)
	 * is preserved; removal happens only in uninstall.php (D19).
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires after Bryvium WP File Chat is deactivated.
		 * Later days hook transient cleanup here.
		 */
		do_action( 'bryvium_wfc_deactivated' );
	}

	/**
	 * Runtime init on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::requirements_met() ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_requirements_notice' ) );
			return;
		}

		Bryvium_Rest::register();
		Bryvium_Rest_Admin::register();
		Bryvium_Admin::register();
		Bryvium_Widget::register();

		/**
		 * Fires when the plugin is ready. Modules register hooks here
		 * (storage D3, REST D6/D10, admin D8/D9, widget D12).
		 */
		do_action( 'bryvium_wfc_loaded' );
	}

	/**
	 * Admin notice shown if requirements fail after activation
	 * (e.g. host downgrades PHP).
	 *
	 * @return void
	 */
	public static function render_requirements_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( self::requirements_message() )
		);
	}
}
