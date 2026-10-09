<?php
/**
 * Per-site database sizes.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

defined( 'ABSPATH' ) || exit;

/**
 * Reads table sizes once per request from information_schema and attributes
 * them to sites by table prefix.
 *
 * Doing it once matters: information_schema is slow on servers with many
 * tables, and a network has one table set per site. When the query is not
 * permitted (restricted hosting), sizes are reported as unavailable rather
 * than as a problem.
 */
final class TableSizes {

	/**
	 * Table name => bytes, or null when sizes could not be read.
	 *
	 * @var array<string,int>|null
	 */
	private ?array $sizes = null;

	private bool $loaded = false;

	/**
	 * Tables that belong to the whole installation, not to the main site.
	 *
	 * @return string[] Suffixes after the base prefix.
	 */
	private static function global_tables(): array {
		return array(
			'users',
			'usermeta',
			'blogs',
			'blogmeta',
			'site',
			'sitemeta',
			'signups',
			'registration_log',
			'sitecategories',
			'wpfleet_sites',
			'wpfleet_operations',
			'wpfleet_user_sites',
		);
	}

	/**
	 * Size in bytes of one site's tables, or null when unavailable.
	 *
	 * @param int $blog_id Blog ID.
	 * @return int|null
	 */
	public function for_site( int $blog_id ): ?int {
		global $wpdb;

		$sizes = $this->sizes();
		if ( null === $sizes ) {
			return null;
		}

		$prefix = $wpdb->get_blog_prefix( $blog_id );
		$base   = $wpdb->base_prefix;
		$total  = 0;

		foreach ( $sizes as $table => $bytes ) {
			if ( 0 !== strpos( $table, $prefix ) ) {
				continue;
			}
			$suffix = substr( $table, strlen( $prefix ) );
			if ( $prefix === $base ) {
				// The main site's prefix also matches sub-site and global tables.
				if ( preg_match( '/^\d+_/', $suffix ) || in_array( $suffix, self::global_tables(), true ) ) {
					continue;
				}
			}
			$total += $bytes;
		}

		return $total;
	}

	/**
	 * Total size of the installation's tables, or null when unavailable.
	 *
	 * @return int|null
	 */
	public function total(): ?int {
		$sizes = $this->sizes();
		return null === $sizes ? null : array_sum( $sizes );
	}

	/**
	 * Loads the size map once.
	 *
	 * @return array<string,int>|null
	 */
	private function sizes(): ?array {
		global $wpdb;

		if ( $this->loaded ) {
			return $this->sizes;
		}
		$this->loaded = true;

		$suppress = $wpdb->suppress_errors( true );
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT TABLE_NAME AS name, (DATA_LENGTH + INDEX_LENGTH) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s',
				DB_NAME
			),
			ARRAY_A
		);
		$wpdb->suppress_errors( $suppress );

		if ( ! $rows ) {
			$this->sizes = null;
			return null;
		}

		$this->sizes = array();
		foreach ( $rows as $row ) {
			$this->sizes[ (string) $row['name'] ] = (int) $row['bytes'];
		}
		return $this->sizes;
	}
}
