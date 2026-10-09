<?php
/**
 * Form handlers (no-JavaScript fallbacks).
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;
use FleetManager\Plugin;
use FleetManager\Sites\DiscoveryInProgressException;
use FleetManager\Sites\SiteDiscovery;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Network Admin form posts via network/edit.php?action=…
 */
final class Actions {

	public const DISCOVER  = 'wpfleet_discover';
	public const OPERATION = 'wpfleet_operation';
	public const EXPORT    = 'wpfleet_export';

	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'network_admin_edit_' . self::DISCOVER, array( $this, 'discover' ) );
		add_action( 'network_admin_edit_' . self::OPERATION, array( $this, 'operation' ) );
		add_action( 'network_admin_edit_' . self::EXPORT, array( $this, 'export' ) );
	}

	/**
	 * Streams a CSV export.
	 *
	 * Read-only, so it uses a nonce in the link plus the reports capability.
	 *
	 * @return void
	 */
	public function export(): void {
		check_admin_referer( self::EXPORT );

		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export reports.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'summary';

		if ( 'diagnostics' === $type ) {
			if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to export diagnostics.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
			}
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="fleet-diagnostics-' . gmdate( 'Y-m-d' ) . '.txt"' );
			echo ( new DiagnosticsPage( $this->plugin ) )->report(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text download.
			exit;
		}

		$this->plugin->csv()->stream( $type );
		exit;
	}

	/**
	 * URL of a CSV export.
	 *
	 * @param string $type Export type.
	 * @return string
	 */
	public static function export_url( string $type ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::EXPORT,
					'type'   => $type,
				),
				network_admin_url( 'edit.php' )
			),
			self::EXPORT
		);
	}

	/**
	 * Runs one or several operations from a form post, then returns to the page.
	 *
	 * Fields:
	 * - single     "operation|target" (a row button), or
	 * - operation + items[] (bulk);
	 * - site_id    for per-site operations;
	 * - nonce[op]  one nonce per operation used on the form;
	 * - return     page to come back to (must be a Fleet Manager page).
	 *
	 * Permission, nonce, target validation and logging are enforced for each
	 * item by OperationService.
	 *
	 * @return void
	 */
	public function operation(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Each operation's nonce is verified by OperationService.
		$post    = wp_unslash( $_POST );
		$site_id = isset( $post['site_id'] ) ? absint( $post['site_id'] ) : 0;
		$nonces  = isset( $post['nonce'] ) && is_array( $post['nonce'] ) ? array_map( 'sanitize_text_field', $post['nonce'] ) : array();
		// phpcs:enable

		$jobs = array();
		if ( ! empty( $post['single'] ) && is_string( $post['single'] ) && false !== strpos( $post['single'], '|' ) ) {
			list( $op, $target ) = explode( '|', sanitize_text_field( $post['single'] ), 2 );
			$jobs[]              = array( $op, $target );
		} elseif ( ! empty( $post['operation'] ) && ! empty( $post['items'] ) && is_array( $post['items'] ) ) {
			$op = sanitize_key( $post['operation'] );
			foreach ( array_slice( $post['items'], 0, 200 ) as $item ) {
				$jobs[] = array( $op, sanitize_text_field( (string) $item ) );
			}
		}

		$return = isset( $post['return'] ) ? esc_url_raw( (string) $post['return'] ) : '';
		if ( 0 !== strpos( $return, network_admin_url( 'admin.php?page=multisite-fleet-manager' ) ) ) {
			$return = Admin::url( Admin::SLUG_EXTENSIONS );
		}

		$results = array();
		$started = microtime( true );

		foreach ( $jobs as $index => $job ) {
			list( $op, $target ) = $job;

			// Long bulk runs stop starting new items after ~25 s; the rest are reported, not attempted.
			if ( $index > 0 && microtime( true ) - $started > 25 ) {
				$results[] = array(
					'status'  => 'skipped',
					'message' => sprintf(
						/* translators: %s: Plugin or theme. */
						__( '%s was not attempted (time limit reached). Run it again.', 'multisite-fleet-manager' ),
						$target
					),
				);
				continue;
			}

			if ( Operation::SETTINGS_UPDATE === $op ) {
				$args = array( 'settings' => isset( $post['settings'] ) && is_array( $post['settings'] ) ? $post['settings'] : array() );
			} elseif ( Operation::ACCESS_UPDATE === $op ) {
				$args = array(
					'user_id'    => (int) $target > 0 ? (int) $target : (int) ( $post['user_id'] ?? 0 ),
					'user_login' => isset( $post['user_login'] ) ? sanitize_text_field( (string) $post['user_login'] ) : '',
					'caps'       => isset( $post['caps'] ) && is_array( $post['caps'] ) ? array_map( 'sanitize_key', $post['caps'] ) : array(),
				);
			} elseif ( Operation::SNAPSHOT_CAPTURE === $op ) {
				$args = array();
			} elseif ( in_array( $op, array( Operation::USER_ADD, Operation::USER_REMOVE, Operation::USER_ROLE ), true ) ) {
				// User operations carry the user in the target and the role alongside it.
				$args = array(
					'user_id' => (int) $target,
					'role'    => isset( $post['role'] ) ? sanitize_key( (string) $post['role'] ) : '',
				);
			} else {
				$args = Operation::THEME_UPDATE === $op ? array( 'theme' => $target ) : array( 'plugin' => $target );
			}
			if ( $site_id && ! in_array( $op, array( Operation::SETTINGS_UPDATE, Operation::ACCESS_UPDATE ), true ) ) {
				$args['site_id'] = $site_id;
			}

			try {
				$result    = $this->plugin->operations()->run( $op, $args, (string) ( $nonces[ $op ] ?? '' ), 'form' );
				$results[] = array(
					'status'  => Operation::SUCCESS,
					'message' => $result['message'],
				);
			} catch ( OperationException $e ) {
				$results[] = array(
					'status'  => $e->status(),
					'message' => $e->getMessage(),
				);
			}
		}

		if ( ! $jobs ) {
			$results[] = array(
				'status'  => Operation::REJECTED,
				'message' => __( 'Nothing was selected.', 'multisite-fleet-manager' ),
			);
		}

		set_site_transient( 'wpfleet_results_' . get_current_user_id(), $results, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'wpfleet_results', 1, $return ) );
		exit;
	}

	/**
	 * Results of the last form operation for the current user (read once).
	 *
	 * @return array<int,array{status: string, message: string}>
	 */
	public static function take_results(): array {
		if ( empty( $_GET['wpfleet_results'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			return array();
		}
		$key     = 'wpfleet_results_' . get_current_user_id();
		$results = get_site_transient( $key );
		delete_site_transient( $key );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Form target URL.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public static function url( string $action ): string {
		return add_query_arg( 'action', $action, network_admin_url( 'edit.php' ) );
	}

	/**
	 * Runs discovery within one request (resuming across clicks on very large networks).
	 *
	 * @return void
	 */
	public function discover(): void {
		check_admin_referer( self::DISCOVER );

		if ( ! current_user_can( Capabilities::RUN_DISCOVERY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run site discovery.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$discovery = $this->plugin->discovery();
		$current   = $discovery->current();

		if ( $current && $discovery->is_active( $current ) && SiteDiscovery::SOURCE_MANUAL !== $current['source'] ) {
			$notice = 'discovery_running';
		} else {
			try {
				$result = $discovery->run( SiteDiscovery::SOURCE_MANUAL, 20.0 );
				$notice = $result['done'] ? 'discovery_done' : 'discovery_partial';
			} catch ( DiscoveryInProgressException $e ) {
				$notice = 'discovery_running';
			}
		}

		$return = isset( $_POST['wpfleet_return'] ) && 'console' === $_POST['wpfleet_return'] ? Admin::SLUG_CONSOLE : Admin::SLUG_DASHBOARD; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		wp_safe_redirect( Admin::url( $return, array( 'wpfleet_notice' => $notice ) ) );
		exit;
	}
}
