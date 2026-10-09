<?php
/**
 * Database schema.
 *
 * @package FleetManager
 */

namespace FleetManager\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plugin's table definitions.
 *
 * Tables are global (they use $wpdb->base_prefix, like wp_blogs), shared by
 * every network of the installation and keyed by network_id. All datetimes
 * are stored in UTC.
 */
final class Schema {

	/**
	 * Schema version; bump when a definition changes so the upgrader re-runs dbDelta.
	 */
	public const VERSION = '7';

	/**
	 * Option (on the main network) holding the installed schema version.
	 */
	public const VERSION_OPTION = 'wpfleet_db_version';

	/**
	 * Sites table name.
	 *
	 * @return string
	 */
	public static function sites_table(): string {
		global $wpdb;
		return $wpdb->base_prefix . 'wpfleet_sites';
	}

	/**
	 * Operation log table name.
	 *
	 * @return string
	 */
	public static function operations_table(): string {
		global $wpdb;
		return $wpdb->base_prefix . 'wpfleet_operations';
	}

	/**
	 * User membership index table name.
	 *
	 * @return string
	 */
	public static function user_sites_table(): string {
		global $wpdb;
		return $wpdb->base_prefix . 'wpfleet_user_sites';
	}

	/**
	 * Report snapshot table name.
	 *
	 * @return string
	 */
	public static function snapshots_table(): string {
		global $wpdb;
		return $wpdb->base_prefix . 'wpfleet_snapshots';
	}

	/**
	 * All table names.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array( self::sites_table(), self::operations_table(), self::user_sites_table(), self::snapshots_table() );
	}

	/**
	 * Installed schema version.
	 *
	 * @return string
	 */
	public static function installed_version(): string {
		return (string) get_network_option( get_main_network_id(), self::VERSION_OPTION, '' );
	}

