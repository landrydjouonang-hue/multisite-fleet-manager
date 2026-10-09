<?php
/**
 * Discovery REST endpoints.
 *
 * @package FleetManager
 */

namespace FleetManager\Rest;

use FleetManager\Core\Capabilities;
use FleetManager\Sites\DiscoveryInProgressException;
use FleetManager\Sites\SiteDiscovery;

defined( 'ABSPATH' ) || exit;

/**
 * Routes (namespace multisite-fleet-manager/v1):
 *
 *  GET  /discovery                → current run and last summary
 *  POST /discovery                → start a run
 *  POST /discovery/{run}/batch    → process the next batch of a run
 */
final class DiscoveryController {

	public const NAMESPACE = 'multisite-fleet-manager/v1';

	private SiteDiscovery $discovery;

	/**
	 * Constructor.
	 *
	 * @param SiteDiscovery $discovery Discovery.
	 */
	public function __construct( SiteDiscovery $discovery ) {
		$this->discovery = $discovery;
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/discovery',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'can_run' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/discovery/(?P<run>[a-f0-9-]{36})/batch',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'batch' ),
				'permission_callback' => array( $this, 'can_run' ),
				'args'                => array(
					'run' => array(
						'type'     => 'string',
						'format'   => 'uuid',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission: view.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::VIEW_DASHBOARD );
	}

	/**
	 * Permission: run discovery.
	 *
	 * @return bool
	 */
	public function can_run(): bool {
		return current_user_can( Capabilities::RUN_DISCOVERY );
	}

	/**
	 * GET /discovery.
	 *
	 * @return \WP_REST_Response
	 */
	public function status(): \WP_REST_Response {
		$run = $this->discovery->current();
		return new \WP_REST_Response(
			array(
				'running' => $run && $this->discovery->is_active( $run ),
				'run'     => $run ? $this->public_run( $run ) : null,
				'last'    => $this->discovery->last(),
			)
		);
	}

	/**
	 * POST /discovery.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start() {
		try {
			$run = $this->discovery->start( SiteDiscovery::SOURCE_MANUAL );
		} catch ( DiscoveryInProgressException $e ) {
			return new \WP_Error( 'wpfleet_discovery_running', $e->getMessage(), array( 'status' => 409 ) );
		}

		return new \WP_REST_Response(
			array(
				'run'        => $this->public_run( $run ),
				'batch_size' => $this->discovery->batch_size(),
			),
			201
		);
	}

	/**
	 * POST /discovery/{run}/batch.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function batch( \WP_REST_Request $request ) {
		try {
			$result = $this->discovery->step( (string) $request['run'] );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wpfleet_discovery_unknown_run', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response(
			array(
				'run'     => $this->public_run( $result['run'] ),
				'done'    => $result['done'],
				'summary' => $result['summary'],
			)
		);
	}

	/**
	 * Run state exposed to clients.
	 *
	 * @param array<string,mixed> $run Run state.
	 * @return array<string,mixed>
	 */
	private function public_run( array $run ): array {
		return array(
			'id'         => (string) $run['id'],
			'source'     => (string) $run['source'],
			'started_at' => (string) $run['started_at'],
			'processed'  => (int) $run['processed'],
			'failed'     => (int) $run['failed'],
			'total'      => (int) $run['total'],
		);
	}
}
