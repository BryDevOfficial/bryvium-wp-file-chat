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
	 * Knowledge page slug.
	 *
	 * @var string
	 */
	const KNOWLEDGE_PAGE_SLUG = 'bryvium-wfc-knowledge';

	/**
	 * Import and export page slug.
	 *
	 * @var string
	 */
	const TOOLS_PAGE_SLUG = 'bryvium-wfc-tools';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( 'Bryvium_Settings', 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_knowledge_assets' ) );
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

		add_options_page(
			__( 'Bryvium WP File Chat Knowledge', 'bryvium-wp-file-chat' ),
			__( 'Knowledge', 'bryvium-wp-file-chat' ),
			'manage_options',
			self::KNOWLEDGE_PAGE_SLUG,
			array( __CLASS__, 'render_knowledge_page' )
		);

		add_options_page(
			__( 'Bryvium WP File Chat Import and Export', 'bryvium-wp-file-chat' ),
			__( 'Import / Export', 'bryvium-wp-file-chat' ),
			'manage_options',
			self::TOOLS_PAGE_SLUG,
			array( __CLASS__, 'render_tools_page' )
		);
	}

	/**
	 * Enqueue knowledge manager assets only on its admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_knowledge_assets( $hook_suffix ) {
		if ( ! in_array(
			$hook_suffix,
			array(
				'settings_page_' . self::KNOWLEDGE_PAGE_SLUG,
				'settings_page_' . self::TOOLS_PAGE_SLUG,
			),
			true
		) ) {
			return;
		}

		$css_path = BRYVIUM_WFC_PATH . 'admin/css/bryvium-admin.css';
		$js_path  = BRYVIUM_WFC_PATH . 'admin/js/bryvium-admin.js';
		wp_enqueue_style(
			'bryvium-wfc-admin',
			BRYVIUM_WFC_URL . 'admin/css/bryvium-admin.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : BRYVIUM_WFC_VERSION
		);
		wp_enqueue_script(
			'bryvium-wfc-admin',
			BRYVIUM_WFC_URL . 'admin/js/bryvium-admin.js',
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : BRYVIUM_WFC_VERSION,
			true
		);
		wp_localize_script(
			'bryvium-wfc-admin',
			'BryviumAdminConfig',
			array(
				'restUrl'        => rest_url( 'bryvium/v1/entries' ),
				'importUrl'      => rest_url( 'bryvium/v1/import' ),
				'exportUrl'      => rest_url( 'bryvium/v1/export' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'maxImportBytes' => Bryvium_Importer::MAX_IMPORT_BYTES,
				'strings'        => array(
					'loadError'       => __( 'Could not load knowledge entries. Please try again.', 'bryvium-wp-file-chat' ),
					'saveError'       => __( 'Could not save this entry. Please try again.', 'bryvium-wp-file-chat' ),
					'deleteError'     => __( 'Could not delete this entry. Please try again.', 'bryvium-wp-file-chat' ),
					'loading'         => __( 'Loading…', 'bryvium-wp-file-chat' ),
					'addTitle'        => __( 'Add a knowledge entry', 'bryvium-wp-file-chat' ),
					'editTitle'       => __( 'Edit knowledge entry', 'bryvium-wp-file-chat' ),
					'addButton'       => __( 'Add entry', 'bryvium-wp-file-chat' ),
					'saveButton'      => __( 'Save changes', 'bryvium-wp-file-chat' ),
					'editButton'      => __( 'Edit', 'bryvium-wp-file-chat' ),
					'deleteButton'    => __( 'Delete', 'bryvium-wp-file-chat' ),
					'disabled'        => __( 'Disabled', 'bryvium-wp-file-chat' ),
					'enabled'         => __( 'Enabled', 'bryvium-wp-file-chat' ),
					/* translators: 1: current page number, 2: total number of pages. */
					'page'            => __( 'Page %1$d of %2$d', 'bryvium-wp-file-chat' ),
					/* translators: %s: knowledge entry title. */
					'deleteConfirm'   => __( 'Delete %s? This cannot be undone.', 'bryvium-wp-file-chat' ),
					'deleteFallback'  => __( 'this entry', 'bryvium-wp-file-chat' ),
					'added'           => __( 'Knowledge entry added.', 'bryvium-wp-file-chat' ),
					'updated'         => __( 'Knowledge entry updated.', 'bryvium-wp-file-chat' ),
					'deleted'         => __( 'Knowledge entry deleted.', 'bryvium-wp-file-chat' ),
					'keywordLimit'    => __( 'Please use no more than 20 keywords.', 'bryvium-wp-file-chat' ),
					'requiredFields'  => __( 'Enter a question or title and an answer before saving.', 'bryvium-wp-file-chat' ),
					'exportError'     => __( 'The knowledge file could not be exported. Please try again.', 'bryvium-wp-file-chat' ),
					'importSuccess'   => __( 'The knowledge data was imported.', 'bryvium-wp-file-chat' ),
					'importError'     => __( 'The knowledge data could not be imported.', 'bryvium-wp-file-chat' ),
					'importTooLarge'  => __( 'The import file must be no larger than 1 MB.', 'bryvium-wp-file-chat' ),
					'importConfirm'   => __( 'Replace all current knowledge entries? A timestamped backup will be created before replacement.', 'bryvium-wp-file-chat' ),
					'chooseSource'    => __( 'Choose a JSON file or paste a knowledge document first.', 'bryvium-wp-file-chat' ),
					'chooseOneSource' => __( 'Use either a JSON file or pasted content, not both.', 'bryvium-wp-file-chat' ),
					'exportSuccess'   => __( 'Your knowledge backup has been downloaded.', 'bryvium-wp-file-chat' ),
					/* translators: %s: timestamped backup filename. */
					'backupCreated'   => __( 'Backup created: %s', 'bryvium-wp-file-chat' ),
					/* translators: 1: entries added, 2: entries updated, 3: entries skipped, 4: errors. */
					'uploadSummary'   => __( 'Added: %1$d · Updated: %2$d · Skipped: %3$d · Errors: %4$d', 'bryvium-wp-file-chat' ),
				),
			)
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
	 * Render the knowledge manager for authorized administrators.
	 *
	 * @return void
	 */
	public static function render_knowledge_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage knowledge entries.', 'bryvium-wp-file-chat' ) );
		}

		require BRYVIUM_WFC_PATH . 'admin/views/page-knowledge.php';
	}

	/**
	 * Render the import/export screen for authorized administrators.
	 *
	 * @return void
	 */
	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import or export knowledge entries.', 'bryvium-wp-file-chat' ) );
		}

		require BRYVIUM_WFC_PATH . 'admin/views/page-tools.php';
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
