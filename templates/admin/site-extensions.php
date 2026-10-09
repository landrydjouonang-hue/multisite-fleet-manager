<?php
/**
 * Plugins & Themes of one site, with activation controls.
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

/** @var WP_Site $wpfs_site */
$wpfs_site    = $vars['site'];
$wpfs_ext     = $vars['extensions'];
$wpfs_id      = (int) $wpfs_site->blog_id;
$wpfs_address = untrailingslashit( $wpfs_site->domain . $wpfs_site->path );
$wpfs_closed  = (int) $wpfs_site->deleted || (int) $wpfs_site->spam;
$wpfs_toggle  = $vars['can_toggle'];
$wpfs_update  = $vars['can_update']['plugin'] && ! $vars['blocker'];
$wpfs_return  = Admin::url(
	Admin::SLUG_EXTENSIONS,
	array(
		'tab'  => 'site',
		'site' => $wpfs_id,
	)
);

$wpfs_states = array(
	'active'   => array( __( 'Active', 'multisite-fleet-manager' ), 'active', 'dashicons-yes-alt' ),
	'network'  => array( __( 'Network active', 'multisite-fleet-manager' ), 'active', 'dashicons-networking' ),
	'inactive' => array( __( 'Inactive', 'multisite-fleet-manager' ), 'archived', 'dashicons-marker' ),
	'missing'  => array( __( 'Files missing', 'multisite-fleet-manager' ), 'deleted', 'dashicons-dismiss' ),
);
$wpfs_counts = array_count_values( array_column( $wpfs_ext['plugins'], 'state' ) );

