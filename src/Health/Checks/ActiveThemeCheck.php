<?php
/**
 * Active theme integrity.
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
 * Error when the active theme (or its parent) is not installed or is broken.
 */
final class ActiveThemeCheck implements HealthCheck {

	public function id(): string {
		return 'active_theme';
	}

	public function label(): string {
		return __( 'Active theme', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( '' === $site->theme_stylesheet ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}
		$theme = $context->theme( $site->theme_stylesheet );
		if ( ! $theme->exists() ) {
			return new Indicator( $this->id(), Indicator::ERROR, array( 'problem' => 'missing', 'theme' => $site->theme_stylesheet ) );
		}
		if ( $theme->errors() ) {
			return new Indicator( $this->id(), Indicator::ERROR, array( 'problem' => 'broken', 'theme' => $site->theme_stylesheet ) );
		}
		return new Indicator( $this->id(), Indicator::GOOD, array( 'theme' => $site->theme_stylesheet ) );
	}

	public function message( Indicator $indicator ): string {
		$theme = (string) ( $indicator->data['theme'] ?? '' );
		switch ( $indicator->data['problem'] ?? '' ) {
			case 'missing':
				/* translators: %s: Theme directory name. */
				return sprintf( __( 'The active theme “%s” is not installed. Visitors may see a broken site.', 'multisite-fleet-manager' ), $theme );
			case 'broken':
				/* translators: %s: Theme directory name. */
				return sprintf( __( 'The active theme “%s” is broken (for example, its parent theme is missing).', 'multisite-fleet-manager' ), $theme );
		}
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'No active theme recorded.', 'multisite-fleet-manager' );
		}
		return __( 'The active theme is installed and valid.', 'multisite-fleet-manager' );
	}
}
