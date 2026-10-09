<?php
/**
 * Multisite Fleet Manager
 *
 * @package           FleetManager
 * @author            Djouonang Landry
 * @copyright         2026 Djouonang Landry
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Multisite Fleet Manager
 * Plugin URI:        https://github.com/landrydjouonang-hue/multisite-fleet-manager
 * Description:       Central management dashboard for WordPress Multisite networks: site inventory, health indicators, plugins and themes, centralized updates, user visibility, network reporting with exports and an operation log. Requires WordPress Multisite and must be network activated.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Djouonang Landry
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       multisite-fleet-manager
 * Domain Path:       /languages
 * Network:           true
 */

/*
 * This file must stay parseable by old PHP versions so the requirements
 * notice can be shown instead of a fatal error. Keep modern syntax in src/.
 */

defined( 'ABSPATH' ) || exit;

define( 'WPFLEET_VERSION', '1.0.0' );
define( 'WPFLEET_FILE', __FILE__ );
define( 'WPFLEET_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFLEET_URL', plugin_dir_url( __FILE__ ) );
define( 'WPFLEET_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPFLEET_MIN_PHP', '8.0' );
define( 'WPFLEET_MIN_WP', '6.4' );

require_once WPFLEET_PATH . 'src/Core/Requirements.php';

$wpfleet_requirements = new FleetManager\Core\Requirements( WPFLEET_MIN_PHP, WPFLEET_MIN_WP );

if ( ! $wpfleet_requirements->met() ) {
	$wpfleet_requirements->register_notice();
	unset( $wpfleet_requirements );
	return;
}

unset( $wpfleet_requirements );

require_once WPFLEET_PATH . 'src/Autoloader.php';

FleetManager\Autoloader::register( 'FleetManager\\', WPFLEET_PATH . 'src/' );

// Registered before the Multisite gate so that activation on a single site is refused with an explanation.
register_activation_hook( __FILE__, array( 'FleetManager\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FleetManager\\Core\\Deactivator', 'deactivate' ) );

/*
 * Multisite gate. The plugin never simulates a network: on a single site, or
 * when it is not network activated, it only explains what is required.
 */
$wpfleet_guard = new FleetManager\Core\MultisiteGuard();

if ( ! $wpfleet_guard->ready() ) {
	( new FleetManager\Admin\RequirementNotice( $wpfleet_guard ) )->register();
	unset( $wpfleet_guard );
	return;
}

unset( $wpfleet_guard );

if ( ! function_exists( 'wpfleet' ) ) {
	// Declared conditionally so PHP does not hoist it above the early returns.

	/**
	 * Returns the main plugin instance.
	 *
	 * @return FleetManager\Plugin
	 */
	function wpfleet() {
		return FleetManager\Plugin::instance();
	}
}

add_action( 'plugins_loaded', array( wpfleet(), 'boot' ) );
