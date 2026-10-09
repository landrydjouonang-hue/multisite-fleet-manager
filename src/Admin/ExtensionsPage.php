<?php
/**
 * Plugins & Themes screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Operation;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Network-level plugin and theme visibility, with centralized update
 * controls, plus a per-site view (?tab=site&site=ID) with activation
 * controls. Controls are rendered only for users allowed to use them; the
 * server re-checks everything in OperationService regardless.
 */
final class ExtensionsPage {

	public const PER_PAGE_OPTIONS = array( 20, 50, 100 );

	public const PLUGIN_FILTERS = array( 'update', 'network', 'active', 'inactive' );
	public const THEME_FILTERS  = array( 'update', 'enabled', 'used', 'unused' );

	private Plugin $plugin;

	/**
	 * Sanitised request state.
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
	 * Request state.
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

		$tab      = in_array( $get( 'tab' ), array( 'plugins', 'themes', 'site' ), true ) ? $get( 'tab' ) : 'plugins';
		$filters  = 'themes' === $tab ? self::THEME_FILTERS : self::PLUGIN_FILTERS;
		$per_page = (int) $get( 'per_page' );

		$this->state = array(
			'tab'      => $tab,
			'site'     => max( 0, (int) $get( 'site' ) ),
			's'        => $get( 's' ),
			'filter'   => in_array( $get( 'filter' ), $filters, true ) ? $get( 'filter' ) : '',
			'orderby'  => in_array( $get( 'orderby' ), array( 'name', 'sites', 'update' ), true ) ? $get( 'orderby' ) : 'name',
			'order'    => 'desc' === strtolower( $get( 'order' ) ) ? 'desc' : 'asc',
			'paged'    => max( 1, (int) $get( 'paged' ) ),
			'per_page' => in_array( $per_page, self::PER_PAGE_OPTIONS, true ) ? $per_page : 20,
		);
		return $this->state;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_EXTENSIONS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$state      = $this->state();
		$operations = $this->plugin->operations();
		$common     = array(
			'page'        => $this,
			'state'       => $state,
			'blocker'     => $this->plugin->update_environment()->blocker(),
			'results'     => Actions::take_results(),
			'can_update'  => array(
				'plugin' => $operations->can( Operation::PLUGIN_UPDATE ),
				'theme'  => $operations->can( Operation::THEME_UPDATE ),
			),
			'can_toggle'  => $operations->can( Operation::PLUGIN_ACTIVATE ),
			'can_sites'   => current_user_can( Capabilities::VIEW_SITES ),
			'can_log'     => current_user_can( Capabilities::VIEW_LOG ),
			'inventoried' => null !== $this->plugin->discovery()->last(),
		);

		if ( 'site' === $state['tab'] ) {
			$site = $state['site'] ? get_site( $state['site'] ) : null;
			if ( ! $site || (int) $site->network_id !== (int) get_current_network_id() ) {
				wp_die( esc_html__( 'This site does not exist on this network.', 'multisite-fleet-manager' ), '', array( 'response' => 404 ) );
			}
			View::render(
				'admin/site-extensions',
				$common + array(
					'site'       => $site,
					'site_name'  => (string) get_blog_option( (int) $site->blog_id, 'blogname', '' ),
					'extensions' => $this->plugin->extensions()->site( (int) $site->blog_id ),
				)
			);
			return;
		}

		$is_themes = 'themes' === $state['tab'];
		$all       = $is_themes ? $this->plugin->extensions()->themes() : $this->plugin->extensions()->plugins();
		$filtered  = $this->filter( $all, $is_themes );
		$total     = count( $filtered );
		$items     = array_slice( $filtered, ( $state['paged'] - 1 ) * $state['per_page'], $state['per_page'] );

		View::render(
			'admin/extensions',
			$common + array(
				'is_themes' => $is_themes,
				'items'     => $items,
				'total'     => $total,
				'stats'     => $this->stats( $all, $is_themes ),
			)
		);
	}

	/**
	 * Applies search, filter and sort.
	 *
	 * @param array<string,array<string,mixed>> $rows      Rows.
	 * @param bool                              $is_themes Themes tab.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter( array $rows, bool $is_themes ): array {
		$state  = $this->state();
		$search = strtolower( $state['s'] );

		$rows = array_filter(
			$rows,
			static function ( array $row ) use ( $state, $search, $is_themes ): bool {
				if ( '' !== $search && false === strpos( strtolower( $row['name'] . ' ' . ( $row['file'] ?? $row['stylesheet'] ) . ' ' . $row['author'] ), $search ) ) {
					return false;
				}
				switch ( $state['filter'] ) {
					case 'update':
						return null !== $row['update'];
					case 'network':
						return ! empty( $row['network_active'] );
					case 'active':
						return empty( $row['network_active'] ) && $row['sites'] > 0;
					case 'inactive':
						return empty( $row['network_active'] ) && 0 === $row['sites'];
					case 'enabled':
						return ! empty( $row['network_enabled'] );
					case 'used':
						return $row['sites'] + ( $row['parent_of'] ?? 0 ) > 0;
					case 'unused':
						return 0 === $row['sites'] + ( $row['parent_of'] ?? 0 );
				}
				return true;
			}
		);

		$dir = 'desc' === $state['order'] ? -1 : 1;
		usort(
			$rows,
			static function ( $a, $b ) use ( $state, $dir, $is_themes ) {
				switch ( $state['orderby'] ) {
					case 'sites':
						$cmp = ( $a['sites'] + ( $is_themes ? $a['parent_of'] : 0 ) ) <=> ( $b['sites'] + ( $is_themes ? $b['parent_of'] : 0 ) );
						break;
					case 'update':
						$cmp = ( null !== $b['update'] ) <=> ( null !== $a['update'] );
						break;
					default:
						$cmp = 0;
				}
				return 0 !== $cmp ? $cmp * $dir : strcasecmp( $a['name'], $b['name'] ) * ( 'name' === $state['orderby'] ? $dir : 1 );
			}
		);

		return array_values( $rows );
	}

	/**
	 * Tile figures.
	 *
	 * @param array<string,array<string,mixed>> $rows      All rows.
	 * @param bool                              $is_themes Themes tab.
	 * @return array<string,int>
	 */
	private function stats( array $rows, bool $is_themes ): array {
		$stats = array(
			'total'  => count( $rows ),
			'update' => 0,
			'a'      => 0,
			'b'      => 0,
			'c'      => 0,
		);
		foreach ( $rows as $row ) {
			$stats['update'] += null !== $row['update'] ? 1 : 0;
			if ( $is_themes ) {
				$used          = $row['sites'] + $row['parent_of'] > 0;
				$stats['a']   += $row['network_enabled'] ? 1 : 0;
				$stats['b']   += $used ? 1 : 0;
				$stats['c']   += $used ? 0 : 1;
			} else {
				$stats['a'] += $row['network_active'] ? 1 : 0;
				$stats['b'] += ! $row['network_active'] && $row['sites'] > 0 ? 1 : 0;
				$stats['c'] += ! $row['network_active'] && 0 === $row['sites'] ? 1 : 0;
			}
		}
		return $stats;
	}

