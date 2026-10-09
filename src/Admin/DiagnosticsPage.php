<?php
/**
 * Developer diagnostics screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Core\Settings;
use FleetManager\Health\Category;
use FleetManager\Notifications\Notifier;
use FleetManager\Operations\Operation;
use FleetManager\Plugin;
use FleetManager\Rest\DiscoveryController;
use FleetManager\Sites\DiscoveryScheduler;
use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * What a developer needs when something looks wrong: the environment, the
 * plugin's own state (schema, tables, cron, locks, last runs), the
 * extension points, and a plain-text report that can be pasted into an
 * issue.
 *
 * The report deliberately contains no e-mail addresses, site names, user
 * names or credentials — only versions, counts and configuration — so it
 * can be shared without leaking anything about the network's content.
 */
final class DiagnosticsPage {

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
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		View::render(
			'admin/diagnostics',
			array(
				'state'   => $this->state(),
				'checks'  => $this->self_checks(),
				'hooks'   => $this->hooks(),
				'routes'  => $this->routes(),
				'report'  => $this->report(),
				'results' => Actions::take_results(),
			)
		);
	}

	/**
	 * Environment and plugin state.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function state(): array {
		global $wpdb, $wp_version;

		$context   = $this->plugin->health()->context();
		$discovery = $this->plugin->discovery();
		$run       = $discovery->current();
		$last      = $discovery->last();
		$digest    = $this->plugin->notifier()->last();

		$main     = (int) get_main_site_id();
		$switched = get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}
		$cron = array(
			'discovery' => wp_next_scheduled( DiscoveryScheduler::HOOK ),
			'continue'  => wp_next_scheduled( DiscoveryScheduler::CONTINUE_HOOK ),
			'digest'    => wp_next_scheduled( Notifier::DIGEST_HOOK ),
		);
		if ( $switched ) {
			restore_current_blog();
		}

		$never = __( 'not scheduled', 'multisite-fleet-manager' );

		return array(
			__( 'Environment', 'multisite-fleet-manager' ) => array(
				__( 'WordPress', 'multisite-fleet-manager' )    => (string) $wp_version . ( $context->core_update ? ' → ' . $context->core_update : '' ),
				__( 'PHP', 'multisite-fleet-manager' )          => PHP_VERSION,
				__( 'Database', 'multisite-fleet-manager' )     => ( $context->is_mariadb ? 'MariaDB ' : 'MySQL ' ) . $context->db_server,
				__( 'Multisite', 'multisite-fleet-manager' )    => is_subdomain_install() ? __( 'subdomains', 'multisite-fleet-manager' ) : __( 'subdirectories', 'multisite-fleet-manager' ),
				__( 'Networks', 'multisite-fleet-manager' )     => (string) (int) get_networks( array( 'count' => true ) ),
				__( 'Table prefix', 'multisite-fleet-manager' ) => $wpdb->base_prefix,
				__( 'Memory limit', 'multisite-fleet-manager' ) => -1 === $context->php_memory_limit ? __( 'unlimited', 'multisite-fleet-manager' ) : size_format( max( 0, $context->php_memory_limit ) ),
				__( 'Object cache', 'multisite-fleet-manager' ) => wp_using_ext_object_cache() ? __( 'external', 'multisite-fleet-manager' ) : __( 'database', 'multisite-fleet-manager' ),
			),
			__( 'Plugin', 'multisite-fleet-manager' )      => array(
				__( 'Version', 'multisite-fleet-manager' )        => WPFLEET_VERSION,
				__( 'Schema', 'multisite-fleet-manager' )         => Schema::installed_version() . ' / ' . Schema::VERSION,
				__( 'Tables', 'multisite-fleet-manager' )         => Schema::exists() ? __( 'present', 'multisite-fleet-manager' ) : __( 'MISSING', 'multisite-fleet-manager' ),
				__( 'Sites indexed', 'multisite-fleet-manager' )  => (string) $this->plugin->sites()->count(),
				__( 'Snapshots', 'multisite-fleet-manager' )      => (string) $this->plugin->snapshot_repository()->count(),
				__( 'Log entries', 'multisite-fleet-manager' )    => (string) $this->plugin->operation_log()->query( array( 'per_page' => 1 ) )['total'],
				__( 'Indicators', 'multisite-fleet-manager' )     => (string) count( $this->plugin->health_checks()->all() ),
			),
			__( 'Runs and locks', 'multisite-fleet-manager' ) => array(
				__( 'Last discovery', 'multisite-fleet-manager' )   => $last ? View::datetime( $last['finished_at'] ) . ' (' . $last['source'] . ')' : __( 'never', 'multisite-fleet-manager' ),
				__( 'Discovery running', 'multisite-fleet-manager' ) => $run ? sprintf( '%d / %d', (int) $run['processed'], (int) $run['total'] ) : __( 'no', 'multisite-fleet-manager' ),
				__( 'Operation lock', 'multisite-fleet-manager' )   => get_site_transient( 'wpfleet_operation_lock' ) ? __( 'held', 'multisite-fleet-manager' ) : __( 'free', 'multisite-fleet-manager' ),
				__( 'Next discovery', 'multisite-fleet-manager' )   => $cron['discovery'] ? View::datetime( gmdate( 'Y-m-d H:i:s', (int) $cron['discovery'] ) ) : $never,
				__( 'Next digest', 'multisite-fleet-manager' )      => $cron['digest'] ? View::datetime( gmdate( 'Y-m-d H:i:s', (int) $cron['digest'] ) ) : $never,
				__( 'Last digest', 'multisite-fleet-manager' )      => $digest ? View::datetime( gmdate( 'Y-m-d H:i:s', (int) $digest['time'] ) ) . ' (' . $digest['result'] . ')' : __( 'never', 'multisite-fleet-manager' ),
			),
		);
	}

	/**
	 * Quick pass/fail checks of the plugin's own installation.
	 *
	 * @return array<int,array{label: string, ok: bool, detail: string}>
	 */
	public function self_checks(): array {
		$checks    = array();
		$discovery = $this->plugin->discovery();

		$checks[] = array(
			'label'  => __( 'Database tables', 'multisite-fleet-manager' ),
			'ok'     => Schema::exists(),
			'detail' => Schema::exists() ? __( 'All tables present.', 'multisite-fleet-manager' ) : __( 'A table is missing. Deactivate and reactivate the plugin.', 'multisite-fleet-manager' ),
		);

		$current = Schema::VERSION === Schema::installed_version();
		$checks[] = array(
			'label'  => __( 'Schema version', 'multisite-fleet-manager' ),
			'ok'     => $current,
			'detail' => $current ? __( 'Up to date.', 'multisite-fleet-manager' ) : __( 'Behind: open any admin screen to run the upgrade.', 'multisite-fleet-manager' ),
		);

		$main     = (int) get_main_site_id();
		$switched = get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}
		$scheduled = (bool) wp_next_scheduled( DiscoveryScheduler::HOOK );
		if ( $switched ) {
			restore_current_blog();
		}
		$checks[] = array(
			'label'  => __( 'Scheduled discovery', 'multisite-fleet-manager' ),
			'ok'     => $scheduled,
			'detail' => $scheduled ? __( 'Queued on the network\'s main site.', 'multisite-fleet-manager' ) : __( 'Not queued. Save the settings to reschedule it.', 'multisite-fleet-manager' ),
		);

		$inventory = null !== $discovery->last();
		$checks[]  = array(
			'label'  => __( 'Inventory', 'multisite-fleet-manager' ),
			'ok'     => $inventory,
			'detail' => $inventory ? __( 'Built.', 'multisite-fleet-manager' ) : __( 'Never built. Run site discovery.', 'multisite-fleet-manager' ),
		);

		$blocker  = $this->plugin->update_environment()->blocker();
		$checks[] = array(
			'label'  => __( 'Update controls', 'multisite-fleet-manager' ),
			'ok'     => null === $blocker,
			'detail' => $blocker ?? __( 'Updates can be installed from here.', 'multisite-fleet-manager' ),
		);

		$cron_ok  = ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
		$checks[] = array(
			'label'  => __( 'WP-Cron', 'multisite-fleet-manager' ),
			'ok'     => $cron_ok,
			'detail' => $cron_ok ? __( 'Enabled.', 'multisite-fleet-manager' ) : __( 'DISABLE_WP_CRON is set: an external scheduler must run wp-cron.php, or background work will not happen.', 'multisite-fleet-manager' ),
		);

		return $checks;
	}

	/**
	 * The plugin's extension points.
	 *
	 * @return array<string,array<int,array{name: string, description: string}>>
	 */
	public function hooks(): array {
		return array(
			__( 'Actions', 'multisite-fleet-manager' ) => array(
				array( 'wpfleet_loaded', __( 'The plugin booted (network activated on Multisite).', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_discovery_started', __( 'A discovery run opened.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_discovery_completed', __( 'A discovery run finished; carries the summary.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_operation_succeeded', __( 'A state-changing operation succeeded.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_operation_logged', __( 'Any operation outcome was logged, including refusals.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_snapshot_captured', __( 'A report snapshot was stored.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_settings_saved', __( 'The network settings changed.', 'multisite-fleet-manager' ) ),
			),
			__( 'Filters', 'multisite-fleet-manager' ) => array(
				array( 'wpfleet_health_checks', __( 'Add or remove health checks.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_health_check_category', __( 'Which group an indicator belongs to.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_inspected_site', __( 'Adjust a site snapshot before it is stored.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_user_capabilities', __( 'Capabilities granted to a user.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_discovery_batch_size', __( 'Sites inspected per batch.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_discovery_recurrence', __( 'How often discovery runs.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_user_index_limit', __( 'Per-site member limit before memberships are skipped.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_snapshot_interval', __( 'Minimum time between automatic snapshots.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_snapshot_retention_days', __( 'How long snapshots are kept.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_operation_log_retention_days', __( 'How long log entries are kept.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_measure_upload_space', __( 'Whether upload space is measured during discovery.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_cron_grace_period', __( 'How late an event counts as overdue.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_cron_stuck_after', __( 'How late means cron is broken.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_autoload_limits', __( 'Autoloaded options thresholds.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_database_size_limits', __( 'Per-site database size thresholds.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_active_plugin_limits', __( 'Active plugin count thresholds.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_memory_limits', __( 'Memory thresholds.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_storage_limits', __( 'Quota and disk thresholds.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_recommended_php', __( 'PHP version treated as current.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_recommended_database', __( 'Database version treated as current.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_many_administrators', __( 'Administrators per site before it is flagged.', 'multisite-fleet-manager' ) ),
				array( 'wpfleet_outdated_components_limit', __( 'Outdated components before it is an error.', 'multisite-fleet-manager' ) ),
			),
		);
	}

	/**
	 * REST routes, with the capability each one needs.
	 *
	 * @return array<int,array{route: string, methods: string, capability: string}>
	 */
	public function routes(): array {
		$ns = DiscoveryController::NAMESPACE;

		return array(
			array( "/{$ns}/sites", 'GET', Capabilities::VIEW_SITES ),
			array( "/{$ns}/sites/{id}", 'GET', Capabilities::VIEW_SITES ),
			array( "/{$ns}/sites/{id}/extensions", 'GET', Capabilities::VIEW_EXTENSIONS ),
			array( "/{$ns}/summary", 'GET', Capabilities::VIEW_DASHBOARD ),
			array( "/{$ns}/health", 'GET', Capabilities::VIEW_DASHBOARD ),
			array( "/{$ns}/discovery", 'GET, POST', Capabilities::RUN_DISCOVERY ),
			array( "/{$ns}/discovery/{run}/batch", 'POST', Capabilities::RUN_DISCOVERY ),
			array( "/{$ns}/plugins", 'GET', Capabilities::VIEW_EXTENSIONS ),
			array( "/{$ns}/themes", 'GET', Capabilities::VIEW_EXTENSIONS ),
			array( "/{$ns}/users", 'GET', Capabilities::VIEW_USERS ),
			array( "/{$ns}/users/{id}", 'GET', Capabilities::VIEW_USERS ),
			array( "/{$ns}/reports", 'GET', Capabilities::VIEW_REPORTS ),
			array( "/{$ns}/reports/updates", 'GET', Capabilities::VIEW_REPORTS ),
			array( "/{$ns}/reports/snapshots", 'GET', Capabilities::VIEW_REPORTS ),
			array( "/{$ns}/operations", 'GET', Capabilities::VIEW_LOG ),
			array( "/{$ns}/operations", 'POST', __( 'per operation, plus a nonce', 'multisite-fleet-manager' ) ),
		);
	}

	/**
	 * Plain-text report for bug reports. Contains no names, addresses or
	 * credentials.
	 *
	 * @return string
	 */
	public function report(): string {
		$lines = array( '### Multisite Fleet Manager diagnostics' );

		foreach ( $this->state() as $section => $rows ) {
			$lines[] = '';
			$lines[] = '## ' . $section;
			foreach ( $rows as $label => $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}

		$lines[] = '';
		$lines[] = '## Self-checks';
		foreach ( $this->self_checks() as $check ) {
			$lines[] = ( $check['ok'] ? '[ok] ' : '[!!] ' ) . $check['label'] . ': ' . $check['detail'];
		}

		$lines[] = '';
		$lines[] = '## Settings';
		foreach ( Settings::all() as $key => $value ) {
			if ( 'notification_recipients' === $key ) {
				// Addresses are personal data; the count is enough to debug with.
				$lines[] = $key . ': ' . count( Settings::emails( (string) $value ) ) . ' recipient(s)';
				continue;
			}
			$lines[] = $key . ': ' . ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value );
		}

		$lines[] = '';
		$lines[] = '## Indicators';
		foreach ( $this->plugin->health_checks()->by_category() as $category => $checks ) {
			$lines[] = Category::label( $category ) . ': ' . implode( ', ', array_keys( $checks ) );
		}

		$lines[] = '';
		$lines[] = '## Operations (last 30 days)';
		foreach ( Operation::statuses() as $status ) {
			$lines[] = $status . ': ' . $this->plugin->operation_log()->query( array( 'status' => $status, 'per_page' => 1 ) )['total'];
		}

		return implode( "\n", $lines );
	}
}
