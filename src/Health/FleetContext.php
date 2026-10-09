<?php
/**
 * Network-wide facts shared by all health checks.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Loaded once per evaluation pass from core's own caches: the update
 * transients WordPress maintains (it checks WordPress.org twice daily), the
 * network-activated plugins and the core database version. Reading these
 * never triggers a remote request.
 */
final class FleetContext {

	/**
	 * Plugin basename → new version, or null when no update data exists.
	 *
	 * @var array<string,string>|null
	 */
	public ?array $plugin_updates;

	/**
	 * Theme stylesheet → new version, or null when no update data exists.
	 *
	 * @var array<string,string>|null
	 */
	public ?array $theme_updates;

	/**
	 * Network-activated plugin basenames.
	 *
	 * @var string[]
	 */
	public array $network_plugins;

	public int $db_version;

	public string $wp_version;

	/**
	 * Newer WordPress version offered for the network, if any.
	 */
	public ?string $core_update;

	public ?int $plugins_checked_at;

	public ?int $themes_checked_at;

	/**
	 * PHP running the network, and the versions WordPress asks for.
	 */
	public string $php_version = PHP_VERSION;

	public string $php_required = '';

	public string $php_recommended = '';

	/**
	 * Database server: version, flavour and the versions WordPress asks for.
	 */
	public string $db_server = '';

	public bool $is_mariadb = false;

	public string $db_required = '';

	public string $db_recommended = '';

	/**
	 * Free and total bytes on the filesystem holding wp-content, or null when
	 * the host does not allow reading them.
	 */
	public ?int $disk_free = null;

	public ?int $disk_total = null;

	/**
	 * WP-Cron configuration (constants, so network-wide).
	 */
	public bool $cron_disabled = false;

	public bool $alternate_cron = false;

	/**
	 * Debugging constants (network-wide).
	 *
	 * @var array<string,bool>
	 */
	public array $debug = array();

	/**
	 * Memory limits in bytes: what PHP allows, and what WordPress asks for
	 * on the front end and in the admin. -1 means unlimited.
	 */
	public int $php_memory_limit = 0;

	public int $wp_memory_limit = 0;

	public int $wp_max_memory_limit = 0;

	/**
	 * File modification constants (network-wide).
	 */
	public bool $file_edit_disallowed = false;

	public bool $file_mods_disallowed = false;

	/**
	 * Administrator signals for the network.
	 */
	public int $super_admins = 0;

	public bool $has_default_admin_login = false;

	/**
	 * Memoised file checks.
	 *
	 * @var array<string,bool>
	 */
	private array $plugin_exists = array();

	/**
	 * Memoised theme lookups.
	 *
	 * @var array<string,\WP_Theme>
	 */
	private array $themes = array();

	/**
	 * Builds the context for the current network.
	 *
	 * @return self
	 */
	public static function load(): self {
		global $wp_db_version, $wp_version, $wpdb;

		$context = new self();

		$plugins                     = get_site_transient( 'update_plugins' );
		$context->plugin_updates     = self::versions( $plugins, 'new_version' );
		$context->plugins_checked_at = is_object( $plugins ) && ! empty( $plugins->last_checked ) ? (int) $plugins->last_checked : null;

		$themes                     = get_site_transient( 'update_themes' );
		$context->theme_updates     = self::versions( $themes, 'new_version' );
		$context->themes_checked_at = is_object( $themes ) && ! empty( $themes->last_checked ) ? (int) $themes->last_checked : null;

		$network                  = get_site_option( 'active_sitewide_plugins', array() );
		$context->network_plugins = is_array( $network ) ? array_map( 'strval', array_keys( $network ) ) : array();

		$context->db_version  = (int) $wp_db_version;
		$context->wp_version  = (string) $wp_version;
		$context->core_update = self::core_update();

		$context->php_version = PHP_VERSION;
		$context->php_required = (string) ( $GLOBALS['required_php_version'] ?? '7.2.24' );
		/**
		 * Filters the PHP version Fleet Manager treats as current enough.
		 *
		 * Supported PHP versions change over time; keep this in step with
		 * php.net's security-support list.
		 *
		 * @since 0.5.0
		 *
		 * @param string $version Recommended minimum PHP version.
		 */
		$context->php_recommended = (string) apply_filters( 'wpfleet_recommended_php', '8.1' );

		$context->db_server  = self::db_version_string();
		$context->is_mariadb = false !== stripos( (string) $wpdb->db_server_info(), 'mariadb' );
		$context->db_required = $context->is_mariadb
			? (string) ( $GLOBALS['required_mysql_version'] ?? '5.5.5' )
			: (string) ( $GLOBALS['required_mysql_version'] ?? '5.5.5' );
		/**
		 * Filters the database server version Fleet Manager treats as current enough.
		 *
		 * @since 0.5.0
		 *
		 * @param string $version    Recommended minimum version.
		 * @param bool   $is_mariadb Whether the server is MariaDB.
		 */
		$context->db_recommended = (string) apply_filters( 'wpfleet_recommended_database', $context->is_mariadb ? '10.6' : '8.0', $context->is_mariadb );

		$disk                = self::disk_space();
		$context->disk_free  = $disk['free'];
		$context->disk_total = $disk['total'];

		$context->cron_disabled  = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$context->alternate_cron = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;

		$context->debug = array(
			'debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'display'       => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
			'log'           => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'script_debug'  => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'display_errors' => filter_var( ini_get( 'display_errors' ), FILTER_VALIDATE_BOOLEAN ),
		);

		$context->php_memory_limit    = self::bytes( (string) ini_get( 'memory_limit' ) );
		$context->wp_memory_limit     = defined( 'WP_MEMORY_LIMIT' ) ? self::bytes( (string) WP_MEMORY_LIMIT ) : 0;
		$context->wp_max_memory_limit = defined( 'WP_MAX_MEMORY_LIMIT' ) ? self::bytes( (string) WP_MAX_MEMORY_LIMIT ) : 0;

		$context->file_edit_disallowed = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$context->file_mods_disallowed = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;

		$supers                           = array_map( 'strtolower', array_map( 'strval', (array) get_super_admins() ) );
		$context->super_admins            = count( $supers );
		$context->has_default_admin_login = in_array( 'admin', $supers, true );

		return $context;
	}

