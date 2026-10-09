<?php
/**
 * Site inventory list table.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Sites\Site;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists inventory records. Read-only: links point to core's own screens.
 */
final class SitesListTable extends \WP_List_Table {

	private SiteRepository $sites;

	/**
	 * Counts per status (for the views).
	 *
	 * @var array<string,int>
	 */
	private array $counts = array();

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $sites Repository.
	 */
	public function __construct( SiteRepository $sites ) {
		$this->sites = $sites;
		parent::__construct(
			array(
				'singular' => 'site',
				'plural'   => 'sites',
				'ajax'     => false,
				'screen'   => get_current_screen(),
			)
		);
	}

	/**
	 * Current status filter ('' for all).
	 *
	 * @return string
	 */
	public function status_filter(): string {
		$status = isset( $_REQUEST['site_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['site_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		return in_array( $status, SiteStatus::all(), true ) ? $status : '';
	}

	/**
	 * Current search term.
	 *
	 * @return string
	 */
	public function search_term(): string {
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'name'            => __( 'Site', 'multisite-fleet-manager' ),
			'blog_id'         => __( 'ID', 'multisite-fleet-manager' ),
			'status'          => __( 'Status', 'multisite-fleet-manager' ),
			'theme'           => __( 'Theme', 'multisite-fleet-manager' ),
			'active_plugins'  => __( 'Active plugins', 'multisite-fleet-manager' ),
			'user_count'      => __( 'Users', 'multisite-fleet-manager' ),
			'post_count'      => __( 'Content', 'multisite-fleet-manager' ),
			'registered_at'   => __( 'Registered', 'multisite-fleet-manager' ),
			'last_updated_at' => __( 'Last updated', 'multisite-fleet-manager' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'name'            => array( 'name', false ),
			'blog_id'         => array( 'blog_id', false ),
			'status'          => array( 'status', false ),
			'active_plugins'  => array( 'active_plugins', true ),
			'user_count'      => array( 'user_count', true ),
			'post_count'      => array( 'post_count', true ),
			'registered_at'   => array( 'registered_at', true ),
			'last_updated_at' => array( 'last_updated_at', true ),
		);
	}

	/**
	 * Primary column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

	/**
	 * Loads items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sorting.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'blog_id';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc';
		// phpcs:enable

		$per_page     = $this->get_items_per_page( 'wpfleet_sites_per_page', 20 );
		$this->counts = $this->sites->count_by_status();
		$result       = $this->sites->query(
			array(
				'status'   => $this->status_filter(),
				'search'   => $this->search_term(),
				'orderby'  => isset( SiteRepository::ORDERBY[ $orderby ] ) ? $orderby : 'blog_id',
				'order'    => $order,
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
		$this->items           = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);
	}

	/**
	 * Status views.
	 *
	 * @return array<string,string>
	 */
	protected function get_views() {
		$current = $this->status_filter();
		$base    = Admin::url( Admin::SLUG_SITES );
		$views   = array();

		$views['all'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $base ),
			'' === $current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'All', 'multisite-fleet-manager' ),
			esc_html( View::number( (int) ( $this->counts['total'] ?? 0 ) ) )
		);

		foreach ( SiteStatus::all() as $status ) {
			$count = (int) ( $this->counts[ $status ] ?? 0 );
			if ( ! $count && $current !== $status ) {
				continue;
			}
			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( add_query_arg( 'site_status', $status, $base ) ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( SiteStatus::label( $status ) ),
				esc_html( View::number( $count ) )
			);
		}

		return $views;
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( ! ( $this->counts['total'] ?? 0 ) ) {
			esc_html_e( 'No sites in the inventory yet. Run site discovery from the dashboard.', 'multisite-fleet-manager' );
			return;
		}
		esc_html_e( 'No sites match the current filter.', 'multisite-fleet-manager' );
	}

	/**
	 * Name column with row actions.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_name( $item ) {
		$html  = '<strong>' . esc_html( $item->label() ) . '</strong>';
		$html .= '<br><span class="wpfleet-address">' . esc_html( View::address( $item ) ) . '</span>';
		$html .= View::flags( $item );

		$actions = array();
		if ( '' !== $item->home_url && SiteStatus::ACTIVE === $item->status ) {
			$actions['visit'] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( $item->home_url ),
				esc_html__( 'Visit', 'multisite-fleet-manager' ),
				/* translators: %s: Site name. */
				esc_html( sprintf( __( '%s (opens in a new tab)', 'multisite-fleet-manager' ), $item->label() ) )
			);
		}
		if ( current_user_can( 'manage_sites' ) ) {
			if ( '' !== $item->admin_url() ) {
				$actions['dashboard'] = sprintf( '<a href="%s">%s</a>', esc_url( $item->admin_url() ), esc_html__( 'Dashboard', 'multisite-fleet-manager' ) );
			}
			$actions['edit']      = sprintf( '<a href="%s">%s</a>', esc_url( network_admin_url( 'site-info.php?id=' . $item->blog_id ) ), esc_html__( 'Edit', 'multisite-fleet-manager' ) );
		}

		return $html . $this->row_actions( $actions );
	}

	/**
	 * ID column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_blog_id( $item ) {
		return esc_html( (string) $item->blog_id );
	}

	/**
	 * Status column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_status( $item ) {
		return View::status_badge( $item->status );
	}

	/**
	 * Theme column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_theme( $item ) {
		if ( '' === $item->theme_stylesheet ) {
			return '—';
		}
		return esc_html( '' !== $item->theme_name ? $item->theme_name : $item->theme_stylesheet );
	}

	/**
	 * Active plugins column (site-level activations only).
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_active_plugins( $item ) {
		return esc_html( View::number( $item->active_plugins ) );
	}

	/**
	 * Users column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_user_count( $item ) {
		return esc_html( View::number( $item->user_count ) );
	}

	/**
	 * Content column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_post_count( $item ) {
		return esc_html( View::content_count( $item->post_count, $item->page_count ) );
	}

	/**
	 * Registered column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_registered_at( $item ) {
		return esc_html( View::date( $item->registered_at ) );
	}

	/**
	 * Last updated column.
	 *
	 * @param Site $item Site.
	 * @return string
	 */
	protected function column_last_updated_at( $item ) {
		if ( ! $item->last_updated_at ) {
			return '—';
		}
		return sprintf(
			'<span title="%1$s">%2$s</span>',
			esc_attr( View::datetime( $item->last_updated_at ) ),
			esc_html( View::ago( $item->last_updated_at ) )
		);
	}
}
