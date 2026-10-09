<?php
/**
 * Site health roll-up statuses.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Overall health of a site, derived from its indicators:
 * any error → Error, otherwise any warning → Needs Attention, otherwise Healthy.
 * Indicators in the "unknown" state do not affect the roll-up.
 */
final class Health {

	public const HEALTHY   = 'healthy';
	public const ATTENTION = 'attention';
	public const ERROR     = 'error';

	/**
	 * All statuses, best first.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::HEALTHY, self::ATTENTION, self::ERROR );
	}

	/**
	 * Rolls indicator states up into a status.
	 *
	 * @param Indicator[] $indicators Indicators.
	 * @return string
	 */
	public static function from_indicators( array $indicators ): string {
		$states = array_map( static fn( Indicator $i ) => $i->state, $indicators );
		if ( in_array( Indicator::ERROR, $states, true ) ) {
			return self::ERROR;
		}
		if ( in_array( Indicator::WARNING, $states, true ) ) {
			return self::ATTENTION;
		}
		return self::HEALTHY;
	}

	/**
	 * Label.
	 *
	 * @param string $health Status ('' = not evaluated yet).
	 * @return string
	 */
	public static function label( string $health ): string {
		$labels = array(
			self::HEALTHY   => __( 'Healthy', 'multisite-fleet-manager' ),
			self::ATTENTION => __( 'Needs Attention', 'multisite-fleet-manager' ),
			self::ERROR     => __( 'Error', 'multisite-fleet-manager' ),
		);
		return $labels[ $health ] ?? __( 'Not checked', 'multisite-fleet-manager' );
	}

	/**
	 * Dashicon.
	 *
	 * @param string $health Status.
	 * @return string
	 */
	public static function icon( string $health ): string {
		$icons = array(
			self::HEALTHY   => 'dashicons-yes-alt',
			self::ATTENTION => 'dashicons-warning',
			self::ERROR     => 'dashicons-dismiss',
		);
		return $icons[ $health ] ?? 'dashicons-clock';
	}
}
