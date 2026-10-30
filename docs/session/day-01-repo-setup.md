# D1: Repo Setup, Bootstrap, Constants, Activation Hooks

**Branch:** `feature/d01-bootstrap`

## 1. Folder skeleton (run from `wp-content/plugins/`)

```powershell
New-Item -ItemType Directory bryvium-wp-file-chat
Set-Location bryvium-wp-file-chat
git init -b main
git switch -c develop
git switch -c feature/d01-bootstrap

$directories = 'includes', 'admin', 'src', 'build', 'languages', 'docs'
New-Item -ItemType Directory -Path $directories
'admin', 'src', 'build', 'languages', 'docs' | ForEach-Object {
    New-Item -ItemType File -Path (Join-Path $_ '.gitkeep')
}

foreach ($directory in '.', 'includes', 'admin', 'languages') {
    [System.IO.File]::WriteAllText(
        (Join-Path $PWD "$directory\index.php"),
        "<?php`n// Silence is golden.`n"
    )
}
```

The root `index.php` is only a directory-listing guard; WordPress loads the
plugin from `bryvium-wp-file-chat.php`. The other `index.php` files block
directory listing on servers that allow it.

## 2. `bryvium-wp-file-chat.php`

```php
<?php
/**
 * Plugin Name:       Bryvium WP File Chat
 * Plugin URI:        https://example.com/bryvium-wp-file-chat
 * Description:       A local, file-based chat widget. Answers visitor questions from a single JSON knowledge file. No external APIs, no database tables.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
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
define( 'BRYVIUM_WFC_MIN_PHP', '7.4' );
define( 'BRYVIUM_WFC_MIN_WP', '6.0' );
define( 'BRYVIUM_WFC_FILE', __FILE__ );
define( 'BRYVIUM_WFC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRYVIUM_WFC_URL', plugin_dir_url( __FILE__ ) );
define( 'BRYVIUM_WFC_BASENAME', plugin_basename( __FILE__ ) );

require_once BRYVIUM_WFC_PATH . 'includes/class-bryvium-loader.php';

register_activation_hook( __FILE__, array( 'Bryvium_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Bryvium_Loader', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'Bryvium_Loader', 'init' ) );
```

## 3. `includes/class-bryvium-loader.php`

```php
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
	 * Storage directory creation arrives in D3.
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

		/**
		 * Fires when the plugin is ready. Modules register hooks here
		 * (storage D3, REST D6, admin D8, widget D12).
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
```

The code uses syntax compatible with PHP 7.4, matching the declared minimum.

## 4. Manual test (3 steps)

1. Confirm **Bryvium WP File Chat** appears in **Plugins**, then activate it. Expected: no errors, and `wp option get bryvium_wfc_version` (or the `wp_options` table) returns `0.1.0`.
2. Temporarily set `BRYVIUM_WFC_MIN_PHP` to `'99'`, deactivate and reactivate. Expected: a clean "requires PHP 99+" screen and the plugin stays inactive. Revert it afterwards.
3. Enable `WP_DEBUG` and `WP_DEBUG_LOG`, then deactivate and reactivate. Expected: nothing in `debug.log`.

## 5. Commit message

```
feat(build): add plugin bootstrap, constants and lifecycle hooks

Adds requirement checks (PHP 7.4 / WP 6.0), activation version
option, non-destructive deactivation, and folder skeleton.
```

## 6. Handoff note for D2

D1 exposes constants `BRYVIUM_WFC_{VERSION,FILE,PATH,URL,BASENAME}`, class `Bryvium_Loader`, and the action `bryvium_wfc_loaded` (modules hook here). Folders `src/`, `build/` and `admin/` are empty and ready for the Vite and PHPCS scaffold.

---

Say **"Start D2"** when you're ready. D2 covers the PHPCS/WPCS config, `.gitignore`, `.editorconfig`, and the Vite + Tailwind scaffold.
