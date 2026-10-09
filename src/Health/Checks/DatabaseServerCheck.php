<?php
/**
 * Database server version.
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
 * The database server is shared by the installation. Error below the
 * version WordPress requires, needs attention below the recommended one
 * (`wpfleet_recommended_database`).
 */
final class DatabaseServerCheck implements HealthCheck {

	public function id(): string {
		return 'database_server';
	}

	public function label(): string {
		return __( 'Database server', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( '' === $context->db_server ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		$data = array(
			'version'     => $context->db_server,
			'server'      => $context->is_mariadb ? 'MariaDB' : 'MySQL',
			'required'    => $context->db_required,
			'recommended' => $context->db_recommended,
		);

		if ( '' !== $context->db_required && version_compare( $context->db_server, $context->db_required, '<' ) ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( '' !== $context->db_recommended && version_compare( $context->db_server, $context->db_recommended, '<' ) ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'The database server version could not be read.', 'multisite-fleet-manager' );
		}

		$server  = (string) ( $indicator->data['server'] ?? '' );
		$version = (string) ( $indicator->data['version'] ?? '' );

		switch ( $indicator->state ) {
			case Indicator::ERROR:
				return sprintf(
					/* translators: 1: Server name, 2: Current version, 3: Required version. */
					__( '%1$s %2$s is below the %3$s that WordPress requires.', 'multisite-fleet-manager' ),
					$server,
					$version,
					(string) ( $indicator->data['required'] ?? '' )
				);
			case Indicator::WARNING:
				return sprintf(
					/* translators: 1: Server name, 2: Current version, 3: Recommended version. */
					__( '%1$s %2$s is older than the %3$s recommended here.', 'multisite-fleet-manager' ),
					$server,
					$version,
					(string) ( $indicator->data['recommended'] ?? '' )
				);
		}

		/* translators: 1: Server name, 2: Version. */
		return sprintf( __( '%1$s %2$s is current.', 'multisite-fleet-manager' ), $server, $version );
	}
}
