<?php
/**
 * WP-Cron status.
 *
 * @package FleetManager
 */

namespace FleetManager\Health\Checks;

use FleetManager\Health\Contracts\HealthCheck;
use FleetManager\Health\FleetContext;
use FleetManager\Health\Indicator;
use FleetManager\Sites\Site;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduled tasks are per site, so each site has its own queue. Events due
 * well in the past mean the queue is not being worked through: on a busy
 * site that is normal for a few minutes, but hours late points at cron not
 * running at all.
 *
 * When DISABLE_WP_CRON is set, an external scheduler is expected to run
 * wp-cron.php. Fleet Manager cannot see that scheduler, so it only reports
 * what the queue looks like.
 */
final class CronCheck implements HealthCheck {

	public function id(): string {
		return 'cron';
	}

	public function label(): string {
		return __( 'Scheduled tasks', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$data = array(
			'events'   => $site->cron_events,
			'overdue'  => $site->cron_overdue,
			'late'     => $site->cron_late_seconds,
			'disabled' => $context->cron_disabled,
			'external' => $context->cron_disabled || $context->alternate_cron,
		);

		/**
		 * Filters how late the oldest event may be before cron is judged broken.
		 *
		 * @since 0.5.0
		 *
		 * @param int $seconds Threshold.
		 */
		$threshold = max( HOUR_IN_SECONDS, (int) apply_filters( 'wpfleet_cron_stuck_after', 6 * HOUR_IN_SECONDS ) );

		if ( $site->cron_late_seconds >= $threshold ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( $site->cron_overdue > 0 ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		$overdue = (int) ( $indicator->data['overdue'] ?? 0 );
		$events  = (int) ( $indicator->data['events'] ?? 0 );
		$late    = (int) ( $indicator->data['late'] ?? 0 );

		$suffix = ! empty( $indicator->data['disabled'] )
			? ' ' . __( 'WP-Cron is disabled in wp-config.php, so an external scheduler should be running wp-cron.php.', 'multisite-fleet-manager' )
			: '';

		switch ( $indicator->state ) {
			case Indicator::ERROR:
				return sprintf(
					/* translators: 1: Number of events, 2: Human-readable duration. */
					_n( '%1$s scheduled task is overdue by %2$s. Scheduled tasks are probably not running on this site.', '%1$s scheduled tasks are overdue, the oldest by %2$s. Scheduled tasks are probably not running on this site.', $overdue, 'multisite-fleet-manager' ),
					number_format_i18n( $overdue ),
					human_time_diff( time() - $late, time() )
				) . $suffix;
			case Indicator::WARNING:
				return sprintf(
					/* translators: 1: Number of events, 2: Human-readable duration. */
					_n( '%1$s scheduled task is waiting (%2$s late). This is normal on a quiet site, which runs cron on its next visit.', '%1$s scheduled tasks are waiting (oldest %2$s late). This is normal on a quiet site, which runs cron on its next visit.', $overdue, 'multisite-fleet-manager' ),
					number_format_i18n( $overdue ),
					human_time_diff( time() - $late, time() )
				) . $suffix;
		}

		return sprintf(
			/* translators: %s: Number of scheduled tasks. */
			_n( '%s scheduled task, none overdue.', '%s scheduled tasks, none overdue.', $events, 'multisite-fleet-manager' ),
			number_format_i18n( $events )
		) . $suffix;
	}
}
