<?php
/**
 * Network-wide theme update.
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
 * Updates one theme with core's Theme_Upgrader. Theme files are shared, so
 * the update applies to every site using the theme (or a child of it).
 */
final class ThemeUpdateHandler implements OperationHandler {

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
		return Operation::THEME_UPDATE;
	}

	public function capabilities(): array {
		return array( Capabilities::MANAGE_UPDATES, 'update_themes' );
	}

	public function validate( array $args ): array {
		$stylesheet = (string) ( $args['theme'] ?? '' );
		$theme      = '' !== $stylesheet && 0 === validate_file( $stylesheet ) ? wp_get_theme( $stylesheet ) : null;

		if ( ! $theme || ! $theme->exists() ) {
			throw new OperationException( __( 'This theme is not installed.', 'multisite-fleet-manager' ), 'invalid_theme', Operation::REJECTED, 404 );
		}

		$blocker = $this->environment->blocker();
		if ( $blocker ) {
			throw new OperationException( $blocker, 'unsupported', Operation::REJECTED, 409 );
		}

		$updates = get_site_transient( 'update_themes' );
		$offer   = is_object( $updates ) && isset( $updates->response[ $stylesheet ] ) ? (array) $updates->response[ $stylesheet ] : null;
		if ( ! $offer || empty( $offer['new_version'] ) ) {
			throw new OperationException( __( 'No update is available for this theme.', 'multisite-fleet-manager' ), 'no_update', Operation::REJECTED, 409 );
		}
		if ( empty( $offer['package'] ) ) {
			throw new OperationException( __( 'The update package is not available. Update the theme from its vendor.', 'multisite-fleet-manager' ), 'no_package', Operation::REJECTED, 409 );
		}
		if ( ! empty( $offer['requires_php'] ) && ! is_php_version_compatible( (string) $offer['requires_php'] ) ) {
			/* translators: %s: PHP version. */
			throw new OperationException( sprintf( __( 'This update requires PHP %s.', 'multisite-fleet-manager' ), (string) $offer['requires_php'] ), 'incompatible_php', Operation::REJECTED, 409 );
		}
		if ( ! empty( $offer['requires'] ) && ! is_wp_version_compatible( (string) $offer['requires'] ) ) {
			/* translators: %s: WordPress version. */
			throw new OperationException( sprintf( __( 'This update requires WordPress %s.', 'multisite-fleet-manager' ), (string) $offer['requires'] ), 'incompatible_wp', Operation::REJECTED, 409 );
		}

		return array(
			'target_type'  => 'theme',
			'target'       => $stylesheet,
			'target_name'  => (string) $theme->get( 'Name' ),
			'site_id'      => 0,
			'from_version' => (string) $theme->get( 'Version' ),
			'to_version'   => (string) $offer['new_version'],
		);
	}

	public function execute( array $target ): array {
		$stylesheet = $target['target'];

		Upgrade::load_upgrader();
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );

		ob_start();
		$result = $upgrader->bulk_upgrade( array( $stylesheet ), array( 'clear_update_cache' => false ) );
		ob_end_clean();

		$error = Upgrade::error( $skin, $result );
		if ( null !== $error ) {
			throw new OperationException( $error, 'upgrade_failed', Operation::FAILED, 500 );
		}

		wp_clean_themes_cache( false );
		$theme   = wp_get_theme( $stylesheet );
		$version = $theme->exists() ? (string) $theme->get( 'Version' ) : '';

		if ( '' === $version || version_compare( $version, $target['from_version'], '<=' ) ) {
			throw new OperationException( __( 'The update did not complete: the installed version did not change.', 'multisite-fleet-manager' ), 'version_unchanged', Operation::FAILED, 500 );
		}

		Upgrade::mark_updated( 'update_themes', $stylesheet, $version );

		return array(
			/* translators: 1: Theme name, 2: Version. */
			'message'    => sprintf( __( '%1$s was updated to version %2$s on all sites.', 'multisite-fleet-manager' ), $target['target_name'], $version ),
			'to_version' => $version,
		);
	}
}
