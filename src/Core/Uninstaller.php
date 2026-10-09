<?php
/**
 * Uninstall handler.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

use FleetManager\Sites\DiscoveryScheduler;
use FleetManager\Sites\SiteDiscovery;
use FleetManager\Storage\Schema;
use FleetManager\Users\UserActivity;
use FleetManager\Users\UserIndex;

defined( 'ABSPATH' ) || exit;

/**
 * Removes every trace of the plugin: tables, network options on every
 * network, and scheduled events.
 */
final class Uninstaller {

	/**
	 * Network options owned by the plugin.
	 *
	 * @return string[]
	 */
	public static function network_options(): array {
		return array(
			SiteDiscovery::RUN_OPTION,
			SiteDiscovery::LAST_OPTION,
			Capabilities::OPTION,
			Activator::NOTICE_OPTION,
			UserIndex::SKIPPED_OPTION,
			\FleetManager\Core\Settings::OPTION,
			\FleetManager\Notifications\Notifier::STATE_OPTION,
		);
	}

	/**
	 * Uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		if ( is_multisite() ) {
			$network_ids = get_networks(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $network_ids as $network_id ) {
				foreach ( self::network_options() as $option ) {
					delete_network_option( (int) $network_id, $option );
				}
				// Operation lock (site transient).
				delete_network_option( (int) $network_id, '_site_transient_wpfleet_operation_lock' );
				delete_network_option( (int) $network_id, '_site_transient_timeout_wpfleet_operation_lock' );
				DiscoveryScheduler::unschedule( (int) $network_id );
				$main = (int) get_main_site_id( (int) $network_id );
				if ( $main ) {
					switch_to_blog( $main );
					wp_clear_scheduled_hook( \FleetManager\Notifications\Notifier::DIGEST_HOOK );
					restore_current_blog();
				}
			}
		}

		// Activity meta recorded on user accounts.
		global $wpdb;
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => UserActivity::META_LAST_LOGIN ), array( '%s' ) );
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => UserActivity::META_LAST_SITE ), array( '%s' ) );

		Schema::drop();
	}
}
