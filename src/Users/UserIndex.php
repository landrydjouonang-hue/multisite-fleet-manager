<?php
/**
 * User membership index.
 *
 * @package FleetManager
 */

namespace FleetManager\Users;

use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a queryable copy of "user X has role Y on site Z" in
 * {base_prefix}wpfleet_user_sites.
 *
 * On Multisite, user accounts are global and roles live in per-site
 * `{prefix}capabilities` usermeta keys, which cannot be filtered or counted
 * efficiently across a network. This index makes that possible; usermeta
 * stays the source of truth and the index is refreshed by discovery and by
 * core's user hooks. It holds no credentials: user ID, site ID and role
 * slugs only.
 */
final class UserIndex {

	/**
	 * Network option listing sites skipped because they have too many users.
	 */
	public const SKIPPED_OPTION = 'wpfleet_user_index_skipped';

	/**
	 * Replaces the memberships of one site.
	 *
	 * @param int      $site_id    Blog ID.
	 * @param int|null $network_id Network ID.
	 * @return int|null Members indexed, or null when the site was skipped (too many users).
	 */
	public function index_site( int $site_id, ?int $network_id = null ): ?int {
		global $wpdb;

		$network = $this->network( $network_id );
		$key     = $wpdb->get_blog_prefix( $site_id ) . 'capabilities';
		$total   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", $key ) );

		if ( $total > $this->limit() ) {
			$this->mark_skipped( $site_id, true );
			$this->clear_site( $site_id, $network );
			return null;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $key ),
			ARRAY_A
		);

		$memberships = array();
		foreach ( (array) $rows as $row ) {
			$memberships[ (int) $row['user_id'] ] = self::roles_from_capabilities( $row['meta_value'] );
		}

		$this->replace_site( $site_id, $memberships, $network );
		$this->mark_skipped( $site_id, false );

