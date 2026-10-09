<?php
/**
 * Plugins & Themes: network inventory with update controls.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by ExtensionsPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\Admin;
use FleetManager\Admin\View;
use FleetManager\Operations\Operation;

defined( 'ABSPATH' ) || exit;

/** @var FleetManager\Admin\ExtensionsPage $wpfx_page */
$wpfx_page   = $vars['page'];
$wpfx_state  = $vars['state'];
$wpfx_themes = $vars['is_themes'];
$wpfx_stats  = $vars['stats'];
$wpfx_op     = $wpfx_themes ? Operation::THEME_UPDATE : Operation::PLUGIN_UPDATE;
$wpfx_can    = $vars['can_update'][ $wpfx_themes ? 'theme' : 'plugin' ] && ! $vars['blocker'];
$wpfx_total  = (int) $vars['total'];
$wpfx_pages  = (int) ceil( $wpfx_total / max( 1, $wpfx_state['per_page'] ) );
$wpfx_first  = $wpfx_total ? ( $wpfx_state['paged'] - 1 ) * $wpfx_state['per_page'] + 1 : 0;

$wpfx_filters = $wpfx_themes
	? array(
		''        => __( 'All themes', 'multisite-fleet-manager' ),
		'update'  => __( 'Update available', 'multisite-fleet-manager' ),
		'enabled' => __( 'Network enabled', 'multisite-fleet-manager' ),
		'used'    => __( 'In use', 'multisite-fleet-manager' ),
		'unused'  => __( 'Not used by any site', 'multisite-fleet-manager' ),
	)
	: array(
		''         => __( 'All plugins', 'multisite-fleet-manager' ),
		'update'   => __( 'Update available', 'multisite-fleet-manager' ),
		'network'  => __( 'Network active', 'multisite-fleet-manager' ),
		'active'   => __( 'Active on sites', 'multisite-fleet-manager' ),
		'inactive' => __( 'Not active anywhere', 'multisite-fleet-manager' ),
	);

$wpfx_tile = static function ( string $filter, string $label, int $value, string $icon, string $accent ) use ( $wpfx_page, $wpfx_state ): array {
	return array(
		'label'   => $label,
		'value'   => $value,
		'icon'    => $icon,
		'accent'  => $accent,
		'url'     => $wpfx_page->url( array( 'filter' => '' === $filter ? null : $filter ) ),
		'current' => $wpfx_state['filter'] === $filter,
	);
};

$vars['tiles'] = $wpfx_themes
	? array(
		$wpfx_tile( '', __( 'Installed themes', 'multisite-fleet-manager' ), $wpfx_stats['total'], 'dashicons-admin-appearance', 'all' ),
		$wpfx_tile( 'update', __( 'Updates available', 'multisite-fleet-manager' ), $wpfx_stats['update'], 'dashicons-update', 'attention' ),
		$wpfx_tile( 'enabled', __( 'Network enabled', 'multisite-fleet-manager' ), $wpfx_stats['a'], 'dashicons-networking', 'plugins' ),
		$wpfx_tile( 'used', __( 'In use', 'multisite-fleet-manager' ), $wpfx_stats['b'], 'dashicons-yes-alt', 'healthy' ),
		$wpfx_tile( 'unused', __( 'Unused', 'multisite-fleet-manager' ), $wpfx_stats['c'], 'dashicons-marker', 'themes' ),
	)
	: array(
		$wpfx_tile( '', __( 'Installed plugins', 'multisite-fleet-manager' ), $wpfx_stats['total'], 'dashicons-admin-plugins', 'all' ),
		$wpfx_tile( 'update', __( 'Updates available', 'multisite-fleet-manager' ), $wpfx_stats['update'], 'dashicons-update', 'attention' ),
		$wpfx_tile( 'network', __( 'Network active', 'multisite-fleet-manager' ), $wpfx_stats['a'], 'dashicons-networking', 'plugins' ),
		$wpfx_tile( 'active', __( 'Active on sites', 'multisite-fleet-manager' ), $wpfx_stats['b'], 'dashicons-yes-alt', 'healthy' ),
		$wpfx_tile( 'inactive', __( 'Not active anywhere', 'multisite-fleet-manager' ), $wpfx_stats['c'], 'dashicons-marker', 'themes' ),
	);
