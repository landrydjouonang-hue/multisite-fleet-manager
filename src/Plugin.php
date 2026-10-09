<?php
/**
 * Main plugin class.
 *
 * @package FleetManager
 */

namespace FleetManager;

use FleetManager\Admin\Admin;
use FleetManager\Admin\Widgets;
use FleetManager\Core\Capabilities;
use FleetManager\Core\Settings;
use FleetManager\Notifications\Notifier;
use FleetManager\Operations\Handlers\SettingsHandler;
use FleetManager\Core\Upgrader;
use FleetManager\Extensions\ExtensionInventory;
use FleetManager\Operations\Handlers\PluginUpdateHandler;
use FleetManager\Operations\Handlers\SitePluginHandler;
use FleetManager\Operations\Handlers\SnapshotHandler;
use FleetManager\Operations\Handlers\ThemeUpdateHandler;
use FleetManager\Operations\Handlers\UserSiteHandler;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationLog;
use FleetManager\Operations\OperationService;
use FleetManager\Operations\UpdateEnvironment;
use FleetManager\Reporting\CsvExporter;
use FleetManager\Reporting\ReportBuilder;
use FleetManager\Reporting\SnapshotRepository;
use FleetManager\Reporting\SnapshotService;
use FleetManager\Rest\ExtensionsController;
use FleetManager\Rest\ReportsController;
use FleetManager\Rest\UsersController;
use FleetManager\Health\HealthCheckRegistry;
use FleetManager\Health\HealthEvaluator;
use FleetManager\Health\HealthRefresher;
use FleetManager\Network\NetworkInfo;
use FleetManager\Rest\DiscoveryController;
use FleetManager\Rest\SitesController;
use FleetManager\Sites\DiscoveryScheduler;
use FleetManager\Sites\SiteDiscovery;
use FleetManager\Sites\SiteInspector;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteSync;
use FleetManager\Users\UserActivity;
use FleetManager\Users\UserDirectory;
use FleetManager\Users\UserIndex;
use FleetManager\Users\UserSync;

defined( 'ABSPATH' ) || exit;

