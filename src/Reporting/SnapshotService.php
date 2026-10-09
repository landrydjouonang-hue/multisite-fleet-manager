<?php
/**
 * Snapshot capture and retention.
 *
 * @package FleetManager
 */

namespace FleetManager\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Captures a snapshot after discovery, at most once per interval, and
 * prunes old ones on the daily event.
 *
 * Automatic capture is what makes the trend useful: the figures are
 * recorded without anyone remembering to press a button, and the interval
 * stops a network that runs discovery often from filling the table.
 */
final class SnapshotService {

	private ReportBuilder $reports;

	private SnapshotRepository $snapshots;

	/**
	 * Constructor.
	 *
	 * @param ReportBuilder      $reports   Report builder.
	 * @param SnapshotRepository $snapshots Storage.
	 */
	public function __construct( ReportBuilder $reports, SnapshotRepository $snapshots ) {
		$this->reports   = $reports;
		$this->snapshots = $snapshots;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wpfleet_discovery_completed', array( $this, 'maybe_capture' ), 20 );
	}

	/**
	 * Captures a snapshot unless a recent one exists.
	 *
	 * @return int Snapshot ID, or 0 when skipped.
	 */
	public function maybe_capture(): int {
		/**
		 * Filters the minimum time between automatic snapshots.
		 *
		 * @since 0.6.0
		 *
		 * @param int $seconds Interval (default 12 hours). 0 captures on every discovery.
		 */
		$interval = max( 0, (int) apply_filters( 'wpfleet_snapshot_interval', 12 * HOUR_IN_SECONDS ) );
		$latest   = $this->snapshots->latest( 1 );

		if ( $latest ) {
			$captured = strtotime( $latest[0]['captured_at'] . ' UTC' );
			if ( $captured && ( time() - $captured ) < $interval ) {
				return 0;
			}
		}

		return $this->capture( 'scheduled' );
	}

	/**
	 * Captures a snapshot now.
	 *
	 * @param string $source 'scheduled' or 'manual'.
	 * @return int Snapshot ID.
	 */
	public function capture( string $source = 'manual' ): int {
		$id = $this->snapshots->insert( $this->reports->summary(), $source );

		/**
		 * Fires after a report snapshot is stored.
		 *
		 * @since 0.6.0
		 *
		 * @param int    $id     Snapshot ID.
		 * @param string $source Capture source.
		 */
		do_action( 'wpfleet_snapshot_captured', $id, $source );

		return $id;
	}

	/**
	 * Removes snapshots past the retention period.
	 *
	 * @return int
	 */
	public function prune(): int {
		return $this->snapshots->prune();
	}

	/**
	 * Trend series for charting, oldest first.
	 *
	 * @param int $limit How many points.
	 * @return array<int,array<string,mixed>>
	 */
	public function series( int $limit = 30 ): array {
		return array_reverse( $this->snapshots->latest( $limit ) );
	}

	/**
	 * Change between the newest snapshot and the one before it.
	 *
	 * @return array<string,int>|null Null when there is nothing to compare.
	 */
	public function change(): ?array {
		$recent = $this->snapshots->latest( 2 );
		if ( count( $recent ) < 2 ) {
			return null;
		}

		$now  = $recent[0];
		$then = $recent[1];
		$keys = array( 'sites', 'healthy', 'attention', 'error', 'sites_needing_updates', 'plugin_updates', 'theme_updates' );

		$delta = array( 'since' => (string) $then['captured_at'] );
		foreach ( $keys as $key ) {
			$delta[ $key ] = (int) $now[ $key ] - (int) $then[ $key ];
		}
		return $delta;
	}
}