	/**
	 * Creates or updates the tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sites   = self::sites_table();

		// dbDelta formatting rules: two spaces after PRIMARY KEY, one field per line.
		dbDelta(
			"CREATE TABLE {$sites} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  network_id bigint(20) unsigned NOT NULL,
  blog_id bigint(20) unsigned NOT NULL,
  domain varchar(200) NOT NULL DEFAULT '',
  path varchar(100) NOT NULL DEFAULT '/',
  name varchar(255) NOT NULL DEFAULT '',
  site_url varchar(255) NOT NULL DEFAULT '',
  home_url varchar(255) NOT NULL DEFAULT '',
  admin_email varchar(100) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  is_main tinyint(1) unsigned NOT NULL DEFAULT 0,
  is_public tinyint(1) unsigned NOT NULL DEFAULT 1,
  is_archived tinyint(1) unsigned NOT NULL DEFAULT 0,
  is_mature tinyint(1) unsigned NOT NULL DEFAULT 0,
  is_spam tinyint(1) unsigned NOT NULL DEFAULT 0,
  is_deleted tinyint(1) unsigned NOT NULL DEFAULT 0,
  locale varchar(20) NOT NULL DEFAULT '',
  theme_stylesheet varchar(191) NOT NULL DEFAULT '',
  theme_name varchar(255) NOT NULL DEFAULT '',
  active_plugins smallint(5) unsigned NOT NULL DEFAULT 0,
  user_count int(10) unsigned NOT NULL DEFAULT 0,
  post_count int(10) unsigned NOT NULL DEFAULT 0,
  page_count int(10) unsigned NOT NULL DEFAULT 0,
  db_version int(10) unsigned NOT NULL DEFAULT 0,
  registered_at datetime DEFAULT NULL,
  last_updated_at datetime DEFAULT NULL,
  discovered_at datetime NOT NULL,
  synced_at datetime NOT NULL,
  sync_run varchar(36) NOT NULL DEFAULT '',
  theme_template varchar(191) NOT NULL DEFAULT '',
  active_plugin_list longtext NULL,
  plugin_updates smallint(5) unsigned DEFAULT NULL,
  theme_updates smallint(5) unsigned DEFAULT NULL,
  health varchar(20) NOT NULL DEFAULT '',
  health_indicators longtext NULL,
  health_checked_at datetime DEFAULT NULL,
  cron_events smallint(5) unsigned NOT NULL DEFAULT 0,
  cron_overdue smallint(5) unsigned NOT NULL DEFAULT 0,
  cron_late_seconds int(10) unsigned NOT NULL DEFAULT 0,
  autoload_bytes bigint(20) unsigned DEFAULT NULL,
  db_size_bytes bigint(20) unsigned DEFAULT NULL,
  upload_used_bytes bigint(20) unsigned DEFAULT NULL,
  upload_quota_bytes bigint(20) unsigned DEFAULT NULL,
  admin_count smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY network_blog (network_id,blog_id),
  KEY network_status (network_id,status),
  KEY network_health (network_id,health),
  KEY network_registered (network_id,registered_at),
  KEY network_updated (network_id,last_updated_at)
) {$charset};"
		);

		$operations = self::operations_table();

		// Append-only audit log. site_id 0 = network-wide operation.
		dbDelta(
			"CREATE TABLE {$operations} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  network_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  operation varchar(40) NOT NULL,
  target_type varchar(20) NOT NULL DEFAULT '',
  target varchar(255) NOT NULL DEFAULT '',
  target_name varchar(255) NOT NULL DEFAULT '',
  site_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL,
  from_version varchar(50) NOT NULL DEFAULT '',
  to_version varchar(50) NOT NULL DEFAULT '',
  message text NULL,
  context longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY network_created (network_id,created_at),
  KEY network_status (network_id,status),
  KEY network_operation (network_id,operation),
  KEY network_site (network_id,site_id)
) {$charset};"
		);

		$user_sites = self::user_sites_table();

		/*
		 * Index of "which user has which role on which site". The source of
		 * truth stays in usermeta; this is a queryable copy, refreshed by
		 * discovery and by core's user hooks. No credentials are stored.
		 */
		dbDelta(
			"CREATE TABLE {$user_sites} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  network_id bigint(20) unsigned NOT NULL,
  site_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  roles varchar(255) NOT NULL DEFAULT '',
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY network_site_user (network_id,site_id,user_id),
  KEY network_user (network_id,user_id),
  KEY network_site (network_id,site_id),
  KEY network_roles (network_id,roles(60))
) {$charset};"
		);

		$snapshots = self::snapshots_table();

		/*
		 * One row per captured report: the headline figures as columns so
		 * trends can be queried, plus the full summary as JSON.
		 */
		dbDelta(
			"CREATE TABLE {$snapshots} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  network_id bigint(20) unsigned NOT NULL,
  captured_at datetime NOT NULL,
  source varchar(20) NOT NULL DEFAULT 'scheduled',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sites int(10) unsigned NOT NULL DEFAULT 0,
  healthy int(10) unsigned NOT NULL DEFAULT 0,
  attention int(10) unsigned NOT NULL DEFAULT 0,
  error int(10) unsigned NOT NULL DEFAULT 0,
  unchecked int(10) unsigned NOT NULL DEFAULT 0,
  sites_needing_updates int(10) unsigned NOT NULL DEFAULT 0,
  plugin_updates int(10) unsigned NOT NULL DEFAULT 0,
  theme_updates int(10) unsigned NOT NULL DEFAULT 0,
  data longtext NULL,
  PRIMARY KEY  (id),
  KEY network_captured (network_id,captured_at)
) {$charset};"
		);

		update_network_option( get_main_network_id(), self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Whether every table exists.
	 *
	 * @return bool
	 */
	public static function exists(): bool {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Drops the tables.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			// Table names come from $wpdb->base_prefix and constants, never from input.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
		delete_network_option( get_main_network_id(), self::VERSION_OPTION );
	}
}
