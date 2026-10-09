<?php
/**
 * Site inventory persistence.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

use FleetManager\Health\Health;
use FleetManager\Health\Indicator;
use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the {base_prefix}wpfleet_sites table.
 *
 * Every method is scoped to one network (the current one by default), so a
 * multi-network installation never mixes inventories.
 */
final class SiteRepository {

	/**
	 * Sort keys → SQL expressions (whitelist; never built from input).
	 * "health" sorts by severity: error, needs attention, healthy, not checked.
	 */
	public const ORDERBY = array(
		'blog_id'         => 'blog_id',
		'name'            => 'name',
		'domain'          => 'domain',
		'status'          => 'status',
		'active_plugins'  => 'active_plugins',
		'user_count'      => 'user_count',
		'post_count'      => 'post_count',
		'registered_at'   => 'registered_at',
		'last_updated_at' => 'last_updated_at',
		'synced_at'       => 'synced_at',
		'health'          => "CASE health WHEN 'error' THEN 0 WHEN 'attention' THEN 1 WHEN 'healthy' THEN 2 ELSE 3 END",
		'theme'           => 'theme_name',
		'plugin_updates'  => 'COALESCE(plugin_updates, -1)',
		'theme_updates'   => 'COALESCE(theme_updates, -1)',
		'db_version'      => 'db_version',
	);

	/**
	 * Update filters → SQL conditions (whitelist).
	 */
	public const UPDATE_FILTERS = array(
		'plugins' => 'plugin_updates > 0',
		'themes'  => 'theme_updates > 0',
		'any'     => '(plugin_updates > 0 OR theme_updates > 0)',
		'none'    => '(plugin_updates = 0 AND theme_updates = 0)',
	);

	/**
	 * Inserts or updates a site. The first-seen date is kept on update.
	 *
	 * @param Site   $site Site.
	 * @param string $run  Discovery run that saw the site ('' for real-time syncs).
	 * @return bool
	 */
	public function save( Site $site, string $run = '' ): bool {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$data = $site->to_row();

		$data['discovered_at'] = $data['discovered_at'] ?? $now;
		$data['synced_at']     = $now;
		$data['sync_run']      = $run;

		$columns      = array();
		$placeholders = array();
		$values       = array();
		$updates      = array();

		foreach ( $data as $column => $value ) {
			$columns[] = "`{$column}`";
			if ( null === $value ) {
				$placeholders[] = 'NULL';
			} else {
				$placeholders[] = is_int( $value ) || is_bool( $value ) ? '%d' : '%s';
				$values[]       = is_bool( $value ) ? (int) $value : $value;
			}
			if ( ! in_array( $column, array( 'network_id', 'blog_id', 'discovered_at' ), true ) ) {
				$updates[] = "`{$column}` = VALUES(`{$column}`)";
			}
		}

		$table = Schema::sites_table();
		$sql   = "INSERT INTO `{$table}` (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $placeholders ) . ') ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates );

		// Column names come from Site::columns(); all values are bound by prepare().
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		return false !== $result;
	}

