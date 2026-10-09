<?php
/**
 * Network-wide plugin update.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Handlers;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Contracts\OperationHandler;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;
use FleetManager\Operations\UpdateEnvironment;

defined( 'ABSPATH' ) || exit;

/**
 * Updates one plugin with core's Plugin_Upgrader, the same path as the
 * Network Admin → Updates screen. Plugin files are shared, so the update
 * applies to every site of the network.
 */
final class PluginUpdateHandler implements OperationHandler {

	private UpdateEnvironment $environment;

	/**
	 * Constructor.
	 *
	 * @param UpdateEnvironment $environment Environment gate.
	 */
	public function __construct( UpdateEnvironment $environment ) {
		$this->environment = $environment;
	}

	public function operation(): string {
		return Operation::PLUGIN_UPDATE;
	}

	public function capabilities(): array {
		return array( Capabilities::MANAGE_UPDATES, 'update_plugins' );
	}

	public function validate( array $args ): array {
		$plugin = (string) ( $args['plugin'] ?? '' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$installed = get_plugins();

		if ( '' === $plugin || 0 !== validate_file( $plugin ) || ! isset( $installed[ $plugin ] ) ) {
			throw new OperationException( __( 'This plugin is not installed.', 'multisite-fleet-manager' ), 'invalid_plugin', Operation::REJECTED, 404 );
		}
		if ( defined( 'WPFLEET_BASENAME' ) && WPFLEET_BASENAME === $plugin ) {
			throw new OperationException( __( 'Multisite Fleet Manager cannot update itself. Use Network Admin → Updates.', 'multisite-fleet-manager' ), 'self_update', Operation::REJECTED, 400 );
		}

		$blocker = $this->environment->blocker();
		if ( $blocker ) {
			throw new OperationException( $blocker, 'unsupported', Operation::REJECTED, 409 );
		}

		$updates = get_site_transient( 'update_plugins' );
		$offer   = is_object( $updates ) && isset( $updates->response[ $plugin ] ) ? (object) $updates->response[ $plugin ] : null;
		if ( ! $offer || empty( $offer->new_version ) ) {
			throw new OperationException( __( 'No update is available for this plugin.', 'multisite-fleet-manager' ), 'no_update', Operation::REJECTED, 409 );
		}
		if ( empty( $offer->package ) ) {
			throw new OperationException( __( 'The update package is not available (the plugin may require a licence). Update it from its vendor.', 'multisite-fleet-manager' ), 'no_package', Operation::REJECTED, 409 );
		}
		if ( ! empty( $offer->requires_php ) && ! is_php_version_compatible( (string) $offer->requires_php ) ) {
			/* translators: %s: PHP version. */
			throw new OperationException( sprintf( __( 'This update requires PHP %s.', 'multisite-fleet-manager' ), (string) $offer->requires_php ), 'incompatible_php', Operation::REJECTED, 409 );
		}
		if ( ! empty( $offer->requires ) && ! is_wp_version_compatible( (string) $offer->requires ) ) {
			/* translators: %s: WordPress version. */
			throw new OperationException( sprintf( __( 'This update requires WordPress %s.', 'multisite-fleet-manager' ), (string) $offer->requires ), 'incompatible_wp', Operation::REJECTED, 409 );
		}

		return array(
			'target_type'  => 'plugin',
			'target'       => $plugin,
			'target_name'  => (string) $installed[ $plugin ]['Name'],
			'site_id'      => 0,
			'from_version' => (string) $installed[ $plugin ]['Version'],
			'to_version'   => (string) $offer->new_version,
		);
	}

	public function execute( array $target ): array {
		$plugin = $target['target'];

		Upgrade::load_upgrader();
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		ob_start();
		// Keep the update data: it is corrected below instead of being thrown away.
		$result = $upgrader->bulk_upgrade( array( $plugin ), array( 'clear_update_cache' => false ) );
		ob_end_clean();

		$error = Upgrade::error( $skin, $result );
		if ( null !== $error ) {
			throw new OperationException( $error, 'upgrade_failed', Operation::FAILED, 500 );
		}

		wp_clean_plugins_cache( false );
		$installed = get_plugins();
		$version   = isset( $installed[ $plugin ] ) ? (string) $installed[ $plugin ]['Version'] : '';

		if ( '' === $version || version_compare( $version, $target['from_version'], '<=' ) ) {
			throw new OperationException( __( 'The update did not complete: the installed version did not change.', 'multisite-fleet-manager' ), 'version_unchanged', Operation::FAILED, 500 );
		}

		Upgrade::mark_updated( 'update_plugins', $plugin, $version );

		return array(
			/* translators: 1: Plugin name, 2: Version. */
			'message'    => sprintf( __( '%1$s was updated to version %2$s on all sites.', 'multisite-fleet-manager' ), $target['target_name'], $version ),
			'to_version' => $version,
			'context'    => array( 'network_active' => is_plugin_active_for_network( $plugin ) ),
		);
	}
}
