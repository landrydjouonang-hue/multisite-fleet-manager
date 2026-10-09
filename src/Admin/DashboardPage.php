<?php
/**
 * Dashboard screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the dashboard data and renders templates/admin/dashboard.php.
 */
final class DashboardPage {

	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$sites     = $this->plugin->sites();
		$discovery = $this->plugin->discovery();
		$run       = $discovery->current();
		$last      = $discovery->last();

		View::render(
			'admin/dashboard',
			array(
				'network'        => $this->plugin->network()->summary(),
				'counts'         => $sites->count_by_status(),
				'totals'         => $sites->totals(),
				'last'           => $last,
				'running'        => $run && $discovery->is_active( $run ) ? $run : null,
				'has_inventory'  => null !== $last,
				'network_sites'  => $discovery->count_network_sites(),
				'recent'         => $last ? $sites->latest( 'registered_at', 5 ) : array(),
				'updated'        => $last ? $sites->latest( 'last_updated_at', 5 ) : array(),
				'themes'         => $last ? $sites->theme_usage( 5 ) : array(),
				'can_run'        => current_user_can( Capabilities::RUN_DISCOVERY ),
				'can_view_sites' => current_user_can( Capabilities::VIEW_SITES ),
				'can_manage'     => current_user_can( 'manage_sites' ),
			)
		);
	}
}
