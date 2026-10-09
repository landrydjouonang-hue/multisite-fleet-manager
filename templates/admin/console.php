<?php
/**
 * Network site dashboard (fleet console) template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by ConsolePage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\Notices;
use FleetManager\Admin\View;
use FleetManager\Health\Health;
use FleetManager\Health\Indicator;
use FleetManager\Sites\SiteRepository;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

/** @var FleetManager\Admin\ConsolePage $wpfc_page */
$wpfc_page    = $vars['page'];
$wpfc_state   = $vars['state'];
$wpfc_health  = $vars['health'];
$wpfc_updates = $vars['updates'];
/** @var FleetManager\Health\FleetContext $wpfc_ctx */
$wpfc_ctx    = $vars['context'];
$wpfc_checks = $vars['checks'];
$wpfc_total  = (int) $vars['total'];
$wpfc_first  = $wpfc_total ? ( $wpfc_state['paged'] - 1 ) * $wpfc_state['per_page'] + 1 : 0;
$wpfc_last   = min( $wpfc_total, $wpfc_state['paged'] * $wpfc_state['per_page'] );
$wpfc_pages  = (int) ceil( $wpfc_total / max( 1, $wpfc_state['per_page'] ) );

$wpfc_update_filters = array(
	''        => __( 'Any update state', 'multisite-fleet-manager' ),
	'any'     => __( 'Updates available', 'multisite-fleet-manager' ),
	'plugins' => __( 'Plugin updates', 'multisite-fleet-manager' ),
	'themes'  => __( 'Theme updates', 'multisite-fleet-manager' ),
	'none'    => __( 'Up to date', 'multisite-fleet-manager' ),
);

