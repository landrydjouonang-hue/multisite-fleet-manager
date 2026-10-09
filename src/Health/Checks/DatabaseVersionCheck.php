<?php
/**
 * Per-site database version.
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
 * All sites share the WordPress core files, but each site's tables are
 * upgraded separately (Network Admin → Upgrade Network). Needs attention
 * when a site's database is behind the installed core.
 */
final class DatabaseVersionCheck implements HealthCheck {

	public function id(): string {
		return 'database_version';
	}

	public function label(): string {
		return __( 'WordPress database', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( $site->db_version <= 0 ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}
		$data = array(
			'site' => $site->db_version,
			'core' => $context->db_version,
		);
		return new Indicator( $this->id(), $site->db_version < $context->db_version ? Indicator::WARNING : Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::WARNING === $indicator->state ) {
			return __( 'This site’s database has not been upgraded to the installed WordPress version. Run Network Admin → Upgrade Network.', 'multisite-fleet-manager' );
		}
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'The site’s database version could not be read.', 'multisite-fleet-manager' );
		}
		return __( 'The site’s database matches the installed WordPress version.', 'multisite-fleet-manager' );
	}
}
