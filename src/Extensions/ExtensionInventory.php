<?php
/**
 * Network plugin and theme inventory.
 *
 * @package FleetManager
 */

namespace FleetManager\Extensions;

use FleetManager\Sites\SiteRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of what is installed on the network and where it runs.
 *
 * Plugin and theme files are shared by every site, so "installed" and
 * "version" are network facts. "Active" is per site: site activations come
 * from the inventory snapshot, network activations from core. Available
 * updates come from core's cached update data (no remote requests).
 */
final class ExtensionInventory {

	private SiteRepository $sites;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $sites Repository.
	 */
	public function __construct( SiteRepository $sites ) {
		$this->sites = $sites;
	}

	/**
	 * All installed plugins.
	 *
	 * @return array<string,array<string,mixed>> Keyed by basename.
	 */
	public function plugins(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$usage   = $this->usage();
		$updates = get_site_transient( 'update_plugins' );
		$network = (array) get_site_option( 'active_sitewide_plugins', array() );
		$auto    = (array) get_site_option( 'auto_update_plugins', array() );
		$total   = $this->sites->count();
		$rows    = array();

		foreach ( get_plugins() as $file => $data ) {
			$offer = is_object( $updates ) && isset( $updates->response[ $file ] ) ? (array) $updates->response[ $file ] : null;

			$rows[ $file ] = array(
				'file'           => $file,
				'name'           => (string) $data['Name'],
				'version'        => (string) $data['Version'],
				'author'         => wp_strip_all_tags( (string) $data['Author'] ),
				'network_only'   => ! empty( $data['Network'] ),
				'network_active' => isset( $network[ $file ] ),
				'sites'          => isset( $network[ $file ] ) ? $total : (int) ( $usage['plugins'][ $file ] ?? 0 ),
				'auto_update'    => in_array( $file, $auto, true ),
				'update'         => $offer ? $this->offer( $offer ) : null,
			);
		}

		return $rows;
	}

	/**
	 * All installed themes.
	 *
	 * @return array<string,array<string,mixed>> Keyed by stylesheet.
	 */
	public function themes(): array {
		$usage   = $this->usage();
		$updates = get_site_transient( 'update_themes' );
		$allowed = (array) get_site_option( 'allowedthemes', array() );
		$auto    = (array) get_site_option( 'auto_update_themes', array() );
		$rows    = array();

		foreach ( wp_get_themes( array( 'errors' => null ) ) as $stylesheet => $theme ) {
			$offer  = is_object( $updates ) && isset( $updates->response[ $stylesheet ] ) ? (array) $updates->response[ $stylesheet ] : null;
			$parent = $theme->parent();

			$rows[ $stylesheet ] = array(
				'stylesheet'      => (string) $stylesheet,
				'name'            => (string) $theme->get( 'Name' ),
				'version'         => (string) $theme->get( 'Version' ),
				'author'          => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
				'parent'          => $parent ? (string) $parent->get_stylesheet() : '',
				'broken'          => (bool) $theme->errors(),
				'network_enabled' => ! empty( $allowed[ $stylesheet ] ),
				'sites'           => (int) ( $usage['themes'][ $stylesheet ] ?? 0 ),
				'parent_of'       => (int) ( $usage['parents'][ $stylesheet ] ?? 0 ),
				'auto_update'     => in_array( $stylesheet, $auto, true ),
				'update'          => $offer ? $this->offer( $offer ) : null,
			);
		}

		return $rows;
	}

	/**
	 * Plugins and theme of one site, read live from the site.
	 *
	 * @param int $blog_id Blog ID.
	 * @return array{plugins: array<int,array<string,mixed>>, theme: array<string,mixed>|null, parent: array<string,mixed>|null}
	 */
	public function site( int $blog_id ): array {
		$active     = (array) get_blog_option( $blog_id, 'active_plugins', array() );
		$stylesheet = (string) get_blog_option( $blog_id, 'stylesheet', '' );
		$template   = (string) get_blog_option( $blog_id, 'template', '' );
		$themes     = $this->themes();
		$installed  = $this->plugins();
		$plugins    = array();

		foreach ( $installed as $file => $row ) {
			$row['state'] = $row['network_active'] ? 'network' : ( in_array( $file, $active, true ) ? 'active' : 'inactive' );
			$plugins[]    = $row;
		}

		// Active plugins whose files are gone are listed too, so they can be deactivated.
		foreach ( array_diff( array_map( 'strval', $active ), array_keys( $installed ) ) as $missing ) {
			$plugins[] = array(
				'file'           => $missing,
				'name'           => $missing,
				'version'        => '',
				'author'         => '',
				'network_only'   => false,
				'network_active' => false,
				'sites'          => 0,
				'auto_update'    => false,
				'update'         => null,
				'state'          => 'missing',
			);
		}

		// Active first, then network, then the rest; alphabetical within.
		$rank = array(
			'missing'  => 0,
			'active'   => 1,
			'network'  => 2,
			'inactive' => 3,
		);
		usort(
			$plugins,
			static fn( $a, $b ) => array( $rank[ $a['state'] ], strtolower( $a['name'] ) ) <=> array( $rank[ $b['state'] ], strtolower( $b['name'] ) )
		);

		return array(
			'plugins'    => $plugins,
			'theme'      => $themes[ $stylesheet ] ?? ( '' !== $stylesheet ? array( 'stylesheet' => $stylesheet, 'missing' => true ) : null ),
			'parent'     => $template && $template !== $stylesheet ? ( $themes[ $template ] ?? array( 'stylesheet' => $template, 'missing' => true ) ) : null,
		);
	}

	/**
	 * Normalised update offer.
	 *
	 * @param array<string,mixed> $offer Raw offer from the transient.
	 * @return array{new_version: string, requires: string, requires_php: string, tested: string, has_package: bool, compatible: bool}
	 */
	private function offer( array $offer ): array {
		$requires     = (string) ( $offer['requires'] ?? '' );
		$requires_php = (string) ( $offer['requires_php'] ?? '' );
		return array(
			'new_version'  => (string) ( $offer['new_version'] ?? '' ),
			'requires'     => $requires,
			'requires_php' => $requires_php,
			'tested'       => (string) ( $offer['tested'] ?? '' ),
			'has_package'  => ! empty( $offer['package'] ),
			'compatible'   => ( '' === $requires || is_wp_version_compatible( $requires ) ) && ( '' === $requires_php || is_php_version_compatible( $requires_php ) ),
		);
	}

	/**
	 * Usage counts from the inventory. Not cached: operations in the same
	 * request change them.
	 *
	 * @return array<string,array<string,int>>
	 */
	private function usage(): array {
		return $this->sites->extension_usage();
	}
}
