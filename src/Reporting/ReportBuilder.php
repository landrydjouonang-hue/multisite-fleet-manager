<?php
/**
 * Network report model.
 *
 * @package FleetManager
 */

namespace FleetManager\Reporting;

use FleetManager\Extensions\ExtensionInventory;
use FleetManager\Health\Health;
use FleetManager\Health\HealthCheckRegistry;
use FleetManager\Health\HealthEvaluator;
use FleetManager\Network\NetworkInfo;
use FleetManager\Sites\SiteDiscovery;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the figures every report view shares: the screen, the printable
 * version, the CSV exports, the REST payload and the stored snapshots.
 *
 * Everything comes from the stored inventory, so a report is cheap and
 * reflects the last discovery rather than hitting every site again.
 */
final class ReportBuilder {

	private SiteRepository $sites;

	private ExtensionInventory $extensions;

	private NetworkInfo $network;

	private SiteDiscovery $discovery;

	private HealthCheckRegistry $checks;

	private HealthEvaluator $health;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository      $sites      Sites.
	 * @param ExtensionInventory  $extensions Plugins and themes.
	 * @param NetworkInfo         $network    Network facts.
	 * @param SiteDiscovery       $discovery  Discovery.
	 * @param HealthCheckRegistry $checks     Health checks.
	 * @param HealthEvaluator     $health     Evaluator (for the environment context).
	 */
	public function __construct(
		SiteRepository $sites,
		ExtensionInventory $extensions,
		NetworkInfo $network,
		SiteDiscovery $discovery,
		HealthCheckRegistry $checks,
		HealthEvaluator $health
	) {
		$this->sites      = $sites;
		$this->extensions = $extensions;
		$this->network    = $network;
		$this->discovery  = $discovery;
		$this->checks     = $checks;
		$this->health     = $health;
	}

