<?php
/**
 * Scheduled discovery.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

defined( 'ABSPATH' ) || exit;

/**
 * Refreshes the inventory in the background through WP-Cron.
 *
 * Cron is per site on Multisite, so events live on the main site of each
 * network. A run that does not fit in one cron request is resumed by a
 * follow-up single event.
 */
final class DiscoveryScheduler {

	public const HOOK          = 'wpfleet_scheduled_discovery';
	public const CONTINUE_HOOK = 'wpfleet_continue_discovery';

	private SiteDiscovery $discovery;

	/**
	 * Constructor.
	 *
	 * @param SiteDiscovery $discovery Discovery.
	 */
	public function __construct( SiteDiscovery $discovery ) {
		$this->discovery = $discovery;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'handle' ) );
		add_action( self::CONTINUE_HOOK, array( $this, 'handle' ) );
	}

	/**
	 * Runs (or resumes) discovery.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! is_main_site() ) {
			return;
		}

		$current = $this->discovery->current();
		if ( $current && $this->discovery->is_active( $current ) && SiteDiscovery::SOURCE_SCHEDULED !== $current['source'] ) {
			return; // A manual run is being driven from the dashboard.
		}

		$result = $this->discovery->run( SiteDiscovery::SOURCE_SCHEDULED, 20.0 );

		if ( ! $result['done'] && ! wp_next_scheduled( self::CONTINUE_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CONTINUE_HOOK );
		}
	}

	/**
	 * Schedules the recurring event on the current network's main site.
	 *
	 * @param bool $run_soon Also queue a first run within a minute.
	 * @return void
	 */
	public static function schedule( bool $run_soon = false ): void {
		self::on_main_site(
			static function () use ( $run_soon ) {
				if ( ! wp_next_scheduled( self::HOOK ) ) {
					/**
					 * Filters the recurrence of scheduled discovery.
					 *
					 * @since 0.1.0
					 *
					 * @param string $recurrence A registered cron schedule, e.g. "daily".
					 */
					$recurrence = (string) apply_filters( 'wpfleet_discovery_recurrence', 'daily' );
					wp_schedule_event( time() + DAY_IN_SECONDS, $recurrence, self::HOOK );
				}
				if ( $run_soon && ! wp_next_scheduled( self::CONTINUE_HOOK ) ) {
					wp_schedule_single_event( time() + 30, self::CONTINUE_HOOK );
				}
			}
		);
	}

	/**
	 * Removes the events from a network's main site.
	 *
	 * @param int|null $network_id Network ID (current network when null).
	 * @return void
	 */
	public static function unschedule( ?int $network_id = null ): void {
		self::on_main_site(
			static function () {
				wp_clear_scheduled_hook( self::HOOK );
				wp_clear_scheduled_hook( self::CONTINUE_HOOK );
			},
			$network_id
		);
	}

	/**
	 * Runs a callback in the context of a network's main site.
	 *
	 * @param callable $callback   Callback.
	 * @param int|null $network_id Network ID.
	 * @return void
	 */
	private static function on_main_site( callable $callback, ?int $network_id = null ): void {
		$main = (int) get_main_site_id( $network_id );
		if ( ! $main ) {
			return;
		}
		$switched = get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}
		try {
			$callback();
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}
}
