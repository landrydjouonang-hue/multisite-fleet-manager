<?php
/**
 * Site status values.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

defined( 'ABSPATH' ) || exit;

/**
 * Normalised lifecycle status of a network site.
 *
 * Core stores independent flags (archived, spam, deleted). The inventory
 * derives one status from them, most severe first, so sites can be filtered
 * and counted without ambiguity. The raw flags are stored as well.
 */
final class SiteStatus {

	public const ACTIVE   = 'active';
	public const ARCHIVED = 'archived';
	public const SPAM     = 'spam';
	public const DELETED  = 'deleted';

	/**
	 * All statuses, in display order.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::ACTIVE, self::ARCHIVED, self::SPAM, self::DELETED );
	}

	/**
	 * Derives the status from core's flags.
	 *
	 * @param bool $deleted  Flagged for deletion (deactivated).
	 * @param bool $spam     Marked as spam.
	 * @param bool $archived Archived.
	 * @return string
	 */
	public static function from_flags( bool $deleted, bool $spam, bool $archived ): string {
		if ( $deleted ) {
			return self::DELETED;
		}
		if ( $spam ) {
			return self::SPAM;
		}
		if ( $archived ) {
			return self::ARCHIVED;
		}
		return self::ACTIVE;
	}

	/**
	 * Label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function label( string $status ): string {
		$labels = array(
			self::ACTIVE   => __( 'Active', 'multisite-fleet-manager' ),
			self::ARCHIVED => __( 'Archived', 'multisite-fleet-manager' ),
			self::SPAM     => __( 'Spam', 'multisite-fleet-manager' ),
			/* translators: Core calls a site flagged for deletion "Deactivated". */
			self::DELETED  => __( 'Deactivated', 'multisite-fleet-manager' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Dashicon.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function icon( string $status ): string {
		$icons = array(
			self::ACTIVE   => 'dashicons-yes-alt',
			self::ARCHIVED => 'dashicons-archive',
			self::SPAM     => 'dashicons-flag',
			self::DELETED  => 'dashicons-dismiss',
		);
		return $icons[ $status ] ?? 'dashicons-marker';
	}
}
