<?php
/**
 * Dashboard template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by DashboardPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\Admin;
use FleetManager\Admin\Notices;
use FleetManager\Admin\View;
use FleetManager\Network\NetworkInfo;
use FleetManager\Sites\SiteDiscovery;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

$wpfleet_network = $vars['network'];
$wpfleet_counts  = $vars['counts'];
$wpfleet_totals  = $vars['totals'];
$wpfleet_last    = $vars['last'];
$wpfleet_running = $vars['running'];
$wpfleet_drift   = $vars['has_inventory'] && (int) $vars['network_sites'] !== (int) $wpfleet_counts['total'];
?>
<div class="wrap wpfleet">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Fleet Manager', 'multisite-fleet-manager' ); ?></h1>
	<?php if ( '' !== $wpfleet_network['name'] ) : ?>
		<span class="subtitle"><?php echo esc_html( $wpfleet_network['name'] ); ?></span>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php Notices::render(); ?>

	<section class="wpfleet-panel wpfleet-launcher" aria-labelledby="wpfleet-discovery-title">
		<div class="wpfleet-launcher__text">
			<h2 id="wpfleet-discovery-title"><?php esc_html_e( 'Site discovery', 'multisite-fleet-manager' ); ?></h2>
			<p>
				<?php
				if ( $wpfleet_last ) {
					printf(
						/* translators: 1: Number of sites, 2: Relative time, e.g. "5 minutes ago", 3: Duration in seconds. */
						esc_html( _n( 'Last run found %1$s site, %2$s (took %3$s s).', 'Last run found %1$s sites, %2$s (took %3$s s).', (int) $wpfleet_last['sites'], 'multisite-fleet-manager' ) ),
						esc_html( View::number( (int) $wpfleet_last['sites'] ) ),
						esc_html( View::ago( $wpfleet_last['finished_at'] ) ),
						esc_html( View::number( (int) $wpfleet_last['duration'] ) )
					);
					if ( SiteDiscovery::SOURCE_SCHEDULED === $wpfleet_last['source'] ) {
						echo ' ' . esc_html__( 'Run automatically.', 'multisite-fleet-manager' );
					}
					if ( (int) $wpfleet_last['removed'] > 0 ) {
						echo ' ';
						printf(
							/* translators: %s: Number of sites. */
							esc_html( _n( '%s deleted site was removed from the inventory.', '%s deleted sites were removed from the inventory.', (int) $wpfleet_last['removed'], 'multisite-fleet-manager' ) ),
							esc_html( View::number( (int) $wpfleet_last['removed'] ) )
						);
					}
				} else {
					esc_html_e( 'Discovery reads every site of this network (read-only) and stores a snapshot in the Fleet Manager inventory. It runs daily in the background and can be started at any time.', 'multisite-fleet-manager' );
				}
				?>
			</p>
		</div>

		<?php if ( $vars['can_run'] ) : ?>
			<form id="wpfleet-discovery-form" method="post" action="<?php echo esc_url( Actions::url( Actions::DISCOVER ) ); ?>">
				<?php wp_nonce_field( Actions::DISCOVER ); ?>
				<button type="submit" class="button button-primary" id="wpfleet-discovery-button">
					<?php echo $wpfleet_last ? esc_html__( 'Refresh inventory', 'multisite-fleet-manager' ) : esc_html__( 'Discover sites', 'multisite-fleet-manager' ); ?>
				</button>
			</form>
		<?php endif; ?>

		<div class="wpfleet-progress" id="wpfleet-progress" hidden>
			<progress id="wpfleet-progress-bar" max="100" value="0" aria-labelledby="wpfleet-progress-status"></progress>
			<p class="wpfleet-progress__status" id="wpfleet-progress-status" role="status" aria-live="polite"></p>
		</div>

		<?php if ( $wpfleet_running ) : ?>
			<p class="wpfleet-launcher__running">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: 1: Sites processed, 2: Total sites. */
					esc_html__( 'A discovery run is in progress (%1$s of %2$s sites).', 'multisite-fleet-manager' ),
					esc_html( View::number( (int) $wpfleet_running['processed'] ) ),
					esc_html( View::number( (int) $wpfleet_running['total'] ) )
				);
				?>
			</p>
		<?php endif; ?>
	</section>

	<?php if ( ! $vars['has_inventory'] ) : ?>
		<div class="wpfleet-panel wpfleet-empty">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No inventory yet', 'multisite-fleet-manager' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: Number of sites. */
					esc_html( _n( 'This network has %s site. Run site discovery to build the inventory.', 'This network has %s sites. Run site discovery to build the inventory.', (int) $vars['network_sites'], 'multisite-fleet-manager' ) ),
					esc_html( View::number( (int) $vars['network_sites'] ) )
				);
				?>
			</p>
			<p class="wpfleet-meta"><?php esc_html_e( 'Discovery only reads data. It does not change any site.', 'multisite-fleet-manager' ); ?></p>
		</div>
	<?php else : ?>
		<?php if ( $wpfleet_drift ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					printf(
						/* translators: 1: Number of sites in core, 2: Number of sites in the inventory. */
						esc_html__( 'The network has %1$s sites but the inventory lists %2$s. Refresh the inventory to bring it up to date.', 'multisite-fleet-manager' ),
						esc_html( View::number( (int) $vars['network_sites'] ) ),
						esc_html( View::number( (int) $wpfleet_counts['total'] ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<h2 class="screen-reader-text"><?php esc_html_e( 'Sites by status', 'multisite-fleet-manager' ); ?></h2>
		<ul class="wpfleet-stats">
			<li class="wpfleet-panel wpfleet-stat wpfleet-stat--total">
				<?php
				$wpfleet_total_label = sprintf(
					'<span class="wpfleet-stat__num">%1$s</span><span class="wpfleet-stat__label">%2$s</span>',
					esc_html( View::number( (int) $wpfleet_counts['total'] ) ),
					esc_html__( 'Sites', 'multisite-fleet-manager' )
				);
				if ( $vars['can_view_sites'] ) {
					printf( '<a href="%s">%s</a>', esc_url( Admin::url( Admin::SLUG_SITES ) ), $wpfleet_total_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				} else {
					echo $wpfleet_total_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				}
				?>
			</li>
			<?php foreach ( SiteStatus::all() as $wpfleet_status ) : ?>
				<li class="wpfleet-panel wpfleet-stat wpfleet-status--<?php echo esc_attr( $wpfleet_status ); ?>">
					<?php
					$wpfleet_stat = sprintf(
						'<span class="wpfleet-stat__num"><span class="dashicons %1$s" aria-hidden="true"></span>%2$s</span><span class="wpfleet-stat__label">%3$s</span>',
						esc_attr( SiteStatus::icon( $wpfleet_status ) ),
						esc_html( View::number( (int) $wpfleet_counts[ $wpfleet_status ] ) ),
						esc_html( SiteStatus::label( $wpfleet_status ) )
					);
					if ( $vars['can_view_sites'] && $wpfleet_counts[ $wpfleet_status ] ) {
						printf( '<a href="%s">%s</a>', esc_url( Admin::url( Admin::SLUG_SITES, array( 'site_status' => $wpfleet_status ) ) ), $wpfleet_stat ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					} else {
						echo $wpfleet_stat; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					}
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div class="wpfleet-grid">
		<section class="wpfleet-panel" aria-labelledby="wpfleet-network-title">
			<h2 id="wpfleet-network-title"><?php esc_html_e( 'Network', 'multisite-fleet-manager' ); ?></h2>
			<dl class="wpfleet-facts">
				<dt><?php esc_html_e( 'Main site', 'multisite-fleet-manager' ); ?></dt>
				<dd><a href="<?php echo esc_url( $wpfleet_network['main_site_url'] ); ?>"><?php echo esc_html( untrailingslashit( $wpfleet_network['domain'] . $wpfleet_network['path'] ) ); ?></a></dd>

				<dt><?php esc_html_e( 'Site addresses', 'multisite-fleet-manager' ); ?></dt>
				<dd><?php echo esc_html( NetworkInfo::install_type_label( $wpfleet_network['install_type'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Registration', 'multisite-fleet-manager' ); ?></dt>
				<dd><?php echo esc_html( NetworkInfo::registration_label( $wpfleet_network['registration'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Network users', 'multisite-fleet-manager' ); ?></dt>
				<dd><?php echo esc_html( View::number( (int) $wpfleet_network['users'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Network-activated plugins', 'multisite-fleet-manager' ); ?></dt>
				<dd><?php echo esc_html( View::number( (int) $wpfleet_network['network_plugins'] ) ); ?></dd>

				<?php if ( $vars['has_inventory'] ) : ?>
					<dt><?php esc_html_e( 'Site-level plugin activations', 'multisite-fleet-manager' ); ?></dt>
					<dd><?php echo esc_html( View::number( (int) $wpfleet_totals['plugin_activations'] ) ); ?></dd>

					<dt><?php esc_html_e( 'Published content', 'multisite-fleet-manager' ); ?></dt>
					<dd><?php echo esc_html( View::content_count( (int) $wpfleet_totals['posts'], (int) $wpfleet_totals['pages'] ) ); ?></dd>
				<?php endif; ?>

				<?php if ( (int) $wpfleet_network['networks'] > 1 ) : ?>
					<dt><?php esc_html_e( 'Networks in this installation', 'multisite-fleet-manager' ); ?></dt>
					<dd>
						<?php echo esc_html( View::number( (int) $wpfleet_network['networks'] ) ); ?>
						<span class="wpfleet-meta"><?php esc_html_e( '(this dashboard covers the current network)', 'multisite-fleet-manager' ); ?></span>
					</dd>
				<?php endif; ?>

				<dt><?php esc_html_e( 'WordPress', 'multisite-fleet-manager' ); ?></dt>
				<dd><?php echo esc_html( $wpfleet_network['wp_version'] ); ?></dd>

				<dt><?php esc_html_e( 'PHP', 'multisite-fleet-manager' ); ?></dt>
				<dd><?php echo esc_html( $wpfleet_network['php_version'] ); ?></dd>
			</dl>
		</section>

		<?php if ( $vars['has_inventory'] ) : ?>
			<section class="wpfleet-panel" aria-labelledby="wpfleet-themes-title">
				<h2 id="wpfleet-themes-title"><?php esc_html_e( 'Active themes', 'multisite-fleet-manager' ); ?></h2>
				<?php if ( $vars['themes'] ) : ?>
					<ul class="wpfleet-bars">
						<?php foreach ( $vars['themes'] as $wpfleet_theme ) : ?>
							<?php $wpfleet_share = $wpfleet_counts['total'] ? round( $wpfleet_theme['sites'] / $wpfleet_counts['total'] * 100 ) : 0; ?>
							<li>
								<span class="wpfleet-bars__label"><?php echo esc_html( '' !== $wpfleet_theme['name'] ? $wpfleet_theme['name'] : $wpfleet_theme['stylesheet'] ); ?></span>
								<span class="wpfleet-bars__value">
									<?php
									printf(
										/* translators: %s: Number of sites. */
										esc_html( _n( '%s site', '%s sites', $wpfleet_theme['sites'], 'multisite-fleet-manager' ) ),
										esc_html( View::number( $wpfleet_theme['sites'] ) )
									);
									?>
								</span>
								<span class="wpfleet-bars__track" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) $wpfleet_share ); ?>%"></span></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="wpfleet-meta"><?php esc_html_e( 'No theme data.', 'multisite-fleet-manager' ); ?></p>
				<?php endif; ?>
			</section>

			<?php
			$wpfleet_lists = array(
				array(
					'id'     => 'wpfleet-recent-title',
					'title'  => __( 'Recently registered', 'multisite-fleet-manager' ),
					'sites'  => $vars['recent'],
					'column' => 'registered_at',
				),
				array(
					'id'     => 'wpfleet-updated-title',
					'title'  => __( 'Recently updated', 'multisite-fleet-manager' ),
					'sites'  => $vars['updated'],
					'column' => 'last_updated_at',
				),
			);
			?>
			<?php foreach ( $wpfleet_lists as $wpfleet_list ) : ?>
				<section class="wpfleet-panel" aria-labelledby="<?php echo esc_attr( $wpfleet_list['id'] ); ?>">
					<h2 id="<?php echo esc_attr( $wpfleet_list['id'] ); ?>"><?php echo esc_html( $wpfleet_list['title'] ); ?></h2>
					<ul class="wpfleet-sitelist">
						<?php foreach ( $wpfleet_list['sites'] as $wpfleet_site ) : ?>
							<li>
								<span class="wpfleet-sitelist__name">
									<?php if ( $vars['can_manage'] ) : ?>
										<a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $wpfleet_site->blog_id ) ); ?>"><?php echo esc_html( $wpfleet_site->label() ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $wpfleet_site->label() ); ?>
									<?php endif; ?>
									<span class="wpfleet-address"><?php echo esc_html( View::address( $wpfleet_site ) ); ?></span>
								</span>
								<span class="wpfleet-sitelist__meta">
									<?php
									if ( SiteStatus::ACTIVE !== $wpfleet_site->status ) {
										echo View::status_badge( $wpfleet_site->status ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in View.
									}
									$wpfleet_date = $wpfleet_site->{$wpfleet_list['column']};
									?>
									<time datetime="<?php echo esc_attr( $wpfleet_date ? gmdate( 'c', (int) strtotime( $wpfleet_date . ' UTC' ) ) : '' ); ?>" title="<?php echo esc_attr( View::datetime( $wpfleet_date ) ); ?>"><?php echo esc_html( $wpfleet_date ? View::ago( $wpfleet_date ) : '—' ); ?></time>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
