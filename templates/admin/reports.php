<?php
/**
 * Network report template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by ReportsPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\Admin;
use FleetManager\Admin\ReportsPage;
use FleetManager\Admin\View;
use FleetManager\Health\Health;
use FleetManager\Operations\Operation;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

$wpfr_summary = $vars['summary'];
$wpfr_health  = $wpfr_summary['health'];
$wpfr_updates = $wpfr_summary['updates'];
$wpfr_sites   = (int) $wpfr_summary['sites']['total'];
$wpfr_change  = $vars['change'];

$wpfr_tiles = array(
	array(
		'label'   => __( 'Total sites', 'multisite-fleet-manager' ),
		'value'   => $wpfr_sites,
		'icon'    => 'dashicons-networking',
		'accent'  => 'all',
		'url'     => $vars['can_sites'] ? Admin::url( Admin::SLUG_CONSOLE ) : '',
		'sub'     => sprintf(
			/* translators: %s: Number of active sites. */
			__( '%s active', 'multisite-fleet-manager' ),
			View::number( (int) $wpfr_summary['sites']['active'] )
		),
		'delta'   => $wpfr_change['sites'] ?? null,
	),
	array(
		'label'   => __( 'Sites needing updates', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfr_updates['sites_needing_updates'],
		'icon'    => 'dashicons-update',
		'accent'  => 'attention',
		'url'     => $vars['can_sites'] ? Admin::url( Admin::SLUG_CONSOLE, array( 'updates' => 'any' ) ) : '',
		'sub'     => sprintf(
			/* translators: %s: Percentage. */
			__( '%s%% of fleet', 'multisite-fleet-manager' ),
			View::number( ReportsPage::percent( (int) $wpfr_updates['sites_needing_updates'], $wpfr_sites ) )
		),
		'delta'   => $wpfr_change['sites_needing_updates'] ?? null,
	),
	array(
		'label'   => __( 'Sites with errors', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfr_summary['errors']['sites_with_errors'],
		'icon'    => 'dashicons-dismiss',
		'accent'  => 'error',
		'url'     => $vars['can_sites'] ? Admin::url( Admin::SLUG_CONSOLE, array( 'health' => Health::ERROR ) ) : '',
		'sub'     => sprintf(
			/* translators: %s: Percentage. */
			__( '%s%% of fleet', 'multisite-fleet-manager' ),
			View::number( ReportsPage::percent( (int) $wpfr_summary['errors']['sites_with_errors'], $wpfr_sites ) )
		),
		'delta'   => $wpfr_change['error'] ?? null,
	),
	array(
		'label'   => __( 'Plugin updates', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfr_updates['plugin_updates'],
		'icon'    => 'dashicons-admin-plugins',
		'accent'  => 'plugins',
		'url'     => Admin::url( Admin::SLUG_EXTENSIONS, array( 'filter' => 'update' ) ),
		'sub'     => sprintf(
			/* translators: %s: Number of sites. */
			_n( 'on %s site', 'on %s sites', (int) $wpfr_updates['sites_with_plugin_updates'], 'multisite-fleet-manager' ),
			View::number( (int) $wpfr_updates['sites_with_plugin_updates'] )
		),
		'delta'   => $wpfr_change['plugin_updates'] ?? null,
	),
	array(
		'label'   => __( 'Theme updates', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfr_updates['theme_updates'],
		'icon'    => 'dashicons-admin-appearance',
		'accent'  => 'themes',
		'url'     => Admin::url( Admin::SLUG_EXTENSIONS, array( 'tab' => 'themes', 'filter' => 'update' ) ),
		'sub'     => sprintf(
			/* translators: %s: Number of sites. */
			_n( 'on %s site', 'on %s sites', (int) $wpfr_updates['sites_with_theme_updates'], 'multisite-fleet-manager' ),
			View::number( (int) $wpfr_updates['sites_with_theme_updates'] )
		),
		'delta'   => $wpfr_change['theme_updates'] ?? null,
	),
	array(
		'label'   => __( 'Healthy', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfr_health['healthy'],
		'icon'    => 'dashicons-yes-alt',
		'accent'  => 'healthy',
		'url'     => $vars['can_sites'] ? Admin::url( Admin::SLUG_CONSOLE, array( 'health' => Health::HEALTHY ) ) : '',
		'sub'     => sprintf(
			/* translators: %s: Percentage. */
			__( '%s%% of fleet', 'multisite-fleet-manager' ),
			View::number( ReportsPage::percent( (int) $wpfr_health['healthy'], $wpfr_sites ) )
		),
		'delta'   => $wpfr_change['healthy'] ?? null,
	),
);
?>
<div class="wrap wpfleet wpfc wpfr">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Network Report', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head" aria-labelledby="wpfr-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( $wpfr_summary['network']['name'] ); ?></p>
				<h2 id="wpfr-title"><?php esc_html_e( 'Network Report', 'multisite-fleet-manager' ); ?></h2>
				<ul class="wpfc-meta">
					<li>
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						<?php
						/* translators: %s: Date and time. */
						echo esc_html( sprintf( __( 'Generated %s', 'multisite-fleet-manager' ), View::datetime( $wpfr_summary['generated_at'] ) ) );
						?>
					</li>
					<li>
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php
						echo $vars['summary']['discovery']
							/* translators: %s: Relative time. */
							? esc_html( sprintf( __( 'Inventory synced %s', 'multisite-fleet-manager' ), View::ago( $vars['summary']['discovery']['finished_at'] ) ) )
							: esc_html__( 'Inventory not synced yet', 'multisite-fleet-manager' );
						?>
					</li>
					<li>
						<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
						<?php
						/* translators: %s: Number of snapshots. */
						echo esc_html( sprintf( _n( '%s snapshot stored', '%s snapshots stored', (int) $vars['total_snapshots'], 'multisite-fleet-manager' ), View::number( (int) $vars['total_snapshots'] ) ) );
						?>
					</li>
				</ul>
			</div>
			<div class="wpfc-head__actions">
				<a class="button wpfc-button-ghost" href="<?php echo esc_url( ReportsPage::url( array( 'view' => 'print' ) ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Printable report', 'multisite-fleet-manager' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'multisite-fleet-manager' ); ?></span>
				</a>
				<?php if ( $vars['can_capture'] ) : ?>
					<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>">
						<input type="hidden" name="return" value="<?php echo esc_url( ReportsPage::url() ); ?>" />
						<input type="hidden" name="nonce[<?php echo esc_attr( Operation::SNAPSHOT_CAPTURE ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( Operation::SNAPSHOT_CAPTURE ) ) ); ?>" />
						<button type="submit" class="button button-primary" name="single" value="<?php echo esc_attr( Operation::SNAPSHOT_CAPTURE . '|snapshot' ); ?>"><?php esc_html_e( 'Capture snapshot', 'multisite-fleet-manager' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<ul class="wpfc-tiles wpfr-tiles">
			<?php foreach ( $wpfr_tiles as $wpfr_tile ) : ?>
				<li class="wpfc-tile wpfc-tile--<?php echo esc_attr( $wpfr_tile['accent'] ); ?>">
					<?php if ( $wpfr_tile['url'] ) : ?>
						<a href="<?php echo esc_url( $wpfr_tile['url'] ); ?>">
					<?php else : ?>
						<span class="wpfh-tile-static">
					<?php endif; ?>
						<span class="wpfc-tile__label"><span class="dashicons <?php echo esc_attr( $wpfr_tile['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $wpfr_tile['label'] ); ?></span>
						<span class="wpfc-tile__value">
							<?php echo esc_html( View::number( (int) $wpfr_tile['value'] ) ); ?>
							<?php if ( null !== $wpfr_tile['delta'] && 0 !== (int) $wpfr_tile['delta'] ) : ?>
								<span class="wpfr-delta wpfr-delta--<?php echo (int) $wpfr_tile['delta'] > 0 ? 'up' : 'down'; ?>">
									<?php echo esc_html( ( (int) $wpfr_tile['delta'] > 0 ? '+' : '−' ) . View::number( abs( (int) $wpfr_tile['delta'] ) ) ); ?>
									<span class="screen-reader-text"><?php esc_html_e( 'since the previous snapshot', 'multisite-fleet-manager' ); ?></span>
								</span>
							<?php endif; ?>
						</span>
						<span class="wpfc-tile__sub"><?php echo esc_html( $wpfr_tile['sub'] ); ?></span>
					<?php if ( $wpfr_tile['url'] ) : ?>
						</a>
					<?php else : ?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php View::render( 'admin/partials/operation-results', $vars ); ?>

	<div class="wpfc-panel wpfr-exports">
		<div class="wpfr-exports__text">
			<h2 class="wpfx-panel-title"><?php esc_html_e( 'Export', 'multisite-fleet-manager' ); ?></h2>
			<p class="wpfc-note"><?php esc_html_e( 'CSV opens in any spreadsheet. For a PDF, open the printable report and choose "Save as PDF" in your browser\'s print dialog — no extra software needed.', 'multisite-fleet-manager' ); ?></p>
		</div>
		<p class="wpfr-exports__links">
			<a class="button" href="<?php echo esc_url( Actions::export_url( 'summary' ) ); ?>"><?php esc_html_e( 'Summary CSV', 'multisite-fleet-manager' ); ?></a>
			<a class="button" href="<?php echo esc_url( Actions::export_url( 'sites' ) ); ?>"><?php esc_html_e( 'Sites CSV', 'multisite-fleet-manager' ); ?></a>
			<a class="button" href="<?php echo esc_url( Actions::export_url( 'updates' ) ); ?>"><?php esc_html_e( 'Updates CSV', 'multisite-fleet-manager' ); ?></a>
			<a class="button" href="<?php echo esc_url( Actions::export_url( 'snapshots' ) ); ?>"><?php esc_html_e( 'Snapshots CSV', 'multisite-fleet-manager' ); ?></a>
		</p>
	</div>

	<div class="wpfleet-grid wpfr-grid">
		<section class="wpfleet-panel" aria-labelledby="wpfr-health-title">
			<h2 id="wpfr-health-title"><?php esc_html_e( 'Health distribution', 'multisite-fleet-manager' ); ?></h2>
			<?php View::render( 'admin/partials/health-distribution', array( 'health' => $wpfr_health, 'total' => $wpfr_sites ) ); ?>

			<h3 class="wpfr-subhead"><?php esc_html_e( 'Most common issues', 'multisite-fleet-manager' ); ?></h3>
			<?php if ( ! $wpfr_summary['errors']['top_indicators'] ) : ?>
				<p class="wpfleet-meta"><?php esc_html_e( 'No indicator is failing on any site.', 'multisite-fleet-manager' ); ?></p>
			<?php else : ?>
				<ul class="wpfr-issues">
					<?php foreach ( $wpfr_summary['errors']['top_indicators'] as $wpfr_issue ) : ?>
						<li>
							<span class="wpfr-issues__label"><?php echo esc_html( $wpfr_issue['label'] ); ?></span>
							<span class="wpfr-issues__counts">
								<?php if ( $wpfr_issue['error'] ) : ?>
									<span class="wpfc-count-badge has-updates"><?php echo esc_html( View::number( $wpfr_issue['error'] ) ); ?></span>
									<span class="wpfc-note"><?php esc_html_e( 'error', 'multisite-fleet-manager' ); ?></span>
								<?php endif; ?>
								<?php if ( $wpfr_issue['warning'] ) : ?>
									<span class="wpfc-count-badge"><?php echo esc_html( View::number( $wpfr_issue['warning'] ) ); ?></span>
									<span class="wpfc-note"><?php esc_html_e( 'needs attention', 'multisite-fleet-manager' ); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="wpfleet-panel" aria-labelledby="wpfr-trend-title">
			<h2 id="wpfr-trend-title"><?php esc_html_e( 'Trend', 'multisite-fleet-manager' ); ?></h2>
			<?php if ( count( $vars['series'] ) < 2 ) : ?>
				<p class="wpfleet-meta"><?php esc_html_e( 'A trend appears once two snapshots have been captured. One is taken automatically after discovery, at most twice a day.', 'multisite-fleet-manager' ); ?></p>
			<?php else : ?>
				<?php
				$wpfr_lines = array(
					array( 'sites_needing_updates', __( 'Sites needing updates', 'multisite-fleet-manager' ), '#dba617' ),
					array( 'error', __( 'Sites with errors', 'multisite-fleet-manager' ), '#b32d2e' ),
					array( 'healthy', __( 'Healthy sites', 'multisite-fleet-manager' ), '#007a3d' ),
				);
				?>
				<?php foreach ( $wpfr_lines as $wpfr_line ) : ?>
					<?php
					$wpfr_first = (int) $vars['series'][0][ $wpfr_line[0] ];
					$wpfr_last  = (int) end( $vars['series'] )[ $wpfr_line[0] ];
					?>
					<div class="wpfr-trend">
						<div class="wpfr-trend__head">
							<span class="wpfr-trend__label"><?php echo esc_html( $wpfr_line[1] ); ?></span>
							<span class="wpfr-trend__value">
								<?php echo esc_html( View::number( $wpfr_last ) ); ?>
								<span class="wpfc-note">
									<?php
									/* translators: %s: Earlier value. */
									echo esc_html( sprintf( __( 'was %s', 'multisite-fleet-manager' ), View::number( $wpfr_first ) ) );
									?>
								</span>
							</span>
						</div>
						<?php echo ReportsPage::sparkline( $vars['series'], $wpfr_line[0], $wpfr_line[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from integers and a fixed colour. ?>
					</div>
				<?php endforeach; ?>
				<p class="wpfc-note">
					<?php
					/* translators: 1: Number of snapshots, 2: Date. */
					echo esc_html( sprintf( __( 'From %1$s snapshots since %2$s.', 'multisite-fleet-manager' ), View::number( count( $vars['series'] ) ), View::date( $vars['series'][0]['captured_at'] ) ) );
					?>
				</p>
			<?php endif; ?>
		</section>
	</div>

	<section class="wpfc-panel wpfr-panel" aria-labelledby="wpfr-updates-title">
		<div class="wpfc-summary">
			<h2 id="wpfr-updates-title" class="wpfx-panel-title"><?php esc_html_e( 'Pending updates', 'multisite-fleet-manager' ); ?></h2>
			<p class="wpfc-count">
				<?php
				/* translators: %s: Number of updates. */
				echo esc_html( sprintf( _n( '%s item has an update', '%s items have updates', count( $vars['updates'] ), 'multisite-fleet-manager' ), View::number( count( $vars['updates'] ) ) ) );
				?>
			</p>
		</div>
		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Item', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Installed', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Available', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sites affected', 'multisite-fleet-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $vars['updates'] ) : ?>
						<tr class="wpfc-empty-row"><td colspan="5"><?php esc_html_e( 'Everything is up to date.', 'multisite-fleet-manager' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $vars['updates'] as $wpfr_update ) : ?>
						<tr class="wpfc-row">
							<td data-label="<?php esc_attr_e( 'Item', 'multisite-fleet-manager' ); ?>">
								<span class="wpfc-site">
									<strong><?php echo esc_html( $wpfr_update['name'] ); ?></strong>
									<span class="wpfc-site__address wpfc-mono"><?php echo esc_html( $wpfr_update['slug'] ); ?></span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Type', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( 'plugin' === $wpfr_update['type'] ? __( 'Plugin', 'multisite-fleet-manager' ) : __( 'Theme', 'multisite-fleet-manager' ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Installed', 'multisite-fleet-manager' ); ?>" class="wpfc-mono"><?php echo esc_html( $wpfr_update['version'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'Available', 'multisite-fleet-manager' ); ?>"><span class="wpfc-count-badge has-updates wpfc-mono"><?php echo esc_html( $wpfr_update['new_version'] ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Sites affected', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( View::number( (int) $wpfr_update['sites'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="wpfc-panel wpfr-panel" aria-labelledby="wpfr-snapshots-title">
		<div class="wpfc-summary">
			<h2 id="wpfr-snapshots-title" class="wpfx-panel-title"><?php esc_html_e( 'Recent snapshots', 'multisite-fleet-manager' ); ?></h2>
			<p class="wpfc-count"><?php esc_html_e( 'Captured automatically after discovery, and on request.', 'multisite-fleet-manager' ); ?></p>
		</div>
		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Captured', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Sites', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Healthy', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Attention', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Error', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Needing updates', 'multisite-fleet-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $vars['snapshots'] ) : ?>
						<tr class="wpfc-empty-row"><td colspan="7"><?php esc_html_e( 'No snapshots yet.', 'multisite-fleet-manager' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $vars['snapshots'] as $wpfr_snapshot ) : ?>
						<tr class="wpfc-row">
							<td data-label="<?php esc_attr_e( 'Captured', 'multisite-fleet-manager' ); ?>">
								<span title="<?php echo esc_attr( View::datetime( $wpfr_snapshot['captured_at'] ) ); ?>"><?php echo esc_html( View::datetime( $wpfr_snapshot['captured_at'] ) ); ?></span>
							</td>
							<td data-label="<?php esc_attr_e( 'Source', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( 'manual' === $wpfr_snapshot['source'] ? __( 'On request', 'multisite-fleet-manager' ) : __( 'Automatic', 'multisite-fleet-manager' ) ); ?></td>
							<td class="wpfc-num"><?php echo esc_html( View::number( $wpfr_snapshot['sites'] ) ); ?></td>
							<td class="wpfc-num"><?php echo esc_html( View::number( $wpfr_snapshot['healthy'] ) ); ?></td>
							<td class="wpfc-num"><?php echo esc_html( View::number( $wpfr_snapshot['attention'] ) ); ?></td>
							<td class="wpfc-num"><?php echo esc_html( View::number( $wpfr_snapshot['error'] ) ); ?></td>
							<td class="wpfc-num"><?php echo esc_html( View::number( $wpfr_snapshot['sites_needing_updates'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
