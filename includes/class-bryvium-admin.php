<?php
/**
 * Plugin admin menu and settings screen.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings screen and plugin-row link.
 */
final class Bryvium_Admin {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'bryvium-wfc-settings';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( 'Bryvium_Settings', 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_filter( 'plugin_action_links_' . BRYVIUM_WFC_BASENAME, array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * Add a settings submenu under the WordPress Settings menu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			__( 'Bryvium WP File Chat Settings', 'bryvium-wp-file-chat' ),
			__( 'Bryvium File Chat', 'bryvium-wp-file-chat' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings screen for authorized administrators.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access these settings.', 'bryvium-wp-file-chat' ) );
		}

		require BRYVIUM_WFC_PATH . 'admin/views/page-settings.php';
	}

	/**
	 * Add a direct settings link to the Plugins list.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public static function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'bryvium-wp-file-chat' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}
}
