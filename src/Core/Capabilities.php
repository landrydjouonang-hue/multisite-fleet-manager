<?php
/**
 * Network capabilities.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Network-level capabilities.
 *
 * WordPress roles are stored per site, so network permissions cannot live on
 * a role. Instead:
 *
 * - Super admins implicitly hold every capability (core behaviour).
 * - Other users can be granted individual capabilities network-wide; grants
 *   are stored in the `wpfleet_access` network option and resolved through
 *   the `user_has_cap` filter.
 *
 * Capabilities:
 * - wpfleet_view_dashboard: open the Fleet Manager dashboard.
 * - wpfleet_view_sites:     browse the site inventory.
 * - wpfleet_run_discovery:  refresh the inventory (site discovery).
 * - wpfleet_view_extensions: browse installed plugins and themes.
 * - wpfleet_view_log:       read the operation log.
 * - wpfleet_manage_updates: update plugins and themes network-wide.
 * - wpfleet_manage_plugins: activate or deactivate plugins on a site.
 * - wpfleet_manage_access:  grant or revoke the grantable capabilities.
 *
 * The three manage_* capabilities are never grantable: they change code or
 * behaviour for the whole network, so they stay with super admins. Operations
 * additionally require core's own capability (update_plugins, update_themes,
 * activate_plugins), which Multisite reserves for super admins.
 */
final class Capabilities {

	public const VIEW_DASHBOARD  = 'wpfleet_view_dashboard';
	public const VIEW_SITES      = 'wpfleet_view_sites';
	public const RUN_DISCOVERY   = 'wpfleet_run_discovery';
	public const VIEW_EXTENSIONS = 'wpfleet_view_extensions';
	public const VIEW_LOG        = 'wpfleet_view_log';
	public const VIEW_USERS      = 'wpfleet_view_users';
	public const VIEW_REPORTS    = 'wpfleet_view_reports';
	public const MANAGE_USERS    = 'wpfleet_manage_users';
	public const MANAGE_UPDATES  = 'wpfleet_manage_updates';
	public const MANAGE_PLUGINS  = 'wpfleet_manage_plugins';
	public const MANAGE_ACCESS   = 'wpfleet_manage_access';
	public const MANAGE_SETTINGS = 'wpfleet_manage_settings';

	public const OPTION = 'wpfleet_access';

	/**
	 * All capabilities defined by the plugin.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::VIEW_DASHBOARD,
			self::VIEW_SITES,
			self::RUN_DISCOVERY,
			self::VIEW_EXTENSIONS,
			self::VIEW_LOG,
			self::VIEW_USERS,
			self::VIEW_REPORTS,
			self::MANAGE_USERS,
			self::MANAGE_UPDATES,
			self::MANAGE_PLUGINS,
			self::MANAGE_ACCESS,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Capabilities that may be delegated to non-super-admins (read-only ones plus discovery).
	 *
	 * @return string[]
	 */
	public static function grantable(): array {
		return array( self::VIEW_DASHBOARD, self::VIEW_SITES, self::RUN_DISCOVERY, self::VIEW_EXTENSIONS, self::VIEW_LOG, self::VIEW_USERS, self::VIEW_REPORTS );
	}

