<?php
/**
 * Plugin updates affecting a site.
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
 * Counts available updates for the plugins running on the site: its own
 * active plugins plus the network-activated ones (which run on every site).
 */
final class PluginUpdatesCheck implements HealthCheck {

	public function id(): string {
		return 'plugin_updates';
	}

	public function label(): string {
		return __( 'Plugin updates', 'multisite-fleet-manager' );
	}

	/**
	 * Plugins with an update, or null when update data is unavailable.
	 *
	 * @param Site         $site    Site.
	 * @param FleetContext $context Context.
	 * @return string[]|null
	 */
	public static function outdated( Site $site, FleetContext $context ): ?array {
		if ( null === $context->plugin_updates ) {
			return null;
		}
		$running = array_unique( array_merge( array_map( 'strval', $site->active_plugin_list ), $context->network_plugins ) );
		return array_values( array_intersect( $running, array_keys( $context->plugin_updates ) ) );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$outdated = self::outdated( $site, $context );
		if ( null === $outdated ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}
		return new Indicator( $this->id(), $outdated ? Indicator::WARNING : Indicator::GOOD, array( 'plugins' => $outdated ) );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'No update data yet. WordPress checks for updates twice a day.', 'multisite-fleet-manager' );
		}
		$plugins = (array) ( $indicator->data['plugins'] ?? array() );
		if ( ! $plugins ) {
			return __( 'All plugins running on this site are up to date.', 'multisite-fleet-manager' );
		}
		$names = array_map( array( self::class, 'name' ), $plugins );
		return sprintf(
			/* translators: 1: Number of updates, 2: Comma-separated plugin names. */
			_n( '%1$s plugin update available: %2$s', '%1$s plugin updates available: %2$s', count( $plugins ), 'multisite-fleet-manager' ),
			number_format_i18n( count( $plugins ) ),
			implode( ', ', $names )
		);
	}

	/**
	 * Plugin name from its header, falling back to the basename.
	 *
	 * @param string $basename Basename.
	 * @return string
	 */
	private static function name( $basename ): string {
		$basename = (string) $basename;
		$file     = WP_PLUGIN_DIR . '/' . $basename;
		if ( 0 === validate_file( $basename ) && is_file( $file ) ) {
			$data = get_file_data( $file, array( 'Name' => 'Plugin Name' ) );
			if ( ! empty( $data['Name'] ) ) {
				return (string) $data['Name'];
			}
		}
		return $basename;
	}
}