	/**
	 * Converts a PHP size string ("256M") to bytes; -1 means unlimited.
	 *
	 * @param string $value Size string.
	 * @return int
	 */
	private static function bytes( string $value ): int {
		$value = trim( $value );
		if ( '' === $value ) {
			return 0;
		}
		if ( '-1' === $value ) {
			return -1;
		}
		return (int) wp_convert_hr_to_bytes( $value );
	}

	/**
	 * Database server version without the build suffix.
	 *
	 * @return string
	 */
	private static function db_version_string(): string {
		global $wpdb;
		$info = (string) $wpdb->db_version();
		if ( '' !== $info ) {
			return $info;
		}
		// MariaDB reports "5.5.5-10.4.32-MariaDB" through some drivers.
		return preg_match( '/[\d.]+/', (string) $wpdb->db_server_info(), $m ) ? $m[0] : '';
	}

	/**
	 * Free and total disk space, when the host allows reading them.
	 *
	 * Some hosts disable these functions or restrict them with open_basedir,
	 * so failure is normal and reported as "unavailable" rather than an error.
	 *
	 * @return array{free: int|null, total: int|null}
	 */
	private static function disk_space(): array {
		$unavailable = array(
			'free'  => null,
			'total' => null,
		);

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( ! function_exists( 'disk_free_space' ) || in_array( 'disk_free_space', $disabled, true ) ) {
			return $unavailable;
		}

		$free  = @disk_free_space( WP_CONTENT_DIR ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Restricted hosts throw warnings.
		$total = function_exists( 'disk_total_space' ) && ! in_array( 'disk_total_space', $disabled, true )
			? @disk_total_space( WP_CONTENT_DIR ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			: false;

		return array(
			'free'  => is_float( $free ) || is_int( $free ) ? (int) $free : null,
			'total' => is_float( $total ) || is_int( $total ) ? (int) $total : null,
		);
	}

	/**
	 * Whether an active plugin's main file exists.
	 *
	 * @param string $basename Plugin basename.
	 * @return bool
	 */
	public function plugin_exists( string $basename ): bool {
		if ( ! isset( $this->plugin_exists[ $basename ] ) ) {
			$this->plugin_exists[ $basename ] = 0 === validate_file( $basename ) && is_file( WP_PLUGIN_DIR . '/' . $basename );
		}
		return $this->plugin_exists[ $basename ];
	}

	/**
	 * Theme object (memoised).
	 *
	 * @param string $stylesheet Stylesheet.
	 * @return \WP_Theme
	 */
	public function theme( string $stylesheet ): \WP_Theme {
		if ( ! isset( $this->themes[ $stylesheet ] ) ) {
			$this->themes[ $stylesheet ] = wp_get_theme( $stylesheet );
		}
		return $this->themes[ $stylesheet ];
	}

	/**
	 * Extracts "item => new version" from an update transient's response.
	 *
	 * @param mixed  $transient Transient value.
	 * @param string $field     Version field.
	 * @return array<string,string>|null Null when WordPress has not checked yet.
	 */
	private static function versions( $transient, string $field ): ?array {
		if ( ! is_object( $transient ) || empty( $transient->last_checked ) ) {
			return null;
		}
		$versions = array();
		foreach ( (array) ( $transient->response ?? array() ) as $key => $item ) {
			$item    = (array) $item;
			$version = isset( $item[ $field ] ) ? (string) $item[ $field ] : '';
			if ( '' !== $version ) {
				$versions[ (string) $key ] = $version;
			}
		}
		return $versions;
	}

	/**
	 * Newer core version offered by the update_core transient.
	 *
	 * @return string|null
	 */
	private static function core_update(): ?string {
		$core = get_site_transient( 'update_core' );
		if ( ! is_object( $core ) || empty( $core->updates ) ) {
			return null;
		}
		foreach ( (array) $core->updates as $offer ) {
			if ( isset( $offer->response, $offer->current ) && 'upgrade' === $offer->response ) {
				return (string) $offer->current;
			}
		}
		return null;
	}
}
