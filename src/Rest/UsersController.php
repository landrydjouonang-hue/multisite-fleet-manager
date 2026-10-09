<?php
/**
 * Users REST endpoints.
 *
 * @package FleetManager
 */

namespace FleetManager\Rest;

use FleetManager\Core\Capabilities;
use FleetManager\Users\UserDirectory;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only user directory (namespace multisite-fleet-manager/v1):
 *
 *  GET /users?search=&role=&site_id=&status=&orderby=&order=&page=&per_page=
 *  GET /users/{id}
 *
 * Responses carry account facts and memberships only. Passwords, password
 * hashes, activation keys, session tokens and application passwords are
 * never included. Changes go through POST /operations.
 */
final class UsersController {

	private UserDirectory $users;

	/**
	 * Constructor.
	 *
	 * @param UserDirectory $users Directory.
	 */
	public function __construct( UserDirectory $users ) {
		$this->users = $users;
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$ns = DiscoveryController::NAMESPACE;

		register_rest_route(
			$ns,
			'/users',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'site_id'  => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'status'   => array(
						'type' => 'string',
						'enum' => UserDirectory::STATUSES,
					),
					'orderby'  => array(
						'type'    => 'string',
						'enum'    => array_keys( UserDirectory::ORDERBY ),
						'default' => 'login',
					),
					'order'    => array(
						'type'    => 'string',
						'enum'    => array( 'asc', 'desc' ),
						'default' => 'asc',
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/users/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * Permission: browse network users.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::VIEW_USERS );
	}

	/**
	 * GET /users.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$result   = $this->users->query(
			array(
				'search'   => (string) $request['search'],
				'role'     => (string) $request['role'],
				'site_id'  => (int) $request['site_id'],
				'status'   => (string) $request['status'],
				'orderby'  => (string) $request['orderby'],
				'order'    => (string) $request['order'],
				'page'     => (int) $request['page'],
				'per_page' => $per_page,
			)
		);

		$response = new \WP_REST_Response( array_map( array( $this, 'prepare' ), $result['items'] ) );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / max( 1, $per_page ) ) );
		return $response;
	}

	/**
	 * GET /users/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$user = $this->users->find( (int) $request['id'] );
		if ( ! $user ) {
			return new \WP_Error( 'wpfleet_user_not_found', __( 'This user does not exist.', 'multisite-fleet-manager' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( $this->prepare( $user ) );
	}

	/**
	 * Fields exposed for a user. E-mail addresses are for super admins only.
	 *
	 * @param array<string,mixed> $user User.
	 * @return array<string,mixed>
	 */
	public function prepare( array $user ): array {
		if ( ! is_super_admin() ) {
			unset( $user['email'] );
		}
		return $user;
	}
}
