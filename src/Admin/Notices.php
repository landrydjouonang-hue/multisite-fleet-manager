<?php
/**
 * Result notices for plugin screens.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a notice selected by the whitelisted `wpfleet_notice` query arg.
 */
final class Notices {

	/**
	 * Renders the notice for the current request, if any.
	 *
	 * @return void
	 */
	public static function render(): void {
		$key = isset( $_GET['wpfleet_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpfleet_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

		$notices = array(
			'discovery_done'    => array( 'success', __( 'Site discovery complete. The inventory is up to date.', 'multisite-fleet-manager' ) ),
			'discovery_partial' => array( 'warning', __( 'Site discovery made progress but did not finish within the time limit. Run it again to continue; it resumes where it stopped.', 'multisite-fleet-manager' ) ),
			'discovery_running' => array( 'info', __( 'Site discovery is already running in the background. Check back in a moment.', 'multisite-fleet-manager' ) ),
		);

		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $key ][0] ),
			esc_html( $notices[ $key ][1] )
		);
	}
}
