<?php
/**
 * Activation handler.
 *
 * @package FleetManager
 */

namespace FleetManager\Core;

use FleetManager\Sites\DiscoveryScheduler;
use FleetManager\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses activation outside a network, otherwise installs the plugin.
 */
final class Activator {

	public const NOTICE_OPTION = 'wpfleet_activated';

	/**
	 * Activation callback.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ): void {
		if ( ! is_multisite() ) {
			self::refuse(
				__( 'Multisite Fleet Manager requires WordPress Multisite.', 'multisite-fleet-manager' ),
				__( 'This installation is a single site, so there is no network to manage. The plugin was not activated. Convert this installation into a network first, then network activate Multisite Fleet Manager.', 'multisite-fleet-manager' )
			);
		}

		if ( ! $network_wide ) {
			self::refuse(
				__( 'Multisite Fleet Manager must be network activated.', 'multisite-fleet-manager' ),
				__( 'It manages the whole network and cannot run on a single site of it. Activate it from Network Admin → Plugins instead.', 'multisite-fleet-manager' )
			);
		}

		Schema::install();
		DiscoveryScheduler::schedule( true );
		update_site_option( self::NOTICE_OPTION, 1 );
	}

	/**
	 * Stops activation with an explanation.
	 *
	 * @param string $title   Title.
	 * @param string $message Explanation.
	 * @return void
	 */
	private static function refuse( string $title, string $message ): void {
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( MultisiteGuard::docs_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to create a network', 'multisite-fleet-manager' ) . '</a></p>',
			esc_html( $title ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}
}
