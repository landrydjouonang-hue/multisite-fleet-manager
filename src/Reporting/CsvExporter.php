<?php
/**
 * CSV exports.
 *
 * @package FleetManager
 */

namespace FleetManager\Reporting;

use FleetManager\Health\Health;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Streams report data as CSV.
 *
 * Values are escaped against spreadsheet formula injection: site names,
 * plugin names and messages come from the network, and a value starting
 * with =, +, - or @ would otherwise be run as a formula when the file is
 * opened. A UTF-8 BOM is written so Excel reads accented names correctly.
 */
final class CsvExporter {

	public const TYPES = array( 'summary', 'sites', 'updates', 'snapshots', 'users', 'operations' );

	private ReportBuilder $reports;

	private SnapshotRepository $snapshots;

	private \FleetManager\Users\UserDirectory $users;

	private \FleetManager\Operations\OperationLog $log;

	/**
	 * Constructor.
	 *
	 * @param ReportBuilder                              $reports   Report builder.
	 * @param SnapshotRepository                         $snapshots Snapshots.
	 * @param \FleetManager\Users\UserDirectory          $users     User directory.
	 * @param \FleetManager\Operations\OperationLog      $log       Operation log.
	 */
	public function __construct( ReportBuilder $reports, SnapshotRepository $snapshots, \FleetManager\Users\UserDirectory $users, \FleetManager\Operations\OperationLog $log ) {
		$this->reports   = $reports;
		$this->snapshots = $snapshots;
		$this->users     = $users;
		$this->log       = $log;
	}

	/**
	 * Rows for one export type: the first row is the header.
	 *
	 * @param string $type One of self::TYPES.
	 * @return array<int,array<int,string|int|null>>
	 */
	public function rows( string $type ): array {
		switch ( $type ) {
			case 'sites':
				return $this->sites_rows();
			case 'updates':
				return $this->update_rows();
			case 'snapshots':
				return $this->snapshot_rows();
			case 'users':
				return $this->user_rows();
			case 'operations':
				return $this->operation_rows();
			default:
				return $this->summary_rows();
		}
	}

	/**
	 * Suggested file name for an export.
	 *
	 * @param string $type Export type.
	 * @return string
	 */
	public function filename( string $type ): string {
		$network = sanitize_title( (string) ( get_network()->site_name ?? 'network' ) );
		$type    = in_array( $type, self::TYPES, true ) ? $type : 'summary';
		return sprintf( 'fleet-%s-%s-%s.csv', $network ?: 'network', $type, gmdate( 'Y-m-d' ) );
	}

