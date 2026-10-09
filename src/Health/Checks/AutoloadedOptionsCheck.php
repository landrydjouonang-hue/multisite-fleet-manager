<?php
/**
 * Autoloaded options size.
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
 * Autoloaded options are read on every single page load of a site, so a
 * large pile of them slows everything down. This is the database indicator
 * that most often explains a slow site, and it is per site.
 */
final class AutoloadedOptionsCheck implements HealthCheck {

	public function id(): string {
		return 'autoloaded_options';
	}

	public function label(): string {
		return __( 'Autoloaded options', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( null === $site->autoload_bytes ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		/**
		 * Filters the autoloaded options sizes that trigger this indicator.
		 *
		 * @since 0.5.0
		 *
		 * @param array{warning: int, error: int} $limits Sizes in bytes.
		 */
		$limits = (array) apply_filters(
			'wpfleet_autoload_limits',
			array(
				'warning' => 800 * KB_IN_BYTES,
				'error'   => 2 * MB_IN_BYTES,
			)
		);

		$data = array(
			'bytes'   => $site->autoload_bytes,
			'warning' => (int) $limits['warning'],
		);

		if ( $site->autoload_bytes >= (int) $limits['error'] ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( $site->autoload_bytes >= (int) $limits['warning'] ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'The size of the autoloaded options could not be read.', 'multisite-fleet-manager' );
		}

		$size = size_format( (int) ( $indicator->data['bytes'] ?? 0 ), 1 );

		if ( Indicator::GOOD === $indicator->state ) {
			/* translators: %s: Size, e.g. "120 KB". */
			return sprintf( __( '%s of options is loaded on every page view.', 'multisite-fleet-manager' ), $size );
		}

		return sprintf(
			/* translators: 1: Size, e.g. "1.4 MB", 2: Size threshold. */
			__( '%1$s of options is loaded on every page view, above the %2$s guideline. Look for plugins storing large values with autoload on.', 'multisite-fleet-manager' ),
			$size,
			size_format( (int) ( $indicator->data['warning'] ?? 0 ) )
		);
	}
}
