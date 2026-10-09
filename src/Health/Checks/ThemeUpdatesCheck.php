<?php
/**
 * Theme updates affecting a site.
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
 * Counts available updates for the site's active theme and its parent theme.
 */
final class ThemeUpdatesCheck implements HealthCheck {

	public function id(): string {
		return 'theme_updates';
	}

	public function label(): string {
		return __( 'Theme updates', 'multisite-fleet-manager' );
	}

	/**
	 * Themes with an update, or null when update data is unavailable.
	 *
	 * @param Site         $site    Site.
	 * @param FleetContext $context Context.
	 * @return string[]|null
	 */
	public static function outdated( Site $site, FleetContext $context ): ?array {
		if ( null === $context->theme_updates ) {
			return null;
		}
		$themes = array_unique( array_filter( array( $site->theme_stylesheet, $site->theme_template ) ) );
		return array_values( array_intersect( $themes, array_keys( $context->theme_updates ) ) );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$outdated = self::outdated( $site, $context );
		if ( null === $outdated ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}
		return new Indicator( $this->id(), $outdated ? Indicator::WARNING : Indicator::GOOD, array( 'themes' => $outdated ) );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'No update data yet. WordPress checks for updates twice a day.', 'multisite-fleet-manager' );
		}
		$themes = (array) ( $indicator->data['themes'] ?? array() );
		if ( ! $themes ) {
			return __( 'The active theme is up to date.', 'multisite-fleet-manager' );
		}
		$names = array_map(
			static function ( $stylesheet ) {
				$theme = wp_get_theme( (string) $stylesheet );
				return $theme->exists() ? (string) $theme->get( 'Name' ) : (string) $stylesheet;
			},
			$themes
		);
		return sprintf(
			/* translators: 1: Number of updates, 2: Comma-separated theme names. */
			_n( '%1$s theme update available: %2$s', '%1$s theme updates available: %2$s', count( $themes ), 'multisite-fleet-manager' ),
			number_format_i18n( count( $themes ) ),
			implode( ', ', $names )
		);
	}
}
