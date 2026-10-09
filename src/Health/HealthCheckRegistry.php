<?php
/**
 * Health check registry.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

use FleetManager\Health\Contracts\HealthCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the health checks, in display order. Extensible through the
 * `wpfleet_health_checks` filter.
 */
final class HealthCheckRegistry {

	/**
	 * Checks keyed by ID, or null until first use.
	 *
	 * @var array<string,HealthCheck>|null
	 */
	private ?array $checks = null;

	/**
	 * Built-in check classes.
	 *
	 * @return string[]
	 */
	public static function builtin(): array {
		return array(
			// Broken first, then versions, updates, configuration and capacity.
			Checks\SiteDataCheck::class,
			Checks\ActiveThemeCheck::class,
			Checks\MissingPluginsCheck::class,
			Checks\WordPressVersionCheck::class,
			Checks\DatabaseVersionCheck::class,
			Checks\PhpVersionCheck::class,
			Checks\DatabaseServerCheck::class,
			Checks\PluginUpdatesCheck::class,
			Checks\ThemeUpdatesCheck::class,
			Checks\OutdatedComponentsCheck::class,
			Checks\HttpsCheck::class,
			Checks\DebugModeCheck::class,
			Checks\FileEditingCheck::class,
			Checks\AdministratorsCheck::class,
			Checks\MemoryLimitCheck::class,
			Checks\CronCheck::class,
			Checks\AutoloadedOptionsCheck::class,
			Checks\DatabaseSizeCheck::class,
			Checks\ActivePluginsCheck::class,
			Checks\StorageCheck::class,
			Checks\SiteAddressCheck::class,
		);
	}

	/**
	 * All checks.
	 *
	 * @return array<string,HealthCheck>
	 */
	public function all(): array {
		if ( null === $this->checks ) {
			$checks = array_map( static fn( $class ) => new $class(), self::builtin() );

			/**
			 * Filters the health checks applied to every site.
			 *
			 * Checks must implement FleetManager\Health\Contracts\HealthCheck and
			 * must not switch sites or make HTTP requests.
			 *
			 * @since 0.2.0
			 *
			 * @param HealthCheck[] $checks Checks.
			 */
			$checks = (array) apply_filters( 'wpfleet_health_checks', $checks );

			$this->checks = array();
			foreach ( $checks as $check ) {
				if ( $check instanceof HealthCheck && ! isset( $this->checks[ $check->id() ] ) ) {
					$this->checks[ $check->id() ] = $check;
				}
			}
		}
		return $this->checks;
	}

	/**
	 * One check.
	 *
	 * @param string $id Check ID.
	 * @return HealthCheck|null
	 */
	public function get( string $id ): ?HealthCheck {
		return $this->all()[ $id ] ?? null;
	}

	/**
	 * Checks grouped by category, in display order, skipping empty groups.
	 *
	 * @return array<string,array<string,HealthCheck>>
	 */
	public function by_category(): array {
		$groups = array_fill_keys( Category::all(), array() );
		foreach ( $this->all() as $id => $check ) {
			$groups[ Category::of( $id ) ][ $id ] = $check;
		}
		return array_filter( $groups );
	}

	/**
	 * Described indicators grouped by category, skipping empty groups.
	 *
	 * @param array<int,array<string,mixed>> $stored Stored indicators.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function describe_by_category( array $stored ): array {
		$groups = array_fill_keys( Category::all(), array() );
		foreach ( $this->describe( $stored ) as $indicator ) {
			$groups[ $indicator['category'] ][] = $indicator;
		}
		return array_filter( $groups );
	}

	/**
	 * Display-ready indicators of a site, in registry order.
	 *
	 * @param array<int,array<string,mixed>> $stored Stored indicators.
	 * @return array<int,array{id: string, label: string, state: string, message: string, category: string}>
	 */
	public function describe( array $stored ): array {
		$by_id = array();
		foreach ( $stored as $row ) {
			$indicator = is_array( $row ) ? Indicator::from_array( $row ) : null;
			if ( $indicator ) {
				$by_id[ $indicator->id ] = $indicator;
			}
		}

		$out = array();
		foreach ( $this->all() as $id => $check ) {
			if ( isset( $by_id[ $id ] ) ) {
				$out[] = array(
					'id'       => $id,
					'label'    => $check->label(),
					'state'    => $by_id[ $id ]->state,
					'message'  => $check->message( $by_id[ $id ] ),
					'category' => Category::of( $id ),
				);
			}
		}
		return $out;
	}
}
