<?php
/**
 * Applies the health checks to a site.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

use FleetManager\Health\Checks\PluginUpdatesCheck;
use FleetManager\Health\Checks\ThemeUpdatesCheck;
use FleetManager\Sites\Site;

defined( 'ABSPATH' ) || exit;

/**
 * Fills a site's update counts, indicators and health roll-up.
 */
final class HealthEvaluator {

	private HealthCheckRegistry $checks;

	private ?FleetContext $context = null;

	/**
	 * Constructor.
	 *
	 * @param HealthCheckRegistry $checks Registry.
	 */
	public function __construct( HealthCheckRegistry $checks ) {
		$this->checks = $checks;
	}

	/**
	 * Network context, loaded once until reset.
	 *
	 * @return FleetContext
	 */
	public function context(): FleetContext {
		if ( null === $this->context ) {
			$this->context = FleetContext::load();
		}
		return $this->context;
	}

	/**
	 * Forgets the cached context (after update data changed).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->context = null;
	}

	/**
	 * Evaluates a site in place.
	 *
	 * @param Site $site Site.
	 * @return Site The same instance.
	 */
	public function apply( Site $site ): Site {
		$context = $this->context();

		$plugins              = PluginUpdatesCheck::outdated( $site, $context );
		$themes               = ThemeUpdatesCheck::outdated( $site, $context );
		$site->plugin_updates = null === $plugins ? null : count( $plugins );
		$site->theme_updates  = null === $themes ? null : count( $themes );

		$indicators = array();
		foreach ( $this->checks->all() as $check ) {
			try {
				$indicators[] = $check->evaluate( $site, $context );
			} catch ( \Throwable $e ) {
				// A faulty (third-party) check must not break the fleet view.
				$indicators[] = new Indicator( $check->id(), Indicator::UNKNOWN, array( 'exception' => $e->getMessage() ) );
			}
		}

		$site->health            = Health::from_indicators( $indicators );
		$site->health_indicators = array_map( static fn( Indicator $i ) => $i->to_array(), $indicators );
		$site->health_checked_at = current_time( 'mysql', true );

		return $site;
	}
}
