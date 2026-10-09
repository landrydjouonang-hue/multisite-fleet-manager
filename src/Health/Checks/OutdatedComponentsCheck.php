<?php
/**
 * Outdated components.
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
 * How much of the code running on this site is behind its latest release:
 * WordPress itself, plus the plugins and themes in use.
 *
 * Running current code is the single most effective maintenance habit, so
 * this is grouped with the security indicators. What it cannot do is tell
 * you whether any particular update fixes a vulnerability: that needs a
 * vulnerability database, which Fleet Manager does not have. Treat a clean
 * result as "nothing is behind", not as "nothing is vulnerable".
 */
final class OutdatedComponentsCheck implements HealthCheck {

	public function id(): string {
		return 'outdated_components';
	}

	public function label(): string {
		return __( 'Outdated components', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( null === $site->plugin_updates && null === $site->theme_updates && null === $context->plugin_updates ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		$core    = $context->core_update ? 1 : 0;
		$plugins = (int) $site->plugin_updates;
		$themes  = (int) $site->theme_updates;
		$total   = $core + $plugins + $themes;

		/**
		 * Filters how many outdated components count as an error.
		 *
		 * @since 0.7.0
		 *
		 * @param int $limit Outdated components.
		 */
		$limit = max( 1, (int) apply_filters( 'wpfleet_outdated_components_limit', 5 ) );

		$data = array(
			'core'    => $core,
			'plugins' => $plugins,
			'themes'  => $themes,
			'total'   => $total,
			'limit'   => $limit,
		);

		if ( 0 === $total ) {
			return new Indicator( $this->id(), Indicator::GOOD, $data );
		}
		return new Indicator( $this->id(), $total >= $limit ? Indicator::ERROR : Indicator::WARNING, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'No update data yet, so how current this site is cannot be judged.', 'multisite-fleet-manager' );
		}

		if ( Indicator::GOOD === $indicator->state ) {
			return __( 'WordPress, plugins and themes on this site are all at their latest installed versions. That does not mean they are free of vulnerabilities.', 'multisite-fleet-manager' );
		}

		$parts = array();
		if ( ! empty( $indicator->data['core'] ) ) {
			$parts[] = __( 'WordPress itself', 'multisite-fleet-manager' );
		}
		if ( ! empty( $indicator->data['plugins'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: Number of plugins. */
				_n( '%s plugin', '%s plugins', (int) $indicator->data['plugins'], 'multisite-fleet-manager' ),
				number_format_i18n( (int) $indicator->data['plugins'] )
			);
		}
		if ( ! empty( $indicator->data['themes'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: Number of themes. */
				_n( '%s theme', '%s themes', (int) $indicator->data['themes'], 'multisite-fleet-manager' ),
				number_format_i18n( (int) $indicator->data['themes'] )
			);
		}

		return sprintf(
			/* translators: %s: List of outdated components, e.g. "WordPress itself, 2 plugins". */
			__( 'Behind the latest release: %s. Fleet Manager cannot tell which updates are security fixes.', 'multisite-fleet-manager' ),
			implode( ', ', $parts )
		);
	}
}
