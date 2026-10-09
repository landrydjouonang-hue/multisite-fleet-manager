<?php
/**
 * Reporting REST endpoints.
 *
 * @package FleetManager
 */

namespace FleetManager\Rest;

use FleetManager\Core\Capabilities;
use FleetManager\Reporting\ReportBuilder;
use FleetManager\Reporting\SnapshotRepository;
use FleetManager\Reporting\SnapshotService;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only reporting (namespace multisite-fleet-manager/v1):
 *
 *  GET /reports               the current report: totals, health, updates, environment
 *  GET /reports/updates       pending plugin and theme updates with affected sites
 *  GET /reports/snapshots     stored snapshots, newest first, plus the latest change
 *  GET /reports/snapshots/{id} one stored snapshot in full
 *
 * Capturing a snapshot is a state change and goes through POST /operations
 * (`snapshot_capture`), not through these routes.
 */
final class ReportsController {

	private ReportBuilder $reports;

	private SnapshotRepository $snapshots;

	private SnapshotService $service;

	/**
	 * Constructor.
	 *
	 * @param ReportBuilder      $reports   Report builder.
	 * @param SnapshotRepository $snapshots Snapshot storage.
	 * @param SnapshotService    $service   Snapshot service.
	 */
	public function __construct( ReportBuilder $reports, SnapshotRepository $snapshots, SnapshotService $service ) {
		$this->reports   = $reports;
		$this->snapshots = $snapshots;
		$this->service   = $service;
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
			'/reports',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => new \WP_REST_Response( $this->reports->summary() ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$ns,
			'/reports/updates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => new \WP_REST_Response( $this->reports->pending_updates() ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$ns,
			'/reports/snapshots',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'snapshots' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 365,
						'default' => 30,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reports/snapshots/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'snapshot' ),
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
	 * Permission: view reports.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::VIEW_REPORTS );
	}

	/**
	 * GET /reports/snapshots.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function snapshots( \WP_REST_Request $request ): \WP_REST_Response {
		$response = new \WP_REST_Response(
			array(
				'snapshots' => $this->snapshots->latest( (int) $request['limit'] ),
				'change'    => $this->service->change(),
			)
		);
		$response->header( 'X-WP-Total', (string) $this->snapshots->count() );
		return $response;
	}

	/**
	 * GET /reports/snapshots/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function snapshot( \WP_REST_Request $request ) {
		$snapshot = $this->snapshots->find( (int) $request['id'] );
		if ( ! $snapshot ) {
			return new \WP_Error( 'wpfleet_snapshot_not_found', __( 'This snapshot does not exist.', 'multisite-fleet-manager' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( $snapshot );
	}
}