// KPI tiles double as one-click filters.
$wpfc_tiles = array(
	array(
		'key'     => 'all',
		'label'   => __( 'Sites', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfc_health['total'],
		'icon'    => 'dashicons-networking',
		'url'     => $wpfc_page->url( array( 'health' => null, 'updates' => null ) ),
		'current' => ! $wpfc_state['health'] && ! $wpfc_state['updates'],
	),
	array(
		'key'     => Health::HEALTHY,
		'label'   => Health::label( Health::HEALTHY ),
		'value'   => (int) $wpfc_health[ Health::HEALTHY ],
		'icon'    => Health::icon( Health::HEALTHY ),
		'url'     => $wpfc_page->url( array( 'health' => Health::HEALTHY, 'updates' => null ) ),
		'current' => Health::HEALTHY === $wpfc_state['health'],
	),
	array(
		'key'     => Health::ATTENTION,
		'label'   => Health::label( Health::ATTENTION ),
		'value'   => (int) $wpfc_health[ Health::ATTENTION ],
		'icon'    => Health::icon( Health::ATTENTION ),
		'url'     => $wpfc_page->url( array( 'health' => Health::ATTENTION, 'updates' => null ) ),
		'current' => Health::ATTENTION === $wpfc_state['health'],
	),
	array(
		'key'     => Health::ERROR,
		'label'   => Health::label( Health::ERROR ),
		'value'   => (int) $wpfc_health[ Health::ERROR ],
		'icon'    => Health::icon( Health::ERROR ),
		'url'     => $wpfc_page->url( array( 'health' => Health::ERROR, 'updates' => null ) ),
		'current' => Health::ERROR === $wpfc_state['health'],
	),
	array(
		'key'     => 'plugins',
		'label'   => __( 'Plugin updates', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfc_updates['plugin_updates'],
		/* translators: %s: Number of sites. */
		'sub'     => sprintf( _n( 'on %s site', 'on %s sites', (int) $wpfc_updates['sites_with_plugin_updates'], 'multisite-fleet-manager' ), View::number( (int) $wpfc_updates['sites_with_plugin_updates'] ) ),
		'icon'    => 'dashicons-admin-plugins',
		'url'     => $wpfc_page->url( array( 'updates' => 'plugins', 'health' => null ) ),
		'current' => 'plugins' === $wpfc_state['updates'] && ! $wpfc_state['health'],
	),
	array(
		'key'     => 'themes',
		'label'   => __( 'Theme updates', 'multisite-fleet-manager' ),
		'value'   => (int) $wpfc_updates['theme_updates'],
		/* translators: %s: Number of sites. */
		'sub'     => sprintf( _n( 'on %s site', 'on %s sites', (int) $wpfc_updates['sites_with_theme_updates'], 'multisite-fleet-manager' ), View::number( (int) $wpfc_updates['sites_with_theme_updates'] ) ),
		'icon'    => 'dashicons-admin-appearance',
		'url'     => $wpfc_page->url( array( 'updates' => 'themes', 'health' => null ) ),
		'current' => 'themes' === $wpfc_state['updates'] && ! $wpfc_state['health'],
	),
);

// Active filters as removable chips.
$wpfc_chips = array();
if ( $wpfc_state['s'] ) {
	/* translators: %s: Search term. */
	$wpfc_chips[] = array( sprintf( __( 'Search: %s', 'multisite-fleet-manager' ), $wpfc_state['s'] ), array( 's' => null ) );
}
if ( $wpfc_state['health'] ) {
	/* translators: %s: Health status. */
	$wpfc_chips[] = array( sprintf( __( 'Health: %s', 'multisite-fleet-manager' ), Health::label( $wpfc_state['health'] ) ), array( 'health' => null ) );
}
if ( $wpfc_state['site_status'] ) {
	/* translators: %s: Site status. */
	$wpfc_chips[] = array( sprintf( __( 'Status: %s', 'multisite-fleet-manager' ), SiteStatus::label( $wpfc_state['site_status'] ) ), array( 'site_status' => null ) );
}
if ( $wpfc_state['updates'] ) {
	$wpfc_chips[] = array( $wpfc_update_filters[ $wpfc_state['updates'] ], array( 'updates' => null ) );
}
if ( $wpfc_state['indicator'] ) {
	$wpfc_check = $wpfc_checks->get( $wpfc_state['indicator'] );
	$wpfc_chips[] = array(
		sprintf(
			/* translators: 1: Indicator name, 2: State, e.g. "(error)". */
			__( 'Indicator: %1$s %2$s', 'multisite-fleet-manager' ),
			$wpfc_check ? $wpfc_check->label() : $wpfc_state['indicator'],
			View::indicator_state( $wpfc_state['state'] )
		),
		array(
			'indicator' => null,
			'state'     => null,
		),
	);
}
if ( $wpfc_state['plugin'] ) {
	/* translators: %s: Plugin file. */
	$wpfc_chips[] = array( sprintf( __( 'Running plugin: %s', 'multisite-fleet-manager' ), $wpfc_state['plugin'] ), array( 'plugin' => null ) );
}
if ( $wpfc_state['theme'] ) {
	/* translators: %s: Theme. */
	$wpfc_chips[] = array( sprintf( __( 'Theme: %s', 'multisite-fleet-manager' ), $wpfc_state['theme'] ), array( 'theme' => null ) );
}

$wpfc_checked = max( (int) $wpfc_ctx->plugins_checked_at, (int) $wpfc_ctx->themes_checked_at );
?>
<div class="wrap wpfleet wpfc">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Network Site Dashboard', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head" aria-labelledby="wpfc-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( $vars['network']['name'] ); ?></p>
				<h2 id="wpfc-title"><?php esc_html_e( 'Site Dashboard', 'multisite-fleet-manager' ); ?></h2>
				<ul class="wpfc-meta">
					<li>
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php
						echo $vars['last']
							/* translators: %s: Relative time, e.g. "5 minutes ago". */
							? esc_html( sprintf( __( 'Inventory synced %s', 'multisite-fleet-manager' ), View::ago( $vars['last']['finished_at'] ) ) )
							: esc_html__( 'Inventory not synced yet', 'multisite-fleet-manager' );
						?>
					</li>
					<li>
						<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
						<?php
						echo $wpfc_checked
							/* translators: %s: Relative time, e.g. "2 hours ago". */
							? esc_html( sprintf( __( 'Update data from %s', 'multisite-fleet-manager' ), View::ago( gmdate( 'Y-m-d H:i:s', $wpfc_checked ) ) ) )
							: esc_html__( 'No update data yet', 'multisite-fleet-manager' );
						?>
					</li>
					<li>
						<span class="dashicons dashicons-wordpress" aria-hidden="true"></span>
						<?php
						/* translators: %s: WordPress version. */
						echo esc_html( sprintf( __( 'WordPress %s', 'multisite-fleet-manager' ), $wpfc_ctx->wp_version ) );
						?>
					</li>
				</ul>
			</div>

			<div class="wpfc-head__actions">
				<?php if ( $vars['can_update'] ) : ?>
					<a class="button wpfc-button-ghost" href="<?php echo esc_url( network_admin_url( 'update-core.php?force-check=1' ) ); ?>"><?php esc_html_e( 'Check for updates', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
				<?php if ( $vars['can_run'] ) : ?>
					<form id="wpfleet-discovery-form" method="post" action="<?php echo esc_url( Actions::url( Actions::DISCOVER ) ); ?>">
						<?php wp_nonce_field( Actions::DISCOVER ); ?>
						<input type="hidden" name="wpfleet_return" value="console" />
						<button type="submit" class="button button-primary" id="wpfleet-discovery-button"><?php esc_html_e( 'Refresh inventory', 'multisite-fleet-manager' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="wpfleet-progress wpfc-progress" id="wpfleet-progress" hidden>
				<progress id="wpfleet-progress-bar" max="100" value="0" aria-labelledby="wpfleet-progress-status"></progress>
				<p class="wpfleet-progress__status" id="wpfleet-progress-status" role="status" aria-live="polite"></p>
			</div>
		</div>

		<ul class="wpfc-tiles">
			<?php foreach ( $wpfc_tiles as $wpfc_tile ) : ?>
				<li class="wpfc-tile wpfc-tile--<?php echo esc_attr( $wpfc_tile['key'] ); ?><?php echo $wpfc_tile['current'] ? ' is-current' : ''; ?>">
					<a href="<?php echo esc_url( $wpfc_tile['url'] ); ?>"<?php echo $wpfc_tile['current'] ? ' aria-current="true"' : ''; ?>>
						<span class="wpfc-tile__label"><span class="dashicons <?php echo esc_attr( $wpfc_tile['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $wpfc_tile['label'] ); ?></span>
						<span class="wpfc-tile__value"><?php echo esc_html( View::number( $wpfc_tile['value'] ) ); ?></span>
						<?php if ( ! empty( $wpfc_tile['sub'] ) ) : ?>
							<span class="wpfc-tile__sub"><?php echo esc_html( $wpfc_tile['sub'] ); ?></span>
						<?php elseif ( $wpfc_health['total'] && 'all' !== $wpfc_tile['key'] ) : ?>
							<span class="wpfc-tile__sub">
								<?php
								/* translators: %s: Percentage. */
								echo esc_html( sprintf( __( '%s%% of fleet', 'multisite-fleet-manager' ), View::number( (int) round( $wpfc_tile['value'] / $wpfc_health['total'] * 100 ) ) ) );
								?>
							</span>
						<?php endif; ?>
						<?php if ( 'all' !== $wpfc_tile['key'] && ! isset( $wpfc_tile['sub'] ) && $wpfc_health['total'] ) : ?>
							<span class="wpfc-tile__meter" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) round( $wpfc_tile['value'] / $wpfc_health['total'] * 100 ) ); ?>%"></span></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php Notices::render(); ?>

	<?php if ( $wpfc_ctx->core_update ) : ?>
		<div class="notice notice-warning inline wpfc-notice">
			<p>
				<?php
				/* translators: %s: WordPress version. */
				echo esc_html( sprintf( __( 'WordPress %s is available. Every site of the network runs the same core files, so this update applies to the whole fleet.', 'multisite-fleet-manager' ), $wpfc_ctx->core_update ) );
				if ( current_user_can( 'update_core' ) ) {
					echo ' <a href="' . esc_url( network_admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Review updates', 'multisite-fleet-manager' ) . '</a>';
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( (int) $wpfc_health['unchecked'] > 0 ) : ?>
		<div class="notice notice-info inline wpfc-notice">
			<p>
				<?php
				/* translators: %s: Number of sites. */
				echo esc_html( sprintf( _n( '%s site has not been health-checked yet. Refresh the inventory to evaluate it.', '%s sites have not been health-checked yet. Refresh the inventory to evaluate them.', (int) $wpfc_health['unchecked'], 'multisite-fleet-manager' ), View::number( (int) $wpfc_health['unchecked'] ) ) );
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $vars['last'] ) : ?>
		<div class="wpfleet-panel wpfleet-empty">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No inventory yet', 'multisite-fleet-manager' ); ?></h2>
			<p><?php esc_html_e( 'Refresh the inventory to discover the sites of this network and evaluate their health.', 'multisite-fleet-manager' ); ?></p>
		</div>
	<?php else : ?>

	<section class="wpfc-panel" aria-labelledby="wpfc-table-title">
		<h2 id="wpfc-table-title" class="screen-reader-text"><?php esc_html_e( 'Sites', 'multisite-fleet-manager' ); ?></h2>

		<form class="wpfc-toolbar" method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) ); ?>" role="search" data-wpfc-filters>
			<input type="hidden" name="page" value="<?php echo esc_attr( FleetManager\Admin\Admin::SLUG_CONSOLE ); ?>" />
			<?php if ( 'health' !== $wpfc_state['orderby'] ) : ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $wpfc_state['orderby'] ); ?>" />
			<?php endif; ?>
			<?php if ( 'asc' !== $wpfc_state['order'] ) : ?>
				<input type="hidden" name="order" value="<?php echo esc_attr( $wpfc_state['order'] ); ?>" />
			<?php endif; ?>
			<?php if ( $wpfc_state['plugin'] ) : ?>
				<input type="hidden" name="plugin" value="<?php echo esc_attr( $wpfc_state['plugin'] ); ?>" />
			<?php endif; ?>
			<?php if ( $wpfc_state['indicator'] ) : ?>
				<input type="hidden" name="indicator" value="<?php echo esc_attr( $wpfc_state['indicator'] ); ?>" />
				<input type="hidden" name="state" value="<?php echo esc_attr( $wpfc_state['state'] ); ?>" />
			<?php endif; ?>

			<div class="wpfc-field wpfc-field--search">
				<label for="wpfc-search"><?php esc_html_e( 'Search', 'multisite-fleet-manager' ); ?></label>
				<span class="wpfc-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="wpfc-search" name="s" value="<?php echo esc_attr( $wpfc_state['s'] ); ?>" placeholder="<?php esc_attr_e( 'Name, address or theme', 'multisite-fleet-manager' ); ?>" />
				</span>
			</div>

			<div class="wpfc-field">
				<label for="wpfc-health"><?php esc_html_e( 'Health', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfc-health" name="health">
					<option value=""><?php esc_html_e( 'All health states', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( Health::all() as $wpfc_value ) : ?>
						<option value="<?php echo esc_attr( $wpfc_value ); ?>" <?php selected( $wpfc_state['health'], $wpfc_value ); ?>>
							<?php echo esc_html( sprintf( '%s (%s)', Health::label( $wpfc_value ), View::number( (int) $wpfc_health[ $wpfc_value ] ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpfc-field">
				<label for="wpfc-status"><?php esc_html_e( 'Status', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfc-status" name="site_status">
					<option value=""><?php esc_html_e( 'All statuses', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( SiteStatus::all() as $wpfc_value ) : ?>
						<option value="<?php echo esc_attr( $wpfc_value ); ?>" <?php selected( $wpfc_state['site_status'], $wpfc_value ); ?>>
							<?php echo esc_html( sprintf( '%s (%s)', SiteStatus::label( $wpfc_value ), View::number( (int) $vars['statuses'][ $wpfc_value ] ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpfc-field">
				<label for="wpfc-updates"><?php esc_html_e( 'Updates', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfc-updates" name="updates">
					<?php foreach ( $wpfc_update_filters as $wpfc_value => $wpfc_label ) : ?>
						<option value="<?php echo esc_attr( $wpfc_value ); ?>" <?php selected( $wpfc_state['updates'], $wpfc_value ); ?>><?php echo esc_html( $wpfc_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpfc-field">
				<label for="wpfc-theme"><?php esc_html_e( 'Theme', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfc-theme" name="theme">
					<option value=""><?php esc_html_e( 'All themes', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( $vars['themes'] as $wpfc_theme ) : ?>
						<option value="<?php echo esc_attr( $wpfc_theme['stylesheet'] ); ?>" <?php selected( $wpfc_state['theme'], $wpfc_theme['stylesheet'] ); ?>>
							<?php echo esc_html( sprintf( '%s (%s)', '' !== $wpfc_theme['name'] ? $wpfc_theme['name'] : $wpfc_theme['stylesheet'], View::number( $wpfc_theme['sites'] ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpfc-field wpfc-field--narrow">
				<label for="wpfc-per-page"><?php esc_html_e( 'Per page', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfc-per-page" name="per_page">
					<?php foreach ( FleetManager\Admin\ConsolePage::PER_PAGE_OPTIONS as $wpfc_value ) : ?>
						<option value="<?php echo esc_attr( (string) $wpfc_value ); ?>" <?php selected( $wpfc_state['per_page'], $wpfc_value ); ?>><?php echo esc_html( (string) $wpfc_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpfc-field wpfc-field--actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'multisite-fleet-manager' ); ?></button>
				<?php if ( $vars['has_filters'] ) : ?>
					<a class="button" href="<?php echo esc_url( FleetManager\Admin\Admin::url( FleetManager\Admin\Admin::SLUG_CONSOLE ) ); ?>"><?php esc_html_e( 'Reset', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<div class="wpfc-summary">
			<p class="wpfc-count" role="status">
				<?php
				if ( $vars['items'] ) {
					/* translators: 1: First item number, 2: Last item number, 3: Total number of sites. */
					echo esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s sites', 'multisite-fleet-manager' ), View::number( $wpfc_first ), View::number( $wpfc_last ), View::number( $wpfc_total ) ) );
				} elseif ( $wpfc_total ) {
					/* translators: %s: Total number of sites. */
					echo esc_html( sprintf( __( 'There are no sites on this page (%s sites in total).', 'multisite-fleet-manager' ), View::number( $wpfc_total ) ) );
				} else {
					esc_html_e( 'No sites match these filters.', 'multisite-fleet-manager' );
				}
				?>
			</p>
			<?php if ( $wpfc_chips ) : ?>
				<ul class="wpfc-chips" aria-label="<?php esc_attr_e( 'Active filters', 'multisite-fleet-manager' ); ?>">
					<?php foreach ( $wpfc_chips as $wpfc_chip ) : ?>
						<li>
							<a class="wpfc-chip" href="<?php echo esc_url( $wpfc_page->url( $wpfc_chip[1] ) ); ?>">
								<?php echo esc_html( $wpfc_chip[0] ); ?>
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( '(remove filter)', 'multisite-fleet-manager' ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- sort_header() escapes.
						echo $wpfc_page->sort_header( 'site', __( 'Site', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'status', __( 'Status', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'health', __( 'Health', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'wordpress', __( 'WordPress', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'theme', __( 'Active theme', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'plugin_updates', __( 'Plugin updates', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'theme_updates', __( 'Theme updates', 'multisite-fleet-manager' ) );
						echo $wpfc_page->sort_header( 'synced', __( 'Synced', 'multisite-fleet-manager' ) );
						// phpcs:enable
						?>
						<th scope="col" class="wpfc-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Details', 'multisite-fleet-manager' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $vars['items'] ) : ?>
						<tr class="wpfc-empty-row">
							<td colspan="9">
								<?php if ( $wpfc_total ) : ?>
									<a href="<?php echo esc_url( $wpfc_page->url( array( 'paged' => 1 ) ) ); ?>"><?php esc_html_e( 'Go to the first page', 'multisite-fleet-manager' ); ?></a>
								<?php else : ?>
									<?php esc_html_e( 'No sites match these filters.', 'multisite-fleet-manager' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>

					<?php foreach ( $vars['items'] as $wpfc_site ) : ?>
						<?php
						/** @var FleetManager\Sites\Site $wpfc_site */
						$wpfc_indicators = $wpfc_checks->describe( $wpfc_site->health_indicators );
						$wpfc_detail_id  = 'wpfc-detail-' . $wpfc_site->blog_id;
						$wpfc_health_key = '' !== $wpfc_site->health ? $wpfc_site->health : 'unchecked';
						$wpfc_by_id      = array_column( $wpfc_indicators, null, 'id' );
						$wpfc_problems   = array_filter( $wpfc_indicators, static fn( $i ) => in_array( $i['state'], array( Indicator::WARNING, Indicator::ERROR ), true ) );
						?>
						<tr class="wpfc-row wpfc-health--<?php echo esc_attr( $wpfc_health_key ); ?>">
							<td class="wpfc-col-site" data-label="<?php esc_attr_e( 'Site', 'multisite-fleet-manager' ); ?>">
								<span class="wpfc-site">
									<strong class="wpfc-site__name">
										<?php if ( $vars['can_manage'] ) : ?>
											<a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $wpfc_site->blog_id ) ); ?>"><?php echo esc_html( $wpfc_site->label() ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $wpfc_site->label() ); ?>
										<?php endif; ?>
									</strong>
									<span class="wpfc-site__address"><?php echo esc_html( View::address( $wpfc_site ) ); ?></span>
									<?php if ( $wpfc_site->is_main ) : ?>
										<span class="wpfleet-tag"><?php esc_html_e( 'Main site', 'multisite-fleet-manager' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Status', 'multisite-fleet-manager' ); ?>">
								<?php echo View::status_badge( $wpfc_site->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in View. ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Health', 'multisite-fleet-manager' ); ?>">
								<span class="wpfc-health">
									<span class="wpfc-pill wpfc-pill--<?php echo esc_attr( $wpfc_health_key ); ?>">
										<span class="dashicons <?php echo esc_attr( Health::icon( $wpfc_site->health ) ); ?>" aria-hidden="true"></span>
										<?php echo esc_html( Health::label( $wpfc_site->health ) ); ?>
									</span>
									<?php if ( $wpfc_indicators ) : ?>
										<span class="wpfc-dots" aria-hidden="true">
											<?php foreach ( $wpfc_indicators as $wpfc_ind ) : ?>
												<span class="wpfc-dot wpfc-dot--<?php echo esc_attr( $wpfc_ind['state'] ); ?>" title="<?php echo esc_attr( $wpfc_ind['label'] . ': ' . $wpfc_ind['message'] ); ?>"></span>
											<?php endforeach; ?>
										</span>
										<span class="wpfc-health__summary">
											<?php
											echo $wpfc_problems
												/* translators: %s: Number of issues. */
												? esc_html( sprintf( _n( '%s issue', '%s issues', count( $wpfc_problems ), 'multisite-fleet-manager' ), View::number( count( $wpfc_problems ) ) ) )
												/* translators: %s: Number of indicators. */
												: esc_html( sprintf( __( 'All %s checks passed', 'multisite-fleet-manager' ), View::number( count( $wpfc_indicators ) ) ) );
											?>
										</span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'WordPress', 'multisite-fleet-manager' ); ?>">
								<span class="wpfc-mono"><?php echo esc_html( $wpfc_ctx->wp_version ); ?></span>
								<?php if ( Indicator::WARNING === ( $wpfc_by_id['database_version']['state'] ?? '' ) ) : ?>
									<span class="wpfc-note wpfc-note--warning"><?php esc_html_e( 'Database upgrade needed', 'multisite-fleet-manager' ); ?></span>
								<?php elseif ( $wpfc_ctx->core_update ) : ?>
									<?php /* translators: %s: WordPress version. */ ?>
									<span class="wpfc-note"><?php echo esc_html( sprintf( __( '%s available', 'multisite-fleet-manager' ), $wpfc_ctx->core_update ) ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Active theme', 'multisite-fleet-manager' ); ?>">
								<?php if ( Indicator::ERROR === ( $wpfc_by_id['active_theme']['state'] ?? '' ) ) : ?>
									<span class="wpfc-theme is-broken"><?php echo esc_html( $wpfc_site->theme_stylesheet ); ?></span>
									<span class="wpfc-note wpfc-note--error"><?php esc_html_e( 'Missing or broken', 'multisite-fleet-manager' ); ?></span>
								<?php else : ?>
									<span class="wpfc-theme"><?php echo esc_html( '' !== $wpfc_site->theme_name ? $wpfc_site->theme_name : ( $wpfc_site->theme_stylesheet ? $wpfc_site->theme_stylesheet : '—' ) ); ?></span>
									<?php if ( $wpfc_site->theme_template && $wpfc_site->theme_template !== $wpfc_site->theme_stylesheet ) : ?>
										<?php /* translators: %s: Parent theme. */ ?>
										<span class="wpfc-note"><?php echo esc_html( sprintf( __( 'Child of %s', 'multisite-fleet-manager' ), $wpfc_site->theme_template ) ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<?php foreach ( array( 'plugin_updates' => $wpfc_site->plugin_updates, 'theme_updates' => $wpfc_site->theme_updates ) as $wpfc_col => $wpfc_count ) : ?>
								<td class="wpfc-num" data-label="<?php echo esc_attr( 'plugin_updates' === $wpfc_col ? __( 'Plugin updates', 'multisite-fleet-manager' ) : __( 'Theme updates', 'multisite-fleet-manager' ) ); ?>">
									<?php if ( null === $wpfc_count ) : ?>
										<span class="wpfc-count-badge is-unknown" title="<?php esc_attr_e( 'No update data yet', 'multisite-fleet-manager' ); ?>">—<span class="screen-reader-text"><?php esc_html_e( 'No update data yet', 'multisite-fleet-manager' ); ?></span></span>
									<?php else : ?>
										<span class="wpfc-count-badge<?php echo $wpfc_count > 0 ? ' has-updates' : ''; ?>"><?php echo esc_html( View::number( $wpfc_count ) ); ?></span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
							<td data-label="<?php esc_attr_e( 'Synced', 'multisite-fleet-manager' ); ?>">
								<span class="wpfc-muted" title="<?php echo esc_attr( View::datetime( $wpfc_site->synced_at ) ); ?>"><?php echo esc_html( View::ago( $wpfc_site->synced_at ) ); ?></span>
							</td>
							<td class="wpfc-col-actions">
								<button type="button" class="button button-small wpfc-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $wpfc_detail_id ); ?>" data-wpfc-toggle>
									<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
									<span class="wpfc-toggle__text"><?php esc_html_e( 'Details', 'multisite-fleet-manager' ); ?></span>
									<span class="screen-reader-text">
										<?php
										/* translators: %s: Site name. */
										echo esc_html( sprintf( __( 'for %s', 'multisite-fleet-manager' ), $wpfc_site->label() ) );
										?>
									</span>
								</button>
							</td>
						</tr>
						<tr class="wpfc-detail" id="<?php echo esc_attr( $wpfc_detail_id ); ?>" data-wpfc-detail>
							<td colspan="9">
								<div class="wpfc-detail__grid">
									<div>
										<h3><?php esc_html_e( 'Health indicators', 'multisite-fleet-manager' ); ?></h3>
										<?php if ( $wpfc_indicators ) : ?>
											<?php foreach ( $wpfc_checks->describe_by_category( $wpfc_site->health_indicators ) as $wpfc_cat => $wpfc_group ) : ?>
												<h4 class="wpfc-indicator-group">
													<span class="dashicons <?php echo esc_attr( FleetManager\Health\Category::icon( $wpfc_cat ) ); ?>" aria-hidden="true"></span>
													<?php echo esc_html( FleetManager\Health\Category::label( $wpfc_cat ) ); ?>
													<span class="wpfc-note"><?php echo esc_html( FleetManager\Health\Category::description( $wpfc_cat ) ); ?></span>
												</h4>
												<ul class="wpfc-indicators">
													<?php foreach ( $wpfc_group as $wpfc_ind ) : ?>
														<li class="wpfc-indicator wpfc-indicator--<?php echo esc_attr( $wpfc_ind['state'] ); ?>">
															<span class="wpfc-dot wpfc-dot--<?php echo esc_attr( $wpfc_ind['state'] ); ?>" aria-hidden="true"></span>
															<span>
																<strong><?php echo esc_html( $wpfc_ind['label'] ); ?></strong>
																<span class="screen-reader-text"><?php echo esc_html( View::indicator_state( $wpfc_ind['state'] ) ); ?></span>
																<?php echo esc_html( $wpfc_ind['message'] ); ?>
															</span>
														</li>
													<?php endforeach; ?>
												</ul>
												<?php if ( FleetManager\Health\Category::SECURITY === $wpfc_cat ) : ?>
													<p class="wpfc-note wpfc-caveat"><?php echo esc_html( FleetManager\Health\Category::security_caveat() ); ?></p>
												<?php endif; ?>
											<?php endforeach; ?>
										<?php else : ?>
											<p class="wpfc-muted"><?php esc_html_e( 'Not checked yet. Refresh the inventory to evaluate this site.', 'multisite-fleet-manager' ); ?></p>
										<?php endif; ?>
									</div>
									<div>
										<h3><?php esc_html_e( 'Site', 'multisite-fleet-manager' ); ?></h3>
										<dl class="wpfleet-facts">
											<dt><?php esc_html_e( 'Site ID', 'multisite-fleet-manager' ); ?></dt>
											<dd><?php echo esc_html( (string) $wpfc_site->blog_id ); ?></dd>
											<dt><?php esc_html_e( 'Active plugins', 'multisite-fleet-manager' ); ?></dt>
											<dd><?php echo esc_html( View::number( $wpfc_site->active_plugins ) ); ?></dd>
											<dt><?php esc_html_e( 'Database version', 'multisite-fleet-manager' ); ?></dt>
											<dd class="wpfc-mono"><?php echo esc_html( (string) $wpfc_site->db_version ); ?></dd>
											<dt><?php esc_html_e( 'Checked', 'multisite-fleet-manager' ); ?></dt>
											<dd><?php echo esc_html( View::datetime( $wpfc_site->health_checked_at ) ); ?></dd>
										</dl>
										<p class="wpfc-links">
											<?php if ( $vars['can_extend'] ) : ?>
												<a href="<?php echo esc_url( FleetManager\Admin\Admin::url( FleetManager\Admin\Admin::SLUG_EXTENSIONS, array( 'tab' => 'site', 'site' => $wpfc_site->blog_id ) ) ); ?>"><?php esc_html_e( 'Plugins & themes', 'multisite-fleet-manager' ); ?></a>
											<?php endif; ?>
											<?php if ( SiteStatus::ACTIVE === $wpfc_site->status && $wpfc_site->home_url ) : ?>
												<a href="<?php echo esc_url( $wpfc_site->home_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit site', 'multisite-fleet-manager' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'multisite-fleet-manager' ); ?></span></a>
											<?php endif; ?>
											<?php if ( $vars['can_manage'] ) : ?>
												<?php if ( '' !== $wpfc_site->admin_url() ) : ?><a href="<?php echo esc_url( $wpfc_site->admin_url() ); ?>"><?php esc_html_e( 'Site dashboard', 'multisite-fleet-manager' ); ?></a><?php endif; ?>
												<a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $wpfc_site->blog_id ) ); ?>"><?php esc_html_e( 'Edit site', 'multisite-fleet-manager' ); ?></a>
											<?php endif; ?>
										</p>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $wpfc_pages > 1 ) : ?>
			<nav class="wpfc-pagination" aria-label="<?php esc_attr_e( 'Sites pagination', 'multisite-fleet-manager' ); ?>">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'      => str_replace( '999999999', '%#%', esc_url( $wpfc_page->url( array( 'paged' => 999999999 ) ) ) ),
							'format'    => '',
							'current'   => $wpfc_state['paged'],
							'total'     => $wpfc_pages,
							'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html__( 'Previous page', 'multisite-fleet-manager' ) . '</span>',
							'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html__( 'Next page', 'multisite-fleet-manager' ) . '</span>',
							'mid_size'  => 2,
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</section>
	<?php endif; ?>
</div>
