<?php
/**
 * Printable network report.
 *
 * Plain, self-contained markup meant for paper or "Save as PDF": no admin
 * chrome, no navigation, every figure visible without interaction.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by ReportsPage::render().
 */

use FleetManager\Admin\ReportsPage;
use FleetManager\Admin\View;
use FleetManager\Health\Health;
use FleetManager\Sites\SiteStatus;

defined( 'ABSPATH' ) || exit;

$wpfp         = $vars['summary'];
$wpfp_sites   = (int) $wpfp['sites']['total'];
$wpfp_updates = $wpfp['updates'];
?>
<div class="wrap wpfleet wpfr-print">
	<div class="wpfr-print__actions">
		<button type="button" class="button button-primary" onclick="window.print();"><?php esc_html_e( 'Print or save as PDF', 'multisite-fleet-manager' ); ?></button>
		<a class="button" href="<?php echo esc_url( ReportsPage::url() ); ?>"><?php esc_html_e( 'Back to the report', 'multisite-fleet-manager' ); ?></a>
		<span class="wpfr-print__hint"><?php esc_html_e( 'In the print dialog, choose "Save as PDF" as the destination.', 'multisite-fleet-manager' ); ?></span>
	</div>

	<header class="wpfr-print__header">
		<h1><?php esc_html_e( 'Network report', 'multisite-fleet-manager' ); ?></h1>
		<p class="wpfr-print__sub">
			<strong><?php echo esc_html( $wpfp['network']['name'] ); ?></strong>
			<?php echo esc_html( $wpfp['network']['address'] ); ?>
		</p>
		<p class="wpfr-print__meta">
			<?php
			/* translators: %s: Date and time. */
			echo esc_html( sprintf( __( 'Generated %s', 'multisite-fleet-manager' ), View::datetime( $wpfp['generated_at'] ) ) );
			if ( $wpfp['discovery'] ) {
				/* translators: %s: Date and time. */
				echo ' · ' . esc_html( sprintf( __( 'inventory synced %s', 'multisite-fleet-manager' ), View::datetime( $wpfp['discovery']['finished_at'] ) ) );
			}
			?>
		</p>
	</header>

	<section class="wpfr-print__section">
		<h2><?php esc_html_e( 'Headline figures', 'multisite-fleet-manager' ); ?></h2>
		<table class="wpfr-print__table">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Total sites', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( $wpfp_sites ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Sites needing updates', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp_updates['sites_needing_updates'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Sites with errors', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp['errors']['sites_with_errors'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Plugin updates', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp_updates['plugin_updates'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Theme updates', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp_updates['theme_updates'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Sites by status', 'multisite-fleet-manager' ); ?></th>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: Active, 2: Archived, 3: Spam, 4: Deactivated. */
								__( '%1$s active, %2$s archived, %3$s spam, %4$s deactivated', 'multisite-fleet-manager' ),
								View::number( (int) $wpfp['sites']['active'] ),
								View::number( (int) $wpfp['sites']['archived'] ),
								View::number( (int) $wpfp['sites']['spam'] ),
								View::number( (int) $wpfp['sites']['deleted'] )
							)
						);
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</section>

	<section class="wpfr-print__section">
		<h2><?php esc_html_e( 'Health distribution', 'multisite-fleet-manager' ); ?></h2>
		<?php View::render( 'admin/partials/health-distribution', array( 'health' => $wpfp['health'], 'total' => $wpfp_sites ) ); ?>
		<?php if ( $wpfp['errors']['top_indicators'] ) : ?>
			<h3><?php esc_html_e( 'Most common issues', 'multisite-fleet-manager' ); ?></h3>
			<table class="wpfr-print__table">
				<thead><tr><th scope="col"><?php esc_html_e( 'Indicator', 'multisite-fleet-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Error', 'multisite-fleet-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Needs attention', 'multisite-fleet-manager' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $wpfp['errors']['top_indicators'] as $wpfp_issue ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $wpfp_issue['label'] ); ?></th>
							<td><?php echo esc_html( View::number( $wpfp_issue['error'] ) ); ?></td>
							<td><?php echo esc_html( View::number( $wpfp_issue['warning'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section class="wpfr-print__section">
		<h2><?php esc_html_e( 'Environment', 'multisite-fleet-manager' ); ?></h2>
		<table class="wpfr-print__table">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'WordPress', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( $wpfp['network']['wp_version'] . ( $wpfp['network']['core_update'] ? ' → ' . $wpfp['network']['core_update'] : '' ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'PHP', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( $wpfp['network']['php_version'] ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $wpfp['network']['is_mariadb'] ? __( 'MariaDB', 'multisite-fleet-manager' ) : __( 'MySQL', 'multisite-fleet-manager' ) ); ?></th><td><?php echo esc_html( $wpfp['network']['db_server'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Sites on HTTPS', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp['environment']['https_sites'] ) . ' / ' . View::number( $wpfp_sites ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Sites with overdue tasks', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp['environment']['cron_overdue'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Database size', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( null === $wpfp['environment']['database_bytes'] ? __( 'Not available', 'multisite-fleet-manager' ) : size_format( (int) $wpfp['environment']['database_bytes'], 1 ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Network users', 'multisite-fleet-manager' ); ?></th><td><?php echo esc_html( View::number( (int) $wpfp['network']['users'] ) ); ?></td></tr>
			</tbody>
		</table>
	</section>

	<?php if ( $vars['updates'] ) : ?>
		<section class="wpfr-print__section">
			<h2><?php esc_html_e( 'Pending updates', 'multisite-fleet-manager' ); ?></h2>
			<table class="wpfr-print__table wpfr-print__table--grid">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Item', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Installed', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Available', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sites', 'multisite-fleet-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $vars['updates'] as $wpfp_update ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $wpfp_update['name'] ); ?></th>
							<td><?php echo esc_html( 'plugin' === $wpfp_update['type'] ? __( 'Plugin', 'multisite-fleet-manager' ) : __( 'Theme', 'multisite-fleet-manager' ) ); ?></td>
							<td><?php echo esc_html( $wpfp_update['version'] ); ?></td>
							<td><?php echo esc_html( $wpfp_update['new_version'] ); ?></td>
							<td><?php echo esc_html( View::number( (int) $wpfp_update['sites'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section class="wpfr-print__section">
		<h2><?php esc_html_e( 'Sites', 'multisite-fleet-manager' ); ?></h2>
		<table class="wpfr-print__table wpfr-print__table--grid">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site', 'multisite-fleet-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'multisite-fleet-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Health', 'multisite-fleet-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Plugin updates', 'multisite-fleet-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Theme updates', 'multisite-fleet-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Issues', 'multisite-fleet-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $vars['sites'] as $wpfp_site ) : ?>
					<tr>
						<th scope="row">
							<?php echo esc_html( $wpfp_site['name'] ); ?>
							<span class="wpfr-print__address"><?php echo esc_html( $wpfp_site['address'] ); ?></span>
						</th>
						<td><?php echo esc_html( SiteStatus::label( $wpfp_site['status'] ) ); ?></td>
						<td><?php echo esc_html( Health::label( 'unchecked' === $wpfp_site['health'] ? '' : $wpfp_site['health'] ) ); ?></td>
						<td><?php echo esc_html( null === $wpfp_site['plugin_updates'] ? '—' : View::number( (int) $wpfp_site['plugin_updates'] ) ); ?></td>
						<td><?php echo esc_html( null === $wpfp_site['theme_updates'] ? '—' : View::number( (int) $wpfp_site['theme_updates'] ) ); ?></td>
						<td><?php echo esc_html( $wpfp_site['issues'] ? (string) count( $wpfp_site['issues'] ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>

	<footer class="wpfr-print__footer">
		<p><?php echo esc_html( $wpfp['scope'] ); ?></p>
		<p>
			<?php
			/* translators: %s: Plugin name. */
			echo esc_html( sprintf( __( 'Produced by %s.', 'multisite-fleet-manager' ), 'Multisite Fleet Manager' ) );
			?>
		</p>
	</footer>
</div>
