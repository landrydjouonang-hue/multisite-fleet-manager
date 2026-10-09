<?php
/**
 * WordPress core version.
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
 * Every site of a network runs the same core files, so this indicator has
 * the same result everywhere: it reports whether a newer WordPress is
 * available for the installation.
 */
final class WordPressVersionCheck implements HealthCheck {

	public function id(): string {
		return 'wordpress_version';
	}

	public function label(): string {
		return __( 'WordPress version', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$data = array(
			'version' => $context->wp_version,
			'update'  => $context->core_update,
		);
		return new Indicator( $this->id(), $context->core_update ? Indicator::WARNING : Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		$version = (string) ( $indicator->data['version'] ?? '' );
		if ( Indicator::WARNING === $indicator->state ) {
			return sprintf(
				/* translators: 1: Installed version, 2: Available version. */
				__( 'WordPress %1$s is installed; %2$s is available. Core files are shared, so updating covers every site.', 'multisite-fleet-manager' ),
				$version,
				(string) ( $indicator->data['update'] ?? '' )
			);
		}
		/* translators: %s: WordPress version. */
		return sprintf( __( 'WordPress %s is up to date.', 'multisite-fleet-manager' ), $version );
	}
}
