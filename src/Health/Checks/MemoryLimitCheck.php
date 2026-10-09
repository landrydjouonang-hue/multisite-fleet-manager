<?php
/**
 * Memory limits.
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
 * PHP's memory_limit and the WordPress limits are set for the whole
 * installation, so every site inherits them.
 *
 * Too little memory shows up as white screens and failed imports or
 * updates, usually on the busiest site first. WordPress's own floor is
 * 40 MB (64 MB in the admin); the thresholds here are deliberately a
 * little above that and filterable.
 */
final class MemoryLimitCheck implements HealthCheck {

	public function id(): string {
		return 'memory_limits';
	}

	public function label(): string {
		return __( 'Memory limits', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		if ( 0 === $context->php_memory_limit ) {
			return new Indicator( $this->id(), Indicator::UNKNOWN );
		}

		/**
		 * Filters the memory thresholds, in bytes.
		 *
		 * @since 0.7.0
		 *
		 * @param array{warning: int, error: int} $limits Thresholds.
		 */
		$limits = (array) apply_filters(
			'wpfleet_memory_limits',
			array(
				'warning' => 128 * MB_IN_BYTES,
				'error'   => 64 * MB_IN_BYTES,
			)
		);

		$data = array(
			'php'     => $context->php_memory_limit,
			'wp'      => $context->wp_memory_limit,
			'wp_max'  => $context->wp_max_memory_limit,
			'warning' => (int) $limits['warning'],
		);

		// -1 means PHP imposes no limit.
		if ( -1 === $context->php_memory_limit ) {
			return new Indicator( $this->id(), Indicator::GOOD, $data );
		}
		if ( $context->php_memory_limit < (int) $limits['error'] ) {
			return new Indicator( $this->id(), Indicator::ERROR, $data );
		}
		if ( $context->php_memory_limit < (int) $limits['warning'] ) {
			return new Indicator( $this->id(), Indicator::WARNING, $data );
		}
		return new Indicator( $this->id(), Indicator::GOOD, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::UNKNOWN === $indicator->state ) {
			return __( 'The PHP memory limit could not be read.', 'multisite-fleet-manager' );
		}

		$php = (int) ( $indicator->data['php'] ?? 0 );
		$wp  = (int) ( $indicator->data['wp'] ?? 0 );

		$detail = $wp > 0
			? sprintf(
				/* translators: 1: PHP memory limit, 2: WP_MEMORY_LIMIT. */
				__( 'PHP allows %1$s; WordPress asks for %2$s.', 'multisite-fleet-manager' ),
				-1 === $php ? __( 'unlimited memory', 'multisite-fleet-manager' ) : size_format( $php ),
				size_format( $wp )
			)
			: sprintf(
				/* translators: %s: PHP memory limit. */
				__( 'PHP allows %s.', 'multisite-fleet-manager' ),
				-1 === $php ? __( 'unlimited memory', 'multisite-fleet-manager' ) : size_format( $php )
			);

		switch ( $indicator->state ) {
			case Indicator::ERROR:
				return $detail . ' ' . __( 'That is below what WordPress needs for admin tasks; expect blank pages during imports and updates.', 'multisite-fleet-manager' );
			case Indicator::WARNING:
				return $detail . ' ' . sprintf(
					/* translators: %s: Recommended memory. */
					__( '%s or more is a safer floor for a busy network.', 'multisite-fleet-manager' ),
					size_format( (int) ( $indicator->data['warning'] ?? 0 ) )
				);
		}

		return $detail;
	}
}
