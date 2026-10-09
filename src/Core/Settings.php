<?php
/**
 * Network settings.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

defined( 'ABSPATH' ) || exit;

/**
 * One network option holds every setting, and the settings are applied by
 * hooking the plugin's own filters.
 *
 * That keeps one source of truth: a developer can override anything with a
 * filter at a later priority, and the settings screen simply provides the
 * defaults for people who would rather not write code.
 */
final class Settings {

	public const OPTION = 'wpfleet_settings';

	/**
	 * Defaults, which are also the schema: anything not listed is dropped.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Discovery.
			'discovery_recurrence'    => 'daily',
			'discovery_batch_size'    => 25,
			// Retention.
			'log_retention_days'      => 180,
			'snapshot_retention_days' => 365,
			'snapshot_interval_hours' => 12,
			// Notifications.
			'notifications_enabled'   => false,
			'notification_recipients' => '',
			'notification_frequency'  => 'weekly',
			'notify_errors'           => true,
			'notify_updates'          => true,
			'notify_failed_ops'       => true,
			// Thresholds.
			'recommended_php'         => '8.1',
			'many_administrators'     => 5,
			'autoload_warning_kb'     => 800,
			'database_warning_mb'     => 512,
			'active_plugin_warning'   => 25,
			'memory_warning_mb'       => 128,
		);
	}

	/**
	 * Current settings, defaults filled in.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_site_option( self::OPTION, array() );
		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Stores settings (sanitised; unknown keys dropped).
	 *
	 * @param array<string,mixed> $values Raw values.
	 * @return array<string,mixed> What was stored.
	 */
	public static function save( array $values ): array {
		$clean = self::sanitize( $values );
		update_site_option( self::OPTION, $clean );

		/**
		 * Fires after the network settings are saved.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $clean Stored settings.
		 */
		do_action( 'wpfleet_settings_saved', $clean );

		return $clean;
	}

	/**
	 * Validates and normalises a settings array.
	 *
	 * @param array<string,mixed> $values Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $values ): array {
		$defaults = self::defaults();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			$value = $values[ $key ] ?? $default;

			switch ( $key ) {
				case 'discovery_recurrence':
					$schedules = array_keys( wp_get_schedules() );
					$clean[ $key ] = in_array( $value, $schedules, true ) ? $value : $default;
					break;
				case 'notification_frequency':
					$clean[ $key ] = in_array( $value, array( 'daily', 'weekly' ), true ) ? $value : $default;
					break;
				case 'notification_recipients':
					$clean[ $key ] = implode( ', ', self::emails( (string) $value ) );
					break;
				case 'recommended_php':
					$clean[ $key ] = preg_match( '/^\d+(\.\d+){0,2}$/', (string) $value ) ? (string) $value : $default;
					break;
				case 'notifications_enabled':
				case 'notify_errors':
				case 'notify_updates':
				case 'notify_failed_ops':
					$clean[ $key ] = (bool) $value;
					break;
				default:
					$clean[ $key ] = self::clamp( $key, (int) $value, (int) $default );
			}
		}

		return $clean;
	}

	/**
	 * Valid, unique e-mail addresses from a comma-separated list.
	 *
	 * Semicolons and any whitespace separate addresses as well. sanitize_email()
	 * would otherwise fuse a pasted header-injection attempt such as
	 * "ops@example.test\r\nBcc: evil@example.test" into one plausible-looking
	 * address, and the intended recipient would silently stop receiving mail.
	 *
	 * @param string $value Raw list.
	 * @return string[]
	 */
	public static function emails( string $value ): array {
		$emails = array_filter( array_map( 'trim', (array) preg_split( '/[\s,;]+/', $value ) ) );
		$valid  = array();
		foreach ( $emails as $email ) {
			$email = sanitize_email( $email );
			if ( $email && is_email( $email ) ) {
				$valid[] = $email;
			}
		}
		return array_values( array_unique( $valid ) );
	}

