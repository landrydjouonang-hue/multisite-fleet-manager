<?php
/**
 * Template rendering and formatting helpers.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Health\Indicator;
use FleetManager\Sites\Site;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Includes templates from /templates and formats values for display.
 */
final class View {

	/**
	 * Renders a template.
	 *
	 * @param string              $template Relative path without extension, e.g. "admin/dashboard".
	 * @param array<string,mixed> $vars     Variables exposed to the template as $vars.
	 * @return void
	 */
	public static function render( string $template, array $vars = array() ): void {
		$file = WPFLEET_PATH . 'templates/' . $template . '.php';
		if ( ! preg_match( '#^[a-z0-9/_-]+$#', $template ) || ! is_readable( $file ) ) {
			return;
		}
		( static function () use ( $file, $vars ) {
			include $file;
		} )();
	}

	/**
	 * Formats a UTC datetime in the network's date and time format.
	 *
	 * @param string|null $gmt MySQL datetime (UTC).
	 * @return string
	 */
	public static function datetime( ?string $gmt ): string {
		if ( ! $gmt ) {
			return '—';
		}
		return (string) get_date_from_gmt( $gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}

	/**
	 * Formats a UTC datetime as a date only.
	 *
	 * @param string|null $gmt MySQL datetime (UTC).
	 * @return string
	 */
	public static function date( ?string $gmt ): string {
		if ( ! $gmt ) {
			return '—';
		}
		return (string) get_date_from_gmt( $gmt, get_option( 'date_format' ) );
	}

	/**
	 * "5 minutes ago" style relative time.
	 *
	 * @param string|null $gmt MySQL datetime (UTC).
	 * @return string
	 */
	public static function ago( ?string $gmt ): string {
		if ( ! $gmt ) {
			return '';
		}
		$time = strtotime( $gmt . ' UTC' );
		if ( ! $time ) {
			return '';
		}
		/* translators: %s: Human-readable time difference, e.g. "5 minutes". */
		return sprintf( __( '%s ago', 'multisite-fleet-manager' ), human_time_diff( $time, time() ) );
	}

	/**
	 * Localised integer.
	 *
	 * @param int $number Number.
	 * @return string
	 */
	public static function number( int $number ): string {
		return (string) number_format_i18n( $number );
	}

	/**
	 * "5 posts, 1 page" with correct plurals.
	 *
	 * @param int $posts Published posts.
	 * @param int $pages Published pages.
	 * @return string
	 */
	public static function content_count( int $posts, int $pages ): string {
		return sprintf(
			/* translators: 1: "5 posts", 2: "1 page". */
			__( '%1$s, %2$s', 'multisite-fleet-manager' ),
			/* translators: %s: Number of posts. */
			sprintf( _n( '%s post', '%s posts', $posts, 'multisite-fleet-manager' ), self::number( $posts ) ),
			/* translators: %s: Number of pages. */
			sprintf( _n( '%s page', '%s pages', $pages, 'multisite-fleet-manager' ), self::number( $pages ) )
		);
	}

	/**
	 * Spoken name of an indicator state (the colour dot is decorative).
	 *
	 * @param string $state Indicator state.
	 * @return string
	 */
	public static function indicator_state( string $state ): string {
		$labels = array(
			Indicator::GOOD    => __( '(passed)', 'multisite-fleet-manager' ),
			Indicator::WARNING => __( '(needs attention)', 'multisite-fleet-manager' ),
			Indicator::ERROR   => __( '(error)', 'multisite-fleet-manager' ),
			Indicator::UNKNOWN => __( '(unknown)', 'multisite-fleet-manager' ),
		);
		return $labels[ $state ] ?? '';
	}

	/**
	 * Status badge (icon + text, never colour alone). Returns escaped HTML.
	 *
	 * @param string $status SiteStatus value.
	 * @return string
	 */
	public static function status_badge( string $status ): string {
		return sprintf(
			'<span class="wpfleet-badge wpfleet-status--%1$s"><span class="dashicons %2$s" aria-hidden="true"></span>%3$s</span>',
			esc_attr( $status ),
			esc_attr( SiteStatus::icon( $status ) ),
			esc_html( SiteStatus::label( $status ) )
		);
	}

	/**
	 * Secondary flags of a site (main site, not public, mature). Returns escaped HTML.
	 *
	 * @param Site $site Site.
	 * @return string
	 */
	public static function flags( Site $site ): string {
		$flags = array();
		if ( $site->is_main ) {
			$flags[] = __( 'Main site', 'multisite-fleet-manager' );
		}
		if ( ! $site->is_public ) {
			$flags[] = __( 'Hidden from search engines', 'multisite-fleet-manager' );
		}
		if ( $site->is_mature ) {
			$flags[] = __( 'Mature', 'multisite-fleet-manager' );
		}
		if ( ! $flags ) {
			return '';
		}
		$html = '';
		foreach ( $flags as $flag ) {
			$html .= '<span class="wpfleet-tag">' . esc_html( $flag ) . '</span>';
		}
		return '<span class="wpfleet-tags">' . $html . '</span>';
	}

	/**
	 * Address shown under a site's name, e.g. "example.com/blog".
	 *
	 * @param Site $site Site.
	 * @return string
	 */
	public static function address( Site $site ): string {
		return untrailingslashit( $site->domain . $site->path );
	}
}
