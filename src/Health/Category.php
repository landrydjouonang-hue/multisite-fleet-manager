<?php
/**
 * Indicator categories.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Groups the health indicators so a developer can read them by concern:
 * what is broken, what is slow, what is exposed, what is behind.
 *
 * The security group is explicitly a set of *configuration indicators*.
 * It is not a security assessment: it cannot see malware, modified files,
 * weak passwords, vulnerable code or an intrusion, and a clean result is
 * not evidence that a site is safe.
 */
final class Category {

	public const RELIABILITY = 'reliability';
	public const PERFORMANCE = 'performance';
	public const SECURITY    = 'security';
	public const MAINTENANCE = 'maintenance';
	public const OTHER       = 'other';

	/**
	 * Categories in display order.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::RELIABILITY, self::SECURITY, self::PERFORMANCE, self::MAINTENANCE, self::OTHER );
	}

	/**
	 * Label.
	 *
	 * @param string $category Category.
	 * @return string
	 */
	public static function label( string $category ): string {
		$labels = array(
			self::RELIABILITY => __( 'Reliability', 'multisite-fleet-manager' ),
			self::PERFORMANCE => __( 'Performance', 'multisite-fleet-manager' ),
			self::SECURITY    => __( 'Security indicators', 'multisite-fleet-manager' ),
			self::MAINTENANCE => __( 'Updates', 'multisite-fleet-manager' ),
			self::OTHER       => __( 'Other', 'multisite-fleet-manager' ),
		);
		return $labels[ $category ] ?? $labels[ self::OTHER ];
	}

	/**
	 * One-line description of what a category covers.
	 *
	 * @param string $category Category.
	 * @return string
	 */
	public static function description( string $category ): string {
		$descriptions = array(
			self::RELIABILITY => __( 'Whether the site is intact and reachable as configured.', 'multisite-fleet-manager' ),
			self::PERFORMANCE => __( 'Resources and settings that affect how fast the site runs.', 'multisite-fleet-manager' ),
			self::SECURITY    => __( 'Configuration signals only — not a security assessment.', 'multisite-fleet-manager' ),
			self::MAINTENANCE => __( 'How far behind the installed components are.', 'multisite-fleet-manager' ),
			self::OTHER       => __( 'Indicators added by other plugins.', 'multisite-fleet-manager' ),
		);
		return $descriptions[ $category ] ?? $descriptions[ self::OTHER ];
	}

	/**
	 * Dashicon.
	 *
	 * @param string $category Category.
	 * @return string
	 */
	public static function icon( string $category ): string {
		$icons = array(
			self::RELIABILITY => 'dashicons-shield-alt',
			self::PERFORMANCE => 'dashicons-performance',
			self::SECURITY    => 'dashicons-lock',
			self::MAINTENANCE => 'dashicons-update',
			self::OTHER       => 'dashicons-marker',
		);
		return $icons[ $category ] ?? $icons[ self::OTHER ];
	}

	/**
	 * The caveat shown wherever the security indicators appear.
	 *
	 * @return string
	 */
	public static function security_caveat(): string {
		return __( 'These are configuration indicators, not a security assessment. Fleet Manager does not scan for malware, modified files, vulnerable code, weak passwords or intrusions, and a clean result here does not mean a site is secure. Use a dedicated security product for that.', 'multisite-fleet-manager' );
	}

	/**
	 * Category of a built-in check.
	 *
	 * Checks added by other plugins fall into "other" unless they declare a
	 * category through the filter below.
	 *
	 * @param string $check_id Check ID.
	 * @return string
	 */
	public static function of( string $check_id ): string {
		$map = array(
			// Is the site intact?
			'site_data'          => self::RELIABILITY,
			'active_theme'       => self::RELIABILITY,
			'missing_plugins'    => self::RELIABILITY,
			'site_address'       => self::RELIABILITY,
			// Is it exposed?
			'https'              => self::SECURITY,
			'debug_mode'         => self::SECURITY,
			'file_editing'       => self::SECURITY,
			'administrators'     => self::SECURITY,
			'outdated_components' => self::SECURITY,
			// Is it fast?
			'php_version'        => self::PERFORMANCE,
			'memory_limits'      => self::PERFORMANCE,
			'cron'               => self::PERFORMANCE,
			'autoloaded_options' => self::PERFORMANCE,
			'database_size'      => self::PERFORMANCE,
			'active_plugins'     => self::PERFORMANCE,
			'storage'            => self::PERFORMANCE,
			// Is it current?
			'wordpress_version'  => self::MAINTENANCE,
			'database_version'   => self::MAINTENANCE,
			'database_server'    => self::MAINTENANCE,
			'plugin_updates'     => self::MAINTENANCE,
			'theme_updates'      => self::MAINTENANCE,
		);

		$category = $map[ $check_id ] ?? self::OTHER;

		/**
		 * Filters the category an indicator belongs to.
		 *
		 * @since 0.7.0
		 *
		 * @param string $category Category.
		 * @param string $check_id Check ID.
		 */
		$category = (string) apply_filters( 'wpfleet_health_check_category', $category, $check_id );

		return in_array( $category, self::all(), true ) ? $category : self::OTHER;
	}
}
