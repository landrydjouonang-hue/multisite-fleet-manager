<?php
/**
 * Deactivation handler.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

use FleetManager\Sites\DiscoveryScheduler;
use FleetManager\Sites\SiteDiscovery;

defined( 'ABSPATH' ) || exit;

/**
 * Stops background work. Data is kept until the plugin is deleted.
 */
final class Deactivator {

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( ! is_multisite() ) {
			return;
		}
		DiscoveryScheduler::unschedule();
		delete_site_option( SiteDiscovery::RUN_OPTION );
		delete_site_option( Activator::NOTICE_OPTION );
	}
}
