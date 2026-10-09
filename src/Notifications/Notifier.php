<?php
/**
 * E-mail notifications.
 *
 * @package FleetManager
 */

namespace FleetManager\Notifications;

use FleetManager\Admin\Admin;
use FleetManager\Core\Settings;
use FleetManager\Health\Health;
use FleetManager\Operations\Operation;
use FleetManager\Reporting\ReportBuilder;
use FleetManager\Sites\SiteRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a digest of the fleet, and an immediate note when an operation
 * fails.
 *
 * Notifications are off until someone turns them on: a plugin that starts
 * e-mailing a network's administrators on activation is a nuisance. The
 * digest is deliberately short — counts, what changed, and a link — because
 * a long mail that nobody reads is worse than none.
 */
final class Notifier {

	public const DIGEST_HOOK = 'wpfleet_send_digest';
	public const STATE_OPTION = 'wpfleet_notification_state';

	private ReportBuilder $reports;

	private SiteRepository $sites;

	/**
	 * Constructor.
	 *
	 * @param ReportBuilder  $reports Report builder.
	 * @param SiteRepository $sites   Sites.
	 */
	public function __construct( ReportBuilder $reports, SiteRepository $sites ) {
		$this->reports = $reports;
		$this->sites   = $sites;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::DIGEST_HOOK, array( $this, 'send_digest' ) );
		add_action( 'wpfleet_operation_logged', array( $this, 'on_operation' ), 10, 2 );
		add_action( 'wpfleet_settings_saved', array( $this, 'reschedule' ) );
	}

	/**
	 * Schedules (or clears) the digest on the network's main site.
	 *
	 * @return void
	 */
	public function reschedule(): void {
		$main     = (int) get_main_site_id();
		$switched = get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}

		wp_clear_scheduled_hook( self::DIGEST_HOOK );
		if ( Settings::get( 'notifications_enabled' ) ) {
			$recurrence = 'daily' === Settings::get( 'notification_frequency' ) ? 'daily' : 'weekly';
			if ( ! wp_next_scheduled( self::DIGEST_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::DIGEST_HOOK );
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}
	}

	/**
	 * Sends the digest.
	 *
	 * @param bool $force Send even when nothing is noteworthy (used by "send a test").
	 * @return bool Whether an e-mail was sent.
	 */
	public function send_digest( bool $force = false ): bool {
		if ( ! Settings::get( 'notifications_enabled' ) && ! $force ) {
			return false;
		}

		$recipients = Settings::recipients();
		if ( ! $recipients ) {
			return false;
		}

		$summary = $this->reports->summary();
		$errors  = (int) $summary['errors']['sites_with_errors'];
		$updates = (int) $summary['updates']['sites_needing_updates'];

		$notify_errors  = (bool) Settings::get( 'notify_errors' );
		$notify_updates = (bool) Settings::get( 'notify_updates' );
		$noteworthy     = ( $notify_errors && $errors > 0 ) || ( $notify_updates && $updates > 0 );

		if ( ! $noteworthy && ! $force ) {
			$this->remember( 'skipped' );
			return false;
		}

		$subject = sprintf(
			/* translators: 1: Network name, 2: Short status, e.g. "3 sites need attention". */
			__( '[%1$s] Fleet report: %2$s', 'multisite-fleet-manager' ),
			$summary['network']['name'],
			$this->headline( $summary )
		);

		$sent = wp_mail( $recipients, $subject, $this->body( $summary ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
		$this->remember( $sent ? 'sent' : 'failed' );

		return (bool) $sent;
	}

	/**
	 * Mails a short note when an operation fails or is denied.
	 *
	 * @param int                 $id  Log entry ID.
	 * @param array<string,mixed> $row Logged row.
	 * @return void
	 */
	public function on_operation( $id, $row ): void {
		if ( ! Settings::get( 'notifications_enabled' ) || ! Settings::get( 'notify_failed_ops' ) ) {
			return;
		}
		if ( ! is_array( $row ) || Operation::FAILED !== ( $row['status'] ?? '' ) ) {
			return;
		}

		$recipients = Settings::recipients();
		if ( ! $recipients ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: Network name, 2: Operation label. */
			__( '[%1$s] Fleet operation failed: %2$s', 'multisite-fleet-manager' ),
			(string) ( get_network()->site_name ?? '' ),
			Operation::label( (string) $row['operation'] )
		);

		$lines = array(
			sprintf(
				/* translators: 1: Operation, 2: Target. */
				__( '%1$s failed for %2$s.', 'multisite-fleet-manager' ),
				Operation::label( (string) $row['operation'] ),
				(string) ( $row['target_name'] ?: $row['target'] )
			),
			'',
			(string) $row['message'],
			'',
			__( 'Operation log:', 'multisite-fleet-manager' ) . ' ' . Admin::url( Admin::SLUG_LOG, array( 'status' => Operation::FAILED ) ),
		);

		wp_mail( $recipients, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * When the last digest was attempted, and what happened.
	 *
	 * @return array{time: int, result: string}|null
	 */
	public function last(): ?array {
		$state = get_site_option( self::STATE_OPTION, null );
		return is_array( $state ) && isset( $state['time'] ) ? $state : null;
	}

	/**
	 * One-line status for the subject.
	 *
	 * @param array<string,mixed> $summary Report summary.
	 * @return string
	 */
	private function headline( array $summary ): string {
		$errors  = (int) $summary['errors']['sites_with_errors'];
		$updates = (int) $summary['updates']['sites_needing_updates'];

		if ( $errors > 0 ) {
			/* translators: %s: Number of sites. */
			return sprintf( _n( '%s site with errors', '%s sites with errors', $errors, 'multisite-fleet-manager' ), number_format_i18n( $errors ) );
		}
		if ( $updates > 0 ) {
			/* translators: %s: Number of sites. */
			return sprintf( _n( '%s site needs updates', '%s sites need updates', $updates, 'multisite-fleet-manager' ), number_format_i18n( $updates ) );
		}
		return __( 'everything looks healthy', 'multisite-fleet-manager' );
	}

	/**
	 * Plain-text digest body.
	 *
	 * @param array<string,mixed> $summary Report summary.
	 * @return string
	 */
	private function body( array $summary ): string {
		$health = $summary['health'];
		$lines  = array(
			sprintf(
				/* translators: 1: Network name, 2: Network address. */
				__( 'Fleet report for %1$s (%2$s)', 'multisite-fleet-manager' ),
				$summary['network']['name'],
				$summary['network']['address']
			),
			'',
			sprintf( /* translators: %s: Number. */ __( 'Sites: %s', 'multisite-fleet-manager' ), number_format_i18n( (int) $summary['sites']['total'] ) ),
			sprintf( /* translators: %s: Number. */ __( 'Healthy: %s', 'multisite-fleet-manager' ), number_format_i18n( (int) $health['healthy'] ) ),
			sprintf( /* translators: %s: Number. */ __( 'Needs attention: %s', 'multisite-fleet-manager' ), number_format_i18n( (int) $health['attention'] ) ),
			sprintf( /* translators: %s: Number. */ __( 'Errors: %s', 'multisite-fleet-manager' ), number_format_i18n( (int) $health['error'] ) ),
			sprintf( /* translators: %s: Number. */ __( 'Sites needing updates: %s', 'multisite-fleet-manager' ), number_format_i18n( (int) $summary['updates']['sites_needing_updates'] ) ),
			sprintf(
				/* translators: 1: Plugin updates, 2: Theme updates. */
				__( 'Pending updates: %1$s plugin, %2$s theme', 'multisite-fleet-manager' ),
				number_format_i18n( (int) $summary['updates']['plugin_updates'] ),
				number_format_i18n( (int) $summary['updates']['theme_updates'] )
			),
		);

		$worst = $this->sites->query(
			array(
				'health'   => Health::ERROR,
				'per_page' => 5,
			)
		);
		if ( $worst['items'] ) {
			$lines[] = '';
			$lines[] = __( 'Sites with errors:', 'multisite-fleet-manager' );
			foreach ( $worst['items'] as $site ) {
				$lines[] = ' - ' . $site->label() . ' (' . untrailingslashit( $site->domain . $site->path ) . ')';
			}
		}

		$lines[] = '';
		$lines[] = __( 'Full report:', 'multisite-fleet-manager' ) . ' ' . Admin::url( Admin::SLUG_REPORTS );
		$lines[] = '';
		$lines[] = __( 'These are operational indicators, not a security assessment.', 'multisite-fleet-manager' );

		return implode( "\n", $lines );
	}

	/**
	 * Records the outcome of the last digest attempt.
	 *
	 * @param string $result sent|skipped|failed.
	 * @return void
	 */
	private function remember( string $result ): void {
		update_site_option(
			self::STATE_OPTION,
			array(
				'time'   => time(),
				'result' => $result,
			)
		);
	}
}
