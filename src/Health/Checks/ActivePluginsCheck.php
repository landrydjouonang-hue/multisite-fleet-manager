<?php
/**
 * Active plugin count.
 *
 * @package FleetManager
 */

namespace FleetManager\Health\Checks;

use FleetManager\Health\Contracts\HealthCheck;
use FleetManager\Health\FleetContext;
use FleetManager\Health\Indicator;
use FleetManager\Sites\Site;

defined( 'ABSPATH' ) || exit;

/**
 * How many plugins actually run on the site: its own active plugins plus
 * the network-activated ones, which load everywhere.
 *
 * A count is not a verdict — one heavy plugin can cost more than twenty
 * small ones — but it is the cheapest signal of a site that has grown
 * faster than anyone has pruned it.
 */
final class ActivePluginsCheck implements HealthCheck {

	public function id(): string {
		return 'active_plugins';
	}

	public function label(): string {
		return __( 'Active plugins', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$network = count( $context->network_plugins );
		$own     = count( $site->active_plugin_list );
		$total   = $own + $network;

		/**
		 * Filters the active plugin count thresholds.
		 *
		 * @since 0.7.0
		 *
		 * @param array{warning: int, error: int} $limits Number of plugins loaded per request.
		 */
		$limits = (array) apply_filters(
			'wpfleet_active_plugin_limits',
			array(
				'warning' => 25,
				'error'   => 45,
			)
		);

		$data = array(
			'total'   => $total,
			'site'    => $own,
			'network' => $network,
			'warning' => (int) $limits['warning'],
		);

		if ( $total >= (int) $limits['error'] ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( $total >= (int) $limits['warning'] ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		$total   = (int) ( $indicator->data['total'] ?? 0 );
		$own     = (int) ( $indicator->data['site'] ?? 0 );
		$network = (int) ( $indicator->data['network'] ?? 0 );

		$detail = sprintf(
			/* translators: 1: Total plugins, 2: Site-level plugins, 3: Network-activated plugins. */
			_n( '%1$s plugin loads on this site (%2$s here, %3$s network-activated).', '%1$s plugins load on this site (%2$s here, %3$s network-activated).', $total, 'multisite-fleet-manager' ),
			number_format_i18n( $total ),
			number_format_i18n( $own ),
			number_format_i18n( $network )
		);

		if ( Indicator::GOOD === $indicator->state ) {
			return $detail;
		}

		return $detail . ' ' . __( 'Every one of them runs on every request; retiring unused plugins is usually the cheapest speed-up.', 'multisite-fleet-manager' );
	}
}
