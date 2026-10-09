<?php
/**
 * Fleet health summary screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Health\Health;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Network-wide health summary: how the fleet scores overall, how each
 * indicator scores across sites, the shared environment behind those
 * numbers, and the sites that need attention first.
 *
 * These are operational indicators, not a security assessment.
 */
final class HealthPage {

	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$sites   = $this->plugin->sites();
		$summary = $sites->health_summary();

		View::render(
			'admin/health',
			array(
				'health'     => $sites->count_by_health(),
				'summary'    => $summary,
				'indicators' => $this->indicators( $summary['indicators'] ),
				'context'    => $this->plugin->health()->context(),
				'attention'  => $sites->query(
					array(
						'orderby'  => 'health',
						'order'    => 'asc',
						'per_page' => 10,
					)
				)['items'],
				'checks'     => $this->plugin->health_checks(),
				'last'       => $this->plugin->discovery()->last(),
				'can_run'    => current_user_can( Capabilities::RUN_DISCOVERY ),
				'can_sites'  => current_user_can( Capabilities::VIEW_SITES ),
			)
		);
	}

	/**
	 * Indicator rows in registry order, worst first.
	 *
	 * @param array<string,array<string,int>> $counts Counts per indicator and state.
	 * @return array<int,array<string,mixed>>
	 */
	private function indicators( array $counts ): array {
		$groups = array();

		foreach ( $this->plugin->health_checks()->by_category() as $category => $checks ) {
			$rows = array();
			foreach ( $checks as $id => $check ) {
				$row    = $counts[ $id ] ?? array();
				$rows[] = array(
					'id'      => $id,
					'label'   => $check->label(),
					'good'    => (int) ( $row['good'] ?? 0 ),
					'warning' => (int) ( $row['warning'] ?? 0 ),
					'error'   => (int) ( $row['error'] ?? 0 ),
					'unknown' => (int) ( $row['unknown'] ?? 0 ),
				);
			}

			// Worst first inside each group.
			usort( $rows, static fn( $a, $b ) => array( $b['error'], $b['warning'] ) <=> array( $a['error'], $a['warning'] ) );
			$groups[ $category ] = $rows;
		}

		return $groups;
	}

	/**
	 * Console URL filtered to the sites failing one indicator.
	 *
	 * @param string $indicator Indicator ID.
	 * @param string $state     Indicator state.
	 * @return string
	 */
	public static function sites_url( string $indicator, string $state ): string {
		return Admin::url(
			Admin::SLUG_CONSOLE,
			array(
				'indicator' => $indicator,
				'state'     => $state,
			)
		);
	}
}
