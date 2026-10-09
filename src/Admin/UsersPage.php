<?php
/**
 * Network users screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Operation;
use FleetManager\Plugin;
use FleetManager\Users\UserDirectory;

defined( 'ABSPATH' ) || exit;

/**
 * Network-wide user visibility, and management of user/site relationships
 * for authorized network administrators.
 */
final class UsersPage {

	public const PER_PAGE_OPTIONS = array( 20, 50, 100 );

	private Plugin $plugin;

	/**
	 * Sanitised request state.
	 *
	 * @var array<string,mixed>
	 */
	private array $state = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Request state.
	 *
	 * @return array<string,mixed>
	 */
	public function state(): array {
		if ( $this->state ) {
			return $this->state;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$get = static fn( string $key ): string => isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		// phpcs:enable

		$per_page = (int) $get( 'per_page' );
		$orderby  = $get( 'orderby' );

		$this->state = array(
			'user'     => max( 0, (int) $get( 'user' ) ),
			's'        => $get( 's' ),
			'role'     => sanitize_key( $get( 'role' ) ),
			'site_id'  => max( 0, (int) $get( 'site_id' ) ),
			'status'   => in_array( $get( 'status' ), UserDirectory::STATUSES, true ) ? $get( 'status' ) : '',
			'orderby'  => isset( UserDirectory::ORDERBY[ $orderby ] ) ? $orderby : 'login',
			'order'    => 'desc' === strtolower( $get( 'order' ) ) ? 'desc' : 'asc',
			'paged'    => max( 1, (int) $get( 'paged' ) ),
			'per_page' => in_array( $per_page, self::PER_PAGE_OPTIONS, true ) ? $per_page : 20,
		);
		return $this->state;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_USERS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$state  = $this->state();
		$users  = $this->plugin->users();
		$common = array(
			'page'       => $this,
			'state'      => $state,
			'results'    => Actions::take_results(),
			'can_manage' => $this->plugin->operations()->can( Operation::USER_ADD ),
			'can_sites'  => current_user_can( Capabilities::VIEW_SITES ),
			'can_log'    => current_user_can( Capabilities::VIEW_LOG ),
			'skipped'    => $this->plugin->user_index()->skipped_sites(),
			'indexed'    => null !== $this->plugin->discovery()->last(),
		);

		if ( $state['user'] ) {
			$user = $users->find( $state['user'] );
			if ( ! $user ) {
				wp_die( esc_html__( 'This user does not exist.', 'multisite-fleet-manager' ), '', array( 'response' => 404 ) );
			}
			View::render(
				'admin/user-detail',
				$common + array(
					'user'       => $user,
					'site_roles' => $this->roles_by_site( $user ),
					'available'  => $this->available_sites( $user ),
				)
			);
			return;
		}

		$result = $users->query(
			array(
				'search'   => $state['s'],
				'role'     => $state['role'],
				'site_id'  => $state['site_id'],
				'status'   => $state['status'],
				'orderby'  => $state['orderby'],
				'order'    => $state['order'],
				'page'     => $state['paged'],
				'per_page' => $state['per_page'],
			)
		);

		View::render(
			'admin/users',
			$common + array(
				'items'   => $result['items'],
				'total'   => $result['total'],
				'summary' => $users->summary(),
				'roles'   => $this->plugin->user_index()->role_counts(),
				'sites'   => $this->plugin->sites()->query(
					array(
						'per_page' => 200,
						'orderby'  => 'name',
					)
				)['items'],
			)
		);
	}

	/**
	 * Roles available on each site the user belongs to (roles are per site).
	 *
	 * @param array<string,mixed> $user User.
	 * @return array<int,array<string,string>> Site ID => role slug => label.
	 */
	private function roles_by_site( array $user ): array {
		$roles = array();
		foreach ( $user['sites'] as $membership ) {
			$roles[ $membership['site_id'] ] = $this->editable_roles( (int) $membership['site_id'] );
		}
		return $roles;
	}

	/**
	 * Sites of the network the user does not belong to yet, with their roles.
	 *
	 * @param array<string,mixed> $user User.
	 * @return array<int,array{name: string, address: string, roles: array<string,string>}>
	 */
	private function available_sites( array $user ): array {
		if ( ! current_user_can( Capabilities::VIEW_SITES ) ) {
			return array();
		}

		$member = array_column( $user['sites'], 'site_id' );
		$sites  = $this->plugin->sites()->query(
			array(
				'per_page' => 200,
				'orderby'  => 'name',
			)
		)['items'];

		$available = array();
		foreach ( $sites as $site ) {
			if ( in_array( $site->blog_id, $member, true ) || $site->is_spam || $site->is_deleted ) {
				continue;
			}
			$available[ $site->blog_id ] = array(
				'name'    => $site->label(),
				'address' => View::address( $site ),
				'roles'   => $this->editable_roles( $site->blog_id ),
			);
		}
		return $available;
	}

	/**
	 * Editable roles of one site.
	 *
	 * @param int $site_id Blog ID.
	 * @return array<string,string> Slug => label.
	 */
	private function editable_roles( int $site_id ): array {
		switch_to_blog( $site_id );
		try {
			$roles = array_map( static fn( $role ) => translate_user_role( $role['name'] ), get_editable_roles() );
		} finally {
			restore_current_blog();
		}
		return $roles;
	}

	/**
	 * URL with the current state changed by $args (null removes).
	 *
	 * @param array<string,mixed> $args Changes.
	 * @return string
	 */
	public function url( array $args = array() ): string {
		$defaults = array(
			'orderby'  => 'login',
			'order'    => 'asc',
			'paged'    => 1,
			'per_page' => 20,
		);
		$query    = array();
		foreach ( array_merge( $this->state(), array( 'paged' => 1 ), $args ) as $key => $value ) {
			if ( null === $value || '' === $value || 0 === $value || ( isset( $defaults[ $key ] ) && $defaults[ $key ] === $value ) ) {
				continue;
			}
			$query[ $key ] = $value;
		}
		return Admin::url( Admin::SLUG_USERS, $query );
	}

	/**
	 * Sortable header (escaped HTML).
	 *
	 * @param string $key   Sort key.
	 * @param string $label Label.
	 * @return string
	 */
	public function sort_header( string $key, string $label ): string {
		$state   = $this->state();
		$current = $state['orderby'] === $key;
		$next    = $current && 'asc' === $state['order'] ? 'desc' : 'asc';
		$icon    = $current ? ( 'asc' === $state['order'] ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' ) : 'dashicons-sort';
		return sprintf(
			'<th scope="col"%1$s><a href="%2$s">%3$s<span class="dashicons %4$s" aria-hidden="true"></span></a></th>',
			$current ? ' class="is-sorted" aria-sort="' . ( 'asc' === $state['order'] ? 'ascending' : 'descending' ) . '"' : '',
			esc_url(
				$this->url(
					array(
						'orderby' => $key,
						'order'   => $next,
					)
				)
			),
			esc_html( $label ),
			esc_attr( $icon )
		);
	}
}
