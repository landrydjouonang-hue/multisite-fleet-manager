<?php
/**
 * Report snapshot storage.
 *
 * @package FleetManager
 */

namespace FleetManager\Reporting;

use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Stores one row per captured report in {base_prefix}wpfleet_snapshots:
 * the headline figures as columns (so trends are a plain query) and the
 * whole summary as JSON (so an old report can be read back in full).
 */
final class SnapshotRepository {

	/**
	 * Writes a snapshot.
	 *
	 * @param array<string,mixed> $summary Report summary.
	 * @param string              $source  'scheduled' or 'manual'.
	 * @return int Snapshot ID (0 on failure).
	 */
	public function insert( array $summary, string $source = 'scheduled' ): int {
		global $wpdb;

		$ok = $wpdb->insert(
			Schema::snapshots_table(),
			array(
				'network_id'            => (int) get_current_network_id(),
				'captured_at'           => current_time( 'mysql', true ),
				'source'                => 'manual' === $source ? 'manual' : 'scheduled',
				'user_id'               => (int) get_current_user_id(),
				'sites'                 => (int) $summary['sites']['total'],
				'healthy'               => (int) $summary['health']['healthy'],
				'attention'             => (int) $summary['health']['attention'],
				'error'                 => (int) $summary['health']['error'],
				'unchecked'             => (int) $summary['health']['unchecked'],
				'sites_needing_updates' => (int) $summary['updates']['sites_needing_updates'],
				'plugin_updates'        => (int) $summary['updates']['plugin_updates'],
				'theme_updates'         => (int) $summary['updates']['theme_updates'],
				'data'                  => (string) wp_json_encode( $summary ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Most recent snapshots, newest first.
	 *
	 * @param int  $limit     How many.
	 * @param bool $with_data Include the stored JSON summary.
	 * @return array<int,array<string,mixed>>
	 */
	public function latest( int $limit = 30, bool $with_data = false ): array {
		global $wpdb;

		$table   = Schema::snapshots_table();
		$columns = $with_data ? '*' : 'id, captured_at, source, user_id, sites, healthy, attention, error, unchecked, sites_needing_updates, plugin_updates, theme_updates';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$columns} FROM `{$table}` WHERE network_id = %d ORDER BY captured_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) get_current_network_id(),
				max( 1, $limit )
			),
			ARRAY_A
		);

		return array_map( array( $this, 'hydrate' ), (array) $rows );
	}

	/**
	 * One snapshot by ID.
	 *
	 * @param int $id Snapshot ID.
	 * @return array<string,mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = Schema::snapshots_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d AND network_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id,
				(int) get_current_network_id()
			),
			ARRAY_A
		);
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * The snapshot before the newest one, for "what changed" figures.
	 *
	 * @return array<string,mixed>|null
	 */
	public function previous(): ?array {
		$latest = $this->latest( 2 );
		return $latest[1] ?? null;
	}

	/**
	 * Number of stored snapshots.
	 *
	 * @return int
	 */
	public function count(): int {
		global $wpdb;
		$table = Schema::snapshots_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE network_id = %d", (int) get_current_network_id() ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Deletes snapshots older than the retention period.
	 *
	 * @return int Rows removed.
	 */
	public function prune(): int {
		global $wpdb;

		/**
		 * Filters how many days report snapshots are kept.
		 *
		 * @since 0.6.0
		 *
		 * @param int $days Retention in days (minimum 7).
		 */
		$days  = max( 7, (int) apply_filters( 'wpfleet_snapshot_retention_days', 365 ) );
		$table = Schema::snapshots_table();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE network_id = %d AND captured_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) get_current_network_id(),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Normalises a row.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	private function hydrate( array $row ): array {
		foreach ( array( 'id', 'user_id', 'sites', 'healthy', 'attention', 'error', 'unchecked', 'sites_needing_updates', 'plugin_updates', 'theme_updates' ) as $int ) {
			if ( isset( $row[ $int ] ) ) {
				$row[ $int ] = (int) $row[ $int ];
			}
		}
		if ( array_key_exists( 'data', $row ) ) {
			$decoded     = json_decode( (string) $row['data'], true );
			$row['data'] = is_array( $decoded ) ? $decoded : array();
		}
		return $row;
	}
}
