<?php
/**
 * Network-level facts.
 *
 * @package FleetManager
 */

namespace FleetManager\Network;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the current network: install type, main site, registration
 * policy and network-wide counts. Read-only, straight from core APIs.
 */
final class NetworkInfo {

	/**
	 * Summary of the current network.
	 *
	 * @return array<string,mixed>
	 */
	public function summary(): array {
		$network = get_network();

		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		return array(
			'network_id'       => (int) get_current_network_id(),
			'name'             => $network ? (string) $network->site_name : '',
			'domain'           => $network ? (string) $network->domain : '',
			'path'             => $network ? (string) $network->path : '',
			'main_site_id'     => (int) get_main_site_id(),
			'main_site_url'    => (string) network_home_url( '/' ),
			'install_type'     => is_subdomain_install() ? 'subdomain' : 'subdirectory',
			'networks'         => $this->network_count(),
			'registration'     => (string) get_site_option( 'registration', 'none' ),
			'network_plugins'  => is_array( $network_plugins ) ? count( $network_plugins ) : 0,
			'users'            => (int) get_user_count(),
			'sites'            => (int) get_blog_count(),
			'wp_version'       => (string) get_bloginfo( 'version' ),
			'php_version'      => PHP_VERSION,
			'is_large_network' => wp_is_large_network(),
		);
	}

	/**
	 * Label for an install type.
	 *
	 * @param string $type 'subdomain' or 'subdirectory'.
	 * @return string
	 */
	public static function install_type_label( string $type ): string {
		return 'subdomain' === $type ? __( 'Subdomains', 'multisite-fleet-manager' ) : __( 'Subdirectories', 'multisite-fleet-manager' );
	}

	/**
	 * Label for the registration setting.
	 *
	 * @param string $value Setting value.
	 * @return string
	 */
	public static function registration_label( string $value ): string {
		$labels = array(
			'none' => __( 'Disabled', 'multisite-fleet-manager' ),
			'user' => __( 'User accounts only', 'multisite-fleet-manager' ),
			'blog' => __( 'Logged-in users may register sites', 'multisite-fleet-manager' ),
			'all'  => __( 'Sites and user accounts', 'multisite-fleet-manager' ),
		);
		return $labels[ $value ] ?? $value;
	}

	/**
	 * Number of networks in the installation.
	 *
	 * @return int
	 */
	private function network_count(): int {
		return (int) get_networks(
			array(
				'count'  => true,
				'number' => 0,
			)
		);
	}
}
