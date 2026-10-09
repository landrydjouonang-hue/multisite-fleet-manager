<?php
/**
 * Storage: upload quota and free disk space.
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
 * Two storage signals, whichever is available:
 *
 * - The site's upload quota, when the network enforces one. Running out
 *   stops uploads on that site only.
 * - Free space on the filesystem, when the host allows reading it. Running
 *   out affects every site, so it is reported on all of them.
 *
 * Hosts often disable disk_free_space() or enforce no quota; that is
 * reported as "not available", never as a problem.
 */
final class StorageCheck implements HealthCheck {

	public function id(): string {
		return 'storage';
	}

	public function label(): string {
		return __( 'Storage', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		/**
		 * Filters the storage thresholds.
		 *
		 * @since 0.5.0
		 *
		 * @param array{quota_warning: float, quota_error: float, disk_warning: int, disk_error: int} $limits
		 */
		$limits = (array) apply_filters(
			'wpfleet_storage_limits',
			array(
				'quota_warning' => 0.8,
				'quota_error'   => 0.95,
				'disk_warning'  => 2 * GB_IN_BYTES,
				'disk_error'    => 512 * MB_IN_BYTES,
			)
		);

		$data   = array();
		$states = array();

		if ( $site->upload_quota_bytes && $site->upload_quota_bytes > 0 && null !== $site->upload_used_bytes ) {
			$ratio         = $site->upload_used_bytes / $site->upload_quota_bytes;
			$data['used']  = $site->upload_used_bytes;
			$data['quota'] = $site->upload_quota_bytes;
			$data['ratio'] = round( $ratio, 3 );
			$states[]      = $this->level( $ratio >= (float) $limits['quota_error'], $ratio >= (float) $limits['quota_warning'] );
		}

		if ( null !== $context->disk_free ) {
			$data['disk_free']  = $context->disk_free;
			$data['disk_total'] = $context->disk_total;
			$states[]           = $this->level(
				$context->disk_free <= (int) $limits['disk_error'],
				$context->disk_free <= (int) $limits['disk_warning']
			);
		}

		if ( ! $states ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		// The most severe of the available signals decides.
		if ( in_array( Indicator::ERROR, $states, true ) ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		return new Indicator( $this->id(), in_array( Indicator::WARNING, $states, true ) ? Indicator::WARNING : Indicator::GOOD, $data );
	}

	/**
	 * Maps two thresholds to a state.
	 *
	 * @param bool $error   Error threshold reached.
	 * @param bool $warning Warning threshold reached.
	 * @return string
	 */
	private function level( bool $error, bool $warning ): string {
		if ( $error ) {
			return Indicator::ERROR;
		}
		return $warning ? Indicator::WARNING : Indicator::GOOD;
	}

	public function message( Indicator $indicator ): string {
		$parts = array();
		$data  = $indicator->data;

		if ( isset( $data['quota'], $data['used'] ) ) {
			$parts[] = sprintf(
				/* translators: 1: Used space, 2: Quota, 3: Percentage. */
				__( 'Uploads use %1$s of the %2$s allowed for this site (%3$s%%).', 'multisite-fleet-manager' ),
				size_format( (int) $data['used'], 1 ),
				size_format( (int) $data['quota'] ),
				number_format_i18n( round( (float) $data['ratio'] * 100 ) )
			);
		}

		if ( isset( $data['disk_free'] ) ) {
			$parts[] = isset( $data['disk_total'] ) && $data['disk_total']
				? sprintf(
					/* translators: 1: Free space, 2: Total space. */
					__( '%1$s free of %2$s on the server filesystem.', 'multisite-fleet-manager' ),
					size_format( (int) $data['disk_free'], 1 ),
					size_format( (int) $data['disk_total'] )
				)
				: sprintf(
					/* translators: %s: Free space. */
					__( '%s free on the server filesystem.', 'multisite-fleet-manager' ),
					size_format( (int) $data['disk_free'], 1 )
				);
		}

		if ( ! $parts ) {
			return __( 'No storage figures are available: this network enforces no upload quota and the host does not report free disk space.', 'multisite-fleet-manager' );
		}

		if ( Indicator::ERROR === $indicator->state ) {
			$parts[] = __( 'Uploads will start failing.', 'multisite-fleet-manager' );
		} elseif ( Indicator::WARNING === $indicator->state ) {
			$parts[] = __( 'Worth planning for before it runs out.', 'multisite-fleet-manager' );
		}

		return implode( ' ', $parts );
	}
}