	/**
	 * Human-readable labels.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			self::VIEW_DASHBOARD => __( 'View the Fleet Manager dashboard', 'multisite-fleet-manager' ),
			self::VIEW_SITES     => __( 'Browse the site inventory', 'multisite-fleet-manager' ),
			self::RUN_DISCOVERY   => __( 'Run site discovery', 'multisite-fleet-manager' ),
			self::VIEW_EXTENSIONS => __( 'Browse plugins and themes', 'multisite-fleet-manager' ),
			self::VIEW_LOG        => __( 'Read the operation log', 'multisite-fleet-manager' ),
			self::VIEW_USERS      => __( 'Browse network users', 'multisite-fleet-manager' ),
			self::VIEW_REPORTS    => __( 'View and export network reports', 'multisite-fleet-manager' ),
			self::MANAGE_USERS    => __( 'Manage user access to sites', 'multisite-fleet-manager' ),
			self::MANAGE_UPDATES  => __( 'Update plugins and themes', 'multisite-fleet-manager' ),
			self::MANAGE_PLUGINS  => __( 'Activate and deactivate plugins on sites', 'multisite-fleet-manager' ),
			self::MANAGE_ACCESS   => __( 'Manage Fleet Manager access', 'multisite-fleet-manager' ),
			self::MANAGE_SETTINGS => __( 'Change network settings', 'multisite-fleet-manager' ),
		);
	}

	/**
	 * Hooks capability resolution.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'user_has_cap', array( $this, 'filter_user_caps' ), 10, 4 );
		add_action( 'wpmu_delete_user', array( $this, 'forget_user' ) );
		add_action( 'deleted_user', array( $this, 'forget_user' ) );
	}

	/**
	 * Adds network grants to a user's capabilities.
	 *
	 * @param array<string,bool> $allcaps All capabilities of the user.
	 * @param string[]           $caps    Required primitive capabilities.
	 * @param array<int,mixed>   $args    Arguments (requested cap, user ID, ...).
	 * @param \WP_User|null      $user    User.
	 * @return array<string,bool>
	 */
	public function filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		if ( ! is_array( $allcaps ) || ! $user instanceof \WP_User || ! $user->ID ) {
			return $allcaps;
		}
		if ( ! array_intersect( (array) $caps, self::grantable() ) ) {
			return $allcaps;
		}
		foreach ( self::granted( $user->ID ) as $cap ) {
			$allcaps[ $cap ] = true;
		}
		return $allcaps;
	}

	/**
	 * Capabilities granted to a user on the current network (super admins excluded).
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	public static function granted( int $user_id ): array {
		$grants = self::grants();
		$caps   = $grants[ $user_id ] ?? array();

		/**
		 * Filters the Fleet Manager capabilities granted to a user.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $caps    Granted capabilities.
		 * @param int      $user_id User ID.
		 */
		$caps = (array) apply_filters( 'wpfleet_user_capabilities', $caps, $user_id );

		return array_values( array_intersect( self::grantable(), $caps ) );
	}

	/**
	 * All grants on the current network.
	 *
	 * @return array<int,string[]>
	 */
	public static function grants(): array {
		$grants = get_site_option( self::OPTION, array() );
		if ( ! is_array( $grants ) ) {
			return array();
		}
		$clean = array();
		foreach ( $grants as $user_id => $caps ) {
			$user_id = absint( $user_id );
			if ( $user_id && is_array( $caps ) ) {
				$clean[ $user_id ] = array_values( array_intersect( self::grantable(), $caps ) );
			}
		}
		return $clean;
	}

	/**
	 * Grants capabilities to a user network-wide, replacing any previous grant.
	 *
	 * Any grant includes the dashboard capability, which the menu requires.
	 *
	 * @param int      $user_id User ID.
	 * @param string[] $caps    Capabilities; non-grantable ones are ignored.
	 * @return bool Whether the user exists and the grant was stored.
	 */
	public static function grant( int $user_id, array $caps ): bool {
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}
		$caps = array_values( array_intersect( self::grantable(), $caps ) );
		if ( ! $caps ) {
			return self::revoke( $user_id );
		}
		if ( ! in_array( self::VIEW_DASHBOARD, $caps, true ) ) {
			array_unshift( $caps, self::VIEW_DASHBOARD );
		}

		$grants             = self::grants();
		$grants[ $user_id ] = $caps;
		update_site_option( self::OPTION, $grants );
		return true;
	}

	/**
	 * Removes every grant held by a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function revoke( int $user_id ): bool {
		$grants = self::grants();
		if ( isset( $grants[ $user_id ] ) ) {
			unset( $grants[ $user_id ] );
			update_site_option( self::OPTION, $grants );
		}
		return true;
	}

	/**
	 * Drops grants of deleted users.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function forget_user( $user_id ): void {
		// On Multisite, `deleted_user` also fires when a user is only removed from one site.
		if ( 'deleted_user' === current_action() && get_userdata( (int) $user_id ) ) {
			return;
		}
		self::revoke( (int) $user_id );
	}
}