	/**
	 * Streams a CSV to the browser. Caller handles permissions and nonces.
	 *
	 * @param string $type Export type.
	 * @return void
	 */
	public function stream( string $type ): void {
		$type = in_array( $type, self::TYPES, true ) ? $type : 'summary';
		$rows = $this->rows( $type );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $this->filename( $type ) . '"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for spreadsheet apps.
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( self::class, 'escape' ), $row ) );
		}
		fclose( $out );
	}

	/**
	 * One row per network user: roles, sites and last activity.
	 *
	 * @return array<int,array<int,string|int|null>>
	 */
	private function user_rows(): array {
		$rows = array(
			array(
				__( 'User ID', 'multisite-fleet-manager' ),
				__( 'Login', 'multisite-fleet-manager' ),
				__( 'Name', 'multisite-fleet-manager' ),
				__( 'Super admin', 'multisite-fleet-manager' ),
				__( 'Sites', 'multisite-fleet-manager' ),
				__( 'Roles', 'multisite-fleet-manager' ),
				__( 'Last activity (UTC)', 'multisite-fleet-manager' ),
				__( 'Registered (UTC)', 'multisite-fleet-manager' ),
			),
		);

		$page = 1;
		do {
			$result = $this->users->query(
				array(
					'per_page' => 200,
					'page'     => $page,
				)
			);
			foreach ( $result['items'] as $user ) {
				$roles = array();
				foreach ( $user['roles'] as $role => $count ) {
					$roles[] = $role . ' x' . $count;
				}
				$rows[] = array(
					$user['id'],
					$user['login'],
					$user['name'],
					$user['super_admin'] ? __( 'Yes', 'multisite-fleet-manager' ) : __( 'No', 'multisite-fleet-manager' ),
					$user['site_count'],
					implode( ' ', $roles ),
					$user['activity']['last_login'] ? gmdate( 'Y-m-d H:i:s', (int) $user['activity']['last_login'] ) : '',
					$user['registered'],
				);
			}
			++$page;
		} while ( count( $result['items'] ) === 200 );

		return $rows;
	}

	/**
	 * One row per logged operation, newest first.
	 *
	 * @return array<int,array<int,string|int|null>>
	 */
	private function operation_rows(): array {
		$rows = array(
			array(
				__( 'When (UTC)', 'multisite-fleet-manager' ),
				__( 'Operation', 'multisite-fleet-manager' ),
				__( 'Result', 'multisite-fleet-manager' ),
				__( 'Target', 'multisite-fleet-manager' ),
				__( 'Site ID', 'multisite-fleet-manager' ),
				__( 'From', 'multisite-fleet-manager' ),
				__( 'To', 'multisite-fleet-manager' ),
				__( 'User', 'multisite-fleet-manager' ),
				__( 'Message', 'multisite-fleet-manager' ),
			),
		);

		$page = 1;
		do {
			$result = $this->log->query(
				array(
					'per_page' => 200,
					'page'     => $page,
				)
			);
			foreach ( $result['items'] as $entry ) {
				$user   = $entry['user_id'] ? get_userdata( (int) $entry['user_id'] ) : false;
				$rows[] = array(
					$entry['created_at'],
					\FleetManager\Operations\Operation::label( (string) $entry['operation'] ),
					\FleetManager\Operations\Operation::status_label( (string) $entry['status'] ),
					$entry['target_name'] ?: $entry['target'],
					$entry['site_id'],
					$entry['from_version'],
					$entry['to_version'],
					$user ? $user->user_login : '',
					$entry['message'],
				);
			}
			++$page;
		} while ( count( $result['items'] ) === 200 );

		return $rows;
	}

	/**
	 * Neutralises values a spreadsheet would treat as a formula.
	 *
	 * @param string|int|float|null $value Value.
	 * @return string
	 */
	public static function escape( $value ): string {
		if ( null === $value ) {
			return '';
		}
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Key figures, one metric per row.
	 *
	 * @return array<int,array<int,string|int|null>>
	 */
	private function summary_rows(): array {
		$summary = $this->reports->summary();

		$rows = array( array( __( 'Metric', 'multisite-fleet-manager' ), __( 'Value', 'multisite-fleet-manager' ) ) );

		$metrics = array(
			__( 'Report generated (UTC)', 'multisite-fleet-manager' ) => $summary['generated_at'],
			__( 'Network', 'multisite-fleet-manager' )                => $summary['network']['name'],
			__( 'WordPress version', 'multisite-fleet-manager' )      => $summary['network']['wp_version'],
			__( 'PHP version', 'multisite-fleet-manager' )            => $summary['network']['php_version'],
			__( 'Total sites', 'multisite-fleet-manager' )            => $summary['sites']['total'],
			__( 'Active sites', 'multisite-fleet-manager' )           => $summary['sites']['active'],
			__( 'Archived sites', 'multisite-fleet-manager' )         => $summary['sites']['archived'],
			__( 'Spam sites', 'multisite-fleet-manager' )             => $summary['sites']['spam'],
			__( 'Deactivated sites', 'multisite-fleet-manager' )      => $summary['sites']['deleted'],
			__( 'Healthy', 'multisite-fleet-manager' )                => $summary['health']['healthy'],
			__( 'Needs attention', 'multisite-fleet-manager' )        => $summary['health']['attention'],
			__( 'Error', 'multisite-fleet-manager' )                  => $summary['health']['error'],
			__( 'Not checked', 'multisite-fleet-manager' )            => $summary['health']['unchecked'],
			__( 'Sites needing updates', 'multisite-fleet-manager' )  => $summary['updates']['sites_needing_updates'],
			__( 'Sites with errors', 'multisite-fleet-manager' )      => $summary['errors']['sites_with_errors'],
			__( 'Plugin updates', 'multisite-fleet-manager' )         => $summary['updates']['plugin_updates'],
			__( 'Theme updates', 'multisite-fleet-manager' )          => $summary['updates']['theme_updates'],
			__( 'Sites on HTTPS', 'multisite-fleet-manager' )         => $summary['environment']['https_sites'],
			__( 'Sites with overdue tasks', 'multisite-fleet-manager' ) => $summary['environment']['cron_overdue'],
		);

		foreach ( $metrics as $label => $value ) {
			$rows[] = array( $label, $value );
		}

		foreach ( $summary['errors']['top_indicators'] as $indicator ) {
			$rows[] = array(
				/* translators: %s: Indicator name. */
				sprintf( __( 'Indicator: %s (errors / needs attention)', 'multisite-fleet-manager' ), $indicator['label'] ),
				$indicator['error'] . ' / ' . $indicator['warning'],
			);
		}

		return $rows;
	}

	/**
	 * One row per site.
	 *
	 * @return array<int,array<int,string|int|null>>
	 */
	private function sites_rows(): array {
		$rows = array(
			array(
				__( 'Site ID', 'multisite-fleet-manager' ),
				__( 'Name', 'multisite-fleet-manager' ),
				__( 'Address', 'multisite-fleet-manager' ),
				__( 'Status', 'multisite-fleet-manager' ),
				__( 'Health', 'multisite-fleet-manager' ),
				__( 'Plugin updates', 'multisite-fleet-manager' ),
				__( 'Theme updates', 'multisite-fleet-manager' ),
				__( 'Active theme', 'multisite-fleet-manager' ),
				__( 'HTTPS', 'multisite-fleet-manager' ),
				__( 'Users', 'multisite-fleet-manager' ),
				__( 'Posts', 'multisite-fleet-manager' ),
				__( 'Pages', 'multisite-fleet-manager' ),
				__( 'Overdue tasks', 'multisite-fleet-manager' ),
				__( 'Autoloaded options (bytes)', 'multisite-fleet-manager' ),
				__( 'Database size (bytes)', 'multisite-fleet-manager' ),
				__( 'Issues', 'multisite-fleet-manager' ),
				__( 'Last synced (UTC)', 'multisite-fleet-manager' ),
			),
		);

		foreach ( $this->reports->site_rows() as $site ) {
			$rows[] = array(
				$site['blog_id'],
				$site['name'],
				$site['address'],
				SiteStatus::label( $site['status'] ),
				Health::label( 'unchecked' === $site['health'] ? '' : $site['health'] ),
				$site['plugin_updates'],
				$site['theme_updates'],
				$site['theme'],
				$site['https'] ? __( 'Yes', 'multisite-fleet-manager' ) : __( 'No', 'multisite-fleet-manager' ),
				$site['users'],
				$site['posts'],
				$site['pages'],
				$site['cron_overdue'],
				$site['autoload_bytes'],
				$site['db_size_bytes'],
				implode( ' ', $site['issues'] ),
				$site['synced_at'],
			);
		}

		return $rows;
	}

	/**
	 * One row per pending plugin or theme update.
	 *
	 * @return array<int,array<int,string|int|null>>
	 */
	private function update_rows(): array {
		$rows = array(
			array(
				__( 'Type', 'multisite-fleet-manager' ),
				__( 'Name', 'multisite-fleet-manager' ),
				__( 'File', 'multisite-fleet-manager' ),
				__( 'Installed version', 'multisite-fleet-manager' ),
				__( 'Available version', 'multisite-fleet-manager' ),
				__( 'Sites affected', 'multisite-fleet-manager' ),
				__( 'Installable from Fleet Manager', 'multisite-fleet-manager' ),
			),
		);

		foreach ( $this->reports->pending_updates() as $update ) {
			$rows[] = array(
				'plugin' === $update['type'] ? __( 'Plugin', 'multisite-fleet-manager' ) : __( 'Theme', 'multisite-fleet-manager' ),
				$update['name'],
				$update['slug'],
				$update['version'],
				$update['new_version'],
				$update['sites'],
				$update['installable'] ? __( 'Yes', 'multisite-fleet-manager' ) : __( 'No', 'multisite-fleet-manager' ),
			);
		}

		return $rows;
	}

	/**
	 * One row per stored snapshot, oldest first.
	 *
	 * @return array<int,array<int,string|int|null>>
	 */
	private function snapshot_rows(): array {
		$rows = array(
			array(
				__( 'Captured (UTC)', 'multisite-fleet-manager' ),
				__( 'Source', 'multisite-fleet-manager' ),
				__( 'Sites', 'multisite-fleet-manager' ),
				__( 'Healthy', 'multisite-fleet-manager' ),
				__( 'Needs attention', 'multisite-fleet-manager' ),
				__( 'Error', 'multisite-fleet-manager' ),
				__( 'Not checked', 'multisite-fleet-manager' ),
				__( 'Sites needing updates', 'multisite-fleet-manager' ),
				__( 'Plugin updates', 'multisite-fleet-manager' ),
				__( 'Theme updates', 'multisite-fleet-manager' ),
			),
		);

		foreach ( array_reverse( $this->snapshots->latest( 500 ) ) as $snapshot ) {
			$rows[] = array(
				$snapshot['captured_at'],
				$snapshot['source'],
				$snapshot['sites'],
				$snapshot['healthy'],
				$snapshot['attention'],
				$snapshot['error'],
				$snapshot['unchecked'],
				$snapshot['sites_needing_updates'],
				$snapshot['plugin_updates'],
				$snapshot['theme_updates'],
			);
		}

		return $rows;
	}
}
