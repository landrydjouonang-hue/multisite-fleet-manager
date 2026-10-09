<?php
/**
 * Developer diagnostics template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by DiagnosticsPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\View;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpfleet wpfc wpfd">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Fleet Manager Diagnostics', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head wpfc-head--compact" aria-labelledby="wpfd-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( get_network()->site_name ); ?></p>
				<h2 id="wpfd-title"><?php esc_html_e( 'Diagnostics', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-meta"><?php esc_html_e( 'The plugin\'s own state, its extension points, and a report you can paste into a bug report.', 'multisite-fleet-manager' ); ?></p>
			</div>
			<div class="wpfc-head__actions">
				<a class="button wpfc-button-ghost" href="<?php echo esc_url( Actions::export_url( 'diagnostics' ) ); ?>"><?php esc_html_e( 'Download report', 'multisite-fleet-manager' ); ?></a>
			</div>
		</div>
	</section>

	<?php View::render( 'admin/partials/operation-results', $vars ); ?>

	<div class="wpfc-panel wpfd-checks">
		<div class="wpfc-summary"><h2 class="wpfx-panel-title"><?php esc_html_e( 'Self-checks', 'multisite-fleet-manager' ); ?></h2></div>
		<ul class="wpfd-check-list">
			<?php foreach ( $vars['checks'] as $wpfd_check ) : ?>
				<li class="wpfd-check wpfd-check--<?php echo $wpfd_check['ok'] ? 'ok' : 'bad'; ?>">
					<span class="dashicons <?php echo $wpfd_check['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					<span>
						<strong><?php echo esc_html( $wpfd_check['label'] ); ?></strong>
						<span class="screen-reader-text"><?php echo $wpfd_check['ok'] ? esc_html__( '(passed)', 'multisite-fleet-manager' ) : esc_html__( '(needs attention)', 'multisite-fleet-manager' ); ?></span>
						<?php echo esc_html( $wpfd_check['detail'] ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="wpfleet-grid wpfd-grid">
		<?php foreach ( $vars['state'] as $wpfd_section => $wpfd_rows ) : ?>
			<section class="wpfleet-panel">
				<h2><?php echo esc_html( $wpfd_section ); ?></h2>
				<dl class="wpfleet-facts">
					<?php foreach ( $wpfd_rows as $wpfd_label => $wpfd_value ) : ?>
						<dt><?php echo esc_html( $wpfd_label ); ?></dt>
						<dd class="wpfc-mono"><?php echo esc_html( $wpfd_value ); ?></dd>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endforeach; ?>
	</div>

	<section class="wpfc-panel wpfd-panel">
		<div class="wpfc-summary"><h2 class="wpfx-panel-title"><?php esc_html_e( 'REST routes', 'multisite-fleet-manager' ); ?></h2></div>
		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Route', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Methods', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Requires', 'multisite-fleet-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $vars['routes'] as $wpfd_route ) : ?>
						<tr class="wpfc-row">
							<td class="wpfc-mono" data-label="<?php esc_attr_e( 'Route', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( $wpfd_route[0] ); ?></td>
							<td data-label="<?php esc_attr_e( 'Methods', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( $wpfd_route[1] ); ?></td>
							<td class="wpfc-mono" data-label="<?php esc_attr_e( 'Requires', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( $wpfd_route[2] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<div class="wpfleet-grid wpfd-grid">
		<?php foreach ( $vars['hooks'] as $wpfd_kind => $wpfd_hooks ) : ?>
			<section class="wpfleet-panel">
				<h2><?php echo esc_html( $wpfd_kind ); ?></h2>
				<dl class="wpfleet-facts wpfd-hooks">
					<?php foreach ( $wpfd_hooks as $wpfd_hook ) : ?>
						<dt class="wpfc-mono"><?php echo esc_html( $wpfd_hook[0] ); ?></dt>
						<dd><?php echo esc_html( $wpfd_hook[1] ); ?></dd>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endforeach; ?>
	</div>

	<section class="wpfc-panel wpfd-panel">
		<div class="wpfc-summary">
			<h2 class="wpfx-panel-title"><?php esc_html_e( 'Report', 'multisite-fleet-manager' ); ?></h2>
			<p class="wpfc-count"><?php esc_html_e( 'Versions, counts and configuration only — no site names, user names or addresses.', 'multisite-fleet-manager' ); ?></p>
		</div>
		<div class="wpfd-report">
			<label class="screen-reader-text" for="wpfd-report"><?php esc_html_e( 'Diagnostic report', 'multisite-fleet-manager' ); ?></label>
			<textarea id="wpfd-report" rows="18" readonly onclick="this.select();"><?php echo esc_textarea( $vars['report'] ); ?></textarea>
			<p class="wpfc-note"><?php esc_html_e( 'Click the report to select it all, then copy.', 'multisite-fleet-manager' ); ?></p>
		</div>
	</section>
</div>
