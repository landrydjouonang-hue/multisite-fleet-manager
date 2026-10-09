<?php
/**
 * Contextual help.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Health\Category;

defined( 'ABSPATH' ) || exit;

/**
 * Documentation where it is needed: WordPress's own Help tab on each
 * screen, so nobody has to leave the admin to find out what a column means
 * or why an action is refused.
 */
final class Help {

	/**
	 * Adds the help tabs for a screen.
	 *
	 * @param string $screen Screen key used in Admin::$hooks.
	 * @return void
	 */
	public static function add( string $screen ): void {
		$current = get_current_screen();
		if ( ! $current ) {
			return;
		}

		foreach ( self::tabs( $screen ) as $id => $tab ) {
			$current->add_help_tab(
				array(
					'id'      => 'wpfleet-' . $id,
					'title'   => $tab['title'],
					'content' => '<p>' . implode( '</p><p>', array_map( 'wp_kses_post', $tab['content'] ) ) . '</p>',
				)
			);
		}

		$current->set_help_sidebar(
			'<p><strong>' . esc_html__( 'Multisite Fleet Manager', 'multisite-fleet-manager' ) . '</strong></p>' .
			'<p>' . esc_html__( 'Operational management for one WordPress Multisite network.', 'multisite-fleet-manager' ) . '</p>' .
			'<p>' . esc_html( Category::security_caveat() ) . '</p>'
		);
	}

	/**
	 * Tabs per screen, with a shared "how it works" tab everywhere.
	 *
	 * @param string $screen Screen key.
	 * @return array<string,array{title: string, content: string[]}>
	 */
	private static function tabs( string $screen ): array {
		$shared = array(
			'overview' => array(
				'title'   => __( 'How it works', 'multisite-fleet-manager' ),
				'content' => array(
					__( 'Fleet Manager keeps its own inventory of the network. Site discovery reads every site (read-only) and stores a snapshot; the inventory then updates in real time as sites, plugins, themes and memberships change.', 'multisite-fleet-manager' ),
					__( 'Everything you see is read from that inventory, which is why the screens are fast on large networks. "Synced" tells you how fresh it is, and you can refresh it at any time.', 'multisite-fleet-manager' ),
					__( 'Every change Fleet Manager makes — updates, activations, memberships, settings — checks your capabilities, verifies a nonce, validates the target and is written to the operation log, including refusals.', 'multisite-fleet-manager' ),
				),
			),
		);

		$map = array(
			'dashboard'   => array(
				'screen' => array(
					'title'   => __( 'Dashboard', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'Status counts, network facts, theme usage and the most recent sites. Use "Discover sites" after adding sites outside WordPress, for example by restoring a database.', 'multisite-fleet-manager' ),
					),
				),
			),
			'console'     => array(
				'screen' => array(
					'title'   => __( 'Site Dashboard', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'One row per site: lifecycle status, health, WordPress version, active theme and pending updates. Health is Healthy, Needs Attention or Error, rolled up from the indicators shown under Details.', 'multisite-fleet-manager' ),
						__( 'Filters, search, sorting and pagination are plain links, so any view can be bookmarked or shared.', 'multisite-fleet-manager' ),
					),
				),
			),
			'health'      => array(
				'screen' => array(
					'title'   => __( 'Health', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'Each indicator is counted across the fleet and grouped as Reliability, Security, Performance or Updates. Click a count to see exactly those sites.', 'multisite-fleet-manager' ),
						Category::security_caveat(),
					),
				),
			),
			'extensions'  => array(
				'screen' => array(
					'title'   => __( 'Plugins & Themes', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'Plugin and theme files are shared by the whole network, so an update applies to every site. Activation is per site: use a site\'s own view to turn a plugin on or off there.', 'multisite-fleet-manager' ),
						__( 'Updates are offered only where they can run safely: file changes allowed, direct filesystem access, no maintenance mode, and a compatible package.', 'multisite-fleet-manager' ),
					),
				),
			),
			'users'       => array(
				'screen' => array(
					'title'   => __( 'Users', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'Accounts are shared by the network; roles are per site. "Last activity" is a login recorded by Fleet Manager, or one derived from an open session — WordPress itself keeps no last-login date.', 'multisite-fleet-manager' ),
						__( 'You can add a user to a site, change their role there, or remove them. A site is never left without an administrator. Creating, editing and deleting accounts stays in Network Admin → Users.', 'multisite-fleet-manager' ),
					),
				),
			),
			'reports'     => array(
				'screen' => array(
					'title'   => __( 'Reports', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'The headline figures, the health distribution, pending updates and a trend built from stored snapshots. Snapshots are captured automatically after discovery and on request.', 'multisite-fleet-manager' ),
						__( 'Export any of it as CSV, or open the printable report and use your browser\'s "Save as PDF".', 'multisite-fleet-manager' ),
					),
				),
			),
			'log'         => array(
				'screen' => array(
					'title'   => __( 'Operation log', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'Every state-changing operation, including ones that were refused: "Denied" means a missing capability, "Rejected" means a failed security check or an invalid target, "Failed" means WordPress reported an error.', 'multisite-fleet-manager' ),
						__( 'Entries cannot be edited and are purged after the retention period set in Settings.', 'multisite-fleet-manager' ),
					),
				),
			),
			'settings'    => array(
				'screen' => array(
					'title'   => __( 'Settings', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'These values are defaults. Every one of them has a matching filter, and a filter set in code wins over the screen, so a site-specific configuration cannot be overwritten by someone clicking Save.', 'multisite-fleet-manager' ),
						__( 'Delegated access grants read-only capabilities (and running discovery) to people who are not super admins. Updates, user management and settings stay with super admins.', 'multisite-fleet-manager' ),
					),
				),
			),
			'diagnostics' => array(
				'screen' => array(
					'title'   => __( 'Diagnostics', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'The plugin\'s own state, its hooks and REST routes, and a plain-text report for bug reports. The report contains versions, counts and configuration only — no site names, user names or addresses.', 'multisite-fleet-manager' ),
					),
				),
			),
			'sites'       => array(
				'screen' => array(
					'title'   => __( 'Site inventory', 'multisite-fleet-manager' ),
					'content' => array(
						__( 'The detailed inventory: users, content, registration dates and sync times, with search, filters and sorting.', 'multisite-fleet-manager' ),
					),
				),
			),
		);

		return ( $map[ $screen ] ?? array() ) + $shared;
	}
}
