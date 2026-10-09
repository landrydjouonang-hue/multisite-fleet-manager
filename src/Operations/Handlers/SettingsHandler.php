<?php
/**
 * Settings and access operations.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Handlers;

use FleetManager\Core\Capabilities;
use FleetManager\Core\Settings;
use FleetManager\Operations\Contracts\OperationHandler;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;

defined( 'ABSPATH' ) || exit;

/**
 * Saves the network settings, or changes which capabilities a user holds.
 *
 * Both change behaviour for everyone, so they run through the same pipeline
 * as updates and membership changes: capability, nonce, validation, audit
 * entry. The log records what changed, never the whole settings blob.
 */
final class SettingsHandler implements OperationHandler {

	private string $operation;

	/**
	 * Constructor.
	 *
	 * @param string $operation Operation::SETTINGS_UPDATE or ACCESS_UPDATE.
	 */
	public function __construct( string $operation ) {
		$this->operation = $operation;
	}

	public function operation(): string {
		return $this->operation;
	}

	public function capabilities(): array {
		return Operation::ACCESS_UPDATE === $this->operation
			? array( Capabilities::MANAGE_ACCESS, 'manage_network_users' )
			: array( Capabilities::MANAGE_SETTINGS, 'manage_network_options' );
	}

	public function validate( array $args ): array {
		if ( Operation::ACCESS_UPDATE === $this->operation ) {
			$user_id = (int) ( $args['user_id'] ?? 0 );
			$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

			// The settings form identifies the person by login or ID.
			if ( ! $user && ! empty( $args['user_login'] ) ) {
				$login = sanitize_text_field( (string) $args['user_login'] );
				$user  = is_numeric( $login ) ? get_userdata( (int) $login ) : get_user_by( 'login', $login );
				if ( ! $user ) {
					$user = get_user_by( 'email', $login );
				}
				$user_id = $user ? (int) $user->ID : 0;
			}

			if ( ! $user ) {
				throw new OperationException( __( 'This user does not exist.', 'multisite-fleet-manager' ), 'invalid_user', Operation::REJECTED, 404 );
			}
			if ( is_super_admin( $user_id ) ) {
				throw new OperationException( __( 'Super admins already hold every Fleet Manager capability.', 'multisite-fleet-manager' ), 'already_super', Operation::REJECTED, 409 );
			}

			$requested = array_values( array_intersect( Capabilities::grantable(), (array) ( $args['caps'] ?? array() ) ) );

			return array(
				'target_type'  => 'user',
				'target'       => $user->user_login,
				'target_name'  => $user->display_name,
				'site_id'      => 0,
				'from_version' => implode( ' ', Capabilities::granted( $user_id ) ),
				'to_version'   => implode( ' ', $requested ),
				'user_id'      => $user_id,
				'caps'         => $requested,
			);
		}

		$submitted = (array) ( $args['settings'] ?? array() );
		if ( ! $submitted ) {
			throw new OperationException( __( 'No settings were submitted.', 'multisite-fleet-manager' ), 'empty_settings', Operation::REJECTED, 400 );
		}

		$before  = Settings::all();
		$after   = Settings::sanitize( $submitted );
		$changed = array();
		foreach ( $after as $key => $value ) {
			if ( ( $before[ $key ] ?? null ) !== $value ) {
				$changed[] = $key;
			}
		}

		return array(
			'target_type'  => 'settings',
			'target'       => 'network',
			'target_name'  => __( 'Network settings', 'multisite-fleet-manager' ),
			'site_id'      => 0,
			'from_version' => '',
			'to_version'   => '',
			'settings'     => $after,
			'changed'      => $changed,
		);
	}

	public function execute( array $target ): array {
		if ( Operation::ACCESS_UPDATE === $this->operation ) {
			$user_id = (int) $target['user_id'];
			$caps    = (array) $target['caps'];

			if ( $caps ) {
				Capabilities::grant( $user_id, $caps );
			} else {
				Capabilities::revoke( $user_id );
			}
			clean_user_cache( $user_id );

			return array(
				'message' => $caps
					/* translators: 1: User, 2: Number of capabilities. */
					? sprintf( __( '%1$s now holds %2$s Fleet Manager capabilities.', 'multisite-fleet-manager' ), $target['target_name'], number_format_i18n( count( Capabilities::granted( $user_id ) ) ) )
					/* translators: %s: User. */
					: sprintf( __( 'Fleet Manager access was removed from %s.', 'multisite-fleet-manager' ), $target['target_name'] ),
				'context' => array(
					'user_id' => $user_id,
					'caps'    => $caps,
				),
			);
		}

		Settings::save( (array) $target['settings'] );

		// Reschedule discovery so a changed recurrence takes effect now.
		\FleetManager\Sites\DiscoveryScheduler::unschedule();
		\FleetManager\Sites\DiscoveryScheduler::schedule();

		$changed = (array) $target['changed'];

		return array(
			'message' => $changed
				/* translators: %s: Number of settings. */
				? sprintf( _n( '%s setting was updated.', '%s settings were updated.', count( $changed ), 'multisite-fleet-manager' ), number_format_i18n( count( $changed ) ) )
				: __( 'Settings saved; nothing changed.', 'multisite-fleet-manager' ),
			'context' => array( 'changed' => $changed ),
		);
	}
}
