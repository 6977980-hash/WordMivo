<?php
/**
 * Plugin Name:       WordMivo Core
 * Description:       Word finder tools, word database, programmatic word-list pages and SEO for WordMivo. Ships the WordMivo theme.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            WordMivo
 * License:           GPL-2.0-or-later
 * Text Domain:       wordmivo
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;

define( 'WORDMIVO_VERSION', '0.2.0' );
define( 'WORDMIVO_FILE', __FILE__ );
define( 'WORDMIVO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORDMIVO_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'WORDMIVO_DATA_DIR' ) ) {
	// First match wins: a folder next to public_html, the plugin's own data/ folder
	// (deployed by Git, web access denied by .htaccess), then wp-content/wordmivo-data.
	// Can be overridden in wp-config.php.
	$wordmivo_dirs = array(
		dirname( untrailingslashit( ABSPATH ) ) . '/wordmivo-data',
		WORDMIVO_DIR . 'data',
		WP_CONTENT_DIR . '/wordmivo-data',
	);
	foreach ( $wordmivo_dirs as $wordmivo_dir ) {
		if ( @is_readable( $wordmivo_dir . '/words_alpha.txt' ) || WP_CONTENT_DIR . '/wordmivo-data' === $wordmivo_dir ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- open_basedir may forbid the check.
			define( 'WORDMIVO_DATA_DIR', untrailingslashit( $wordmivo_dir ) );
			break;
		}
	}
	unset( $wordmivo_dirs, $wordmivo_dir );
}

require_once WORDMIVO_DIR . 'includes/helpers.php';
require_once WORDMIVO_DIR . 'includes/class-installer.php';
require_once WORDMIVO_DIR . 'includes/content-pages.php';
require_once WORDMIVO_DIR . 'includes/class-importer.php';
require_once WORDMIVO_DIR . 'includes/class-pages.php';
require_once WORDMIVO_DIR . 'includes/class-rest.php';
require_once WORDMIVO_DIR . 'includes/class-seo.php';
require_once WORDMIVO_DIR . 'includes/class-sitemap.php';
require_once WORDMIVO_DIR . 'includes/class-shortcodes.php';
require_once WORDMIVO_DIR . 'includes/class-admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WORDMIVO_DIR . 'includes/class-cli.php';
	WP_CLI::add_command( 'wordmivo', 'WordMivo\\CLI' );
}

// The theme ships inside this plugin so one Git deploy updates both.
register_theme_directory( WORDMIVO_DIR . 'themes' );

register_activation_hook( __FILE__, array( 'WordMivo\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WordMivo\\Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WordMivo\\Installer', 'maybe_upgrade' ) );

WordMivo\Pages::init();
WordMivo\Rest::init();
WordMivo\Seo::init();
WordMivo\Sitemap::init();
WordMivo\Shortcodes::init();
WordMivo\Admin::init();