	/**
	 * URL of this page with the current state changed by $args (null removes).
	 *
	 * @param array<string,mixed> $args Changes.
	 * @return string
	 */
	public function url( array $args = array() ): string {
		$state = array_merge( $this->state(), array( 'paged' => 1 ), $args );
		$query = array();
		foreach ( $state as $key => $value ) {
			if ( null === $value || '' === $value || 0 === $value ) {
				continue;
			}
			$defaults = array(
				'tab'      => 'plugins',
				'orderby'  => 'name',
				'order'    => 'asc',
				'paged'    => 1,
				'per_page' => 20,
			);
			if ( isset( $defaults[ $key ] ) && $defaults[ $key ] === $value ) {
				continue;
			}
			$query[ $key ] = $value;
		}
		return Admin::url( Admin::SLUG_EXTENSIONS, $query );
	}

	/**
	 * Sortable header (escaped HTML).
	 *
	 * @param string $key   Sort key.
	 * @param string $label Label.
	 * @param string $class Extra class.
	 * @return string
	 */
	public function sort_header( string $key, string $label, string $class = '' ): string {
		$state   = $this->state();
		$current = $state['orderby'] === $key;
		$next    = $current && 'asc' === $state['order'] ? 'desc' : 'asc';
		$icon    = $current ? ( 'asc' === $state['order'] ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' ) : 'dashicons-sort';
		return sprintf(
			'<th scope="col" class="%1$s%2$s"%3$s><a href="%4$s">%5$s<span class="dashicons %6$s" aria-hidden="true"></span></a></th>',
			esc_attr( $class ),
			$current ? ' is-sorted' : '',
			$current ? ' aria-sort="' . ( 'asc' === $state['order'] ? 'ascending' : 'descending' ) . '"' : '',
			esc_url(
				$this->url(
					array(
						'orderby' => $key,
						'order'   => $next,
					)
				)
			),
			esc_html( $label ),
			esc_attr( $icon )
		);
	}
}
