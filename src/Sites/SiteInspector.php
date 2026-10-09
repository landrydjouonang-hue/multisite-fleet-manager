<?php
/**
 * Reads the live state of one site.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

use FleetManager\Health\HealthEvaluator;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a Site snapshot from core's network tables and the site's own options.
 *
 * Read-only: it switches into the site to read options and counts, and always
 * restores the previous site, even when a read fails.
 */
final class SiteInspector {

	private ?HealthEvaluator $health;

	private TableSizes $tables;

	/**
	 * Constructor.
	 *
	 * @param HealthEvaluator|null $health Evaluator applied to every snapshot.
	 * @param TableSizes|null      $tables Database size reader.
	 */
	public function __construct( ?HealthEvaluator $health = null, ?TableSizes $tables = null ) {
		$this->health = $health;
		$this->tables = $tables ?? new TableSizes();
	}

	/**
	 * Inspects a site.
	 *
	 * @param int $blog_id Blog ID.
	 * @return Site|null Null when the site does not exist.
	 */
	public function inspect( int $blog_id ): ?Site {
		global $wpdb;

		$wp_site = get_site( $blog_id );
		if ( ! $wp_site instanceof \WP_Site ) {
			return null;
		}

		$site              = new Site();
		$site->network_id  = (int) $wp_site->network_id;
		$site->blog_id     = (int) $wp_site->blog_id;
		$site->domain      = (string) $wp_site->domain;
		$site->path        = (string) $wp_site->path;
		$site->is_public   = (bool) (int) $wp_site->public;
		$site->is_archived = (bool) (int) $wp_site->archived;
		$site->is_mature   = (bool) (int) $wp_site->mature;
		$site->is_spam     = (bool) (int) $wp_site->spam;
		$site->is_deleted  = (bool) (int) $wp_site->deleted;
		$site->is_main     = is_main_site( $site->blog_id, $site->network_id );
		$site->status      = SiteStatus::from_flags( $site->is_deleted, $site->is_spam, $site->is_archived );

		$site->registered_at   = Site::normalize_datetime( $wp_site->registered );
		$site->last_updated_at = Site::normalize_datetime( $wp_site->last_updated );

		$suppress = $wpdb->suppress_errors( true );
		switch_to_blog( $site->blog_id );

		try {
			$site->name        = wp_strip_all_tags( (string) get_option( 'blogname', '' ) );
			$site->site_url    = (string) get_option( 'siteurl', '' );
			$site->home_url    = (string) get_option( 'home', '' );
			$site->admin_email = (string) get_option( 'admin_email', '' );
			$site->locale      = $this->locale();
			$site->db_version  = (int) get_option( 'db_version', 0 );

			$plugins                  = get_option( 'active_plugins', array() );
			$site->active_plugin_list = is_array( $plugins ) ? array_values( array_filter( array_map( 'strval', $plugins ) ) ) : array();
			$site->active_plugins     = count( $site->active_plugin_list );

			$stylesheet             = (string) get_option( 'stylesheet', '' );
			$site->theme_stylesheet = $stylesheet;
			$site->theme_template   = (string) get_option( 'template', '' );
			if ( '' !== $stylesheet ) {
				$theme            = wp_get_theme( $stylesheet );
				$site->theme_name = $theme->exists() ? (string) $theme->get( 'Name' ) : $stylesheet;
			}

			$site->user_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$wpdb->get_blog_prefix( $site->blog_id ) . 'capabilities'
				)
			);

