<?php
/**
 * Multisite detection.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the plugin may run.
 *
 * Multisite Fleet Manager only works on a real WordPress Multisite network and only
 * when it is network activated. It never emulates a network on a single
 * site: every other state keeps the plugin dormant and explains why.
 */
final class MultisiteGuard {

	public const STATE_READY              = 'ready';
	public const STATE_NOT_MULTISITE      = 'not_multisite';
	public const STATE_NOT_NETWORK_ACTIVE = 'not_network_active';

	/**
	 * Current state.
	 *
	 * @return string One of the STATE_* constants.
	 */
	public function state(): string {
		if ( ! is_multisite() ) {
			return self::STATE_NOT_MULTISITE;
		}
		if ( ! self::is_network_active() ) {
			return self::STATE_NOT_NETWORK_ACTIVE;
		}
		return self::STATE_READY;
	}

	/**
	 * Whether the plugin may boot.
	 *
	 * @return bool
	 */
	public function ready(): bool {
		return self::STATE_READY === $this->state();
	}

	/**
	 * Whether the plugin is network activated on the current network.
	 *
	 * Reads the option directly: is_plugin_active_for_network() lives in an
	 * admin include that is not loaded on the front end or during REST calls.
	 *
	 * @return bool
	 */
	public static function is_network_active(): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		$plugins = get_site_option( 'active_sitewide_plugins', array() );
		return is_array( $plugins ) && isset( $plugins[ WPFLEET_BASENAME ] );
	}

	/**
	 * Whether the "Network Setup" screen is already unlocked on a single site
	 * (WP_ALLOW_MULTISITE is true), so the notice can link straight to it.
	 *
	 * @return bool
	 */
	public static function network_setup_available(): bool {
		return ! is_multisite() && defined( 'WP_ALLOW_MULTISITE' ) && WP_ALLOW_MULTISITE;
	}

	/**
	 * Documentation on converting a site into a network.
	 *
	 * @return string
	 */
	public static function docs_url(): string {
		return 'https://developer.wordpress.org/advanced-administration/multisite/create-network/';
	}
}
