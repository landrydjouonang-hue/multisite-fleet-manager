<?php
/**
 * Site inventory REST endpoints.
 *
 * @package FleetManager
 */

namespace FleetManager\Rest;

use FleetManager\Core\Capabilities;
use FleetManager\Health\Category;
use FleetManager\Health\Health;
use FleetManager\Health\HealthCheckRegistry;
use FleetManager\Sites\Site;
use FleetManager\Sites\SiteDiscovery;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to the inventory (namespace multisite-fleet-manager/v1):
 *
 *  GET /sites            → paginated list (X-WP-Total / X-WP-TotalPages headers)
 *  GET /sites/{blog_id}  → one site
 *  GET /summary          → counts per status and aggregate figures
 */
final class SitesController {

	private SiteRepository $sites;

	private SiteDiscovery $discovery;

	private HealthCheckRegistry $checks;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository      $sites     Repository.
	 * @param SiteDiscovery       $discovery Discovery.
	 * @param HealthCheckRegistry $checks    Health checks (for indicator labels and messages).
	 */
	public function __construct( SiteRepository $sites, SiteDiscovery $discovery, HealthCheckRegistry $checks ) {
		$this->sites     = $sites;
		$this->discovery = $discovery;
		$this->checks    = $checks;
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			DiscoveryController::NAMESPACE,
			'/sites',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'can_view_sites' ),
				'args'                => array(
					'status'   => array(
						'type' => 'string',
						'enum' => SiteStatus::all(),
					),
					'health'   => array(
						'type' => 'string',
						'enum' => Health::all(),
					),
					'updates'  => array(
						'type' => 'string',
						'enum' => array_keys( SiteRepository::UPDATE_FILTERS ),
					),
					'theme'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'search'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderby'  => array(
						'type'    => 'string',
						'enum'    => array_keys( SiteRepository::ORDERBY ),
						'default' => 'blog_id',
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
			DiscoveryController::NAMESPACE,
			'/sites/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => array( $this, 'can_view_sites' ),
				'args'                => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			DiscoveryController::NAMESPACE,
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => array( $this, 'can_view_dashboard' ),
			)
		);

		register_rest_route(
			DiscoveryController::NAMESPACE,
			'/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'summary' ),
				'permission_callback' => array( $this, 'can_view_dashboard' ),
			)
		);
	}

	/**
	 * Permission: inventory.
	 *
	 * @return bool
	 */
	public function can_view_sites(): bool {
		return current_user_can( Capabilities::VIEW_SITES );
	}

	/**
	 * Permission: dashboard.
	 *
	 * @return bool
	 */
	public function can_view_dashboard(): bool {
		return current_user_can( Capabilities::VIEW_DASHBOARD );
	}

	/**
	 * GET /sites.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$result   = $this->sites->query(
			array(
				'status'   => (string) $request['status'],
				'health'   => (string) $request['health'],
				'updates'  => (string) $request['updates'],
				'theme'    => (string) $request['theme'],
				'search'   => (string) $request['search'],
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
	 * GET /sites/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$site = $this->sites->find( (int) $request['id'] );
		if ( ! $site ) {
			return new \WP_Error( 'wpfleet_site_not_found', __( 'This site is not in the inventory.', 'multisite-fleet-manager' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( $this->prepare( $site ) );
	}

	/**
	 * GET /health: fleet health summary, per indicator.
	 *
	 * These are operational indicators, not a security assessment.
	 *
	 * @return \WP_REST_Response
	 */
	public function health(): \WP_REST_Response {
		$summary = $this->sites->health_summary();
		$rows    = array();

		foreach ( $this->checks->all() as $id => $check ) {
			$counts      = $summary['indicators'][ $id ] ?? array();
			$rows[ $id ] = array(
				'label'    => $check->label(),
				'category' => Category::of( $id ),
				'good'     => (int) ( $counts['good'] ?? 0 ),
				'warning'  => (int) ( $counts['warning'] ?? 0 ),
				'error'    => (int) ( $counts['error'] ?? 0 ),
				'unknown'  => (int) ( $counts['unknown'] ?? 0 ),
			);
		}

		return new \WP_REST_Response(
			array(
				'health'     => $this->sites->count_by_health(),
				'indicators' => $rows,
				'totals'     => $summary['totals'],
				'categories' => array_map(
					static fn( $id ) => array(
						'label'       => Category::label( $id ),
						'description' => Category::description( $id ),
					),
					array_combine( Category::all(), Category::all() )
				),
				'scope'      => __( 'Operational health indicators only. This is not a security scan.', 'multisite-fleet-manager' ),
				'security_caveat' => Category::security_caveat(),
			)
		);
	}

	/**
	 * GET /summary.
	 *
	 * @return \WP_REST_Response
	 */
	public function summary(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'counts'         => $this->sites->count_by_status(),
				'health'         => $this->sites->count_by_health(),
				'updates'        => $this->sites->update_summary(),
				'totals'         => $this->sites->totals(),
				'last_discovery' => $this->discovery->last(),
			)
		);
	}

	/**
	 * Site representation. The admin e-mail is only exposed to super admins.
	 *
	 * @param Site $site Site.
	 * @return array<string,mixed>
	 */
	public function prepare( Site $site ): array {
		$data                      = $site->to_array();
		$data['health_label']      = Health::label( $site->health );
		$data['health_indicators'] = $this->checks->describe( $site->health_indicators );
		if ( ! is_super_admin() ) {
			unset( $data['admin_email'] );
		}
		return $data;
	}
}
