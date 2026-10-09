<?php
/**
 * Administrator-related indicators.
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
 * Who can administer the site, as a count rather than a judgement:
 *
 * - No administrator at all: nobody can manage the site from its own
 *   dashboard (super admins still can), which is usually an accident.
 * - Unusually many administrators: every one of them is a way in, and the
 *   number tends to grow quietly on older sites.
 * - A super admin still using the default "admin" login: a well-known
 *   username to aim credential-stuffing at.
 *
 * These are counts and names. They say nothing about password strength,
 * two-factor use, or whether any account has been misused.
 */
final class AdministratorsCheck implements HealthCheck {

	public function id(): string {
		return 'administrators';
	}

	public function label(): string {
		return __( 'Administrators', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		/**
		 * Filters how many site administrators count as unusually many.
		 *
		 * @since 0.7.0
		 *
		 * @param int $limit Administrators per site.
		 */
		$many = max( 2, (int) apply_filters( 'wpfleet_many_administrators', 5 ) );

		$data = array(
			'admins'        => $site->admin_count,
			'many'          => $many,
			'super_admins'  => $context->super_admins,
			'default_login' => $context->has_default_admin_login,
		);

		if ( 0 === $site->admin_count ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( $site->admin_count >= $many || $context->has_default_admin_login ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		$admins = (int) ( $indicator->data['admins'] ?? 0 );
		$many   = (int) ( $indicator->data['many'] ?? 5 );

		if ( Indicator::ERROR === $indicator->state ) {
			return __( 'This site has no administrator of its own. Only super admins can manage it; give someone the administrator role if that is not deliberate.', 'multisite-fleet-manager' );
		}

		$count = sprintf(
			/* translators: %s: Number of administrators. */
			_n( '%s administrator on this site.', '%s administrators on this site.', $admins, 'multisite-fleet-manager' ),
			number_format_i18n( $admins )
		);

		$notes = array();
		if ( $admins >= $many ) {
			$notes[] = __( 'That is more than most sites need; each one is another account worth protecting.', 'multisite-fleet-manager' );
		}
		if ( ! empty( $indicator->data['default_login'] ) ) {
			$notes[] = __( 'A super admin still uses the default "admin" login, which is the first username attackers try.', 'multisite-fleet-manager' );
		}

		return trim( $count . ' ' . implode( ' ', $notes ) );
	}
}
