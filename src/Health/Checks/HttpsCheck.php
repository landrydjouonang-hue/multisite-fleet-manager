<?php
/**
 * HTTPS.
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
 * Compares the site's two addresses: the Site Address (`home`) visitors use
 * and the WordPress Address (`siteurl`) the admin runs on.
 *
 * - Both HTTPS: good.
 * - One of each: error — logins and assets typically break or load insecurely.
 * - Both HTTP: needs attention.
 *
 * This only reads the configured addresses. It does not test the
 * certificate, the redirect or the TLS configuration.
 */
final class HttpsCheck implements HealthCheck {

	public function id(): string {
		return 'https';
	}

	public function label(): string {
		return __( 'HTTPS', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( '' === $site->site_url || '' === $site->home_url ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		$admin   = 'https' === strtolower( (string) wp_parse_url( $site->site_url, PHP_URL_SCHEME ) );
		$visitor = 'https' === strtolower( (string) wp_parse_url( $site->home_url, PHP_URL_SCHEME ) );
		$data    = array(
			'site_url' => $admin,
			'home_url' => $visitor,
		);

		if ( $admin !== $visitor ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		return new Indicator( $this->id(), $admin ? Indicator::GOOD : Indicator::WARNING, $data );
	}

	public function message( Indicator $indicator ): string {
		switch ( $indicator->state ) {
			case Indicator::ERROR:
				return empty( $indicator->data['site_url'] )
					? __( 'The Site Address uses HTTPS but the WordPress Address does not. Signing in and admin assets are likely to break.', 'multisite-fleet-manager' )
					: __( 'The WordPress Address uses HTTPS but the Site Address does not. Visitors may load the site insecurely.', 'multisite-fleet-manager' );
			case Indicator::WARNING:
				return __( 'This site is served over HTTP. Traffic, including sign-ins, is not encrypted.', 'multisite-fleet-manager' );
			case Indicator::UNKNOWN:
				return __( 'The site addresses could not be read.', 'multisite-fleet-manager' );
		}
		return __( 'Both site addresses use HTTPS. The certificate itself is not checked.', 'multisite-fleet-manager' );
	}
}
