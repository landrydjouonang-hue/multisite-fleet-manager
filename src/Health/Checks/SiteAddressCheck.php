<?php
/**
 * Site address consistency.
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
 * Needs attention when the site's WordPress Address (siteurl option) does
 * not point at the domain and path the network routes to it (wp_blogs).
 * A mismatch typically breaks logins, admin links or asset URLs.
 */
final class SiteAddressCheck implements HealthCheck {

	public function id(): string {
		return 'site_address';
	}

	public function label(): string {
		return __( 'Site address', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( '' === $site->site_url ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}
		$host = strtolower( (string) wp_parse_url( $site->site_url, PHP_URL_HOST ) );
		$port = wp_parse_url( $site->site_url, PHP_URL_PORT );
		$path = trailingslashit( (string) wp_parse_url( $site->site_url, PHP_URL_PATH ) );

		// wp_blogs stores a non-default port as part of the domain ("example.test:8080").
		$actual  = $port ? $host . ':' . $port : $host;
		$matches = strtolower( $site->domain ) === $actual && strtolower( $path ) === strtolower( trailingslashit( $site->path ) );

		return new Indicator(
			$this->id(),
			$matches ? Indicator::GOOD : Indicator::WARNING,
			array(
				'url'      => $site->site_url,
				'expected' => $site->domain . $site->path,
			)
		);
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::WARNING === $indicator->state ) {
			return sprintf(
				/* translators: 1: Configured URL, 2: Network address. */
				__( 'The WordPress Address (%1$s) does not match the network address (%2$s).', 'multisite-fleet-manager' ),
				(string) ( $indicator->data['url'] ?? '' ),
				(string) ( $indicator->data['expected'] ?? '' )
			);
		}
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'The site address could not be read.', 'multisite-fleet-manager' );
		}
		return __( 'The site address matches the network address.', 'multisite-fleet-manager' );
	}
}
