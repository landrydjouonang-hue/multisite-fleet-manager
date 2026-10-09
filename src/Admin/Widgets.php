<?php
/**
 * Dashboard widgets.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Health\Health;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Two widgets, both read-only and both capability-gated:
 *
 * - Network Admin dashboard: the fleet at a glance, for whoever runs the
 *   network.
 * - Main site dashboard: the same headline for super admins who live in the
 *   site admin rather than the network admin.
 *
 * Nothing is rendered for users without the capability, and the widgets
 * read the stored inventory, so they cost a few aggregate queries.
 */
final class Widgets {

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
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_network_dashboard_setup', array( $this, 'add_network_widget' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_site_widget' ) );
	}

	/**
	 * Adds the Network Admin widget.
	 *
	 * @return void
	 */
	public function add_network_widget(): void {
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}
		wp_add_dashboard_widget( 'wpfleet_network_status', __( 'Fleet Manager', 'multisite-fleet-manager' ), array( $this, 'render' ) );
	}

	/**
	 * Adds the widget to the main site's dashboard.
	 *
	 * @return void
	 */
	public function add_site_widget(): void {
		if ( ! is_main_site() || ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}
		wp_add_dashboard_widget( 'wpfleet_network_status', __( 'Fleet Manager', 'multisite-fleet-manager' ), array( $this, 'render' ) );
	}

	/**
	 * Renders the widget.
	 *
	 * @return void
	 */
	public function render(): void {
		$sites     = $this->plugin->sites();
		$health    = $sites->count_by_health();
		$updates   = $sites->update_summary();
		$discovery = $this->plugin->discovery()->last();
		$total     = (int) $health['total'];

		if ( ! $discovery ) {
			printf(
				'<p>%s</p><p><a class="button" href="%s">%s</a></p>',
				esc_html__( 'No inventory yet. Run site discovery to see the network at a glance.', 'multisite-fleet-manager' ),
				esc_url( Admin::url() ),
				esc_html__( 'Open Fleet Manager', 'multisite-fleet-manager' )
			);
			return;
		}

		$rows = array(
			array( __( 'Sites', 'multisite-fleet-manager' ), $total, '', Admin::url( Admin::SLUG_CONSOLE ) ),
			array( Health::label( Health::HEALTHY ), (int) $health[ Health::HEALTHY ], 'healthy', Admin::url( Admin::SLUG_CONSOLE, array( 'health' => Health::HEALTHY ) ) ),
			array( Health::label( Health::ATTENTION ), (int) $health[ Health::ATTENTION ], 'attention', Admin::url( Admin::SLUG_CONSOLE, array( 'health' => Health::ATTENTION ) ) ),
			array( Health::label( Health::ERROR ), (int) $health[ Health::ERROR ], 'error', Admin::url( Admin::SLUG_CONSOLE, array( 'health' => Health::ERROR ) ) ),
		);

		echo '<ul class="wpfleet-widget">';
		foreach ( $rows as $row ) {
			printf(
				'<li class="wpfleet-widget__row%1$s"><a href="%2$s"><span class="wpfleet-widget__label">%3$s</span><span class="wpfleet-widget__value">%4$s</span></a></li>',
				$row[2] ? ' wpfleet-widget__row--' . esc_attr( $row[2] ) : '',
				esc_url( $row[3] ),
				esc_html( $row[0] ),
				esc_html( View::number( (int) $row[1] ) )
			);
		}
		echo '</ul>';

		printf(
			'<p class="wpfleet-widget__meta">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Plugin updates, 2: Theme updates, 3: Relative time. */
					__( '%1$s plugin and %2$s theme updates pending. Inventory synced %3$s.', 'multisite-fleet-manager' ),
					View::number( (int) $updates['plugin_updates'] ),
					View::number( (int) $updates['theme_updates'] ),
					View::ago( $discovery['finished_at'] )
				)
			)
		);

		$links = array();
		if ( current_user_can( Capabilities::VIEW_REPORTS ) ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( Admin::url( Admin::SLUG_REPORTS ) ), esc_html__( 'Report', 'multisite-fleet-manager' ) );
		}
		if ( current_user_can( Capabilities::VIEW_SITES ) ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( Admin::url( Admin::SLUG_CONSOLE ) ), esc_html__( 'Site Dashboard', 'multisite-fleet-manager' ) );
		}
		if ( current_user_can( Capabilities::VIEW_EXTENSIONS ) ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( Admin::url( Admin::SLUG_EXTENSIONS, array( 'filter' => 'update' ) ) ), esc_html__( 'Pending updates', 'multisite-fleet-manager' ) );
		}

		if ( $links ) {
			echo '<p class="wpfleet-widget__links">' . wp_kses_post( implode( ' · ', $links ) ) . '</p>';
		}
	}
}
