<?php
/**
 * Whether centralized updates can run safely here.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations;

defined( 'ABSPATH' ) || exit;

/**
 * Updates are offered only where they can run unattended and safely:
 *
 * - File modifications must be allowed (DISALLOW_FILE_MODS, wp_is_file_mod_allowed()).
 * - WordPress must be able to write files directly. With FTP/SSH credentials
 *   the core Updates screen has to prompt for them, so updates are left to it.
 * - The site must not already be in maintenance mode (another update running).
 *
 * Everything else (download, unpacking, maintenance mode, rollback of a
 * failed install) is done by core's own Plugin_Upgrader / Theme_Upgrader.
 */
final class UpdateEnvironment {

	/**
	 * Why updates are unavailable, or null when they are supported.
	 *
	 * @return string|null
	 */
	public function blocker(): ?string {
		if ( ! wp_is_file_mod_allowed( 'wpfleet_updates' ) ) {
			return __( 'File changes are disabled on this installation (DISALLOW_FILE_MODS), so updates cannot be installed from here.', 'multisite-fleet-manager' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( 'direct' !== get_filesystem_method( array(), WP_CONTENT_DIR ) ) {
			return __( 'WordPress cannot write files directly on this server (FTP or SSH credentials are required). Use Network Admin → Updates, which can ask for them.', 'multisite-fleet-manager' );
		}

		if ( wp_is_maintenance_mode() ) {
			return __( 'The network is in maintenance mode, probably because another update is running. Try again in a few minutes.', 'multisite-fleet-manager' );
		}

		return null;
	}

	/**
	 * Whether updates are supported.
	 *
	 * @return bool
	 */
	public function supported(): bool {
		return null === $this->blocker();
	}
}
