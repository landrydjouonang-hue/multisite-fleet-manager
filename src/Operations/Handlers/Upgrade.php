<?php
/**
 * Shared upgrader helpers.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Handlers;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the parts of core's upgrader API used by the update handlers.
 */
final class Upgrade {

	/**
	 * Loads core's upgrader classes and their dependencies.
	 *
	 * @return void
	 */
	public static function load_upgrader(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
	}

	/**
	 * Extracts an error from an upgrader run, mirroring core's AJAX handlers.
	 *
	 * @param \WP_Ajax_Upgrader_Skin $skin   Skin.
	 * @param mixed                  $result bulk_upgrade() result.
	 * @return string|null Error message, or null on success.
	 */
	public static function error( \WP_Ajax_Upgrader_Skin $skin, $result ): ?string {
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result->get_error_message();
		}
		if ( $skin->get_errors()->has_errors() ) {
			return implode( ' ', array_map( 'wp_strip_all_tags', $skin->get_error_messages() ) );
		}
		if ( false === $result ) {
			global $wp_filesystem;
			if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				return $wp_filesystem->errors->get_error_message();
			}
			return __( 'Unable to connect to the filesystem.', 'multisite-fleet-manager' );
		}
		return null;
	}

	/**
	 * Moves an item from "update available" to "up to date" in core's update
	 * transient, so counts stay correct without a new remote check. Core's next
	 * scheduled check replaces this data anyway.
	 *
	 * @param string $transient 'update_plugins' or 'update_themes'.
	 * @param string $key       Plugin basename or theme stylesheet.
	 * @param string $version   Installed version.
	 * @return void
	 */
	public static function mark_updated( string $transient, string $key, string $version ): void {
		$data = get_site_transient( $transient );
		if ( ! is_object( $data ) ) {
			return;
		}
		$offer = $data->response[ $key ] ?? null;
		unset( $data->response[ $key ] );
		if ( isset( $data->checked ) && is_array( $data->checked ) ) {
			$data->checked[ $key ] = $version;
		}
		if ( null !== $offer && isset( $data->no_update ) && is_array( $data->no_update ) ) {
			$data->no_update[ $key ] = $offer;
		}
		set_site_transient( $transient, $data );
	}
}