$vars['title']    = '' !== $vars['site_name'] ? $vars['site_name'] : $wpfs_address;
$vars['subtitle'] = sprintf(
	/* translators: 1: Site address, 2: Site ID. */
	__( 'Plugins and theme of %1$s (site #%2$s). Activation changes apply to this site only; updates apply to the whole network.', 'multisite-fleet-manager' ),
	$wpfs_address,
	$wpfs_id
);
$vars['tiles'] = array(
	array(
		'label'   => __( 'Active here', 'multisite-fleet-manager' ),
		'value'   => (int) ( $wpfs_counts['active'] ?? 0 ),
		'icon'    => 'dashicons-yes-alt',
		'accent'  => 'healthy',
		'url'     => '#wpfs-plugins',
		'current' => false,
	),
	array(
		'label'   => __( 'Network active', 'multisite-fleet-manager' ),
		'value'   => (int) ( $wpfs_counts['network'] ?? 0 ),
		'icon'    => 'dashicons-networking',
		'accent'  => 'plugins',
		'url'     => '#wpfs-plugins',
		'current' => false,
	),
	array(
		'label'   => __( 'Updates for this site', 'multisite-fleet-manager' ),
		'value'   => count( array_filter( $wpfs_ext['plugins'], static fn( $p ) => $p['update'] && in_array( $p['state'], array( 'active', 'network' ), true ) ) ),
		'icon'    => 'dashicons-update',
		'accent'  => 'attention',
		'url'     => '#wpfs-plugins',
		'current' => false,
	),
	array(
		'label'   => __( 'Missing plugin files', 'multisite-fleet-manager' ),
		'value'   => (int) ( $wpfs_counts['missing'] ?? 0 ),
		'icon'    => 'dashicons-dismiss',
		'accent'  => 'error',
		'url'     => '#wpfs-plugins',
		'current' => false,
	),
);
?>
<div class="wrap wpfleet wpfc wpfx" data-wpfx-namespace="<?php echo esc_attr( FleetManager\Rest\DiscoveryController::NAMESPACE ); ?>">
	<p class="wpfx-back">
		<?php if ( $vars['can_sites'] ) : ?>
			<a href="<?php echo esc_url( Admin::url( Admin::SLUG_CONSOLE ) ); ?>">&larr; <?php esc_html_e( 'Site Dashboard', 'multisite-fleet-manager' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( Admin::url( Admin::SLUG_EXTENSIONS ) ); ?>"><?php esc_html_e( 'All plugins', 'multisite-fleet-manager' ); ?></a>
		<?php if ( $vars['can_log'] ) : ?>
			<a href="<?php echo esc_url( Admin::url( Admin::SLUG_LOG, array( 'site_id' => $wpfs_id ) ) ); ?>"><?php esc_html_e( 'Operations on this site', 'multisite-fleet-manager' ); ?></a>
		<?php endif; ?>
	</p>

	<?php View::render( 'admin/partials/extensions-head', $vars ); ?>

	<?php if ( $wpfs_closed ) : ?>
		<div class="notice notice-warning inline wpfc-notice"><p><?php esc_html_e( 'This site is deactivated or marked as spam: plugins can be deactivated but not activated.', 'multisite-fleet-manager' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" data-wpfx-ops>
		<input type="hidden" name="site_id" value="<?php echo esc_attr( (string) $wpfs_id ); ?>" />
		<input type="hidden" name="return" value="<?php echo esc_url( $wpfs_return ); ?>" />
		<?php foreach ( array( Operation::PLUGIN_ACTIVATE, Operation::PLUGIN_DEACTIVATE, Operation::PLUGIN_UPDATE, Operation::THEME_UPDATE ) as $wpfs_op ) : ?>
			<input type="hidden" name="nonce[<?php echo esc_attr( $wpfs_op ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( $wpfs_op ) ) ); ?>" data-wpfx-nonce="<?php echo esc_attr( $wpfs_op ); ?>" />
		<?php endforeach; ?>

		<div class="wpfx-site-grid">
			<section class="wpfc-panel" id="wpfs-plugins" aria-labelledby="wpfs-plugins-title">
				<div class="wpfc-summary"><h2 id="wpfs-plugins-title" class="wpfx-panel-title"><?php esc_html_e( 'Plugins', 'multisite-fleet-manager' ); ?></h2></div>
				<div class="wpfc-table-wrap">
					<table class="wpfc-table wpfx-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Plugin', 'multisite-fleet-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'On this site', 'multisite-fleet-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Version', 'multisite-fleet-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Update', 'multisite-fleet-manager' ); ?></th>
								<th scope="col" class="wpfc-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'multisite-fleet-manager' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wpfs_ext['plugins'] as $wpfs_plugin ) : ?>
								<?php
								$wpfs_state = $wpfs_states[ $wpfs_plugin['state'] ];
								$wpfs_self  = defined( 'WPFLEET_BASENAME' ) && WPFLEET_BASENAME === $wpfs_plugin['file'];
								?>
								<tr class="wpfc-row wpfx-row" data-wpfx-item="<?php echo esc_attr( $wpfs_plugin['file'] ); ?>">
									<td data-label="<?php esc_attr_e( 'Plugin', 'multisite-fleet-manager' ); ?>">
										<span class="wpfc-site">
											<strong><?php echo esc_html( $wpfs_plugin['name'] ); ?></strong>
											<span class="wpfc-site__address wpfc-mono"><?php echo esc_html( $wpfs_plugin['file'] ); ?></span>
										</span>
									</td>
									<td data-label="<?php esc_attr_e( 'On this site', 'multisite-fleet-manager' ); ?>">
										<span class="wpfleet-badge wpfleet-status--<?php echo esc_attr( $wpfs_state[1] ); ?>" data-wpfx-state><span class="dashicons <?php echo esc_attr( $wpfs_state[2] ); ?>" aria-hidden="true"></span><?php echo esc_html( $wpfs_state[0] ); ?></span>
									</td>
									<td data-label="<?php esc_attr_e( 'Version', 'multisite-fleet-manager' ); ?>"><span class="wpfc-mono" data-wpfx-version><?php echo esc_html( '' !== $wpfs_plugin['version'] ? $wpfs_plugin['version'] : '—' ); ?></span></td>
									<td data-label="<?php esc_attr_e( 'Update', 'multisite-fleet-manager' ); ?>" data-wpfx-update>
										<?php if ( $wpfs_plugin['update'] ) : ?>
											<span class="wpfc-count-badge has-updates wpfc-mono"><?php echo esc_html( $wpfs_plugin['update']['new_version'] ); ?></span>
										<?php elseif ( 'missing' !== $wpfs_plugin['state'] ) : ?>
											<span class="wpfc-muted"><?php esc_html_e( 'Up to date', 'multisite-fleet-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="wpfc-col-actions" data-wpfx-status>
										<?php if ( $wpfs_toggle && ! $wpfs_self && in_array( $wpfs_plugin['state'], array( 'active', 'missing' ), true ) ) : ?>
											<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( Operation::PLUGIN_DEACTIVATE . '|' . $wpfs_plugin['file'] ); ?>" data-wpfx-single data-wpfx-confirm="<?php esc_attr_e( 'Deactivate this plugin on this site?', 'multisite-fleet-manager' ); ?>">
												<?php esc_html_e( 'Deactivate', 'multisite-fleet-manager' ); ?><span class="screen-reader-text"> <?php echo esc_html( $wpfs_plugin['name'] ); ?></span>
											</button>
										<?php elseif ( $wpfs_toggle && ! $wpfs_self && ! $wpfs_closed && 'inactive' === $wpfs_plugin['state'] && ! $wpfs_plugin['network_only'] ) : ?>
											<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( Operation::PLUGIN_ACTIVATE . '|' . $wpfs_plugin['file'] ); ?>" data-wpfx-single>
												<?php esc_html_e( 'Activate', 'multisite-fleet-manager' ); ?><span class="screen-reader-text"> <?php echo esc_html( $wpfs_plugin['name'] ); ?></span>
											</button>
										<?php elseif ( 'network' === $wpfs_plugin['state'] ) : ?>
											<span class="wpfc-muted"><?php esc_html_e( 'Managed network-wide', 'multisite-fleet-manager' ); ?></span>
										<?php endif; ?>
										<?php if ( $wpfs_update && $wpfs_plugin['update'] && $wpfs_plugin['update']['has_package'] && $wpfs_plugin['update']['compatible'] && ! $wpfs_self ) : ?>
											<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( Operation::PLUGIN_UPDATE . '|' . $wpfs_plugin['file'] ); ?>" data-wpfx-single data-wpfx-confirm="<?php esc_attr_e( 'Plugin files are shared: this updates the plugin on every site of the network. Continue?', 'multisite-fleet-manager' ); ?>">
												<?php esc_html_e( 'Update', 'multisite-fleet-manager' ); ?><span class="screen-reader-text"> <?php echo esc_html( $wpfs_plugin['name'] ); ?></span>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="wpfc-panel wpfx-theme-card" aria-labelledby="wpfs-theme-title">
				<div class="wpfc-summary"><h2 id="wpfs-theme-title" class="wpfx-panel-title"><?php esc_html_e( 'Active theme', 'multisite-fleet-manager' ); ?></h2></div>
				<div class="wpfx-theme-card__body">
					<?php foreach ( array_filter( array( $wpfs_ext['theme'], $wpfs_ext['parent'] ) ) as $wpfs_index => $wpfs_theme ) : ?>
						<div class="wpfx-theme" data-wpfx-item="<?php echo esc_attr( $wpfs_theme['stylesheet'] ); ?>">
							<?php if ( 1 === $wpfs_index ) : ?>
								<p class="wpfc-eyebrow wpfx-eyebrow"><?php esc_html_e( 'Parent theme', 'multisite-fleet-manager' ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $wpfs_theme['missing'] ) ) : ?>
								<strong class="wpfc-theme is-broken"><?php echo esc_html( $wpfs_theme['stylesheet'] ); ?></strong>
								<span class="wpfc-note wpfc-note--error"><?php esc_html_e( 'Not installed', 'multisite-fleet-manager' ); ?></span>
							<?php else : ?>
								<strong><?php echo esc_html( $wpfs_theme['name'] ); ?></strong>
								<dl class="wpfleet-facts">
									<dt><?php esc_html_e( 'Version', 'multisite-fleet-manager' ); ?></dt>
									<dd class="wpfc-mono" data-wpfx-version><?php echo esc_html( $wpfs_theme['version'] ); ?></dd>
									<dt><?php esc_html_e( 'Update', 'multisite-fleet-manager' ); ?></dt>
									<dd data-wpfx-update><?php echo $wpfs_theme['update'] ? '<span class="wpfc-count-badge has-updates wpfc-mono">' . esc_html( $wpfs_theme['update']['new_version'] ) . '</span>' : esc_html__( 'Up to date', 'multisite-fleet-manager' ); ?></dd>
									<dt><?php esc_html_e( 'Used by', 'multisite-fleet-manager' ); ?></dt>
									<?php /* translators: %s: Number of sites. */ ?>
									<dd><?php echo esc_html( sprintf( _n( '%s site', '%s sites', $wpfs_theme['sites'], 'multisite-fleet-manager' ), View::number( $wpfs_theme['sites'] ) ) ); ?></dd>
								</dl>
								<p data-wpfx-status>
									<?php if ( $vars['can_update']['theme'] && ! $vars['blocker'] && $wpfs_theme['update'] && $wpfs_theme['update']['has_package'] && $wpfs_theme['update']['compatible'] ) : ?>
										<button type="submit" class="button" name="single" value="<?php echo esc_attr( Operation::THEME_UPDATE . '|' . $wpfs_theme['stylesheet'] ); ?>" data-wpfx-single data-wpfx-confirm="<?php esc_attr_e( 'Theme files are shared: this updates the theme on every site that uses it. Continue?', 'multisite-fleet-manager' ); ?>">
											<?php
											/* translators: %s: Version. */
											echo esc_html( sprintf( __( 'Update to %s', 'multisite-fleet-manager' ), $wpfs_theme['update']['new_version'] ) );
											?>
										</button>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<?php if ( ! $wpfs_ext['theme'] ) : ?>
						<p class="wpfc-muted"><?php esc_html_e( 'No active theme recorded.', 'multisite-fleet-manager' ); ?></p>
					<?php endif; ?>
				</div>
			</section>
		</div>
	</form>
</div>
