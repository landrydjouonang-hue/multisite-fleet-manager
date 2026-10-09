<?php
/**
 * Health check contract.
 *
 * @package FleetManager
 */

namespace FleetManager\Health\Contracts;

use FleetManager\Health\FleetContext;
use FleetManager\Health\Indicator;
use FleetManager\Sites\Site;

defined( 'ABSPATH' ) || exit;

/**
 * A health check judges one aspect of a site from its stored snapshot and
 * the network context. Checks must be pure and cheap: no switch_to_blog(),
 * no HTTP requests. That lets the whole fleet be re-evaluated in one pass
 * whenever update data changes.
 */
interface HealthCheck {

	/**
	 * Stable identifier, e.g. "plugin_updates".
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Short label, e.g. "Plugin updates".
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Evaluates a site.
	 *
	 * @param Site         $site    Snapshot.
	 * @param FleetContext $context Network context.
	 * @return Indicator
	 */
	public function evaluate( Site $site, FleetContext $context ): Indicator;

	/**
	 * Human-readable explanation of an indicator produced by this check.
	 *
	 * @param Indicator $indicator Indicator.
	 * @return string
	 */
	public function message( Indicator $indicator ): string;
}
