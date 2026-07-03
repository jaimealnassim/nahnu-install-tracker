<?php
/**
 * Plugin Name:       Nahnu Install and Version Tracker
 * Plugin URI:        https://github.com/jaimealnassim/nahnu-install-tracker
 * Description:       Tracks WordPress.org install counts, WP.org plugin versions, and GitHub release versions. Display anywhere via shortcode.
 * Version:           1.0.0
 * Author:            ja1me4
 * Author URI:        https://github.com/jaimealnassim
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nahnu-install-tracker
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'NIT_VERSION',         '1.0.0' );
define( 'NIT_FILE',            __FILE__ );
define( 'NIT_DIR',             plugin_dir_path( __FILE__ ) );
define( 'NIT_URL',             plugin_dir_url( __FILE__ ) );

// Install tracker options.
define( 'NIT_OPTION_SLUGS',    'nit_plugin_slugs' );
define( 'NIT_OPTION_DATA',     'nit_install_data' );
define( 'NIT_OPTION_UPDATED',  'nit_last_updated' );
define( 'NIT_OPTION_INTERVAL', 'nit_fetch_interval' );
define( 'NIT_CRON_HOOK',       'nit_daily_fetch' );

// WP.org version tracker options.
define( 'NIT_OPTION_WP_SLUGS', 'nit_wp_version_slugs' );
define( 'NIT_OPTION_WP_DATA',  'nit_wp_version_data' );

// GitHub version tracker options.
define( 'NIT_OPTION_GH_SOURCES', 'nit_gh_sources' );
define( 'NIT_OPTION_GH_DATA',    'nit_gh_version_data' );

// Load all classes immediately — no hook needed, safe at any point after ABSPATH.
require_once NIT_DIR . 'includes/class-nit-fetcher.php';
require_once NIT_DIR . 'includes/class-nit-wp-version.php';
require_once NIT_DIR . 'includes/class-nit-gh-version.php';
require_once NIT_DIR . 'includes/class-nit-admin.php';
require_once NIT_DIR . 'includes/class-nit-shortcode.php';
require_once NIT_DIR . 'includes/class-nit-cron.php';

register_activation_hook( NIT_FILE,   array( 'NIT_Cron', 'activate' ) );
register_deactivation_hook( NIT_FILE, array( 'NIT_Cron', 'deactivate' ) );

// Register shortcodes immediately so they are available for any hook or context.
NIT_Shortcode::init();

// Admin and cron only need to hook in after plugins are loaded.
add_action( 'plugins_loaded', 'nahnu_install_tracker_init' );

/**
 * Bootstrap admin and cron components.
 */
function nahnu_install_tracker_init() {
	NIT_Admin::init();
	NIT_Cron::init();
}
