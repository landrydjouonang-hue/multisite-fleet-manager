<?php
/**
 * Network reports screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Operation;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The network report: totals, health distribution, updates and trend, with
 * CSV exports and a printable version (which is also how a PDF is made:
 * every browser can print to PDF, so no PDF library is bundled).
 */
final class ReportsPage {

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
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$print    = isset( $_GET['view'] ) && 'print' === sanitize_key( wp_unslash( $_GET['view'] ) );
		$reports  = $this->plugin->reports();
		$snapshot = $this->plugin->snapshots();

		$vars = array(
			'page'       => $this,
			'summary'    => $reports->summary(),
			'updates'    => $reports->pending_updates(),
			'sites'      => $reports->site_rows( $print ? 0 : 10 ),
			'series'     => $snapshot->series( 30 ),
			'change'     => $snapshot->change(),
			'snapshots'  => $this->plugin->snapshot_repository()->latest( 10 ),
			'total_snapshots' => $this->plugin->snapshot_repository()->count(),
			'results'    => Actions::take_results(),
			'can_capture' => $this->plugin->operations()->can( Operation::SNAPSHOT_CAPTURE ),
			'can_sites'  => current_user_can( Capabilities::VIEW_SITES ),
			'print'      => $print,
		);

		View::render( $print ? 'admin/report-print' : 'admin/reports', $vars );
	}

	/**
	 * URL of this screen.
	 *
	 * @param array<string,mixed> $args Extra query arguments.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return Admin::url( Admin::SLUG_REPORTS, $args );
	}

	/**
	 * Percentage of a total, guarding against division by zero.
	 *
	 * @param int $value Value.
	 * @param int $total Total.
	 * @return int
	 */
	public static function percent( int $value, int $total ): int {
		return $total > 0 ? (int) round( $value / $total * 100 ) : 0;
	}

	/**
	 * A sparkline for one series, as inline SVG.
	 *
	 * Decorative: every figure it draws is also in the table beneath it.
	 *
	 * @param array<int,array<string,mixed>> $series Snapshots, oldest first.
	 * @param string                         $key    Column to plot.
	 * @param string                         $colour CSS colour.
	 * @return string
	 */
	public static function sparkline( array $series, string $key, string $colour ): string {
		$values = array_map( static fn( $row ) => (int) ( $row[ $key ] ?? 0 ), $series );
		if ( count( $values ) < 2 ) {
			return '';
		}

		$max    = max( max( $values ), 1 );
		$width  = 240;
		$height = 40;
		$step   = $width / ( count( $values ) - 1 );
		$points = array();

		foreach ( $values as $index => $value ) {
			$x        = round( $index * $step, 1 );
			$y        = round( $height - ( $value / $max * ( $height - 4 ) ) - 2, 1 );
			$points[] = $x . ',' . $y;
		}

		return sprintf(
			'<svg class="wpfr-spark" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" role="presentation" focusable="false" aria-hidden="true"><polyline fill="none" stroke="%3$s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="%4$s" /></svg>',
			$width,
			$height,
			esc_attr( $colour ),
			esc_attr( implode( ' ', $points ) )
		);
	}
}
