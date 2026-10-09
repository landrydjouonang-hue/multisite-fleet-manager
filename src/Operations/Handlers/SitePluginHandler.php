<?php
/**
 * Per-site plugin activation and deactivation.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Handlers;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Contracts\OperationHandler;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;
use FleetManager\Sites\SiteInspector;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Activates or deactivates a plugin on one site of the current network,
 * using core's activate_plugin() / deactivate_plugins() inside that site.
 * Network-activated and network-only plugins are left to the Network Admin
 * Plugins screen, and Fleet Manager never deactivates itself.
 */
final class SitePluginHandler implements OperationHandler {

	private bool $activate;

	private SiteInspector $inspector;

	private SiteRepository $sites;

	/**
	 * Constructor.
	 *
	 * @param bool           $activate  True to activate, false to deactivate.
	 * @param SiteInspector  $inspector Inspector (refreshes the site's record afterwards).
	 * @param SiteRepository $sites     Repository.
	 */
	public function __construct( bool $activate, SiteInspector $inspector, SiteRepository $sites ) {
		$this->activate  = $activate;
		$this->inspector = $inspector;
		$this->sites     = $sites;
	}

	public function operation(): string {
		return $this->activate ? Operation::PLUGIN_ACTIVATE : Operation::PLUGIN_DEACTIVATE;
	}

	public function capabilities(): array {
		return array( Capabilities::MANAGE_PLUGINS, 'activate_plugins' );
	}

	public function validate( array $args ): array {
		$site_id = (int) ( $args['site_id'] ?? 0 );
		$plugin  = (string) ( $args['plugin'] ?? '' );

		// Target site: must exist and belong to the current network.
		$site = $site_id > 0 ? get_site( $site_id ) : null;
		if ( ! $site instanceof \WP_Site || (int) $site->network_id !== (int) get_current_network_id() ) {
			throw new OperationException( __( 'This site does not exist on this network.', 'multisite-fleet-manager' ), 'invalid_site', Operation::REJECTED, 404 );
		}
		if ( $this->activate && ( (int) $site->deleted || (int) $site->spam ) ) {
			throw new OperationException( __( 'Plugins cannot be activated on a deactivated or spam site.', 'multisite-fleet-manager' ), 'site_inactive', Operation::REJECTED, 409 );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$installed = get_plugins();

		$active  = '' !== $plugin && in_array( $plugin, (array) get_blog_option( $site_id, 'active_plugins', array() ), true );
		$missing = ! isset( $installed[ $plugin ] );

		// A plugin whose files are gone may still be deactivated (clears the "Plugin files" error).
		if ( '' === $plugin || 0 !== validate_file( $plugin ) || ( $missing && ( $this->activate || ! $active ) ) ) {
			throw new OperationException( __( 'This plugin is not installed.', 'multisite-fleet-manager' ), 'invalid_plugin', Operation::REJECTED, 404 );
		}
		if ( defined( 'WPFLEET_BASENAME' ) && WPFLEET_BASENAME === $plugin ) {
			throw new OperationException( __( 'Multisite Fleet Manager cannot change its own activation.', 'multisite-fleet-manager' ), 'self_target', Operation::REJECTED, 400 );
		}
		if ( is_plugin_active_for_network( $plugin ) ) {
			throw new OperationException( __( 'This plugin is network activated, so it runs on every site. Manage it from Network Admin → Plugins.', 'multisite-fleet-manager' ), 'network_active', Operation::REJECTED, 409 );
		}
		if ( $this->activate && is_network_only_plugin( $plugin ) ) {
			throw new OperationException( __( 'This plugin can only be activated for the whole network.', 'multisite-fleet-manager' ), 'network_only', Operation::REJECTED, 409 );
		}

		if ( $this->activate && $active ) {
			throw new OperationException( __( 'This plugin is already active on this site.', 'multisite-fleet-manager' ), 'already_active', Operation::REJECTED, 409 );
		}
		if ( ! $this->activate && ! $active ) {
			throw new OperationException( __( 'This plugin is not active on this site.', 'multisite-fleet-manager' ), 'not_active', Operation::REJECTED, 409 );
		}

		$version = $missing ? '' : (string) $installed[ $plugin ]['Version'];

		return array(
			'target_type'  => 'plugin',
			'target'       => $plugin,
			'target_name'  => $missing ? $plugin : (string) $installed[ $plugin ]['Name'],
			'site_id'      => $site_id,
			'from_version' => $version,
			'to_version'   => $version,
		);
	}

	public function execute( array $target ): array {
		$plugin  = $target['target'];
		$site_id = (int) $target['site_id'];

		switch_to_blog( $site_id );
		try {
			ob_start();
			if ( $this->activate ) {
				// Runs the plugin's activation hook in the site's context, as core does.
				$result = activate_plugin( $plugin, '', false, false );
			} else {
				deactivate_plugins( $plugin, false, false );
				$result = null;
			}
			$output = trim( (string) ob_get_clean() );
			$active = in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
		} finally {
			restore_current_blog();
		}

		if ( is_wp_error( $result ) ) {
			throw new OperationException( $result->get_error_message(), 'activation_failed', Operation::FAILED, 500 );
		}
		if ( $active !== $this->activate ) {
			throw new OperationException( __( 'WordPress did not apply the change.', 'multisite-fleet-manager' ), 'not_applied', Operation::FAILED, 500 );
		}

		// Refresh the site's inventory record (plugin list, health) right away.
		$snapshot = $this->inspector->inspect( $site_id );
		if ( $snapshot ) {
			$this->sites->save( $snapshot );
		}

		$site_name = (string) get_blog_option( $site_id, 'blogname', '' );
		$message   = $this->activate
			/* translators: 1: Plugin name, 2: Site name. */
			? sprintf( __( '%1$s was activated on %2$s.', 'multisite-fleet-manager' ), $target['target_name'], $site_name )
			/* translators: 1: Plugin name, 2: Site name. */
			: sprintf( __( '%1$s was deactivated on %2$s.', 'multisite-fleet-manager' ), $target['target_name'], $site_name );

		return array(
			'message' => $message,
			'context' => array_filter(
				array(
					'site_status'       => SiteStatus::from_flags( (bool) get_site( $site_id )->deleted, (bool) get_site( $site_id )->spam, (bool) get_site( $site_id )->archived ),
					'unexpected_output' => '' !== $output ? substr( wp_strip_all_tags( $output ), 0, 500 ) : null,
				)
			),
		);
	}
}
