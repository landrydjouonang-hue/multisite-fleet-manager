<?php
/**
 * Network settings template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by SettingsPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\View;
use FleetManager\Core\Capabilities;
use FleetManager\Operations\Operation;

defined( 'ABSPATH' ) || exit;

$wpfs = $vars['settings'];

/**
 * Renders a number field.
 *
 * @param string $key   Setting key.
 * @param string $label Label.
 * @param string $help  Description.
 * @param int    $value Current value.
 * @param string $unit  Unit shown after the field.
 * @return void
 */
$wpfs_number = static function ( string $key, string $label, string $help, int $value, string $unit = '' ): void {
	printf(
		'<tr><th scope="row"><label for="wpfs-%1$s">%2$s</label></th><td><input type="number" class="small-text" id="wpfs-%1$s" name="settings[%1$s]" value="%3$d" /> %4$s<p class="description">%5$s</p></td></tr>',
		esc_attr( $key ),
		esc_html( $label ),
		(int) $value,
		esc_html( $unit ),
		esc_html( $help )
	);
};
?>
<div class="wrap wpfleet wpfc wpfs">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Fleet Manager Settings', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head wpfc-head--compact" aria-labelledby="wpfs-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( get_network()->site_name ); ?></p>
				<h2 id="wpfs-title"><?php esc_html_e( 'Settings', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-meta"><?php esc_html_e( 'These are defaults. Anything here can also be set in code with the matching filter, which then wins.', 'multisite-fleet-manager' ); ?></p>
			</div>
		</div>
	</section>

	<?php View::render( 'admin/partials/operation-results', $vars ); ?>

	<?php if ( $vars['can_save'] ) : ?>
		<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" class="wpfc-panel wpfs-form">
			<input type="hidden" name="return" value="<?php echo esc_url( FleetManager\Admin\Admin::url( FleetManager\Admin\Admin::SLUG_SETTINGS ) ); ?>" />
			<input type="hidden" name="nonce[<?php echo esc_attr( Operation::SETTINGS_UPDATE ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( Operation::SETTINGS_UPDATE ) ) ); ?>" />

			<h2 class="wpfs-section"><?php esc_html_e( 'Discovery', 'multisite-fleet-manager' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfs-recurrence"><?php esc_html_e( 'How often', 'multisite-fleet-manager' ); ?></label></th>
					<td>
						<select id="wpfs-recurrence" name="settings[discovery_recurrence]">
							<?php foreach ( $vars['schedules'] as $wpfs_key => $wpfs_schedule ) : ?>
								<option value="<?php echo esc_attr( $wpfs_key ); ?>" <?php selected( $wpfs['discovery_recurrence'], $wpfs_key ); ?>><?php echo esc_html( $wpfs_schedule['display'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Discovery also runs on demand, and the inventory updates in real time as sites change.', 'multisite-fleet-manager' ); ?></p>
					</td>
				</tr>
				<?php $wpfs_number( 'discovery_batch_size', __( 'Sites per batch', 'multisite-fleet-manager' ), __( 'Lower this if discovery times out on a large network.', 'multisite-fleet-manager' ), (int) $wpfs['discovery_batch_size'] ); ?>
			</table>

			<h2 class="wpfs-section"><?php esc_html_e( 'History', 'multisite-fleet-manager' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$wpfs_number( 'log_retention_days', __( 'Keep log entries', 'multisite-fleet-manager' ), __( 'Operation log entries older than this are deleted daily.', 'multisite-fleet-manager' ), (int) $wpfs['log_retention_days'], __( 'days', 'multisite-fleet-manager' ) );
				$wpfs_number( 'snapshot_retention_days', __( 'Keep snapshots', 'multisite-fleet-manager' ), __( 'Report snapshots older than this are deleted daily.', 'multisite-fleet-manager' ), (int) $wpfs['snapshot_retention_days'], __( 'days', 'multisite-fleet-manager' ) );
				$wpfs_number( 'snapshot_interval_hours', __( 'Snapshot interval', 'multisite-fleet-manager' ), __( 'Minimum time between automatic snapshots. 0 captures one after every discovery run.', 'multisite-fleet-manager' ), (int) $wpfs['snapshot_interval_hours'], __( 'hours', 'multisite-fleet-manager' ) );
				?>
			</table>

			<h2 class="wpfs-section"><?php esc_html_e( 'Notifications', 'multisite-fleet-manager' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Send e-mail', 'multisite-fleet-manager' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Notifications', 'multisite-fleet-manager' ); ?></legend>
							<label><input type="checkbox" name="settings[notifications_enabled]" value="1" <?php checked( $wpfs['notifications_enabled'] ); ?> /> <?php esc_html_e( 'Send a digest of the fleet', 'multisite-fleet-manager' ); ?></label><br />
							<label><input type="checkbox" name="settings[notify_errors]" value="1" <?php checked( $wpfs['notify_errors'] ); ?> /> <?php esc_html_e( 'Include sites with errors', 'multisite-fleet-manager' ); ?></label><br />
							<label><input type="checkbox" name="settings[notify_updates]" value="1" <?php checked( $wpfs['notify_updates'] ); ?> /> <?php esc_html_e( 'Include sites needing updates', 'multisite-fleet-manager' ); ?></label><br />
							<label><input type="checkbox" name="settings[notify_failed_ops]" value="1" <?php checked( $wpfs['notify_failed_ops'] ); ?> /> <?php esc_html_e( 'Mail immediately when an operation fails', 'multisite-fleet-manager' ); ?></label>
							<p class="description"><?php esc_html_e( 'Nothing is sent while the digest is off. A digest with nothing to report is skipped.', 'multisite-fleet-manager' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfs-frequency"><?php esc_html_e( 'Digest frequency', 'multisite-fleet-manager' ); ?></label></th>
					<td>
						<select id="wpfs-frequency" name="settings[notification_frequency]">
							<option value="weekly" <?php selected( $wpfs['notification_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'multisite-fleet-manager' ); ?></option>
							<option value="daily" <?php selected( $wpfs['notification_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'multisite-fleet-manager' ); ?></option>
						</select>
						<p class="description">
							<?php
							echo $vars['next']
								/* translators: %s: Date and time. */
								? esc_html( sprintf( __( 'Next digest: %s', 'multisite-fleet-manager' ), View::datetime( gmdate( 'Y-m-d H:i:s', (int) $vars['next'] ) ) ) )
								: esc_html__( 'No digest is scheduled.', 'multisite-fleet-manager' );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfs-recipients"><?php esc_html_e( 'Recipients', 'multisite-fleet-manager' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wpfs-recipients" name="settings[notification_recipients]" value="<?php echo esc_attr( $wpfs['notification_recipients'] ); ?>" />
						<p class="description">
							<?php
							/* translators: %s: E-mail address. */
							echo esc_html( sprintf( __( 'Comma separated. Leave empty to use the network admin address (%s).', 'multisite-fleet-manager' ), (string) get_site_option( 'admin_email', '' ) ) );
							?>
						</p>
					</td>
				</tr>
			</table>

			<h2 class="wpfs-section"><?php esc_html_e( 'Indicator thresholds', 'multisite-fleet-manager' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfs-php"><?php esc_html_e( 'Recommended PHP', 'multisite-fleet-manager' ); ?></label></th>
					<td>
						<input type="text" class="small-text" id="wpfs-php" name="settings[recommended_php]" value="<?php echo esc_attr( $wpfs['recommended_php'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Sites below this are flagged. Keep it in step with the versions PHP still supports.', 'multisite-fleet-manager' ); ?></p>
					</td>
				</tr>
				<?php
				$wpfs_number( 'memory_warning_mb', __( 'Memory warning below', 'multisite-fleet-manager' ), __( 'PHP memory_limit under this is flagged.', 'multisite-fleet-manager' ), (int) $wpfs['memory_warning_mb'], 'MB' );
				$wpfs_number( 'autoload_warning_kb', __( 'Autoloaded options above', 'multisite-fleet-manager' ), __( 'Loaded on every page view of a site.', 'multisite-fleet-manager' ), (int) $wpfs['autoload_warning_kb'], 'KB' );
				$wpfs_number( 'database_warning_mb', __( 'Site database above', 'multisite-fleet-manager' ), __( 'Per-site database size that counts as large.', 'multisite-fleet-manager' ), (int) $wpfs['database_warning_mb'], 'MB' );
				$wpfs_number( 'active_plugin_warning', __( 'Active plugins above', 'multisite-fleet-manager' ), __( 'Site plugins plus network-activated ones.', 'multisite-fleet-manager' ), (int) $wpfs['active_plugin_warning'] );
				$wpfs_number( 'many_administrators', __( 'Administrators above', 'multisite-fleet-manager' ), __( 'Administrators on one site before it is flagged.', 'multisite-fleet-manager' ), (int) $wpfs['many_administrators'] );
				?>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" name="single" value="<?php echo esc_attr( Operation::SETTINGS_UPDATE . '|settings' ); ?>"><?php esc_html_e( 'Save settings', 'multisite-fleet-manager' ); ?></button>
			</p>
		</form>
	<?php endif; ?>

	<?php if ( $vars['can_access'] ) : ?>
		<section class="wpfc-panel wpfs-access" aria-labelledby="wpfs-access-title">
			<div class="wpfc-summary">
				<h2 id="wpfs-access-title" class="wpfx-panel-title"><?php esc_html_e( 'Delegated access', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-count"><?php esc_html_e( 'Super admins always hold every capability. These are extra people.', 'multisite-fleet-manager' ); ?></p>
			</div>

			<div class="wpfc-table-wrap">
				<table class="wpfc-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'User', 'multisite-fleet-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Capabilities', 'multisite-fleet-manager' ); ?></th>
							<th scope="col" class="wpfc-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'multisite-fleet-manager' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $vars['grants'] ) : ?>
							<tr class="wpfc-empty-row"><td colspan="3"><?php esc_html_e( 'Nobody outside the super admins has Fleet Manager access.', 'multisite-fleet-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $vars['grants'] as $wpfs_grant ) : ?>
							<tr class="wpfc-row">
								<td data-label="<?php esc_attr_e( 'User', 'multisite-fleet-manager' ); ?>">
									<span class="wpfc-site">
										<strong><?php echo esc_html( $wpfs_grant['user']->display_name ); ?></strong>
										<span class="wpfc-site__address"><?php echo esc_html( $wpfs_grant['user']->user_login ); ?></span>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Capabilities', 'multisite-fleet-manager' ); ?>">
									<?php foreach ( $wpfs_grant['caps'] as $wpfs_cap ) : ?>
										<span class="wpfleet-tag"><?php echo esc_html( $vars['caps'][ $wpfs_cap ] ?? $wpfs_cap ); ?></span>
									<?php endforeach; ?>
								</td>
								<td class="wpfc-col-actions">
									<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" onsubmit="return window.confirm(this.dataset.confirm);" data-confirm="<?php esc_attr_e( 'Remove this user\'s Fleet Manager access?', 'multisite-fleet-manager' ); ?>">
										<input type="hidden" name="return" value="<?php echo esc_url( FleetManager\Admin\Admin::url( FleetManager\Admin\Admin::SLUG_SETTINGS ) ); ?>" />
										<input type="hidden" name="nonce[<?php echo esc_attr( Operation::ACCESS_UPDATE ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( Operation::ACCESS_UPDATE ) ) ); ?>" />
										<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $wpfs_grant['user']->ID ); ?>" />
										<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( Operation::ACCESS_UPDATE . '|' . $wpfs_grant['user']->ID ); ?>"><?php esc_html_e( 'Remove', 'multisite-fleet-manager' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="wpfu-add">
				<h3><?php esc_html_e( 'Give someone access', 'multisite-fleet-manager' ); ?></h3>
				<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" class="wpfs-grant">
					<input type="hidden" name="return" value="<?php echo esc_url( FleetManager\Admin\Admin::url( FleetManager\Admin\Admin::SLUG_SETTINGS ) ); ?>" />
					<input type="hidden" name="nonce[<?php echo esc_attr( Operation::ACCESS_UPDATE ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( Operation::ACCESS_UPDATE ) ) ); ?>" />
					<p>
						<label for="wpfs-user"><?php esc_html_e( 'User ID or login', 'multisite-fleet-manager' ); ?></label><br />
						<input type="text" id="wpfs-user" name="user_login" class="regular-text" required />
					</p>
					<fieldset>
						<legend><?php esc_html_e( 'Capabilities', 'multisite-fleet-manager' ); ?></legend>
						<?php foreach ( $vars['grantable'] as $wpfs_cap ) : ?>
							<label><input type="checkbox" name="caps[]" value="<?php echo esc_attr( $wpfs_cap ); ?>" /> <?php echo esc_html( $vars['caps'][ $wpfs_cap ] ?? $wpfs_cap ); ?></label><br />
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Only read-only capabilities and running discovery can be delegated. Updates, user management and settings stay with super admins.', 'multisite-fleet-manager' ); ?></p>
					<p>
						<button type="submit" class="button button-primary" name="single" value="<?php echo esc_attr( Operation::ACCESS_UPDATE . '|0' ); ?>"><?php esc_html_e( 'Grant access', 'multisite-fleet-manager' ); ?></button>
					</p>
				</form>
			</div>
		</section>
	<?php endif; ?>
</div>
