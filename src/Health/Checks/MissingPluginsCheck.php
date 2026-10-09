<?php
/**
 * Active plugins that are no longer installed.
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
 * Error when the site lists active plugins whose files are gone, so the
 * functionality they provide is silently missing.
 */
final class MissingPluginsCheck implements HealthCheck {

	public function id(): string {
		return 'missing_plugins';
	}

	public function label(): string {
		return __( 'Plugin files', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$missing = array_values( array_filter( $site->active_plugin_list, static fn( $plugin ) => ! $context->plugin_exists( (string) $plugin ) ) );
		if ( $missing ) {
			return new Indicator( $this->id(), Indicator::ERROR, array( 'plugins' => $missing ) );
		}
		return new Indicator( $this->id(), Indicator::GOOD );
	}

	public function message( Indicator $indicator ): string {
		$plugins = (array) ( $indicator->data['plugins'] ?? array() );
		if ( ! $plugins ) {
			return __( 'All active plugins are installed.', 'multisite-fleet-manager' );
		}
		return sprintf(
			/* translators: 1: Number of plugins, 2: Comma-separated plugin files. */
			_n( '%1$s active plugin is not installed: %2$s', '%1$s active plugins are not installed: %2$s', count( $plugins ), 'multisite-fleet-manager' ),
			number_format_i18n( count( $plugins ) ),
			implode( ', ', array_map( 'strval', $plugins ) )
		);
	}
}
