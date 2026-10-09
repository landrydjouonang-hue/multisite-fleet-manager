<?php
/**
 * Site data readability.
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
 * Error when the site's options could not be read during discovery, which
 * usually means its database tables are missing or damaged.
 */
final class SiteDataCheck implements HealthCheck {

	public function id(): string {
		return 'site_data';
	}

	public function label(): string {
		return __( 'Site database', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		return new Indicator( $this->id(), '' === $site->site_url ? Indicator::ERROR : Indicator::GOOD );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::ERROR === $indicator->state ) {
			return __( 'The site’s settings could not be read. Its database tables may be missing or damaged.', 'multisite-fleet-manager' );
		}
		return __( 'The site’s database tables are readable.', 'multisite-fleet-manager' );
	}
}
