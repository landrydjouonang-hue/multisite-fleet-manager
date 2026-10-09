<?php
/**
 * Fleet-wide health re-evaluation.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

use FleetManager\Sites\SiteDiscovery;
use FleetManager\Sites\SiteRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Re-scores every stored site when network-level inputs change: WordPress
 * refreshed its update data, a plugin or theme was updated, installed or
 * deleted, or network activations changed. Checks work from stored
 * snapshots, so this needs no site switching and runs once, on shutdown.
 */
final class HealthRefresher {

	private SiteRepository $sites;

	private HealthEvaluator $evaluator;

	private SiteDiscovery $discovery;

	private bool $queued = false;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository  $sites     Repository.
	 * @param HealthEvaluator $evaluator Evaluator.
	 * @param SiteDiscovery   $discovery Discovery.
	 */
	public function __construct( SiteRepository $sites, HealthEvaluator $evaluator, SiteDiscovery $discovery ) {
		$this->sites     = $sites;
		$this->evaluator = $evaluator;
		$this->discovery = $discovery;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( array( 'update_plugins', 'update_themes', 'update_core' ) as $transient ) {
			add_action( "set_site_transient_{$transient}", array( $this, 'queue' ), 10, 0 );
		}
		add_action( 'deleted_site_transient', array( $this, 'on_transient_deleted' ) );
		add_action( 'update_site_option_active_sitewide_plugins', array( $this, 'queue' ), 10, 0 );
		add_action( 'upgrader_process_complete', array( $this, 'queue' ), 10, 0 );
		add_action( 'deleted_plugin', array( $this, 'queue' ), 10, 0 );
		add_action( 'deleted_theme', array( $this, 'queue' ), 10, 0 );
	}

	/**
	 * A site transient was deleted.
	 *
	 * @param string $transient Name.
	 * @return void
	 */
	public function on_transient_deleted( $transient ): void {
		if ( in_array( $transient, array( 'update_plugins', 'update_themes', 'update_core' ), true ) ) {
			$this->queue();
		}
	}

	/**
	 * Schedules one refresh at the end of the request.
	 *
	 * @return void
	 */
	public function queue(): void {
		if ( $this->queued || ! $this->discovery->last() ) {
			return;
		}
		$this->queued = true;
		add_action( 'shutdown', array( $this, 'refresh' ), 20 );
	}

	/**
	 * Re-evaluates all sites of the current network.
	 *
	 * @return int Number of sites whose health data changed.
	 */
	public function refresh(): int {
		$this->queued = false;
		$this->evaluator->reset();

		$changed = 0;
		$after   = 0;
		do {
			$batch = $this->sites->chunk( $after, 200 );
			foreach ( $batch as $id => $site ) {
				$before = array( $site->health, $site->plugin_updates, $site->theme_updates, $site->health_indicators );
				$this->evaluator->apply( $site );
				if ( array( $site->health, $site->plugin_updates, $site->theme_updates, $site->health_indicators ) !== $before ) {
					$this->sites->save_health( $id, $site );
					++$changed;
				}
				$after = $id;
			}
		} while ( count( $batch ) === 200 );

		return $changed;
	}
}
