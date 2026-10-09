<?php
/**
 * Network admin bootstrap.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Activator;
use FleetManager\Core\Capabilities;
use FleetManager\Plugin;
use FleetManager\Rest\DiscoveryController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Network Admin menu, assets, actions and notices.
 *
 * Every screen lives in Network Admin: the plugin manages the network, not an
 * individual site, so nothing is added to site-level admin menus.
 */
final class Admin {

	public const SLUG_DASHBOARD = 'multisite-fleet-manager';
	public const SLUG_SITES     = 'multisite-fleet-manager-sites';
	public const SLUG_CONSOLE    = 'multisite-fleet-manager-console';
	public const SLUG_EXTENSIONS = 'multisite-fleet-manager-extensions';
	public const SLUG_HEALTH     = 'multisite-fleet-manager-health';
	public const SLUG_REPORTS    = 'multisite-fleet-manager-reports';
	public const SLUG_USERS      = 'multisite-fleet-manager-users';
	public const SLUG_LOG         = 'multisite-fleet-manager-log';
	public const SLUG_SETTINGS    = 'multisite-fleet-manager-settings';
	public const SLUG_DIAGNOSTICS = 'multisite-fleet-manager-diagnostics';

	private Plugin $plugin;

	private ?SitesPage $sites_page = null;

	/**
	 * Page hook suffixes returned by add_*_page().
	 *
	 * @var array<string,string>
	 */
	private array $hooks = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks everything.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'network_admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'network_admin_notices', array( $this, 'activation_notice' ) );
		add_filter( 'network_admin_plugin_action_links_' . WPFLEET_BASENAME, array( $this, 'action_links' ) );
		add_filter(
			'set_screen_option_wpfleet_sites_per_page',
			static fn( $status, $option, $value ) => max( 1, min( 200, (int) $value ) ),
			10,
			3
		);

