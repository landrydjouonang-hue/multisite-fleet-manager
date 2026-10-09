<?php
/**
 * Schema upgrades.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

use FleetManager\Sites\DiscoveryScheduler;
use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Re-runs the installer when the stored schema version is behind, e.g. after
 * the plugin files are updated without a re-activation.
 */
final class Upgrader {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Upgrades when needed.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = Schema::installed_version();
		if ( Schema::VERSION === $installed ) {
			return;
		}

		Schema::install();

		// Schema 2 added update and health data, which only a discovery run can collect.
		if ( '' !== $installed && version_compare( $installed, '2', '<' ) ) {
			DiscoveryScheduler::schedule( true );
		}
	}
}