			// Administrators of this site, from the same capabilities meta.
			$site->admin_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
					$wpdb->get_blog_prefix( $site->blog_id ) . 'capabilities',
					'%' . $wpdb->esc_like( '"administrator"' ) . '%'
				)
			);

			$this->read_cron( $site );
			$this->read_database( $site );
			$this->read_upload_space( $site );

			$counts = $wpdb->get_results(
				"SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page') GROUP BY post_type",
				OBJECT_K
			);
			$site->post_count = isset( $counts['post'] ) ? (int) $counts['post']->total : 0;
			$site->page_count = isset( $counts['page'] ) ? (int) $counts['page']->total : 0;
		} finally {
			restore_current_blog();
			$wpdb->suppress_errors( $suppress );
		}

		/**
		 * Filters a site snapshot before it is stored.
		 *
		 * @since 0.1.0
		 *
		 * @param Site     $site    Snapshot.
		 * @param \WP_Site $wp_site Core site object.
		 */
		$filtered = apply_filters( 'wpfleet_inspected_site', $site, $wp_site );
		$site     = $filtered instanceof Site ? $filtered : $site;

		// Health is judged after the filter so that adjusted snapshots are scored.
		return $this->health ? $this->health->apply( $site ) : $site;
	}

	/**
	 * Scheduled events of the current (switched) site, and how late they are.
	 *
	 * WP-Cron stores its queue in the `cron` option, so this is a plain read.
	 * Events due a while ago suggest cron is not running on that site.
	 *
	 * @param Site $site Site being inspected.
	 * @return void
	 */
	private function read_cron( Site $site ): void {
		$cron = get_option( 'cron' );
		if ( ! is_array( $cron ) ) {
			return;
		}

		/**
		 * Filters how late an event must be before it counts as overdue.
		 *
		 * @since 0.5.0
		 *
		 * @param int $seconds Grace period.
		 */
		$grace  = max( 60, (int) apply_filters( 'wpfleet_cron_grace_period', 15 * MINUTE_IN_SECONDS ) );
		$now    = time();
		$events = 0;
		$late   = 0;
		$oldest = 0;

		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! is_numeric( $timestamp ) || ! is_array( $hooks ) ) {
				continue; // The array also holds a "version" key.
			}
			foreach ( $hooks as $args ) {
				$count   = is_array( $args ) ? count( $args ) : 1;
				$events += $count;
				if ( (int) $timestamp < $now - $grace ) {
					$late  += $count;
					$oldest = max( $oldest, $now - (int) $timestamp );
				}
			}
		}

		$site->cron_events       = $events;
		$site->cron_overdue      = $late;
		$site->cron_late_seconds = $oldest;
	}

	/**
	 * Database indicators of the current (switched) site.
	 *
	 * @param Site $site Site being inspected.
	 * @return void
	 */
	private function read_database( Site $site ): void {
		global $wpdb;

		$values = function_exists( 'wp_autoload_values_to_autoload' )
			? wp_autoload_values_to_autoload()
			: array( 'yes' );
		$in     = implode( ', ', array_fill( 0, count( $values ), '%s' ) );

		$bytes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ({$in})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values
			)
		);

		$site->autoload_bytes = null === $bytes ? null : (int) $bytes;
		$site->db_size_bytes  = $this->tables->for_site( $site->blog_id );
	}

	/**
	 * Upload space used and allowed, when the network enforces quotas.
	 *
	 * Measuring used space walks the uploads folder, so it is only done when
	 * the network actually enforces a quota (core measures it then anyway).
	 *
	 * @param Site $site Site being inspected.
	 * @return void
	 */
	private function read_upload_space( Site $site ): void {
		$enforced = ! get_site_option( 'upload_space_check_disabled' );

		/**
		 * Filters whether upload space is measured during discovery.
		 *
		 * @since 0.5.0
		 *
		 * @param bool $measure Defaults to true only when quotas are enforced.
		 * @param int  $blog_id Blog ID.
		 */
		if ( ! apply_filters( 'wpfleet_measure_upload_space', $enforced, $site->blog_id ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/ms.php';

		$allowed = (int) get_space_allowed();
		$used    = (float) get_space_used();

		$site->upload_quota_bytes = $allowed > 0 ? $allowed * MB_IN_BYTES : null;
		$site->upload_used_bytes  = (int) round( $used * MB_IN_BYTES );
	}

	/**
	 * Locale of the current (switched) site.
	 *
	 * get_locale() is computed once per request, so it cannot be trusted after
	 * switch_to_blog(); read the options instead. Empty means en_US in core.
	 *
	 * @return string
	 */
	private function locale(): string {
		$locale = (string) get_option( 'WPLANG', '' );
		if ( '' === $locale ) {
			$locale = (string) get_site_option( 'WPLANG', '' );
		}
		return '' === $locale ? 'en_US' : $locale;
	}
}
