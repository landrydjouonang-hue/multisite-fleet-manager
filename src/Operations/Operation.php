<?php
/**
 * Operation identifiers and result statuses.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations;

defined( 'ABSPATH' ) || exit;

/**
 * Names of the state-changing operations and of their logged outcomes.
 */
final class Operation {

	public const PLUGIN_UPDATE     = 'plugin_update';
	public const THEME_UPDATE      = 'theme_update';
	public const PLUGIN_ACTIVATE   = 'plugin_activate';
	public const PLUGIN_DEACTIVATE = 'plugin_deactivate';
	public const USER_ADD          = 'user_add_to_site';
	public const USER_REMOVE       = 'user_remove_from_site';
	public const USER_ROLE         = 'user_change_role';
	public const SNAPSHOT_CAPTURE  = 'snapshot_capture';
	public const SETTINGS_UPDATE   = 'settings_update';
	public const ACCESS_UPDATE     = 'access_update';

	/** Completed as requested. */
	public const SUCCESS = 'success';
	/** Attempted, but WordPress reported a failure. */
	public const FAILED = 'failed';
	/** Refused before execution: bad nonce, invalid target, unsupported, conflict. */
	public const REJECTED = 'rejected';
	/** Refused because the user lacks a required capability. */
	public const DENIED = 'denied';

	/**
	 * All operations.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::PLUGIN_UPDATE, self::THEME_UPDATE, self::PLUGIN_ACTIVATE, self::PLUGIN_DEACTIVATE, self::USER_ADD, self::USER_REMOVE, self::USER_ROLE, self::SNAPSHOT_CAPTURE, self::SETTINGS_UPDATE, self::ACCESS_UPDATE );
	}

	/**
	 * All outcome statuses.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return array( self::SUCCESS, self::FAILED, self::REJECTED, self::DENIED );
	}

	/**
	 * Nonce action protecting an operation.
	 *
	 * @param string $operation Operation.
	 * @return string
	 */
	public static function nonce_action( string $operation ): string {
		return 'wpfleet_op_' . $operation;
	}

	/**
	 * Operation label.
	 *
	 * @param string $operation Operation.
	 * @return string
	 */
	public static function label( string $operation ): string {
		$labels = array(
			self::PLUGIN_UPDATE     => __( 'Plugin update', 'multisite-fleet-manager' ),
			self::THEME_UPDATE      => __( 'Theme update', 'multisite-fleet-manager' ),
			self::PLUGIN_ACTIVATE   => __( 'Plugin activation', 'multisite-fleet-manager' ),
			self::PLUGIN_DEACTIVATE => __( 'Plugin deactivation', 'multisite-fleet-manager' ),
			self::USER_ADD          => __( 'User added to site', 'multisite-fleet-manager' ),
			self::USER_REMOVE       => __( 'User removed from site', 'multisite-fleet-manager' ),
			self::USER_ROLE         => __( 'Role change', 'multisite-fleet-manager' ),
			self::SNAPSHOT_CAPTURE  => __( 'Report snapshot', 'multisite-fleet-manager' ),
			self::SETTINGS_UPDATE   => __( 'Settings change', 'multisite-fleet-manager' ),
			self::ACCESS_UPDATE     => __( 'Access change', 'multisite-fleet-manager' ),
		);
		return $labels[ $operation ] ?? $operation;
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			self::SUCCESS  => __( 'Succeeded', 'multisite-fleet-manager' ),
			self::FAILED   => __( 'Failed', 'multisite-fleet-manager' ),
			self::REJECTED => __( 'Rejected', 'multisite-fleet-manager' ),
			self::DENIED   => __( 'Denied', 'multisite-fleet-manager' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Status icon.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_icon( string $status ): string {
		$icons = array(
			self::SUCCESS  => 'dashicons-yes-alt',
			self::FAILED   => 'dashicons-dismiss',
			self::REJECTED => 'dashicons-warning',
			self::DENIED   => 'dashicons-lock',
		);
		return $icons[ $status ] ?? 'dashicons-marker';
	}
}
