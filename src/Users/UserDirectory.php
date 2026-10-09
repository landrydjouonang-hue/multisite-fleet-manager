<?php
/**
 * Network user directory.
 *
 * @package FleetManager
 */

namespace FleetManager\Users;

use FleetManager\Sites\SiteRepository;
use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of the network's users: who they are, which sites they
 * belong to, with which roles, whether they are super admins, and when they
 * were last seen.
 *
 * Only account facts an administrator may see are returned (login, display
 * name, e-mail, registration date, status flags). Passwords, password
 * hashes, activation keys, session tokens and application passwords are
 * never read or returned.
 */
final class UserDirectory {

	public const ORDERBY = array(
		'login'      => 'u.user_login',
		'name'       => 'u.display_name',
		'email'      => 'u.user_email',
		'registered' => 'u.user_registered',
		'sites'      => 'sites',
		'activity'   => 'activity',
	);

	public const STATUSES = array( 'super', 'no_site', 'spam', 'deleted' );

	private UserIndex $index;

	private UserActivity $activity;

	private SiteRepository $sites;

	/**
	 * Super admin user IDs, memoised.
	 *
	 * @var int[]|null
	 */
	private ?array $super_ids = null;

	/**
	 * Constructor.
	 *
	 * @param UserIndex      $index    Membership index.
	 * @param UserActivity   $activity Activity.
	 * @param SiteRepository $sites    Site repository (for site names).
	 */
	public function __construct( UserIndex $index, UserActivity $activity, SiteRepository $sites ) {
		$this->index    = $index;
		$this->activity = $activity;
		$this->sites    = $sites;
	}

	/**
	 * Paginated, filtered list of users.
	 *
	 * @param array<string,mixed> $args search, role, site_id, status, orderby, order, page, per_page.
	 * @return array{items: array<int,array<string,mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'role'     => '',
				'site_id'  => 0,
				'status'   => '',
				'orderby'  => 'login',
				'order'    => 'asc',
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$network = (int) get_current_network_id();
		$index   = Schema::user_sites_table();
		$where   = array( '1=1' );
		$params  = array();

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$role = (string) $args['role'];
		if ( '' !== $role ) {
			$where[]  = "EXISTS (SELECT 1 FROM `{$index}` r WHERE r.user_id = u.ID AND r.network_id = %d AND FIND_IN_SET(%s, r.roles))";
			$params[] = $network;
			$params[] = $role;
		}

		$site_id = (int) $args['site_id'];
		if ( $site_id > 0 ) {
			$where[]  = "EXISTS (SELECT 1 FROM `{$index}` s WHERE s.user_id = u.ID AND s.network_id = %d AND s.site_id = %d)";
			$params[] = $network;
			$params[] = $site_id;
		}

		switch ( $args['status'] ) {
			case 'super':
				$ids = $this->super_admin_ids();
				if ( ! $ids ) {
					return array(
						'items' => array(),
						'total' => 0,
					);
				}
				$where[] = 'u.ID IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')';
				$params  = array_merge( $params, $ids );
				break;
			case 'no_site':
				$where[]  = "NOT EXISTS (SELECT 1 FROM `{$index}` n WHERE n.user_id = u.ID AND n.network_id = %d)";
				$params[] = $network;
				break;
			case 'spam':
				$where[] = 'u.spam = 1';
				break;
			case 'deleted':
				$where[] = 'u.deleted = 1';
				break;
		}

		$orderby  = self::ORDERBY[ $args['orderby'] ] ?? 'u.user_login';
		$order    = 'desc' === strtolower( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );
		$select    = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered, u.spam, u.deleted,
			(SELECT COUNT(*) FROM `{$index}` c WHERE c.user_id = u.ID AND c.network_id = %d) AS sites,
			(SELECT m.meta_value FROM {$wpdb->usermeta} m WHERE m.user_id = u.ID AND m.meta_key = %s LIMIT 1) + 0 AS activity";

		// Placeholders carry every value; table and column names come from constants and whitelists.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->users} u WHERE {$where_sql}";
		// prepare() must not be called without placeholders (no filters active).
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"{$select} FROM {$wpdb->users} u WHERE {$where_sql} ORDER BY {$orderby} {$order}, u.ID ASC LIMIT %d OFFSET %d",
				array_merge( array( $network, UserActivity::META_LAST_LOGIN ), $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => $this->hydrate( (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * One user, or null.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>|null
	 */
	public function find( int $user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT ID, user_login, user_email, display_name, user_registered, spam, deleted FROM {$wpdb->users} WHERE ID = %d", $user_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$items = $this->hydrate( array( $row ) );
		return $items[0] ?? null;
	}

