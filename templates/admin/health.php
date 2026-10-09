<?php
/**
 * Fleet health summary template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by HealthPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\Admin;
use FleetManager\Admin\HealthPage;
use FleetManager\Admin\Notices;
use FleetManager\Admin\View;
use FleetManager\Health\Category;
use FleetManager\Health\Health;
use FleetManager\Health\Indicator;

defined( 'ABSPATH' ) || exit;

$wpfh_health = $vars['health'];
$wpfh_totals = $vars['summary']['totals'];
/** @var FleetManager\Health\FleetContext $wpfh_ctx */
$wpfh_ctx   = $vars['context'];
$wpfh_sites = max( 1, (int) $wpfh_totals['sites'] );

$wpfh_tiles = array(
	array( 'all', __( 'Sites checked', 'multisite-fleet-manager' ), (int) $wpfh_totals['sites'], 'dashicons-networking', null ),
	array( 'healthy', Health::label( Health::HEALTHY ), (int) $wpfh_health[ Health::HEALTHY ], Health::icon( Health::HEALTHY ), Health::HEALTHY ),
	array( 'attention', Health::label( Health::ATTENTION ), (int) $wpfh_health[ Health::ATTENTION ], Health::icon( Health::ATTENTION ), Health::ATTENTION ),
	array( 'error', Health::label( Health::ERROR ), (int) $wpfh_health[ Health::ERROR ], Health::icon( Health::ERROR ), Health::ERROR ),
);
?>
<div class="wrap wpfleet wpfc wpfh">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Fleet Health', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head" aria-labelledby="wpfh-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( get_network()->site_name ); ?></p>
				<h2 id="wpfh-title"><?php esc_html_e( 'Fleet Health', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-meta">
					<?php
					echo $vars['last']
						/* translators: %s: Relative time, e.g. "5 minutes ago". */
						? esc_html( sprintf( __( 'From the inventory synced %s.', 'multisite-fleet-manager' ), View::ago( $vars['last']['finished_at'] ) ) )
						: esc_html__( 'Run site discovery to build these figures.', 'multisite-fleet-manager' );
					?>
				</p>
			</div>
			<div class="wpfc-head__actions">
				<?php if ( $vars['can_sites'] ) : ?>
					<a class="button wpfc-button-ghost" href="<?php echo esc_url( Admin::url( Admin::SLUG_CONSOLE ) ); ?>"><?php esc_html_e( 'Site Dashboard', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
				<?php if ( $vars['can_run'] ) : ?>
					<form id="wpfleet-discovery-form" method="post" action="<?php echo esc_url( Actions::url( Actions::DISCOVER ) ); ?>">
						<?php wp_nonce_field( Actions::DISCOVER ); ?>
						<button type="submit" class="button button-primary" id="wpfleet-discovery-button"><?php esc_html_e( 'Refresh inventory', 'multisite-fleet-manager' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<div class="wpfleet-progress wpfc-progress" id="wpfleet-progress" hidden>
				<progress id="wpfleet-progress-bar" max="100" value="0" aria-labelledby="wpfleet-progress-status"></progress>
				<p class="wpfleet-progress__status" id="wpfleet-progress-status" role="status" aria-live="polite"></p>
			</div>
		</div>

		<ul class="wpfc-tiles wpfh-tiles">
			<?php foreach ( $wpfh_tiles as $wpfh_tile ) : ?>
				<li class="wpfc-tile wpfc-tile--<?php echo esc_attr( $wpfh_tile[0] ); ?>">
					<?php $wpfh_url = $vars['can_sites'] ? Admin::url( Admin::SLUG_CONSOLE, $wpfh_tile[4] ? array( 'health' => $wpfh_tile[4] ) : array() ) : ''; ?>
					<?php if ( $wpfh_url ) : ?>
						<a href="<?php echo esc_url( $wpfh_url ); ?>">
					<?php else : ?>
						<span class="wpfh-tile-static">
					<?php endif; ?>
						<span class="wpfc-tile__label"><span class="dashicons <?php echo esc_attr( $wpfh_tile[3] ); ?>" aria-hidden="true"></span><?php echo esc_html( $wpfh_tile[1] ); ?></span>
						<span class="wpfc-tile__value"><?php echo esc_html( View::number( $wpfh_tile[2] ) ); ?></span>
						<?php if ( $wpfh_tile[4] ) : ?>
							<?php /* translators: %s: Percentage. */ ?>
							<span class="wpfc-tile__sub"><?php echo esc_html( sprintf( __( '%s%% of fleet', 'multisite-fleet-manager' ), View::number( (int) round( $wpfh_tile[2] / $wpfh_sites * 100 ) ) ) ); ?></span>
						<?php endif; ?>
					<?php if ( $wpfh_url ) : ?>
						</a>
					<?php else : ?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php Notices::render(); ?>

	<div class="notice notice-info inline wpfc-notice wpfh-scope">
		<p>
			<strong><?php esc_html_e( 'What these indicators are.', 'multisite-fleet-manager' ); ?></strong>
			<?php esc_html_e( 'Operational health: reliability, performance, configuration and how current the code is, read from the network itself. The security group is a set of configuration indicators only.', 'multisite-fleet-manager' ); ?>
			<?php echo esc_html( Category::security_caveat() ); ?>
		</p>
	</div>

	<div class="wpfc-panel wpfh-panel">
		<div class="wpfc-summary">
			<h2 class="wpfx-panel-title"><?php esc_html_e( 'Indicators across the fleet', 'multisite-fleet-manager' ); ?></h2>
			<p class="wpfc-count"><?php esc_html_e( 'Each row counts the sites in each state.', 'multisite-fleet-manager' ); ?></p>
		</div>

		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Indicator', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Error', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Needs attention', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Passed', 'multisite-fleet-manager' ); ?></th>
						<th scope="col" class="wpfc-num"><?php esc_html_e( 'Unknown', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Coverage', 'multisite-fleet-manager' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $vars['indicators'] as $wpfh_category => $wpfh_rows ) : ?>
				<tbody class="wpfh-group">
					<tr class="wpfh-group__head">
						<th colspan="6" scope="colgroup">
							<span class="dashicons <?php echo esc_attr( Category::icon( $wpfh_category ) ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( Category::label( $wpfh_category ) ); ?>
							<span class="wpfc-note"><?php echo esc_html( Category::description( $wpfh_category ) ); ?></span>
						</th>
					</tr>
					<?php foreach ( $wpfh_rows as $wpfh_row ) : ?>
						<?php
						$wpfh_bad   = $wpfh_row['error'] + $wpfh_row['warning'];
						$wpfh_total = max( 1, $wpfh_row['error'] + $wpfh_row['warning'] + $wpfh_row['good'] + $wpfh_row['unknown'] );
						?>
						<tr class="wpfc-row<?php echo $wpfh_bad ? ' has-issues' : ''; ?>">
							<td data-label="<?php esc_attr_e( 'Indicator', 'multisite-fleet-manager' ); ?>"><strong><?php echo esc_html( $wpfh_row['label'] ); ?></strong></td>
							<?php foreach ( array( 'error', 'warning', 'good', 'unknown' ) as $wpfh_state ) : ?>
								<td class="wpfc-num" data-label="<?php echo esc_attr( View::indicator_state( $wpfh_state ) ); ?>">
									<?php if ( $wpfh_row[ $wpfh_state ] && $vars['can_sites'] && 'good' !== $wpfh_state ) : ?>
										<a class="wpfc-count-badge<?php echo in_array( $wpfh_state, array( 'error', 'warning' ), true ) ? ' has-updates' : ''; ?>" href="<?php echo esc_url( HealthPage::sites_url( $wpfh_row['id'], $wpfh_state ) ); ?>">
											<?php echo esc_html( View::number( $wpfh_row[ $wpfh_state ] ) ); ?>
										</a>
									<?php elseif ( $wpfh_row[ $wpfh_state ] ) : ?>
										<span class="wpfc-count-badge"><?php echo esc_html( View::number( $wpfh_row[ $wpfh_state ] ) ); ?></span>
									<?php else : ?>
										<span class="wpfc-muted">—</span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
							<td data-label="<?php esc_attr_e( 'Coverage', 'multisite-fleet-manager' ); ?>">
								<span class="wpfh-bar" aria-hidden="true">
									<span class="wpfh-bar__error" style="width: <?php echo esc_attr( (string) round( $wpfh_row['error'] / $wpfh_total * 100 ) ); ?>%"></span>
									<span class="wpfh-bar__warning" style="width: <?php echo esc_attr( (string) round( $wpfh_row['warning'] / $wpfh_total * 100 ) ); ?>%"></span>
									<span class="wpfh-bar__good" style="width: <?php echo esc_attr( (string) round( $wpfh_row['good'] / $wpfh_total * 100 ) ); ?>%"></span>
								</span>
								<span class="wpfc-note">
									<?php
									echo $wpfh_bad
										/* translators: %s: Number of sites. */
										? esc_html( sprintf( _n( '%s site needs work', '%s sites need work', $wpfh_bad, 'multisite-fleet-manager' ), View::number( $wpfh_bad ) ) )
										: esc_html__( 'All sites pass', 'multisite-fleet-manager' );
									?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( Category::SECURITY === $wpfh_category ) : ?>
						<tr class="wpfh-group__note">
							<td colspan="6"><?php echo esc_html( Category::security_caveat() ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
				<?php endforeach; ?>
			</table>
		</div>
	</div>

	<div class="wpfleet-grid wpfh-grid">
		<section class="wpfleet-panel" aria-labelledby="wpfh-env-title">
			<h2 id="wpfh-env-title"><?php esc_html_e( 'Shared environment', 'multisite-fleet-manager' ); ?></h2>
			<p class="wpfleet-meta"><?php esc_html_e( 'These apply to every site of the network.', 'multisite-fleet-manager' ); ?></p>
			<dl class="wpfleet-facts">
				<dt><?php esc_html_e( 'WordPress', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<span class="wpfc-mono"><?php echo esc_html( $wpfh_ctx->wp_version ); ?></span>
					<?php if ( $wpfh_ctx->core_update ) : ?>
						<?php /* translators: %s: Version. */ ?>
						<span class="wpfc-note wpfc-note--warning"><?php echo esc_html( sprintf( __( '%s available', 'multisite-fleet-manager' ), $wpfh_ctx->core_update ) ); ?></span>
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'PHP', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<span class="wpfc-mono"><?php echo esc_html( $wpfh_ctx->php_version ); ?></span>
					<?php if ( version_compare( $wpfh_ctx->php_version, $wpfh_ctx->php_recommended, '<' ) ) : ?>
						<?php /* translators: %s: Version. */ ?>
						<span class="wpfc-note wpfc-note--warning"><?php echo esc_html( sprintf( __( '%s or newer recommended', 'multisite-fleet-manager' ), $wpfh_ctx->php_recommended ) ); ?></span>
					<?php endif; ?>
				</dd>

				<dt><?php echo esc_html( $wpfh_ctx->is_mariadb ? __( 'MariaDB', 'multisite-fleet-manager' ) : __( 'MySQL', 'multisite-fleet-manager' ) ); ?></dt>
				<dd><span class="wpfc-mono"><?php echo esc_html( '' !== $wpfh_ctx->db_server ? $wpfh_ctx->db_server : '—' ); ?></span></dd>

				<dt><?php esc_html_e( 'Scheduled tasks', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<?php
					if ( $wpfh_ctx->cron_disabled ) {
						esc_html_e( 'WP-Cron disabled (external scheduler expected)', 'multisite-fleet-manager' );
					} elseif ( $wpfh_ctx->alternate_cron ) {
						esc_html_e( 'Alternate WP-Cron', 'multisite-fleet-manager' );
					} else {
						esc_html_e( 'WP-Cron on page visits', 'multisite-fleet-manager' );
					}
					?>
					<?php if ( $wpfh_totals['cron_overdue'] ) : ?>
						<span class="wpfc-note wpfc-note--warning">
							<?php
							/* translators: %s: Number of sites. */
							echo esc_html( sprintf( _n( '%s site has overdue tasks', '%s sites have overdue tasks', (int) $wpfh_totals['cron_overdue'], 'multisite-fleet-manager' ), View::number( (int) $wpfh_totals['cron_overdue'] ) ) );
							?>
						</span>
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'Debugging', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<?php
					$wpfh_debug = array_keys( array_filter( $wpfh_ctx->debug ) );
					echo $wpfh_debug
						? esc_html( implode( ', ', $wpfh_debug ) )
						: esc_html__( 'Off', 'multisite-fleet-manager' );
					?>
				</dd>

				<dt><?php esc_html_e( 'Disk space', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<?php
					if ( null === $wpfh_ctx->disk_free ) {
						esc_html_e( 'Not reported by this host', 'multisite-fleet-manager' );
					} elseif ( $wpfh_ctx->disk_total ) {
						/* translators: 1: Free space, 2: Total space. */
						echo esc_html( sprintf( __( '%1$s free of %2$s', 'multisite-fleet-manager' ), size_format( $wpfh_ctx->disk_free, 1 ), size_format( $wpfh_ctx->disk_total ) ) );
					} else {
						echo esc_html( size_format( $wpfh_ctx->disk_free, 1 ) );
					}
					?>
				</dd>

				<dt><?php esc_html_e( 'HTTPS', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<?php
					/* translators: 1: Number of sites on HTTPS, 2: Total sites. */
					echo esc_html( sprintf( __( '%1$s of %2$s sites', 'multisite-fleet-manager' ), View::number( (int) $wpfh_totals['https'] ), View::number( (int) $wpfh_totals['sites'] ) ) );
					?>
				</dd>

				<dt><?php esc_html_e( 'Database size', 'multisite-fleet-manager' ); ?></dt>
				<dd>
					<?php
					echo null === $wpfh_totals['db_size']
						? esc_html__( 'Not available on this server', 'multisite-fleet-manager' )
						: esc_html( size_format( (int) $wpfh_totals['db_size'], 1 ) );
					?>
				</dd>

				<?php if ( $wpfh_totals['uploads'] ) : ?>
					<dt><?php esc_html_e( 'Uploads measured', 'multisite-fleet-manager' ); ?></dt>
					<dd><?php echo esc_html( size_format( (int) $wpfh_totals['uploads'], 1 ) ); ?></dd>
				<?php endif; ?>
			</dl>
		</section>

		<section class="wpfleet-panel" aria-labelledby="wpfh-attention-title">
			<h2 id="wpfh-attention-title"><?php esc_html_e( 'Sites to look at first', 'multisite-fleet-manager' ); ?></h2>
			<?php if ( ! $vars['attention'] ) : ?>
				<p class="wpfleet-meta"><?php esc_html_e( 'No sites in the inventory yet.', 'multisite-fleet-manager' ); ?></p>
			<?php else : ?>
				<ul class="wpfleet-sitelist">
					<?php foreach ( $vars['attention'] as $wpfh_site ) : ?>
						<?php
						$wpfh_issues = array_filter(
							$vars['checks']->describe( $wpfh_site->health_indicators ),
							static fn( $i ) => in_array( $i['state'], array( Indicator::WARNING, Indicator::ERROR ), true )
						);
						?>
						<li>
							<span class="wpfleet-sitelist__name">
								<?php if ( $vars['can_sites'] ) : ?>
									<a href="<?php echo esc_url( Admin::url( Admin::SLUG_CONSOLE, array( 's' => $wpfh_site->label() ) ) ); ?>"><?php echo esc_html( $wpfh_site->label() ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $wpfh_site->label() ); ?>
								<?php endif; ?>
								<span class="wpfc-note">
									<?php
									echo $wpfh_issues
										? esc_html( implode( ', ', array_slice( array_column( $wpfh_issues, 'label' ), 0, 3 ) ) )
										: esc_html__( 'All checks passed', 'multisite-fleet-manager' );
									?>
								</span>
							</span>
							<span class="wpfleet-sitelist__meta">
								<span class="wpfc-pill wpfc-pill--<?php echo esc_attr( '' !== $wpfh_site->health ? $wpfh_site->health : 'unchecked' ); ?>">
									<span class="dashicons <?php echo esc_attr( Health::icon( $wpfh_site->health ) ); ?>" aria-hidden="true"></span>
									<?php echo esc_html( Health::label( $wpfh_site->health ) ); ?>
								</span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>
</div>