?>
<div class="wrap wpfleet wpfc wpfx" data-wpfx-namespace="<?php echo esc_attr( FleetManager\Rest\DiscoveryController::NAMESPACE ); ?>">
	<?php View::render( 'admin/partials/extensions-head', $vars ); ?>

	<section class="wpfc-panel" aria-labelledby="wpfx-table-title">
		<h2 id="wpfx-table-title" class="screen-reader-text"><?php echo esc_html( $wpfx_themes ? __( 'Themes', 'multisite-fleet-manager' ) : __( 'Plugins', 'multisite-fleet-manager' ) ); ?></h2>

		<form class="wpfc-toolbar" method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) ); ?>" role="search">
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG_EXTENSIONS ); ?>" />
			<?php if ( $wpfx_themes ) : ?>
				<input type="hidden" name="tab" value="themes" />
			<?php endif; ?>
			<div class="wpfc-field wpfc-field--search">
				<label for="wpfc-search"><?php esc_html_e( 'Search', 'multisite-fleet-manager' ); ?></label>
				<span class="wpfc-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="wpfc-search" name="s" value="<?php echo esc_attr( $wpfx_state['s'] ); ?>" placeholder="<?php esc_attr_e( 'Name, file or author', 'multisite-fleet-manager' ); ?>" />
				</span>
			</div>
			<div class="wpfc-field">
				<label for="wpfx-filter"><?php esc_html_e( 'Show', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfx-filter" name="filter">
					<?php foreach ( $wpfx_filters as $wpfx_value => $wpfx_label ) : ?>
						<option value="<?php echo esc_attr( $wpfx_value ); ?>" <?php selected( $wpfx_state['filter'], $wpfx_value ); ?>><?php echo esc_html( $wpfx_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field wpfc-field--narrow">
				<label for="wpfx-per-page"><?php esc_html_e( 'Per page', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfx-per-page" name="per_page">
					<?php foreach ( FleetManager\Admin\ExtensionsPage::PER_PAGE_OPTIONS as $wpfx_value ) : ?>
						<option value="<?php echo esc_attr( (string) $wpfx_value ); ?>" <?php selected( $wpfx_state['per_page'], $wpfx_value ); ?>><?php echo esc_html( (string) $wpfx_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field wpfc-field--actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'multisite-fleet-manager' ); ?></button>
				<?php if ( $wpfx_state['s'] || $wpfx_state['filter'] ) : ?>
					<a class="button" href="<?php echo esc_url( $wpfx_page->url( array( 's' => null, 'filter' => null ) ) ); ?>"><?php esc_html_e( 'Reset', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" data-wpfx-ops>
			<input type="hidden" name="operation" value="<?php echo esc_attr( $wpfx_op ); ?>" />
			<input type="hidden" name="nonce[<?php echo esc_attr( $wpfx_op ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( $wpfx_op ) ) ); ?>" data-wpfx-nonce="<?php echo esc_attr( $wpfx_op ); ?>" />
			<input type="hidden" name="return" value="<?php echo esc_url( $wpfx_page->url( array( 'paged' => $wpfx_state['paged'] ) ) ); ?>" />

			<div class="wpfc-summary">
				<p class="wpfc-count" role="status">
					<?php
					if ( $vars['items'] ) {
						/* translators: 1: First item, 2: Last item, 3: Total. */
						echo esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s', 'multisite-fleet-manager' ), View::number( $wpfx_first ), View::number( $wpfx_first + count( $vars['items'] ) - 1 ), View::number( $wpfx_total ) ) );
					} else {
						esc_html_e( 'Nothing matches these filters.', 'multisite-fleet-manager' );
					}
					?>
				</p>
				<?php if ( $wpfx_can ) : ?>
					<div class="wpfx-bulk">
						<button type="submit" class="button" data-wpfx-bulk disabled>
							<?php echo esc_html( $wpfx_themes ? __( 'Update selected themes', 'multisite-fleet-manager' ) : __( 'Update selected plugins', 'multisite-fleet-manager' ) ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<div class="wpfc-table-wrap">
				<table class="wpfc-table wpfx-table">
					<thead>
						<tr>
							<?php if ( $wpfx_can ) : ?>
								<td class="wpfx-col-check"><input type="checkbox" id="wpfx-select-all" data-wpfx-select-all aria-label="<?php esc_attr_e( 'Select all items with an update', 'multisite-fleet-manager' ); ?>" /></td>
							<?php endif; ?>
							<?php
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- sort_header() escapes.
							echo $wpfx_page->sort_header( 'name', $wpfx_themes ? __( 'Theme', 'multisite-fleet-manager' ) : __( 'Plugin', 'multisite-fleet-manager' ) );
							?>
							<th scope="col"><?php esc_html_e( 'Version', 'multisite-fleet-manager' ); ?></th>
							<?php
							echo $wpfx_page->sort_header( 'update', __( 'Update', 'multisite-fleet-manager' ) );
							echo $wpfx_page->sort_header( 'sites', $wpfx_themes ? __( 'Used by', 'multisite-fleet-manager' ) : __( 'Activation', 'multisite-fleet-manager' ) );
							// phpcs:enable
							?>
							<th scope="col" class="wpfc-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'multisite-fleet-manager' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $vars['items'] ) : ?>
							<tr class="wpfc-empty-row"><td colspan="6"><?php esc_html_e( 'Nothing matches these filters.', 'multisite-fleet-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $vars['items'] as $wpfx_item ) : ?>
							<?php
							$wpfx_key    = $wpfx_themes ? $wpfx_item['stylesheet'] : $wpfx_item['file'];
							$wpfx_update = $wpfx_item['update'];
							$wpfx_ready  = $wpfx_can && $wpfx_update && $wpfx_update['has_package'] && $wpfx_update['compatible'] && ( ! defined( 'WPFLEET_BASENAME' ) || WPFLEET_BASENAME !== $wpfx_key );
							?>
							<tr class="wpfc-row wpfx-row<?php echo $wpfx_update ? ' has-update' : ''; ?>" data-wpfx-item="<?php echo esc_attr( $wpfx_key ); ?>">
								<?php if ( $wpfx_can ) : ?>
									<td class="wpfx-col-check">
										<?php if ( $wpfx_ready ) : ?>
											<input type="checkbox" name="items[]" value="<?php echo esc_attr( $wpfx_key ); ?>" id="wpfx-cb-<?php echo esc_attr( md5( $wpfx_key ) ); ?>" data-wpfx-check />
											<label class="screen-reader-text" for="wpfx-cb-<?php echo esc_attr( md5( $wpfx_key ) ); ?>">
												<?php
												/* translators: %s: Plugin or theme name. */
												echo esc_html( sprintf( __( 'Select %s', 'multisite-fleet-manager' ), $wpfx_item['name'] ) );
												?>
											</label>
										<?php endif; ?>
									</td>
								<?php endif; ?>
								<td data-label="<?php echo esc_attr( $wpfx_themes ? __( 'Theme', 'multisite-fleet-manager' ) : __( 'Plugin', 'multisite-fleet-manager' ) ); ?>">
									<span class="wpfc-site">
										<strong><?php echo esc_html( $wpfx_item['name'] ); ?></strong>
										<span class="wpfc-site__address wpfc-mono"><?php echo esc_html( $wpfx_key ); ?></span>
										<span class="wpfleet-tags">
											<?php if ( ! $wpfx_themes && $wpfx_item['network_only'] ) : ?>
												<span class="wpfleet-tag"><?php esc_html_e( 'Network only', 'multisite-fleet-manager' ); ?></span>
											<?php endif; ?>
											<?php if ( $wpfx_themes && $wpfx_item['parent'] ) : ?>
												<?php /* translators: %s: Parent theme. */ ?>
												<span class="wpfleet-tag"><?php echo esc_html( sprintf( __( 'Child of %s', 'multisite-fleet-manager' ), $wpfx_item['parent'] ) ); ?></span>
											<?php endif; ?>
											<?php if ( $wpfx_themes && $wpfx_item['broken'] ) : ?>
												<span class="wpfleet-tag wpfx-tag--error"><?php esc_html_e( 'Broken', 'multisite-fleet-manager' ); ?></span>
											<?php endif; ?>
											<?php if ( $wpfx_item['auto_update'] ) : ?>
												<span class="wpfleet-tag"><?php esc_html_e( 'Auto-updates on', 'multisite-fleet-manager' ); ?></span>
											<?php endif; ?>
										</span>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Version', 'multisite-fleet-manager' ); ?>"><span class="wpfc-mono" data-wpfx-version><?php echo esc_html( '' !== $wpfx_item['version'] ? $wpfx_item['version'] : '—' ); ?></span></td>
								<td data-label="<?php esc_attr_e( 'Update', 'multisite-fleet-manager' ); ?>" data-wpfx-update>
									<?php if ( $wpfx_update ) : ?>
										<span class="wpfc-count-badge has-updates wpfc-mono"><?php echo esc_html( $wpfx_update['new_version'] ); ?></span>
										<?php if ( ! $wpfx_update['compatible'] ) : ?>
											<span class="wpfc-note wpfc-note--error">
												<?php
												echo esc_html(
													$wpfx_update['requires_php'] && ! is_php_version_compatible( $wpfx_update['requires_php'] )
														/* translators: %s: PHP version. */
														? sprintf( __( 'Requires PHP %s', 'multisite-fleet-manager' ), $wpfx_update['requires_php'] )
														/* translators: %s: WordPress version. */
														: sprintf( __( 'Requires WordPress %s', 'multisite-fleet-manager' ), $wpfx_update['requires'] )
												);
												?>
											</span>
										<?php elseif ( ! $wpfx_update['has_package'] ) : ?>
											<span class="wpfc-note wpfc-note--warning"><?php esc_html_e( 'Package unavailable (licence?)', 'multisite-fleet-manager' ); ?></span>
										<?php elseif ( $wpfx_update['tested'] ) : ?>
											<?php /* translators: %s: WordPress version. */ ?>
											<span class="wpfc-note"><?php echo esc_html( sprintf( __( 'Tested up to WordPress %s', 'multisite-fleet-manager' ), $wpfx_update['tested'] ) ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="wpfc-muted"><?php esc_html_e( 'Up to date', 'multisite-fleet-manager' ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php echo esc_attr( $wpfx_themes ? __( 'Used by', 'multisite-fleet-manager' ) : __( 'Activation', 'multisite-fleet-manager' ) ); ?>">
									<?php if ( $wpfx_themes ) : ?>
										<?php if ( $wpfx_item['sites'] ) : ?>
											<?php
											$wpfx_text = sprintf(
												/* translators: %s: Number of sites. */
												_n( 'Active on %s site', 'Active on %s sites', $wpfx_item['sites'], 'multisite-fleet-manager' ),
												View::number( $wpfx_item['sites'] )
											);
											?>
											<?php if ( $vars['can_sites'] ) : ?>
												<a href="<?php echo esc_url( Admin::url( Admin::SLUG_CONSOLE, array( 'theme' => $wpfx_key ) ) ); ?>"><?php echo esc_html( $wpfx_text ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $wpfx_text ); ?>
											<?php endif; ?>
										<?php endif; ?>
										<?php if ( $wpfx_item['parent_of'] ) : ?>
											<?php /* translators: %s: Number of sites. */ ?>
											<span class="wpfc-note"><?php echo esc_html( sprintf( _n( 'Parent theme on %s site', 'Parent theme on %s sites', $wpfx_item['parent_of'], 'multisite-fleet-manager' ), View::number( $wpfx_item['parent_of'] ) ) ); ?></span>
										<?php endif; ?>
										<?php if ( ! $wpfx_item['sites'] && ! $wpfx_item['parent_of'] ) : ?>
											<span class="wpfc-muted"><?php esc_html_e( 'Not used', 'multisite-fleet-manager' ); ?></span>
										<?php endif; ?>
										<span class="wpfc-note"><?php echo esc_html( $wpfx_item['network_enabled'] ? __( 'Network enabled', 'multisite-fleet-manager' ) : __( 'Not network enabled', 'multisite-fleet-manager' ) ); ?></span>
									<?php elseif ( $wpfx_item['network_active'] ) : ?>
										<span class="wpfleet-badge wpfleet-status--active"><span class="dashicons dashicons-networking" aria-hidden="true"></span><?php esc_html_e( 'Network active', 'multisite-fleet-manager' ); ?></span>
									<?php elseif ( $wpfx_item['sites'] ) : ?>
										<?php
										$wpfx_text = sprintf(
											/* translators: %s: Number of sites. */
											_n( 'Active on %s site', 'Active on %s sites', $wpfx_item['sites'], 'multisite-fleet-manager' ),
											View::number( $wpfx_item['sites'] )
										);
										?>
										<?php if ( $vars['can_sites'] ) : ?>
											<a href="<?php echo esc_url( Admin::url( Admin::SLUG_CONSOLE, array( 'plugin' => $wpfx_key ) ) ); ?>"><?php echo esc_html( $wpfx_text ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $wpfx_text ); ?>
										<?php endif; ?>
									<?php else : ?>
										<span class="wpfc-muted"><?php esc_html_e( 'Not active anywhere', 'multisite-fleet-manager' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="wpfc-col-actions" data-wpfx-status>
									<?php if ( $wpfx_ready ) : ?>
										<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( $wpfx_op . '|' . $wpfx_key ); ?>" data-wpfx-single data-wpfx-confirm="<?php echo esc_attr( $wpfx_themes ? __( 'Theme files are shared: this updates the theme on every site that uses it. Continue?', 'multisite-fleet-manager' ) : __( 'Plugin files are shared: this updates the plugin on every site of the network. Continue?', 'multisite-fleet-manager' ) ); ?>">
											<?php
											/* translators: %s: Version. */
											echo esc_html( sprintf( __( 'Update to %s', 'multisite-fleet-manager' ), $wpfx_update['new_version'] ) );
											?>
											<span class="screen-reader-text"><?php echo esc_html( $wpfx_item['name'] ); ?></span>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</form>

		<?php if ( $wpfx_pages > 1 ) : ?>
			<nav class="wpfc-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'multisite-fleet-manager' ); ?>">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'      => str_replace( '999999999', '%#%', esc_url( $wpfx_page->url( array( 'paged' => 999999999 ) ) ) ),
							'format'    => '',
							'current'   => $wpfx_state['paged'],
							'total'     => $wpfx_pages,
							'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html__( 'Previous page', 'multisite-fleet-manager' ) . '</span>',
							'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html__( 'Next page', 'multisite-fleet-manager' ) . '</span>',
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</section>
</div>
