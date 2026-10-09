<?php
/**
 * PHP version.
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
 * PHP is shared by the whole installation. Error below the version
 * WordPress requires, needs attention below the version Fleet Manager
 * treats as current (`wpfleet_recommended_php`, default 8.1).
 *
 * This is about support and compatibility, not a vulnerability scan: an
 * older PHP may still have back-ported fixes from the distributor.
 */
final class PhpVersionCheck implements HealthCheck {

	public function id(): string {
		return 'php_version';
	}

	public function label(): string {
		return __( 'PHP version', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$data = array(
			'version'     => $context->php_version,
			'required'    => $context->php_required,
			'recommended' => $context->php_recommended,
		);

		if ( '' !== $context->php_required && version_compare( $context->php_version, $context->php_required, '<' ) ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( '' !== $context->php_recommended && version_compare( $context->php_version, $context->php_recommended, '<' ) ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		$version = (string) ( $indicator->data['version'] ?? '' );

		switch ( $indicator->state ) {
			case Indicator::ERROR:
				return sprintf(
					/* translators: 1: Current PHP version, 2: Required PHP version. */
					__( 'PHP %1$s is below the %2$s that WordPress requires. Ask your host to upgrade.', 'multisite-fleet-manager' ),
					$version,
					(string) ( $indicator->data['required'] ?? '' )
				);
			case Indicator::WARNING:
				return sprintf(
					/* translators: 1: Current PHP version, 2: Recommended PHP version. */
					__( 'PHP %1$s is older than the %2$s recommended here. Newer versions are faster and stay supported longer.', 'multisite-fleet-manager' ),
					$version,
					(string) ( $indicator->data['recommended'] ?? '' )
				);
		}

		/* translators: %s: PHP version. */
		return sprintf( __( 'PHP %s is current.', 'multisite-fleet-manager' ), $version );
	}
}
