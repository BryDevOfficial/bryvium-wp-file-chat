<?php
/**
 * Plugin Name:       Bryvium WP File Chat
 * Plugin URI:        https://example.com/bryvium-wp-file-chat
 * Description:       A local, file-based chat widget. Answers visitor questions from a single JSON knowledge file. No external APIs, no database tables.
 * Version:           0.1.0
 * Requires at least: 7.1.4
 * Requires PHP:      8.5
 * Author:            Bryan Granse
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bryvium-wp-file-chat
 * Domain Path:       /languages
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

define( 'BRYVIUM_WFC_VERSION', '0.1.0' );
define( 'BRYVIUM_WFC_MIN_PHP', '8.5' );
define( 'BRYVIUM_WFC_MIN_WP', '7.1.4' );
define( 'BRYVIUM_WFC_FILE', __FILE__ );
define( 'BRYVIUM_WFC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRYVIUM_WFC_URL', plugin_dir_url( __FILE__ ) );
define( 'BRYVIUM_WFC_BASENAME', plugin_basename( __FILE__ ) );

require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-loader.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-storage.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-settings.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-admin.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-search.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-rate-limiter.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-rest.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-importer.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-rest-admin.php';
require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-widget.php';

register_activation_hook( __FILE__, array( 'Bryvium_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Bryvium_Loader', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'Bryvium_Loader', 'init' ) );
