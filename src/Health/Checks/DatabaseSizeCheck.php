<?php
/**
 * Per-site database size.
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
 * How much database a single site occupies.
 *
 * On a network this matters more than on a standalone site: every site
 * shares one server, so one runaway table set (usually logs, transients
 * or revisions) slows down backups and restores for everyone.
 */
final class DatabaseSizeCheck implements HealthCheck {

	public function id(): string {
		return 'database_size';
	}

	public function label(): string {
		return __( 'Database size', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( null === $site->db_size_bytes ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		/**
		 * Filters the per-site database size thresholds, in bytes.
		 *
		 * @since 0.7.0
		 *
		 * @param array{warning: int, error: int} $limits Thresholds.
		 */
		$limits = (array) apply_filters(
			'wpfleet_database_size_limits',
			array(
				'warning' => 512 * MB_IN_BYTES,
				'error'   => 2 * GB_IN_BYTES,
			)
		);

		$data = array(
			'bytes'   => $site->db_size_bytes,
			'warning' => (int) $limits['warning'],
		);

		if ( $site->db_size_bytes >= (int) $limits['error'] ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( $site->db_size_bytes >= (int) $limits['warning'] ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'The database size could not be read on this server.', 'multisite-fleet-manager' );
		}

		$size = size_format( (int) ( $indicator->data['bytes'] ?? 0 ), 1 );

		if ( Indicator::GOOD === $indicator->state ) {
			/* translators: %s: Size. */
			return sprintf( __( 'This site uses %s of the database.', 'multisite-fleet-manager' ), $size );
		}

		return sprintf(
			/* translators: 1: Size, 2: Threshold. */
			__( 'This site uses %1$s of the database, above the %2$s guideline. Look at revisions, transients and plugin log tables.', 'multisite-fleet-manager' ),
			$size,
			size_format( (int) ( $indicator->data['warning'] ?? 0 ) )
		);
	}
}
