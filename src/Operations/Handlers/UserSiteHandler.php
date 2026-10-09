<?php
/**
 * User/site membership operations.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Handlers;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Contracts\OperationHandler;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;
use FleetManager\Users\UserIndex;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a user to a site, removes them, or changes their role there, using
 * core's own functions.
 *
 * Deliberately out of scope: creating, deleting or editing user accounts,
 * changing passwords or e-mail addresses, and granting or revoking super
 * admin. Those stay on core's Network Admin → Users screens.
 *
 * A site is never left without an administrator: removing or demoting its
 * last administrator is refused.
 */
final class UserSiteHandler implements OperationHandler {

	private string $operation;

	private UserIndex $index;

	/**
	 * Constructor.
	 *
	 * @param string    $operation Operation::USER_ADD|USER_REMOVE|USER_ROLE.
	 * @param UserIndex $index     Membership index.
	 */
	public function __construct( string $operation, UserIndex $index ) {
		$this->operation = $operation;
		$this->index     = $index;
	}

	public function operation(): string {
		return $this->operation;
	}

	public function capabilities(): array {
		// manage_network_users is a super-admin-only capability on Multisite.
		return array( Capabilities::MANAGE_USERS, 'manage_network_users' );
	}

	public function validate( array $args ): array {
		$user_id = (int) ( $args['user_id'] ?? 0 );
		$site_id = (int) ( $args['site_id'] ?? 0 );
		$role    = sanitize_key( (string) ( $args['role'] ?? '' ) );

		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			throw new OperationException( __( 'This user does not exist.', 'multisite-fleet-manager' ), 'invalid_user', Operation::REJECTED, 404 );
		}

		$site = $site_id > 0 ? get_site( $site_id ) : null;
		if ( ! $site instanceof \WP_Site || (int) $site->network_id !== (int) get_current_network_id() ) {
			throw new OperationException( __( 'This site does not exist on this network.', 'multisite-fleet-manager' ), 'invalid_site', Operation::REJECTED, 404 );
		}

		$adding = Operation::USER_ADD === $this->operation;
		if ( $adding && ( (int) $site->deleted || (int) $site->spam ) ) {
			throw new OperationException( __( 'Users cannot be added to a deactivated or spam site.', 'multisite-fleet-manager' ), 'site_inactive', Operation::REJECTED, 409 );
		}
		if ( $adding && ( (int) $user->spam || (int) $user->deleted ) ) {
			throw new OperationException( __( 'This user is marked as spam or deleted on the network.', 'multisite-fleet-manager' ), 'user_flagged', Operation::REJECTED, 409 );
		}

		$member = is_user_member_of_blog( $user_id, $site_id );
		if ( $adding && $member ) {
			throw new OperationException( __( 'This user already belongs to that site.', 'multisite-fleet-manager' ), 'already_member', Operation::REJECTED, 409 );
		}
		if ( ! $adding && ! $member ) {
			throw new OperationException( __( 'This user does not belong to that site.', 'multisite-fleet-manager' ), 'not_member', Operation::REJECTED, 409 );
		}

		$current = $member ? (array) ( $this->index->for_site( $site_id )[ $user_id ] ?? array() ) : array();

		if ( Operation::USER_REMOVE !== $this->operation ) {
			$role = $this->validate_role( $role, $site_id );
			if ( Operation::USER_ROLE === $this->operation && array( $role ) === $current ) {
				throw new OperationException( __( 'The user already has that role on this site.', 'multisite-fleet-manager' ), 'role_unchanged', Operation::REJECTED, 409 );
			}
		}

		// Never leave a site without an administrator.
		if ( ! $adding && in_array( 'administrator', $current, true ) && 'administrator' !== $role && $this->admin_count( $site_id ) <= 1 ) {
			throw new OperationException( __( 'This is the only administrator of that site. Give another user the administrator role first.', 'multisite-fleet-manager' ), 'last_administrator', Operation::REJECTED, 409 );
		}

