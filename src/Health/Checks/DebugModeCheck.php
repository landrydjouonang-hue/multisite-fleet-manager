<?php
/**
 * Debugging constants.
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
 * Debug settings live in wp-config.php, so they apply to every site.
 *
 * Showing errors to visitors is an error: it breaks pages and leaks paths
 * and query details. Debug logging on its own only needs attention.
 */
final class DebugModeCheck implements HealthCheck {

	public function id(): string {
		return 'debug_mode';
	}

	public function label(): string {
		return __( 'Debug mode', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$debug   = $context->debug;
		$data    = $debug;
		$visible = ! empty( $debug['debug'] ) && ( ! empty( $debug['display'] ) || ! empty( $debug['display_errors'] ) );

		if ( $visible ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( ! empty( $debug['debug'] ) || ! empty( $debug['script_debug'] ) ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		switch ( $indicator->state ) {
			case Indicator::ERROR:
				return __( 'Debug mode is on and errors can be shown to visitors. Set WP_DEBUG_DISPLAY to false (and display_errors off) on a live network.', 'multisite-fleet-manager' );
			case Indicator::WARNING:
				$parts = array();
				if ( ! empty( $indicator->data['debug'] ) ) {
					$parts[] = ! empty( $indicator->data['log'] )
						? __( 'Debug mode is on and writing to the debug log.', 'multisite-fleet-manager' )
						: __( 'Debug mode is on.', 'multisite-fleet-manager' );
				}
				if ( ! empty( $indicator->data['script_debug'] ) ) {
					$parts[] = __( 'SCRIPT_DEBUG loads unminified assets, which slows pages down.', 'multisite-fleet-manager' );
				}
				return implode( ' ', $parts ) . ' ' . __( 'Useful while developing; turn it off on a live network.', 'multisite-fleet-manager' );
		}
		return __( 'Debug mode is off.', 'multisite-fleet-manager' );
	}
}
