<?php
/**
 * Real-time membership index updates.
 *
 * @package FleetManager
 */

namespace FleetManager\Users;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to core's user hooks so the membership index stays correct between
 * discovery runs, whoever made the change (Fleet Manager, Network Admin,
 * WP-CLI or another plugin).
 */
final class UserSync {

	private UserIndex $index;

	/**
	 * Constructor.
	 *
	 * @param UserIndex $index Index.
	 */
	public function __construct( UserIndex $index ) {
		$this->index = $index;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_user_to_blog', array( $this, 'on_added' ), 100, 3 );
		add_action( 'remove_user_from_blog', array( $this, 'on_removed' ), 100, 2 );
		add_action( 'set_user_role', array( $this, 'on_role_changed' ), 100 );
		add_action( 'add_user_role', array( $this, 'on_role_changed' ), 100 );
		add_action( 'remove_user_role', array( $this, 'on_role_changed' ), 100 );
		add_action( 'wpmu_delete_user', array( $this, 'on_user_deleted' ) );
		add_action( 'wp_delete_site', array( $this, 'on_site_deleted' ), 100 );
	}

	/**
	 * A user was added to a site.
	 *
	 * @param int        $user_id User ID.
	 * @param string     $role    Role.
	 * @param int|string $blog_id Blog ID.
	 * @return void
	 */
	public function on_added( $user_id, $role = '', $blog_id = 0 ): void {
		$this->refresh( (int) $user_id, (int) $blog_id );
	}

	/**
	 * A user was removed from a site.
	 *
	 * @param int        $user_id User ID.
	 * @param int|string $blog_id Blog ID.
	 * @return void
	 */
	public function on_removed( $user_id, $blog_id = 0 ): void {
		$blog_id = (int) $blog_id ?: get_current_blog_id();
		$this->index->remove( (int) $user_id, $blog_id, $this->network_of( $blog_id ) );
	}

	/**
	 * A user's roles changed on the current site.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_role_changed( $user_id ): void {
		$this->refresh( (int) $user_id, get_current_blog_id() );
	}

	/**
	 * A user was deleted from the network.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_deleted( $user_id ): void {
		$this->index->clear_user( (int) $user_id );
	}

	/**
	 * A site was deleted.
	 *
	 * @param \WP_Site $site Site.
	 * @return void
	 */
	public function on_site_deleted( $site ): void {
		if ( $site instanceof \WP_Site ) {
			$this->index->clear_site( (int) $site->blog_id, (int) $site->network_id );
		}
	}

	/**
	 * Re-reads a user's roles on a site and stores them.
	 *
	 * @param int $user_id User ID.
	 * @param int $blog_id Blog ID.
	 * @return void
	 */
	private function refresh( int $user_id, int $blog_id ): void {
		global $wpdb;
		if ( $user_id <= 0 || $blog_id <= 0 ) {
			return;
		}

		$caps  = get_user_meta( $user_id, $wpdb->get_blog_prefix( $blog_id ) . 'capabilities', true );
		$roles = UserIndex::roles_from_capabilities( $caps );

		if ( ! is_array( $caps ) || ! $caps ) {
			$this->index->remove( $user_id, $blog_id, $this->network_of( $blog_id ) );
			return;
		}
		$this->index->set( $user_id, $blog_id, $roles, $this->network_of( $blog_id ) );
	}

	/**
	 * Network of a site (the current one by default).
	 *
	 * @param int $blog_id Blog ID.
	 * @return int
	 */
	private function network_of( int $blog_id ): int {
		$site = get_site( $blog_id );
		return $site ? (int) $site->network_id : (int) get_current_network_id();
	}
}