		return array(
			'target_type'  => 'user',
			'target'       => $user->user_login,
			'target_name'  => $user->display_name,
			'site_id'      => $site_id,
			'from_version' => implode( ',', $current ),
			'to_version'   => Operation::USER_REMOVE === $this->operation ? '' : $role,
			'user_id'      => $user_id,
			'role'         => $role,
		);
	}

	public function execute( array $target ): array {
		$user_id = (int) $target['user_id'];
		$site_id = (int) $target['site_id'];
		$role    = (string) $target['role'];
		$site    = get_blog_option( $site_id, 'blogname', '' );
		$name    = $target['target_name'];

		switch ( $this->operation ) {
			case Operation::USER_ADD:
				$result = add_user_to_blog( $site_id, $user_id, $role );
				if ( is_wp_error( $result ) ) {
					throw new OperationException( $result->get_error_message(), 'add_failed', Operation::FAILED, 500 );
				}
				$message = sprintf(
					/* translators: 1: User, 2: Role, 3: Site. */
					__( '%1$s was added to %3$s as %2$s.', 'multisite-fleet-manager' ),
					$name,
					\FleetManager\Users\UserDirectory::role_label( $role ),
					$site
				);
				break;

			case Operation::USER_REMOVE:
				remove_user_from_blog( $user_id, $site_id );
				if ( is_user_member_of_blog( $user_id, $site_id ) ) {
					throw new OperationException( __( 'WordPress did not remove the user from the site.', 'multisite-fleet-manager' ), 'remove_failed', Operation::FAILED, 500 );
				}
				$message = sprintf(
					/* translators: 1: User, 2: Site. */
					__( '%1$s was removed from %2$s. Their content on that site was left untouched.', 'multisite-fleet-manager' ),
					$name,
					$site
				);
				break;

			default:
				// Switching keeps core's role hooks (and the index) in the right site context.
				switch_to_blog( $site_id );
				try {
					$user = new \WP_User( $user_id );
					$user->set_role( $role );
					$applied = in_array( $role, (array) $user->roles, true );
				} finally {
					restore_current_blog();
				}
				if ( ! $applied ) {
					throw new OperationException( __( 'WordPress did not apply the new role.', 'multisite-fleet-manager' ), 'role_failed', Operation::FAILED, 500 );
				}
				$message = sprintf(
					/* translators: 1: User, 2: Role, 3: Site. */
					__( '%1$s is now %2$s on %3$s.', 'multisite-fleet-manager' ),
					$name,
					\FleetManager\Users\UserDirectory::role_label( $role ),
					$site
				);
		}

		return array(
			'message' => $message,
			'context' => array(
				'user_id'  => $user_id,
				'role'     => $role,
				'previous' => $target['from_version'],
			),
		);
	}

	/**
	 * Checks a role against the target site's own roles.
	 *
	 * @param string $role    Role slug.
	 * @param int    $site_id Blog ID.
	 * @return string
	 * @throws OperationException When the role does not exist there.
	 */
	private function validate_role( string $role, int $site_id ): string {
		switch_to_blog( $site_id );
		try {
			$editable = array_keys( get_editable_roles() );
		} finally {
			restore_current_blog();
		}

		if ( '' === $role || ! in_array( $role, $editable, true ) ) {
			throw new OperationException( __( 'That role does not exist on this site.', 'multisite-fleet-manager' ), 'invalid_role', Operation::REJECTED, 400 );
		}
		return $role;
	}

	/**
	 * Number of administrators on a site, from the membership index.
	 *
	 * @param int $site_id Blog ID.
	 * @return int
	 */
	private function admin_count( int $site_id ): int {
		$members = $this->index->for_site( $site_id );
		if ( ! $members ) {
			// The index is empty (discovery has not run yet): ask core directly.
			return count(
				get_users(
					array(
						'blog_id' => $site_id,
						'role'    => 'administrator',
						'fields'  => 'ID',
						'number'  => 5,
					)
				)
			);
		}

		$count = 0;
		foreach ( $members as $roles ) {
			$count += in_array( 'administrator', $roles, true ) ? 1 : 0;
		}
		return $count;
	}
}