	/**
	 * Adds memberships, roles, activity and super-admin status to rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Raw rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function hydrate( array $rows ): array {
		if ( ! $rows ) {
			return array();
		}

		$ids         = array_map( static fn( $row ) => (int) $row['ID'], $rows );
		$memberships = $this->index->for_users( $ids );
		$activity    = $this->activity->for_users( $ids );
		$supers      = $this->super_admin_ids();

		$site_ids = array();
		foreach ( $memberships as $sites ) {
			$site_ids = array_merge( $site_ids, array_keys( $sites ) );
		}
		$names = $this->sites->names( array_unique( $site_ids ) );

		$items = array();
		foreach ( $rows as $row ) {
			$id    = (int) $row['ID'];
			$mine  = $memberships[ $id ] ?? array();
			$sites = array();
			foreach ( $mine as $site_id => $roles ) {
				$sites[] = array(
					'site_id' => (int) $site_id,
					'name'    => $names[ $site_id ]['name'] ?? '',
					'address' => $names[ $site_id ]['address'] ?? '',
					'roles'   => $roles,
				);
			}

			$items[] = array(
				'id'          => $id,
				'login'       => (string) $row['user_login'],
				'name'        => (string) $row['display_name'],
				'email'       => (string) $row['user_email'],
				'registered'  => (string) $row['user_registered'],
				'spam'        => ! empty( $row['spam'] ),
				'deleted'     => ! empty( $row['deleted'] ),
				'super_admin' => in_array( $id, $supers, true ),
				'site_count'  => count( $sites ),
				'sites'       => $sites,
				'roles'       => $this->role_summary( $mine ),
				'activity'    => $activity[ $id ] ?? array(
					'last_login' => null,
					'source'     => '',
					'sessions'   => 0,
					'site_id'    => 0,
				),
			);
		}

		return $items;
	}

	/**
	 * Role slug => number of sites where the user holds it.
	 *
	 * @param array<int,string[]> $memberships Site ID => roles.
	 * @return array<string,int>
	 */
	private function role_summary( array $memberships ): array {
		$summary = array();
		foreach ( $memberships as $roles ) {
			foreach ( $roles ?: array( 'none' ) as $role ) {
				$summary[ $role ] = ( $summary[ $role ] ?? 0 ) + 1;
			}
		}
		arsort( $summary );
		return $summary;
	}

	/**
	 * Super admin user IDs of this network.
	 *
	 * @return int[]
	 */
	public function super_admin_ids(): array {
		global $wpdb;
		if ( null !== $this->super_ids ) {
			return $this->super_ids;
		}

		$logins = array_filter( array_map( 'strval', (array) get_super_admins() ) );
		if ( ! $logins ) {
			$this->super_ids = array();
			return $this->super_ids;
		}

		$placeholders    = implode( ', ', array_fill( 0, count( $logins ), '%s' ) );
		$this->super_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login IN ({$placeholders})", $logins ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
		return $this->super_ids;
	}

	/**
	 * Headline figures for the directory.
	 *
	 * @return array{total: int, super: int, no_site: int, flagged: int, active: int}
	 */
	public function summary(): array {
		global $wpdb;
		$index   = Schema::user_sites_table();
		$network = (int) get_current_network_id();

		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
		$no_site = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->users} u WHERE NOT EXISTS (SELECT 1 FROM `{$index}` n WHERE n.user_id = u.ID AND n.network_id = %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$network
			)
		);
		$flagged = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE spam = 1 OR deleted = 1" );
		$active  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value + 0 > %d",
				UserActivity::META_LAST_LOGIN,
				time() - 30 * DAY_IN_SECONDS
			)
		);

		return array(
			'total'   => $total,
			'super'   => count( $this->super_admin_ids() ),
			'no_site' => $no_site,
			'flagged' => $flagged,
			'active'  => $active,
		);
	}

	/**
	 * Role label from a slug, falling back to a readable form. Role names are
	 * per site, so the main site's names are used for display.
	 *
	 * @param string $role Role slug.
	 * @return string
	 */
	public static function role_label( string $role ): string {
		if ( 'none' === $role || '' === $role ) {
			return __( 'No role on that site', 'multisite-fleet-manager' );
		}
		$names = wp_roles()->get_names();
		if ( isset( $names[ $role ] ) ) {
			return translate_user_role( $names[ $role ] );
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $role ) );
	}
}
