<?php
/**
 * Plugins, themes and operations REST endpoints.
 *
 * @package FleetManager
 */

namespace FleetManager\Rest;

use FleetManager\Core\Capabilities;
use FleetManager\Extensions\ExtensionInventory;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;
use FleetManager\Operations\OperationLog;
use FleetManager\Operations\OperationService;

defined( 'ABSPATH' ) || exit;

/**
 * Routes (namespace multisite-fleet-manager/v1):
 *
 *  GET  /plugins                 installed plugins, versions, activations, updates
 *  GET  /themes                  installed themes, usage, updates
 *  GET  /sites/{id}/extensions   one site's plugins (active / network / inactive) and theme
 *  POST /operations              run an operation (see OperationService)
 *  GET  /operations              operation log
 *
 * POST /operations requires, besides the REST cookie nonce, an operation
 * nonce (`nonce` = wp_create_nonce( 'wpfleet_op_{operation}' )). Its
 * permission callback only requires a logged-in user so that
 * OperationService can check and log denied attempts.
 */
final class ExtensionsController {

	private ExtensionInventory $inventory;

	private OperationService $operations;

	private OperationLog $log;

	/**
	 * Constructor.
	 *
	 * @param ExtensionInventory $inventory  Inventory.
	 * @param OperationService   $operations Operations.
	 * @param OperationLog       $log        Log.
	 */
	public function __construct( ExtensionInventory $inventory, OperationService $operations, OperationLog $log ) {
		$this->inventory  = $inventory;
		$this->operations = $operations;
		$this->log        = $log;
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
			'/plugins',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => new \WP_REST_Response( array_values( $this->inventory->plugins() ) ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$ns,
			'/themes',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => new \WP_REST_Response( array_values( $this->inventory->themes() ) ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$ns,
			'/sites/(?P<id>\d+)/extensions',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'site' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/operations',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run' ),
					'permission_callback' => array( $this, 'can_run' ),
					'args'                => array(
						'operation' => array(
							'type'     => 'string',
							'enum'     => Operation::all(),
							'required' => true,
						),
						'plugin'    => array( 'type' => 'string' ),
						'theme'     => array( 'type' => 'string' ),
						'site_id'   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'user_id'   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'role'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'nonce'     => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'history' ),
					'permission_callback' => fn() => current_user_can( Capabilities::VIEW_LOG ),
					'args'                => array(
						'operation' => array(
							'type' => 'string',
							'enum' => Operation::all(),
						),
						'status'    => array(
							'type' => 'string',
							'enum' => Operation::statuses(),
						),
						'site_id'   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission: browse plugins and themes.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::VIEW_EXTENSIONS );
	}

	/**
	 * Permission: run operations.
	 *
	 * Holding the capabilities of at least one operation is the minimum to
	 * reach the pipeline; the pipeline then enforces the capabilities of the
	 * operation that was actually asked for.
	 *
	 * @return bool
	 */
	public function can_run(): bool {
		return $this->operations->can_any();
	}

	/**
	 * GET /sites/{id}/extensions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function site( \WP_REST_Request $request ) {
		$site = get_site( (int) $request['id'] );
		if ( ! $site || (int) $site->network_id !== (int) get_current_network_id() ) {
			return new \WP_Error( 'wpfleet_invalid_site', __( 'This site does not exist on this network.', 'multisite-fleet-manager' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( $this->inventory->site( (int) $site->blog_id ) );
	}

	/**
	 * POST /operations.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run( \WP_REST_Request $request ) {
		$args = array_filter(
			array(
				'plugin'  => $request['plugin'],
				'theme'   => $request['theme'],
				'site_id' => $request['site_id'],
				'user_id' => $request['user_id'],
				'role'    => $request['role'],
			),
			static fn( $v ) => null !== $v
		);

		try {
			$result = $this->operations->run( (string) $request['operation'], $args, (string) $request['nonce'], 'rest' );
		} catch ( OperationException $e ) {
			return $e->to_wp_error();
		}

		return new \WP_REST_Response( $result );
	}

	/**
	 * GET /operations.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function history( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$result   = $this->log->query(
			array(
				'operation' => (string) $request['operation'],
				'status'    => (string) $request['status'],
				'site_id'   => (int) $request['site_id'],
				'page'      => (int) $request['page'],
				'per_page'  => $per_page,
			)
		);

		$response = new \WP_REST_Response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / max( 1, $per_page ) ) );
		return $response;
	}
}