	/**
	 * Finds a site by blog ID.
	 *
	 * @param int      $blog_id    Blog ID.
	 * @param int|null $network_id Network ID (current network when null).
	 * @return Site|null
	 */
	public function find( int $blog_id, ?int $network_id = null ): ?Site {
		global $wpdb;
		$table = Schema::sites_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE network_id = %d AND blog_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id ),
				$blog_id
			),
			ARRAY_A
		);
		return $row ? Site::from_array( $row ) : null;
	}

	/**
	 * Removes a site from the inventory.
	 *
	 * @param int      $blog_id    Blog ID.
	 * @param int|null $network_id Network ID.
	 * @return bool
	 */
	public function delete( int $blog_id, ?int $network_id = null ): bool {
		global $wpdb;
		return (bool) $wpdb->delete(
			Schema::sites_table(),
			array(
				'network_id' => $this->network( $network_id ),
				'blog_id'    => $blog_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Removes sites that a completed discovery run did not see.
	 *
	 * Rows saved in real time while the run was in progress are kept.
	 *
	 * @param string   $run        Run ID.
	 * @param string   $started_at Run start (UTC).
	 * @param int|null $network_id Network ID.
	 * @return int Number of rows removed.
	 */
	public function delete_stale( string $run, string $started_at, ?int $network_id = null ): int {
		global $wpdb;
		$table = Schema::sites_table();
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE network_id = %d AND sync_run <> %s AND synced_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id ),
				$run,
				$started_at
			)
		);
	}

	/**
	 * Paginated, filtered list.
	 *
	 * @param array<string,mixed> $args {
	 *     @type string $status   One of SiteStatus::all(), or '' for all.
	 *     @type string $search   Matches name, domain or path.
	 *     @type string $orderby  Key of self::ORDERBY.
	 *     @type string $order    ASC|DESC.
	 *     @type int    $per_page Items per page (1–200).
	 *     @type int    $page     Page number (1-based).
	 *     @type int    $network_id Network ID.
	 * }
	 * @return array{items: Site[], total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'     => '',
				'health'     => '',
				'updates'    => '',
				'theme'      => '',
				'plugin'     => '',
				'indicator'  => '',
				'state'      => '',
				'search'     => '',
				'orderby'    => 'blog_id',
				'order'      => 'ASC',
				'per_page'   => 20,
				'page'       => 1,
				'network_id' => null,
			)
		);

		$where  = array( 'network_id = %d' );
		$params = array( $this->network( $args['network_id'] ) );

		if ( in_array( $args['status'], SiteStatus::all(), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( in_array( $args['health'], Health::all(), true ) ) {
			$where[]  = 'health = %s';
			$params[] = $args['health'];
		}

		if ( isset( self::UPDATE_FILTERS[ $args['updates'] ] ) ) {
			$where[] = self::UPDATE_FILTERS[ $args['updates'] ];
		}

		$theme = (string) $args['theme'];
		if ( '' !== $theme ) {
			// Matches the active theme or, for child themes, the parent.
			$where[]  = '(theme_stylesheet = %s OR theme_template = %s)';
			$params[] = $theme;
			$params[] = $theme;
		}

		$indicator = (string) $args['indicator'];
		if ( '' !== $indicator ) {
			// Indicators are stored as {"id":"…","state":"…","data":…} in order.
			$state    = in_array( $args['state'], Indicator::states(), true ) ? $args['state'] : '';
			$needle   = '"id":"' . $indicator . '","state":"' . $state;
			$where[]  = 'health_indicators LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}

		$plugin = (string) $args['plugin'];
		if ( '' !== $plugin ) {
			// Exact element match in the stored JSON array (quotes delimit the basename).
			$where[]  = 'active_plugin_list LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) wp_json_encode( $plugin ) ) . '%';
		}

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(name LIKE %s OR domain LIKE %s OR path LIKE %s OR theme_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby  = self::ORDERBY[ $args['orderby'] ] ?? 'blog_id';
		$order    = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$table     = Schema::sites_table();
		$where_sql = implode( ' AND ', $where );

		// $table, $where_sql, $orderby and $order are built from constants and whitelists only.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$orderby} {$order}, blog_id ASC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => array_map( array( Site::class, 'from_array' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Number of sites per status, plus 'total'.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array<string,int>
	 */
	public function count_by_status( ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::sites_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS total FROM `{$table}` WHERE network_id = %d GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id )
			),
			ARRAY_A
		);

		$counts = array_fill_keys( SiteStatus::all(), 0 );
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['total'];
			}
		}
		$counts['total'] = array_sum( $counts );
		return $counts;
	}

	/**
	 * Aggregate figures for the dashboard.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array{public: int, mature: int, posts: int, pages: int, plugin_activations: int, oldest_sync: string|null}
	 */
	public function totals( ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::sites_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT SUM(is_public) AS public, SUM(is_mature) AS mature, SUM(post_count) AS posts, SUM(page_count) AS pages, SUM(active_plugins) AS plugin_activations, MIN(synced_at) AS oldest_sync FROM `{$table}` WHERE network_id = %d",
				$this->network( $network_id )
			),
			ARRAY_A
		);
		return array(
			'public'             => (int) ( $row['public'] ?? 0 ),
			'mature'             => (int) ( $row['mature'] ?? 0 ),
			'posts'              => (int) ( $row['posts'] ?? 0 ),
			'pages'              => (int) ( $row['pages'] ?? 0 ),
			'plugin_activations' => (int) ( $row['plugin_activations'] ?? 0 ),
			'oldest_sync'        => $row['oldest_sync'] ?? null,
		);
	}

	/**
	 * Most recent sites by a date column.
	 *
	 * @param string   $column     'registered_at' or 'last_updated_at'.
	 * @param int      $limit      Limit.
	 * @param int|null $network_id Network ID.
	 * @return Site[]
	 */
	public function latest( string $column, int $limit = 5, ?int $network_id = null ): array {
		$column = in_array( $column, array( 'registered_at', 'last_updated_at' ), true ) ? $column : 'registered_at';
		$result = $this->query(
			array(
				'orderby'    => $column,
				'order'      => 'DESC',
				'per_page'   => $limit,
				'network_id' => $network_id,
			)
		);
		return $result['items'];
	}

	/**
	 * Most used themes.
	 *
	 * @param int      $limit      Limit.
	 * @param int|null $network_id Network ID.
	 * @return array<int,array{stylesheet: string, name: string, sites: int}>
	 */
	public function theme_usage( int $limit = 5, ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::sites_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT theme_stylesheet AS stylesheet, MAX(theme_name) AS name, COUNT(*) AS sites FROM `{$table}` WHERE network_id = %d AND theme_stylesheet <> '' GROUP BY theme_stylesheet ORDER BY sites DESC, stylesheet ASC LIMIT %d",
				$this->network( $network_id ),
				max( 1, $limit )
			),
			ARRAY_A
		);
		return array_map(
			static fn( $row ) => array(
				'stylesheet' => (string) $row['stylesheet'],
				'name'       => (string) $row['name'],
				'sites'      => (int) $row['sites'],
			),
			(array) $rows
		);
	}

	/**
	 * Number of sites per health status, plus 'unchecked' and 'total'.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array<string,int>
	 */
	public function count_by_health( ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::sites_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT health, COUNT(*) AS total FROM `{$table}` WHERE network_id = %d GROUP BY health", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id )
			),
			ARRAY_A
		);

		$counts = array_fill_keys( Health::all(), 0 ) + array( 'unchecked' => 0 );
		foreach ( (array) $rows as $row ) {
			$key             = isset( $counts[ $row['health'] ] ) ? $row['health'] : 'unchecked';
			$counts[ $key ] += (int) $row['total'];
		}
		$counts['total'] = array_sum( $counts );
		return $counts;
	}

	/**
	 * Update figures across the network.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array{plugin_updates: int, theme_updates: int, sites_with_plugin_updates: int, sites_with_theme_updates: int, unknown: int}
	 */
	public function update_summary( ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::sites_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT SUM(plugin_updates) AS plugin_updates, SUM(theme_updates) AS theme_updates, SUM(plugin_updates > 0) AS sites_plugins, SUM(theme_updates > 0) AS sites_themes, SUM(plugin_updates IS NULL OR theme_updates IS NULL) AS unknown FROM `{$table}` WHERE network_id = %d",
				$this->network( $network_id )
			),
			ARRAY_A
		);
		return array(
			'plugin_updates'            => (int) ( $row['plugin_updates'] ?? 0 ),
			'theme_updates'             => (int) ( $row['theme_updates'] ?? 0 ),
			'sites_with_plugin_updates' => (int) ( $row['sites_plugins'] ?? 0 ),
			'sites_with_theme_updates'  => (int) ( $row['sites_themes'] ?? 0 ),
			'unknown'                   => (int) ( $row['unknown'] ?? 0 ),
		);
	}

	/**
	 * Names and addresses of the given sites, for display.
	 *
	 * @param int[]    $blog_ids   Blog IDs.
	 * @param int|null $network_id Network ID.
	 * @return array<int,array{name: string, address: string}> Keyed by blog ID.
	 */
	public function names( array $blog_ids, ?int $network_id = null ): array {
		global $wpdb;
		$blog_ids = array_values( array_filter( array_map( 'intval', $blog_ids ) ) );
		if ( ! $blog_ids ) {
			return array();
		}

		$table        = Schema::sites_table();
		$placeholders = implode( ', ', array_fill( 0, count( $blog_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT blog_id, name, domain, path FROM `{$table}` WHERE network_id = %d AND blog_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->network( $network_id ) ), $blog_ids )
			),
			ARRAY_A
		);

		$names = array();
		foreach ( (array) $rows as $row ) {
			$names[ (int) $row['blog_id'] ] = array(
				'name'    => (string) $row['name'],
				'address' => untrailingslashit( $row['domain'] . $row['path'] ),
			);
		}
		return $names;
	}

	/**
	 * Sites after a row ID, for batch processing.
	 *
	 * @param int      $after_id   Last processed row ID.
	 * @param int      $limit      Batch size.
	 * @param int|null $network_id Network ID.
	 * @return array<int,Site> Keyed by row ID.
	 */
	public function chunk( int $after_id, int $limit, ?int $network_id = null ): array {
		global $wpdb;
		$table = Schema::sites_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE network_id = %d AND id > %d ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->network( $network_id ),
				$after_id,
				max( 1, $limit )
			),
			ARRAY_A
		);
		$sites = array();
		foreach ( (array) $rows as $row ) {
			$sites[ (int) $row['id'] ] = Site::from_array( $row );
		}
		return $sites;
	}

	/**
	 * How many sites are in each state for each health indicator, plus the
	 * environment figures worth summarising across the fleet.
	 *
	 * Read in batches from the stored indicators: no site switching, and one
	 * pass whatever the fleet size.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array{indicators: array<string,array<string,int>>, totals: array<string,int|null>}
	 */
	public function health_summary( ?int $network_id = null ): array {
		global $wpdb;

		$table      = Schema::sites_table();
		$network    = $this->network( $network_id );
		$after      = 0;
		$indicators = array();
		$totals     = array(
			'sites'        => 0,
			'https'        => 0,
			'cron_overdue' => 0,
			'autoload'     => 0,
			'db_size'      => 0,
			'uploads'      => 0,
			'measured_db'  => 0,
		);

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT id, health_indicators, home_url, cron_overdue, autoload_bytes, db_size_bytes, upload_used_bytes FROM `{$table}` WHERE network_id = %d AND id > %d ORDER BY id ASC LIMIT 500",
					$network,
					$after
				),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				$after = (int) $row['id'];
				++$totals['sites'];

				$totals['https']        += 0 === strpos( strtolower( (string) $row['home_url'] ), 'https://' ) ? 1 : 0;
				$totals['cron_overdue'] += (int) $row['cron_overdue'] > 0 ? 1 : 0;
				$totals['autoload']     += (int) $row['autoload_bytes'];
				$totals['uploads']      += (int) $row['upload_used_bytes'];
				if ( null !== $row['db_size_bytes'] ) {
					$totals['db_size'] += (int) $row['db_size_bytes'];
					++$totals['measured_db'];
				}

				$stored = json_decode( (string) $row['health_indicators'], true );
				foreach ( is_array( $stored ) ? $stored : array() as $item ) {
					if ( empty( $item['id'] ) || empty( $item['state'] ) ) {
						continue;
					}
					$indicators[ $item['id'] ][ $item['state'] ] = ( $indicators[ $item['id'] ][ $item['state'] ] ?? 0 ) + 1;
				}
			}
		} while ( count( (array) $rows ) === 500 );

		if ( ! $totals['measured_db'] ) {
			$totals['db_size'] = null;
		}

		return array(
			'indicators' => $indicators,
			'totals'     => $totals,
		);
	}

	/**
	 * How many sites use each plugin and theme, read in batches with only the
	 * needed columns.
	 *
	 * @param int|null $network_id Network ID.
	 * @return array{plugins: array<string,int>, themes: array<string,int>, parents: array<string,int>}
	 */
	public function extension_usage( ?int $network_id = null ): array {
		global $wpdb;
		$table   = Schema::sites_table();
		$usage   = array(
			'plugins' => array(),
			'themes'  => array(),
			'parents' => array(),
		);
		$after   = 0;
		$network = $this->network( $network_id );

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, active_plugin_list, theme_stylesheet, theme_template FROM `{$table}` WHERE network_id = %d AND id > %d ORDER BY id ASC LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$network,
					$after
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				$after   = (int) $row['id'];
				$plugins = json_decode( (string) $row['active_plugin_list'], true );
				foreach ( is_array( $plugins ) ? $plugins : array() as $plugin ) {
					$usage['plugins'][ (string) $plugin ] = ( $usage['plugins'][ (string) $plugin ] ?? 0 ) + 1;
				}
				if ( '' !== $row['theme_stylesheet'] ) {
					$usage['themes'][ $row['theme_stylesheet'] ] = ( $usage['themes'][ $row['theme_stylesheet'] ] ?? 0 ) + 1;
				}
				if ( '' !== $row['theme_template'] && $row['theme_template'] !== $row['theme_stylesheet'] ) {
					$usage['parents'][ $row['theme_template'] ] = ( $usage['parents'][ $row['theme_template'] ] ?? 0 ) + 1;
				}
			}
		} while ( count( (array) $rows ) === 500 );

		return $usage;
	}

	/**
	 * Stores only the health-related columns of a row.
	 *
	 * @param int  $id   Row ID.
	 * @param Site $site Evaluated site.
	 * @return bool
	 */
	public function save_health( int $id, Site $site ): bool {
		global $wpdb;
		$row = $site->to_row();
		return false !== $wpdb->update(
			Schema::sites_table(),
			array(
				'plugin_updates'    => $row['plugin_updates'],
				'theme_updates'     => $row['theme_updates'],
				'health'            => $row['health'],
				'health_indicators' => $row['health_indicators'],
				'health_checked_at' => $row['health_checked_at'],
			),
			array( 'id' => $id ),
			array( '%d', '%d', '%s', '%s', '%s' ), // wpdb writes PHP null as SQL NULL whatever the format.
			array( '%d' )
		);
	}

	/**
	 * Number of stored sites.
	 *
	 * @param int|null $network_id Network ID.
	 * @return int
	 */
	public function count( ?int $network_id = null ): int {
		return $this->count_by_status( $network_id )['total'];
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