/**
 * Composition root: builds services lazily and wires hooks.
 *
 * Only constructed once MultisiteGuard has confirmed a network activation.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	/**
	 * Lazily created services.
	 *
	 * @var array<string,object>
	 */
	private array $services = array();

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wires hooks. Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->settings()->register();
		$this->capabilities()->register();
		( new Upgrader() )->register();
		$this->sync()->register();
		$this->user_sync()->register();
		$this->user_activity()->register();
		$this->scheduler()->register();
		$this->health_refresher()->register();
		$this->snapshots()->register();
		$this->notifier()->register();

		// Log retention rides on the daily scheduled discovery event.
		add_action(
			DiscoveryScheduler::HOOK,
			function () {
				$this->operation_log()->purge();
				$this->snapshots()->prune();
			}
		);

		add_action(
			'rest_api_init',
			function () {
				( new DiscoveryController( $this->discovery() ) )->register_routes();
				( new SitesController( $this->sites(), $this->discovery(), $this->health_checks() ) )->register_routes();
				( new ExtensionsController( $this->extensions(), $this->operations(), $this->operation_log() ) )->register_routes();
				( new UsersController( $this->users() ) )->register_routes();
				( new ReportsController( $this->reports(), $this->snapshot_repository(), $this->snapshots() ) )->register_routes();
			}
		);

		if ( is_admin() ) {
			( new Widgets( $this ) )->register();
		}

		if ( is_network_admin() ) {
			( new Admin( $this ) )->register();
		}

		/**
		 * Fires once the plugin is fully booted (network activated on Multisite).
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wpfleet_loaded', $this );
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'multisite-fleet-manager', false, dirname( WPFLEET_BASENAME ) . '/languages' );
	}

	public function capabilities(): Capabilities {
		return $this->service( Capabilities::class, static fn() => new Capabilities() );
	}

	public function network(): NetworkInfo {
		return $this->service( NetworkInfo::class, static fn() => new NetworkInfo() );
	}

	public function sites(): SiteRepository {
		return $this->service( SiteRepository::class, static fn() => new SiteRepository() );
	}

	public function inspector(): SiteInspector {
		return $this->service( SiteInspector::class, fn() => new SiteInspector( $this->health() ) );
	}

	public function health_checks(): HealthCheckRegistry {
		return $this->service( HealthCheckRegistry::class, static fn() => new HealthCheckRegistry() );
	}

	public function health(): HealthEvaluator {
		return $this->service( HealthEvaluator::class, fn() => new HealthEvaluator( $this->health_checks() ) );
	}

	public function extensions(): ExtensionInventory {
		return $this->service( ExtensionInventory::class, fn() => new ExtensionInventory( $this->sites() ) );
	}

	public function update_environment(): UpdateEnvironment {
		return $this->service( UpdateEnvironment::class, static fn() => new UpdateEnvironment() );
	}

	public function operation_log(): OperationLog {
		return $this->service( OperationLog::class, static fn() => new OperationLog() );
	}

	public function operations(): OperationService {
		return $this->service(
			OperationService::class,
			fn() => new OperationService(
				$this->operation_log(),
				array(
					new PluginUpdateHandler( $this->update_environment() ),
					new ThemeUpdateHandler( $this->update_environment() ),
					new SitePluginHandler( true, $this->inspector(), $this->sites() ),
					new SitePluginHandler( false, $this->inspector(), $this->sites() ),
					new UserSiteHandler( Operation::USER_ADD, $this->user_index() ),
					new UserSiteHandler( Operation::USER_REMOVE, $this->user_index() ),
					new UserSiteHandler( Operation::USER_ROLE, $this->user_index() ),
					new SnapshotHandler( $this->snapshots() ),
					new SettingsHandler( Operation::SETTINGS_UPDATE ),
					new SettingsHandler( Operation::ACCESS_UPDATE ),
				)
			)
		);
	}

	public function settings(): Settings {
		return $this->service( Settings::class, static fn() => new Settings() );
	}

	public function notifier(): Notifier {
		return $this->service( Notifier::class, fn() => new Notifier( $this->reports(), $this->sites() ) );
	}

	public function reports(): ReportBuilder {
		return $this->service(
			ReportBuilder::class,
			fn() => new ReportBuilder( $this->sites(), $this->extensions(), $this->network(), $this->discovery(), $this->health_checks(), $this->health() )
		);
	}

	public function snapshot_repository(): SnapshotRepository {
		return $this->service( SnapshotRepository::class, static fn() => new SnapshotRepository() );
	}

	public function snapshots(): SnapshotService {
		return $this->service( SnapshotService::class, fn() => new SnapshotService( $this->reports(), $this->snapshot_repository() ) );
	}

	public function csv(): CsvExporter {
		return $this->service( CsvExporter::class, fn() => new CsvExporter( $this->reports(), $this->snapshot_repository(), $this->users(), $this->operation_log() ) );
	}

	public function health_refresher(): HealthRefresher {
		return $this->service( HealthRefresher::class, fn() => new HealthRefresher( $this->sites(), $this->health(), $this->discovery() ) );
	}

	public function discovery(): SiteDiscovery {
		return $this->service( SiteDiscovery::class, fn() => new SiteDiscovery( $this->sites(), $this->inspector(), $this->user_index() ) );
	}

	public function user_index(): UserIndex {
		return $this->service( UserIndex::class, static fn() => new UserIndex() );
	}

	public function user_activity(): UserActivity {
		return $this->service( UserActivity::class, static fn() => new UserActivity() );
	}

	public function users(): UserDirectory {
		return $this->service( UserDirectory::class, fn() => new UserDirectory( $this->user_index(), $this->user_activity(), $this->sites() ) );
	}

	public function user_sync(): UserSync {
		return $this->service( UserSync::class, fn() => new UserSync( $this->user_index() ) );
	}

	public function sync(): SiteSync {
		return $this->service( SiteSync::class, fn() => new SiteSync( $this->sites(), $this->inspector(), $this->discovery() ) );
	}

	public function scheduler(): DiscoveryScheduler {
		return $this->service( DiscoveryScheduler::class, fn() => new DiscoveryScheduler( $this->discovery() ) );
	}

	/**
	 * Returns a shared service, creating it on first use.
	 *
	 * @template T of object
	 * @param class-string<T> $id      Service ID.
	 * @param callable():T    $factory Factory.
	 * @return T
	 */
	private function service( string $id, callable $factory ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $factory();
		}
		return $this->services[ $id ];
	}

	/**
	 * Prevents direct instantiation.
	 */
	private function __construct() {}
}
