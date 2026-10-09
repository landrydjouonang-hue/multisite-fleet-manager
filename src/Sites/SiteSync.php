<?php
/**
 * Real-time inventory updates.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the inventory current between discovery runs by listening to core's
 * site lifecycle hooks and to a few option changes.
 *
 * Changes are collected during the request and written once, on shutdown.
 * Nothing is written before the first discovery run: a partial inventory
 * would misrepresent the network.
 */
final class SiteSync {

	/**
	 * Site options whose change refreshes the site's record.
	 */
	private const WATCHED_OPTIONS = array( 'blogname', 'siteurl', 'home', 'admin_email', 'active_plugins', 'stylesheet', 'template', 'WPLANG', 'blog_public' );

	private SiteRepository $sites;

	private SiteInspector $inspector;

	private SiteDiscovery $discovery;

	/**
	 * Blog IDs to refresh on shutdown, keyed by ID.
	 *
	 * @var array<int,true>
	 */
	private array $queue = array();

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $sites     Repository.
	 * @param SiteInspector  $inspector Inspector.
	 * @param SiteDiscovery  $discovery Discovery.
	 */
	public function __construct( SiteRepository $sites, SiteInspector $inspector, SiteDiscovery $discovery ) {
		$this->sites     = $sites;
		$this->inspector = $inspector;
		$this->discovery = $discovery;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priority 100: after core and other plugins have finished setting the site up.
		add_action( 'wp_initialize_site', array( $this, 'on_site_changed' ), 100 );
		add_action( 'wp_update_site', array( $this, 'on_site_changed' ), 100 );
		add_action( 'wp_delete_site', array( $this, 'on_site_deleted' ), 100 );

		foreach ( self::WATCHED_OPTIONS as $option ) {
			add_action( "update_option_{$option}", array( $this, 'on_option_changed' ), 10, 0 );
			add_action( "add_option_{$option}", array( $this, 'on_option_changed' ), 10, 0 );
		}

		add_action( 'add_user_to_blog', array( $this, 'on_membership_changed' ), 10, 3 );
		add_action( 'remove_user_from_blog', array( $this, 'on_membership_changed' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'on_post_status' ), 10, 3 );
	}

	/**
	 * A site was created or its row in wp_blogs changed.
	 *
	 * @param \WP_Site $site Site.
	 * @return void
	 */
	public function on_site_changed( $site ): void {
		if ( $site instanceof \WP_Site ) {
			$this->enqueue( (int) $site->blog_id );
		}
	}

	/**
	 * A site was deleted permanently.
	 *
	 * @param \WP_Site $site Deleted site.
	 * @return void
	 */
	public function on_site_deleted( $site ): void {
		if ( $site instanceof \WP_Site ) {
			unset( $this->queue[ (int) $site->blog_id ] );
			$this->sites->delete( (int) $site->blog_id, (int) $site->network_id );
		}
	}

	/**
	 * A watched option of the current site changed.
	 *
	 * @return void
	 */
	public function on_option_changed(): void {
		$this->enqueue( get_current_blog_id() );
	}

	/**
	 * A user was added to or removed from a site.
	 *
	 * @param int        $user_id User ID.
	 * @param int|string $arg2    Role (add) or blog ID (remove).
	 * @param int|string $arg3    Blog ID (add) or reassign user ID (remove).
	 * @return void
	 */
	public function on_membership_changed( $user_id, $arg2 = 0, $arg3 = 0 ): void {
		$blog_id = 'add_user_to_blog' === current_action() ? (int) $arg3 : (int) $arg2;
		if ( $blog_id ) {
			$this->enqueue( $blog_id );
		}
	}

	/**
	 * A post or page was published or unpublished.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_post_status( $new_status, $old_status, $post ): void {
		if ( ( 'publish' === $new_status ) === ( 'publish' === $old_status ) ) {
			return;
		}
		if ( $post instanceof \WP_Post && in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			$this->enqueue( get_current_blog_id() );
		}
	}

	/**
	 * Writes queued refreshes.
	 *
	 * @return void
	 */
	public function flush(): void {
		$queue       = array_keys( $this->queue );
		$this->queue = array();

		foreach ( $queue as $blog_id ) {
			$site = $this->inspector->inspect( $blog_id );
			// Sites of other networks are refreshed only if already known there.
			if ( $site && ( (int) get_current_network_id() === $site->network_id || $this->sites->find( $blog_id, $site->network_id ) ) ) {
				$this->sites->save( $site );
			}
		}
	}

	/**
	 * Queues a site, once the inventory exists.
	 *
	 * @param int $blog_id Blog ID.
	 * @return void
	 */
	private function enqueue( int $blog_id ): void {
		if ( $blog_id <= 0 || ! $this->discovery->last() ) {
			return;
		}
		if ( ! $this->queue ) {
			add_action( 'shutdown', array( $this, 'flush' ) );
		}
		$this->queue[ $blog_id ] = true;
	}
}
