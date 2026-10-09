<?php
/**
 * Network settings screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Core\Settings;
use FleetManager\Operations\Operation;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings for discovery, retention, notifications and thresholds, plus
 * delegating Fleet Manager capabilities to people who are not super admins.
 *
 * Saving goes through OperationService, so a settings change is audited
 * like any other change.
 */
final class SettingsPage {

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
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) && ! current_user_can( Capabilities::MANAGE_ACCESS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		View::render(
			'admin/settings',
			array(
				'settings'   => Settings::all(),
				'defaults'   => Settings::defaults(),
				'results'    => Actions::take_results(),
				'can_save'   => $this->plugin->operations()->can( Operation::SETTINGS_UPDATE ),
				'can_access' => $this->plugin->operations()->can( Operation::ACCESS_UPDATE ),
				'grants'     => $this->grants(),
				'caps'       => Capabilities::labels(),
				'grantable'  => Capabilities::grantable(),
				'schedules'  => wp_get_schedules(),
				'recipients' => Settings::recipients(),
				'digest'     => $this->plugin->notifier()->last(),
				'next'       => $this->next_digest(),
			)
		);
	}

	/**
	 * Current delegations, with user details.
	 *
	 * @return array<int,array{user: \WP_User, caps: string[]}>
	 */
	private function grants(): array {
		$rows = array();
		foreach ( Capabilities::grants() as $user_id => $caps ) {
			$user = get_userdata( (int) $user_id );
			if ( $user ) {
				$rows[] = array(
					'user' => $user,
					'caps' => $caps,
				);
			}
		}
		return $rows;
	}

	/**
	 * When the next digest is due, read on the network's main site.
	 *
	 * @return int|null
	 */
	private function next_digest(): ?int {
		$main     = (int) get_main_site_id();
		$switched = get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}
		$next = wp_next_scheduled( \FleetManager\Notifications\Notifier::DIGEST_HOOK );
		if ( $switched ) {
			restore_current_blog();
		}
		return $next ? (int) $next : null;
	}
}