	/**
	 * The headline report.
	 *
	 * @return array<string,mixed>
	 */
	public function summary(): array {
		$status  = $this->sites->count_by_status();
		$health  = $this->sites->count_by_health();
		$updates = $this->sites->update_summary();
		$totals  = $this->sites->totals();
		$fleet   = $this->sites->health_summary();
		$context = $this->health->context();

		$needing = $this->sites->query( array( 'updates' => 'any', 'per_page' => 1 ) )['total'];
		$errors  = $this->sites->query( array( 'health' => Health::ERROR, 'per_page' => 1 ) )['total'];

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'network'      => array(
				'name'         => (string) ( get_network()->site_name ?? '' ),
				'address'      => untrailingslashit( ( get_network()->domain ?? '' ) . ( get_network()->path ?? '' ) ),
				'wp_version'   => $context->wp_version,
				'core_update'  => $context->core_update,
				'php_version'  => $context->php_version,
				'db_server'    => $context->db_server,
				'is_mariadb'   => $context->is_mariadb,
				'install_type' => $this->network->summary()['install_type'],
				'users'        => (int) get_user_count(),
			),
			'sites'        => array(
				'total'    => (int) $status['total'],
				'active'   => (int) $status[ SiteStatus::ACTIVE ],
				'archived' => (int) $status[ SiteStatus::ARCHIVED ],
				'spam'     => (int) $status[ SiteStatus::SPAM ],
				'deleted'  => (int) $status[ SiteStatus::DELETED ],
			),
			'health'       => array(
				'healthy'   => (int) $health[ Health::HEALTHY ],
				'attention' => (int) $health[ Health::ATTENTION ],
				'error'     => (int) $health[ Health::ERROR ],
				'unchecked' => (int) $health['unchecked'],
			),
			'updates'      => array(
				'plugin_updates'            => (int) $updates['plugin_updates'],
				'theme_updates'             => (int) $updates['theme_updates'],
				'sites_with_plugin_updates' => (int) $updates['sites_with_plugin_updates'],
				'sites_with_theme_updates'  => (int) $updates['sites_with_theme_updates'],
				'sites_needing_updates'     => (int) $needing,
				'sites_unknown'             => (int) $updates['unknown'],
			),
			'errors'       => array(
				'sites_with_errors' => (int) $errors,
				'top_indicators'    => $this->top_issues( $fleet['indicators'] ),
			),
			'indicators'   => $fleet['indicators'],
			'environment'  => array(
				'https_sites'     => (int) $fleet['totals']['https'],
				'cron_overdue'    => (int) $fleet['totals']['cron_overdue'],
				'autoload_bytes'  => (int) $fleet['totals']['autoload'],
				'database_bytes'  => $fleet['totals']['db_size'],
				'upload_bytes'    => (int) $fleet['totals']['uploads'],
				'disk_free'       => $context->disk_free,
				'disk_total'      => $context->disk_total,
				'posts'           => (int) $totals['posts'],
				'pages'           => (int) $totals['pages'],
				'network_plugins' => (int) $this->network->summary()['network_plugins'],
			),
			'discovery'    => $this->discovery->last(),
			'scope'        => __( 'Operational reporting from the Fleet Manager inventory. The health indicators are not a security scan.', 'multisite-fleet-manager' ),
		);
	}

	/**
	 * Plugins and themes with an update, and how many sites run them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function pending_updates(): array {
		$rows = array();

		foreach ( $this->extensions->plugins() as $file => $plugin ) {
			if ( $plugin['update'] ) {
				$rows[] = array(
					'type'        => 'plugin',
					'slug'        => $file,
					'name'        => $plugin['name'],
					'version'     => $plugin['version'],
					'new_version' => $plugin['update']['new_version'],
					'sites'       => (int) $plugin['sites'],
					'installable' => (bool) ( $plugin['update']['has_package'] && $plugin['update']['compatible'] ),
				);
			}
		}

		foreach ( $this->extensions->themes() as $stylesheet => $theme ) {
			if ( $theme['update'] ) {
				$rows[] = array(
					'type'        => 'theme',
					'slug'        => $stylesheet,
					'name'        => $theme['name'],
					'version'     => $theme['version'],
					'new_version' => $theme['update']['new_version'],
					'sites'       => (int) ( $theme['sites'] + $theme['parent_of'] ),
					'installable' => (bool) ( $theme['update']['has_package'] && $theme['update']['compatible'] ),
				);
			}
		}

		usort( $rows, static fn( $a, $b ) => array( $b['sites'], $a['name'] ) <=> array( $a['sites'], $b['name'] ) );

		return $rows;
	}

	/**
	 * One row per site, for the table, the printable report and the CSV.
	 *
	 * @param int $limit Maximum rows (0 for all).
	 * @return array<int,array<string,mixed>>
	 */
	public function site_rows( int $limit = 0 ): array {
		$rows  = array();
		$after = 0;

		do {
			$chunk = $this->sites->chunk( $after, 500 );
			foreach ( $chunk as $id => $site ) {
				$after      = $id;
				$states     = array_column( $site->health_indicators, 'state', 'id' );
				$issues     = array_keys( array_filter( $states, static fn( $s ) => in_array( $s, array( 'warning', 'error' ), true ) ) );
				$rows[]     = array(
					'blog_id'        => $site->blog_id,
					'name'           => $site->label(),
					'address'        => untrailingslashit( $site->domain . $site->path ),
					'status'         => $site->status,
					'health'         => '' !== $site->health ? $site->health : 'unchecked',
					'plugin_updates' => $site->plugin_updates,
					'theme_updates'  => $site->theme_updates,
					'theme'          => '' !== $site->theme_name ? $site->theme_name : $site->theme_stylesheet,
					'https'          => 0 === strpos( strtolower( $site->home_url ), 'https://' ),
					'users'          => $site->user_count,
					'posts'          => $site->post_count,
					'pages'          => $site->page_count,
					'cron_overdue'   => $site->cron_overdue,
					'autoload_bytes' => $site->autoload_bytes,
					'db_size_bytes'  => $site->db_size_bytes,
					'issues'         => $issues,
					'synced_at'      => $site->synced_at,
				);
				if ( $limit && count( $rows ) >= $limit ) {
					return $rows;
				}
			}
		} while ( count( $chunk ) === 500 );

		return $rows;
	}

	/**
	 * The indicators failing on the most sites.
	 *
	 * @param array<string,array<string,int>> $indicators Counts per indicator.
	 * @param int                             $limit      How many to return.
	 * @return array<int,array{id: string, label: string, error: int, warning: int}>
	 */
	private function top_issues( array $indicators, int $limit = 5 ): array {
		$rows = array();
		foreach ( $this->checks->all() as $id => $check ) {
			$error   = (int) ( $indicators[ $id ]['error'] ?? 0 );
			$warning = (int) ( $indicators[ $id ]['warning'] ?? 0 );
			if ( $error || $warning ) {
				$rows[] = array(
					'id'      => $id,
					'label'   => $check->label(),
					'error'   => $error,
					'warning' => $warning,
				);
			}
		}

		usort( $rows, static fn( $a, $b ) => array( $b['error'], $b['warning'] ) <=> array( $a['error'], $a['warning'] ) );

		return array_slice( $rows, 0, $limit );
	}
}
