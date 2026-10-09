<?php
/**
 * Network site dashboard (fleet console).
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Health\Health;
use FleetManager\Health\Indicator;
use FleetManager\Plugin;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Fleet console: every site with its status, WordPress version, theme,
 * update counts and health indicators. Search, filters, sorting and
 * pagination are plain GET parameters, so every view is linkable and works
 * without JavaScript.
 */
final class ConsolePage {

	public const PER_PAGE_OPTIONS = array( 10, 20, 50, 100 );

	/**
	 * Sortable columns: request key → repository sort key.
	 */
	public const SORTABLE = array(
		'site'           => 'name',
		'status'         => 'status',
		'health'         => 'health',
		'wordpress'      => 'db_version',
		'theme'          => 'theme',
		'plugin_updates' => 'plugin_updates',
		'theme_updates'  => 'theme_updates',
		'synced'         => 'synced_at',
	);

	private Plugin $plugin;

	/**
	 * Current, sanitised request state.
	 *
	 * @var array<string,mixed>
	 */
	private array $state = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Reads and whitelists the query arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function state(): array {
		if ( $this->state ) {
			return $this->state;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$get = static fn( string $key ): string => isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		// phpcs:enable

		$orderby  = $get( 'orderby' );
		$order    = strtolower( $get( 'order' ) );
		$per_page = (int) $get( 'per_page' );

		$this->state = array(
			's'           => $get( 's' ),
			'health'      => in_array( $get( 'health' ), Health::all(), true ) ? $get( 'health' ) : '',
			'site_status' => in_array( $get( 'site_status' ), SiteStatus::all(), true ) ? $get( 'site_status' ) : '',
			'updates'     => isset( SiteRepository::UPDATE_FILTERS[ $get( 'updates' ) ] ) ? $get( 'updates' ) : '',
			'theme'       => preg_match( '#^[\w.\-/]{1,191}$#', $get( 'theme' ) ) ? $get( 'theme' ) : '',
			'plugin'      => preg_match( '#^[\w.\-/]{1,255}$#', $get( 'plugin' ) ) && 0 === validate_file( $get( 'plugin' ) ) ? $get( 'plugin' ) : '',
			'indicator'   => sanitize_key( $get( 'indicator' ) ),
			'state'       => in_array( $get( 'state' ), Indicator::states(), true ) ? $get( 'state' ) : '',
			'orderby'     => isset( self::SORTABLE[ $orderby ] ) ? $orderby : 'health',
			'order'       => in_array( $order, array( 'asc', 'desc' ), true ) ? $order : 'asc',
			'paged'       => max( 1, (int) $get( 'paged' ) ),
			'per_page'    => in_array( $per_page, self::PER_PAGE_OPTIONS, true ) ? $per_page : 20,
		);

		return $this->state;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_SITES ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$state  = $this->state();
		$sites  = $this->plugin->sites();
		$result = $sites->query(
			array(
				'search'   => $state['s'],
				'health'   => $state['health'],
				'status'   => $state['site_status'],
				'updates'  => $state['updates'],
				'theme'    => $state['theme'],
				'plugin'    => $state['plugin'],
				'indicator' => $state['indicator'],
				'state'     => $state['state'],
				'orderby'  => self::SORTABLE[ $state['orderby'] ],
				'order'    => $state['order'],
				'page'     => $state['paged'],
				'per_page' => $state['per_page'],
			)
		);

		$network = $this->plugin->network()->summary();

		View::render(
			'admin/console',
			array(
				'page'        => $this,
				'state'       => $state,
				'items'       => $result['items'],
				'total'       => $result['total'],
				'health'      => $sites->count_by_health(),
				'statuses'    => $sites->count_by_status(),
				'updates'     => $sites->update_summary(),
				'themes'      => $sites->theme_usage( 100 ),
				'context'     => $this->plugin->health()->context(),
				'checks'      => $this->plugin->health_checks(),
				'last'        => $this->plugin->discovery()->last(),
				'network'     => $network,
				'can_run'     => current_user_can( Capabilities::RUN_DISCOVERY ),
				'can_manage'  => current_user_can( 'manage_sites' ),
				'can_update'  => current_user_can( 'update_core' ) || current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ),
				'has_filters' => '' !== $state['s'] || '' !== $state['health'] || '' !== $state['site_status'] || '' !== $state['updates'] || '' !== $state['theme'] || '' !== $state['plugin'] || '' !== $state['indicator'],
				'can_extend'  => current_user_can( Capabilities::VIEW_EXTENSIONS ),
			)
		);
	}

	/**
	 * URL of the console with the current state, changed by $args
	 * (null removes an argument). Defaults are left out to keep URLs short.
	 *
	 * @param array<string,mixed> $args Changes.
	 * @return string
	 */
	public function url( array $args = array() ): string {
		$state = array_merge( $this->state(), array( 'paged' => 1 ), $args );
		$query = array();
		foreach ( $state as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}
			if ( ( 'paged' === $key && 1 === (int) $value ) || ( 'per_page' === $key && 20 === (int) $value ) ) {
				continue;
			}
			if ( ( 'orderby' === $key && 'health' === $value ) || ( 'order' === $key && 'asc' === $value ) ) {
				continue;
			}
			$query[ $key ] = $value;
		}
		return Admin::url( Admin::SLUG_CONSOLE, $query );
	}

	/**
	 * Sortable column header (escaped HTML).
	 *
	 * @param string $key   Key of self::SORTABLE.
	 * @param string $label Label.
	 * @return string
	 */
	public function sort_header( string $key, string $label ): string {
		$state   = $this->state();
		$current = $state['orderby'] === $key;
		$next    = $current && 'asc' === $state['order'] ? 'desc' : 'asc';
		$aria    = $current ? ( 'asc' === $state['order'] ? 'ascending' : 'descending' ) : '';

		$indicator = $current ? ( 'asc' === $state['order'] ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' ) : 'dashicons-sort';

		return sprintf(
			'<th scope="col" class="wpfc-col-%1$s%2$s"%3$s><a href="%4$s">%5$s<span class="dashicons %6$s" aria-hidden="true"></span></a></th>',
			esc_attr( $key ),
			$current ? ' is-sorted' : '',
			$aria ? ' aria-sort="' . esc_attr( $aria ) . '"' : '',
			esc_url(
				$this->url(
					array(
						'orderby' => $key,
						'order'   => $next,
					)
				)
			),
			esc_html( $label ),
			esc_attr( $indicator )
		);
	}
}