	/**
	 * Keeps a numeric setting inside sane bounds.
	 *
	 * @param string $key     Setting key.
	 * @param int    $value   Submitted value.
	 * @param int    $default Default.
	 * @return int
	 */
	private static function clamp( string $key, int $value, int $default ): int {
		$bounds = array(
			'discovery_batch_size'    => array( 5, 200 ),
			'log_retention_days'      => array( 7, 3650 ),
			'snapshot_retention_days' => array( 7, 3650 ),
			'snapshot_interval_hours' => array( 0, 720 ),
			'many_administrators'     => array( 2, 100 ),
			'autoload_warning_kb'     => array( 50, 102400 ),
			'database_warning_mb'     => array( 10, 1048576 ),
			'active_plugin_warning'   => array( 5, 500 ),
			'memory_warning_mb'       => array( 32, 4096 ),
		);

		if ( ! isset( $bounds[ $key ] ) ) {
			return $default;
		}
		list( $min, $max ) = $bounds[ $key ];
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Applies the settings by feeding the plugin's own filters.
	 *
	 * Priority 10 leaves room for site-specific overrides at a later
	 * priority, which keeps code in control of configuration.
	 *
	 * @return void
	 */
	public function register(): void {
		// Each closure reads the value when the filter runs, so a save takes
		// effect immediately rather than on the next request.
		add_filter( 'wpfleet_discovery_recurrence', static fn() => self::get( 'discovery_recurrence' ) );
		add_filter( 'wpfleet_discovery_batch_size', static fn() => self::get( 'discovery_batch_size' ) );
		add_filter( 'wpfleet_operation_log_retention_days', static fn() => self::get( 'log_retention_days' ) );
		add_filter( 'wpfleet_snapshot_retention_days', static fn() => self::get( 'snapshot_retention_days' ) );
		add_filter( 'wpfleet_snapshot_interval', static fn() => (int) self::get( 'snapshot_interval_hours' ) * HOUR_IN_SECONDS );
		add_filter( 'wpfleet_recommended_php', static fn() => self::get( 'recommended_php' ) );
		add_filter( 'wpfleet_many_administrators', static fn() => self::get( 'many_administrators' ) );

		add_filter(
			'wpfleet_autoload_limits',
			static function ( $limits ) {
				$warning = (int) self::get( 'autoload_warning_kb' ) * KB_IN_BYTES;
				return array(
					'warning' => $warning,
					'error'   => max( (int) $limits['error'], $warning * 2 ),
				);
			}
		);
		add_filter(
			'wpfleet_database_size_limits',
			static function ( $limits ) {
				$warning = (int) self::get( 'database_warning_mb' ) * MB_IN_BYTES;
				return array(
					'warning' => $warning,
					'error'   => max( (int) $limits['error'], $warning * 4 ),
				);
			}
		);
		add_filter(
			'wpfleet_active_plugin_limits',
			static function ( $limits ) {
				$warning = (int) self::get( 'active_plugin_warning' );
				return array(
					'warning' => $warning,
					'error'   => max( (int) $limits['error'], $warning * 2 ),
				);
			}
		);
		add_filter(
			'wpfleet_memory_limits',
			static function ( $limits ) {
				$warning = (int) self::get( 'memory_warning_mb' ) * MB_IN_BYTES;
				return array(
					'warning' => $warning,
					'error'   => min( (int) $limits['error'], (int) ( $warning / 2 ) ),
				);
			}
		);
	}

	/**
	 * Who receives notifications: the configured addresses, or the network
	 * admin e-mail when none are set.
	 *
	 * @return string[]
	 */
	public static function recipients(): array {
		$configured = self::emails( (string) self::get( 'notification_recipients' ) );
		if ( $configured ) {
			return $configured;
		}
		$fallback = sanitize_email( (string) get_site_option( 'admin_email', '' ) );
		return $fallback && is_email( $fallback ) ? array( $fallback ) : array();
	}
}