		return count( $memberships );
	}

	/**
	 * Writes a site's memberships and removes the ones that disappeared.
	 *
	 * @param array<int,string[]> $memberships User ID => role slugs.
	 * @param int                 $site_id     Blog ID.
	 * @param int                 $network_id  Network ID.
	 * @return void
	 */
	private function replace_site( int $site_id, array $memberships, int $network_id ): void {
		global $wpdb;
		$table = Schema::user_sites_table();
		$now   = current_time( 'mysql', true );

		foreach ( array_chunk( $memberships, 200, true ) as $chunk ) {
			$values = array();
			$params = array();
			foreach ( $chunk as $user_id => $roles ) {
				$values[] = '(%d, %d, %d, %s, %s)';
				array_push( $params, $network_id, $site_id, (int) $user_id, implode( ',', $roles ), $now );
			}
			// Placeholders only; the column list is fixed.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO `{$table}` (network_id, site_id, user_id, roles, updated_at) VALUES " . implode( ', ', $values ) . ' ON DUPLICATE KEY UPDATE roles = VALUES(roles), updated_at = VALUES(updated_at)', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$params
				)
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE network_id = %d AND site_id = %d AND updated_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$network_id,
				$site_id,
				$now
			)
		);
	}

	/**
	 * Stores one membership.
	 *
	 * @param int      $user_id    User ID.
	 * @param int      $site_id    Blog ID.
	 * @param string[] $roles      Role slugs.
	 * @param int|null $network_id Network ID.
	 * @return void
	 */
	public function set( int $user_id, int $site_id, array $roles, ?int $network_id = null ): void {
		global $wpdb;
		$table = Schema::user_sites_table();
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$table}` (network_id, site_id, user_id, roles, updated_at) VALUES (%d, %d, %d, %s, %s) ON DUPLICATE KEY UPDATE roles = VALUES(roles), updated_at = VALUES(updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id ),
				$site_id,
				$user_id,
				implode( ',', array_map( 'strval', $roles ) ),
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Removes one membership.
	 *
	 * @param int      $user_id    User ID.
	 * @param int      $site_id    Blog ID.
	 * @param int|null $network_id Network ID.
	 * @return void
	 */
	public function remove( int $user_id, int $site_id, ?int $network_id = null ): void {
		global $wpdb;
		$wpdb->delete(
			Schema::user_sites_table(),
			array(
				'network_id' => $this->network( $network_id ),
				'site_id'    => $site_id,
				'user_id'    => $user_id,
			),
			array( '%d', '%d', '%d' )
		);
	}

	/**
	 * Drops every membership of a site.
	 *
	 * @param int      $site_id    Blog ID.
	 * @param int|null $network_id Network ID.
	 * @return void
	 */
	public function clear_site( int $site_id, ?int $network_id = null ): void {
		global $wpdb;
		$wpdb->delete(
			Schema::user_sites_table(),
			array(
				'network_id' => $this->network( $network_id ),
				'site_id'    => $site_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Drops every membership of a user (deleted from the network).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function clear_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( Schema::user_sites_table(), array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Memberships of the given users, keyed by user then site.
	 *
	 * @param int[]    $user_ids   User IDs.
	 * @param int|null $network_id Network ID.
	 * @return array<int,array<int,string[]>>
	 */
	public function for_users( array $user_ids, ?int $network_id = null ): array {
		global $wpdb;
		$user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );
		if ( ! $user_ids ) {
			return array();
		}

		$table        = Schema::user_sites_table();
		$placeholders = implode( ', ', array_fill( 0, count( $user_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, site_id, roles FROM `{$table}` WHERE network_id = %d AND user_id IN ({$placeholders}) ORDER BY site_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->network( $network_id ) ), $user_ids )
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['user_id'] ][ (int) $row['site_id'] ] = '' === $row['roles'] ? array() : explode( ',', (string) $row['roles'] );
		}
		return $out;
	}

	/**
	 * Members of one site.
	 *
	 * @param int      $site_id    Blog ID.
	 * @param int|null $network_id Network ID.
	 * @return array<int,string[]> User ID => roles.
	 */
	public function for_site( int $site_id, ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::user_sites_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, roles FROM `{$table}` WHERE network_id = %d AND site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id ),
				$site_id
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['user_id'] ] = '' === $row['roles'] ? array() : explode( ',', (string) $row['roles'] );
		}
		return $out;
	}

	/**
	 * How many users hold each role across the network.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array<string,int> Role slug => memberships.
	 */
	public function role_counts( ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::user_sites_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT roles, COUNT(*) AS total FROM `{$table}` WHERE network_id = %d GROUP BY roles", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id )
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			// A membership with no role slug is counted as "none".
			$roles = '' === $row['roles'] ? array( 'none' ) : explode( ',', (string) $row['roles'] );
			foreach ( $roles as $role ) {
				$counts[ $role ] = ( $counts[ $role ] ?? 0 ) + (int) $row['total'];
			}
		}
		arsort( $counts );
		return $counts;
	}

	/**
	 * Sites whose memberships were not indexed because they have too many users.
	 *
	 * @return int[]
	 */
	public function skipped_sites(): array {
		$skipped = get_site_option( self::SKIPPED_OPTION, array() );
		return is_array( $skipped ) ? array_map( 'intval', $skipped ) : array();
	}

	/**
	 * Role slugs from a stored capabilities value.
	 *
	 * @param mixed $value Raw `{prefix}capabilities` meta value.
	 * @return string[]
	 */
	public static function roles_from_capabilities( $value ): array {
		$caps = maybe_unserialize( $value );
		if ( ! is_array( $caps ) ) {
			return array();
		}
		// Keys are role slugs (true) plus, rarely, individual capabilities.
		$roles = array_keys( array_filter( $caps ) );
		return array_values( array_filter( array_map( 'strval', $roles ), static fn( $role ) => '' !== $role && false === strpos( $role, ',' ) ) );
	}

	/**
	 * Maximum members a site may have before its memberships are skipped.
	 *
	 * @return int
	 */
	public function limit(): int {
		/**
		 * Filters the per-site member limit for the user index.
		 *
		 * Sites above it are listed with a member count but their individual
		 * memberships are not indexed, to keep discovery fast.
		 *
		 * @since 0.4.0
		 *
		 * @param int $limit Members per site.
		 */
		return max( 100, (int) apply_filters( 'wpfleet_user_index_limit', 5000 ) );
	}

	/**
	 * Records or clears a skipped site.
	 *
	 * @param int  $site_id Blog ID.
	 * @param bool $skipped Whether it was skipped.
	 * @return void
	 */
	private function mark_skipped( int $site_id, bool $skipped ): void {
		$list    = $this->skipped_sites();
		$updated = $skipped ? array_unique( array_merge( $list, array( $site_id ) ) ) : array_diff( $list, array( $site_id ) );
		if ( array_values( $updated ) !== array_values( $list ) ) {
			update_site_option( self::SKIPPED_OPTION, array_values( $updated ) );
		}
	}

	/**
	 * Resolves the network ID.
	 *
	 * @param int|null $network_id Network ID.
	 * @return int
	 */
	private function network( $network_id ): int {
		return $network_id ? (int) $network_id : (int) get_current_network_id();
	}
}
