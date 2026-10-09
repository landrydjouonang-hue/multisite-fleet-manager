<?php
/**
 * Notice shown while the plugin is dormant.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\MultisiteGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Explains why Multisite Fleet Manager is not running (no Multisite, or not network
 * activated) and what the administrator can do about it.
 */
final class RequirementNotice {

	private MultisiteGuard $guard;

	/**
	 * Constructor.
	 *
	 * @param MultisiteGuard $guard Guard.
	 */
	public function __construct( MultisiteGuard $guard ) {
		$this->guard = $guard;
	}

	/**
	 * Hooks the notices.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'network_admin_notices', array( $this, 'render' ) );
		add_action( 'after_plugin_row_' . WPFLEET_BASENAME, array( $this, 'plugin_row' ), 10, 0 );
	}

	/**
	 * Renders the notice (Dashboard and Plugins screens only, to stay unobtrusive).
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->base, array( 'dashboard', 'dashboard-network', 'plugins', 'plugins-network' ), true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Multisite Fleet Manager is not running.', 'multisite-fleet-manager' ) . '</strong></p>';
		echo wp_kses_post( $this->message() );
		echo '</div>';
	}

	/**
	 * Adds the explanation under the plugin's row on the Plugins screen.
	 *
	 * @return void
	 */
	public function plugin_row(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$active = is_plugin_active( WPFLEET_BASENAME ) || MultisiteGuard::is_network_active();
		printf(
			'<tr class="plugin-update-tr %s"><td colspan="4" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt">%s</div></td></tr>',
			$active ? 'active' : 'inactive',
			wp_kses_post( $this->message() )
		);
	}

	/**
	 * State-specific explanation (HTML, already escaped).
	 *
	 * @return string
	 */
	public function message(): string {
		if ( MultisiteGuard::STATE_NOT_NETWORK_ACTIVE === $this->guard->state() ) {
			return '<p>' . esc_html__( 'This plugin manages the whole network, so it must be network activated. Deactivate it on this site, then activate it from Network Admin → Plugins.', 'multisite-fleet-manager' ) . '</p>';
		}

		$html  = '<p>' . esc_html__( 'Multisite Fleet Manager requires WordPress Multisite. This installation is a single site, so there is no network to manage and the plugin stays inactive. It does not emulate a network.', 'multisite-fleet-manager' ) . '</p>';
		$html .= '<p>';

		if ( MultisiteGuard::network_setup_available() && current_user_can( 'setup_network' ) ) {
			$html .= sprintf(
				/* translators: %s: Link to the Network Setup screen. */
				esc_html__( 'Multisite is allowed on this installation. Continue in %s, then network activate Multisite Fleet Manager.', 'multisite-fleet-manager' ),
				'<a href="' . esc_url( admin_url( 'network.php' ) ) . '">' . esc_html__( 'Tools → Network Setup', 'multisite-fleet-manager' ) . '</a>'
			);
		} else {
			$html .= esc_html__( 'To use it, convert this installation into a network (back up files and database first), then network activate the plugin.', 'multisite-fleet-manager' );
		}

		$html .= ' <a href="' . esc_url( MultisiteGuard::docs_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to create a network', 'multisite-fleet-manager' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'multisite-fleet-manager' ) . '</span></a></p>';

		return $html;
	}
}
