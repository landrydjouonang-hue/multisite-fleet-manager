<?php
/**
 * Operation log storage.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations;

use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only audit log in {base_prefix}wpfleet_operations, scoped per network.
 * Entries are never edited; old ones are purged after a retention period.
 */
final class OperationLog {

	/**
	 * Writes an entry.
	 *
	 * @param array<string,mixed> $entry {
	 *     @type string $operation   Operation.
	 *     @type string $status      Outcome.
	 *     @type string $target_type 'plugin'|'theme'.
	 *     @type string $target      Plugin basename or theme stylesheet.
	 *     @type string $target_name Display name.
	 *     @type int    $site_id     Target site (0 = network-wide).
	 *     @type string $from_version, $to_version, $message
	 *     @type array  $context     Extra data (JSON).
	 * }
	 * @return int Entry ID (0 on failure).
	 */
	public function write( array $entry ): int {
		global $wpdb;

		$row = array(
			'network_id'   => (int) get_current_network_id(),
			'user_id'      => (int) get_current_user_id(),
			'operation'    => substr( (string) ( $entry['operation'] ?? '' ), 0, 40 ),
			'target_type'  => substr( (string) ( $entry['target_type'] ?? '' ), 0, 20 ),
			'target'       => substr( (string) ( $entry['target'] ?? '' ), 0, 255 ),
			'target_name'  => substr( wp_strip_all_tags( (string) ( $entry['target_name'] ?? '' ) ), 0, 255 ),
			'site_id'      => (int) ( $entry['site_id'] ?? 0 ),
			'status'       => in_array( $entry['status'] ?? '', Operation::statuses(), true ) ? $entry['status'] : Operation::FAILED,
			'from_version' => substr( (string) ( $entry['from_version'] ?? '' ), 0, 50 ),
			'to_version'   => substr( (string) ( $entry['to_version'] ?? '' ), 0, 50 ),
			'message'      => wp_strip_all_tags( (string) ( $entry['message'] ?? '' ) ),
			'context'      => (string) wp_json_encode( (array) ( $entry['context'] ?? array() ) ),
			'created_at'   => current_time( 'mysql', true ),
		);

		$ok = $wpdb->insert(
			Schema::operations_table(),
			$row,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$id = $ok ? (int) $wpdb->insert_id : 0;

		/**
		 * Fires after an operation outcome is logged.
		 *
		 * @since 0.3.0
		 *
		 * @param int                 $id  Log entry ID.
		 * @param array<string,mixed> $row Stored row.
		 */
		do_action( 'wpfleet_operation_logged', $id, $row );

		return $id;
	}

	/**
	 * Paginated, filtered list (newest first).
	 *
	 * @param array<string,mixed> $args operation, status, site_id, search, per_page, page.
	 * @return array{items: array<int,array<string,mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'operation' => '',
				'status'    => '',
				'site_id'   => 0,
				'search'    => '',
				'per_page'  => 20,
				'page'      => 1,
			)
		);

		$where  = array( 'network_id = %d' );
		$params = array( (int) get_current_network_id() );

		if ( in_array( $args['operation'], Operation::all(), true ) ) {
			$where[]  = 'operation = %s';
			$params[] = $args['operation'];
		}
		if ( in_array( $args['status'], Operation::statuses(), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( (int) $args['site_id'] > 0 ) {
			$where[]  = 'site_id = %d';
			$params[] = (int) $args['site_id'];
		}
		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(target LIKE %s OR target_name LIKE %s OR message LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$per_page  = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
		$table     = Schema::operations_table();
		$where_sql = implode( ' AND ', $where );

		// $table and $where_sql are built from constants and placeholders only.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		$items = array();
		foreach ( (array) $rows as $row ) {
			$row['id']      = (int) $row['id'];
			$row['user_id'] = (int) $row['user_id'];
			$row['site_id'] = (int) $row['site_id'];
			$decoded        = json_decode( (string) $row['context'], true );
			$row['context'] = is_array( $decoded ) ? $decoded : array();
			$items[]        = $row;
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Deletes entries older than the retention period.
	 *
	 * @return int Rows deleted.
	 */
	public function purge(): int {
		global $wpdb;

		/**
		 * Filters how many days operation log entries are kept.
		 *
		 * @since 0.3.0
		 *
		 * @param int $days Retention in days (minimum 7).
		 */
		$days  = max( 7, (int) apply_filters( 'wpfleet_operation_log_retention_days', 180 ) );
		$table = Schema::operations_table();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE network_id = %d AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) get_current_network_id(),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}
}