		( new Actions( $this->plugin ) )->register();
	}

	/**
	 * Network admin URL of a plugin page.
	 *
	 * @param string              $slug Page slug.
	 * @param array<string,mixed> $args Extra query args.
	 * @return string
	 */
	public static function url( string $slug = self::SLUG_DASHBOARD, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), network_admin_url( 'admin.php' ) );
	}

	/**
	 * Adds the menu pages.
	 *
	 * @return void
	 */
	public function menu(): void {
		$dashboard = new DashboardPage( $this->plugin );

		$this->hooks['dashboard'] = (string) add_menu_page(
			__( 'Fleet Manager', 'multisite-fleet-manager' ),
			__( 'Fleet Manager', 'multisite-fleet-manager' ),
			Capabilities::VIEW_DASHBOARD,
			self::SLUG_DASHBOARD,
			array( $dashboard, 'render' ),
			'dashicons-networking',
			6
		);

		add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Fleet Manager Dashboard', 'multisite-fleet-manager' ),
			__( 'Dashboard', 'multisite-fleet-manager' ),
			Capabilities::VIEW_DASHBOARD,
			self::SLUG_DASHBOARD,
			array( $dashboard, 'render' )
		);

		$console                = new ConsolePage( $this->plugin );
		$this->hooks['console'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Network Site Dashboard', 'multisite-fleet-manager' ),
			__( 'Site Dashboard', 'multisite-fleet-manager' ),
			Capabilities::VIEW_SITES,
			self::SLUG_CONSOLE,
			array( $console, 'render' )
		);

		$health                = new HealthPage( $this->plugin );
		$this->hooks['health'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Fleet Health', 'multisite-fleet-manager' ),
			__( 'Health', 'multisite-fleet-manager' ),
			Capabilities::VIEW_DASHBOARD,
			self::SLUG_HEALTH,
			array( $health, 'render' )
		);

		$extensions                = new ExtensionsPage( $this->plugin );
		$this->hooks['extensions'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Plugins & Themes', 'multisite-fleet-manager' ),
			__( 'Plugins & Themes', 'multisite-fleet-manager' ),
			Capabilities::VIEW_EXTENSIONS,
			self::SLUG_EXTENSIONS,
			array( $extensions, 'render' )
		);

		$this->hooks['sites'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Site Inventory', 'multisite-fleet-manager' ),
			__( 'Site Inventory', 'multisite-fleet-manager' ),
			Capabilities::VIEW_SITES,
			self::SLUG_SITES,
			array( $this, 'render_sites' )
		);

		$users                = new UsersPage( $this->plugin );
		$this->hooks['users'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Network Users', 'multisite-fleet-manager' ),
			__( 'Users', 'multisite-fleet-manager' ),
			Capabilities::VIEW_USERS,
			self::SLUG_USERS,
			array( $users, 'render' )
		);

		$reports                = new ReportsPage( $this->plugin );
		$this->hooks['reports'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Network Report', 'multisite-fleet-manager' ),
			__( 'Reports', 'multisite-fleet-manager' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG_REPORTS,
			array( $reports, 'render' )
		);

		$log                = new LogPage( $this->plugin );
		$this->hooks['log'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Operation Log', 'multisite-fleet-manager' ),
			__( 'Operation Log', 'multisite-fleet-manager' ),
			Capabilities::VIEW_LOG,
			self::SLUG_LOG,
			array( $log, 'render' )
		);

		$settings                = new SettingsPage( $this->plugin );
		$this->hooks['settings'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Fleet Manager Settings', 'multisite-fleet-manager' ),
			__( 'Settings', 'multisite-fleet-manager' ),
			Capabilities::MANAGE_SETTINGS,
			self::SLUG_SETTINGS,
			array( $settings, 'render' )
		);

		$diagnostics                = new DiagnosticsPage( $this->plugin );
		$this->hooks['diagnostics'] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Fleet Manager Diagnostics', 'multisite-fleet-manager' ),
			__( 'Diagnostics', 'multisite-fleet-manager' ),
			Capabilities::MANAGE_SETTINGS,
			self::SLUG_DIAGNOSTICS,
			array( $diagnostics, 'render' )
		);

		// The list table must be built before output so screen options work.
		add_action( 'load-' . $this->hooks['sites'], array( $this, 'load_sites' ) );

		// Contextual help on every Fleet Manager screen.
		foreach ( $this->hooks as $key => $hook ) {
			if ( '' !== $hook ) {
				add_action( 'load-' . $hook, function () use ( $key ) {
					Help::add( $key );
				} );
			}
		}
	}

	/**
	 * Prepares the inventory page.
	 *
	 * @return void
	 */
	public function load_sites(): void {
		$this->sites_page = new SitesPage( $this->plugin );
		$this->sites_page->load();
	}

	/**
	 * Renders the inventory page.
	 *
	 * @return void
	 */
	public function render_sites(): void {
		if ( $this->sites_page ) {
			$this->sites_page->render();
		}
	}

	/**
	 * Enqueues assets on plugin screens only.
	 *
	 * @param string $hook_suffix Current screen hook.
	 * @return void
	 */
	public function assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'wpfleet-admin', WPFLEET_URL . 'assets/css/admin.css', array(), WPFLEET_VERSION );

		if ( $hook_suffix === ( $this->hooks['extensions'] ?? '' ) ) {
			wp_enqueue_script(
				'wpfleet-extensions',
				WPFLEET_URL . 'assets/js/extensions.js',
				array( 'wp-api-fetch', 'wp-i18n' ),
				WPFLEET_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_set_script_translations( 'wpfleet-extensions', 'multisite-fleet-manager', WPFLEET_PATH . 'languages' );
		}

		$console_js = array( $this->hooks['console'] ?? '', $this->hooks['extensions'] ?? '', $this->hooks['log'] ?? '', $this->hooks['users'] ?? '', $this->hooks['reports'] ?? '', $this->hooks['settings'] ?? '', $this->hooks['diagnostics'] ?? '' );
		if ( in_array( $hook_suffix, $console_js, true ) ) {
			wp_enqueue_script(
				'wpfleet-console',
				WPFLEET_URL . 'assets/js/console.js',
				array( 'wp-i18n' ),
				WPFLEET_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_set_script_translations( 'wpfleet-console', 'multisite-fleet-manager', WPFLEET_PATH . 'languages' );
		}

		$runner_pages = array( $this->hooks['dashboard'] ?? '', $this->hooks['console'] ?? '', $this->hooks['health'] ?? '' );
		if ( in_array( $hook_suffix, $runner_pages, true ) && current_user_can( Capabilities::RUN_DISCOVERY ) ) {
			$done_slug = self::SLUG_DASHBOARD;
			if ( $hook_suffix === ( $this->hooks['console'] ?? '' ) ) {
				$done_slug = self::SLUG_CONSOLE;
			} elseif ( $hook_suffix === ( $this->hooks['health'] ?? '' ) ) {
				$done_slug = self::SLUG_HEALTH;
			}
			wp_enqueue_script(
				'wpfleet-dashboard',
				WPFLEET_URL . 'assets/js/dashboard.js',
				array( 'wp-api-fetch', 'wp-i18n' ),
				WPFLEET_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_set_script_translations( 'wpfleet-dashboard', 'multisite-fleet-manager', WPFLEET_PATH . 'languages' );
			wp_localize_script(
				'wpfleet-dashboard',
				'wpFleetDashboard',
				array(
					'namespace' => DiscoveryController::NAMESPACE,
					'doneUrl'   => self::url( $done_slug, array( 'wpfleet_notice' => 'discovery_done' ) ),
				)
			);
		}
	}

	/**
	 * One-time notice after network activation.
	 *
	 * @return void
	 */
	public function activation_notice(): void {
		if ( ! get_site_option( Activator::NOTICE_OPTION ) || ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}
		delete_site_option( Activator::NOTICE_OPTION );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Multisite Fleet Manager is network active.', 'multisite-fleet-manager' ),
			esc_url( self::url() ),
			esc_html__( 'Open the dashboard and discover your sites', 'multisite-fleet-manager' )
		);
	}

	/**
	 * Adds a Dashboard link on the Network Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		if ( current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Dashboard', 'multisite-fleet-manager' ) )
			);
		}
		return $links;
	}
}
