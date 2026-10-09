<?php
/**
 * One user: profile facts, memberships and management controls.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by UsersPage::render().
 */

use FleetManager\Admin\Actions;
use FleetManager\Admin\Admin;
use FleetManager\Admin\View;
use FleetManager\Operations\Operation;
use FleetManager\Users\UserDirectory;

defined( 'ABSPATH' ) || exit;

/** @var FleetManager\Admin\UsersPage $wpfd_page */
$wpfd_page   = $vars['page'];
$wpfd_user   = $vars['user'];
$wpfd_manage = $vars['can_manage'];
$wpfd_return = $wpfd_page->url( array( 'user' => $wpfd_user['id'] ) );
$wpfd_nonces = array( Operation::USER_ADD, Operation::USER_REMOVE, Operation::USER_ROLE );

/**
 * Hidden fields shared by every management form on this page.
 *
 * @param int $site_id Site the form acts on.
 * @param int $user_id User the form acts on.
 * @return void
 */
$wpfd_fields = static function ( int $site_id, int $user_id ) use ( $wpfd_return, $wpfd_nonces ): void {
	printf( '<input type="hidden" name="site_id" value="%d" />', $site_id );
	printf( '<input type="hidden" name="return" value="%s" />', esc_url( $wpfd_return ) );
	foreach ( $wpfd_nonces as $operation ) {
		printf(
			'<input type="hidden" name="nonce[%1$s]" value="%2$s" />',
			esc_attr( $operation ),
			esc_attr( wp_create_nonce( Operation::nonce_action( $operation ) ) )
		);
	}
	unset( $user_id );
};
?>
<div class="wrap wpfleet wpfc wpfu">
	<h1 class="screen-reader-text"><?php echo esc_html( $wpfd_user['name'] ); ?></h1>
	<hr class="wp-header-end">

	<p class="wpfx-back">
		<a href="<?php echo esc_url( Admin::url( Admin::SLUG_USERS ) ); ?>">&larr; <?php esc_html_e( 'Network Users', 'multisite-fleet-manager' ); ?></a>
		<?php if ( current_user_can( 'edit_users' ) ) : ?>
			<a href="<?php echo esc_url( network_admin_url( 'user-edit.php?user_id=' . $wpfd_user['id'] ) ); ?>"><?php esc_html_e( 'Edit account (Network Admin)', 'multisite-fleet-manager' ); ?></a>
		<?php endif; ?>
		<?php if ( $vars['can_log'] ) : ?>
			<a href="<?php echo esc_url( Admin::url( Admin::SLUG_LOG, array( 's' => $wpfd_user['login'] ) ) ); ?>"><?php esc_html_e( 'Operations for this user', 'multisite-fleet-manager' ); ?></a>
		<?php endif; ?>
	</p>

	<section class="wpfc-head wpfc-head--compact" aria-labelledby="wpfd-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php esc_html_e( 'Network user', 'multisite-fleet-manager' ); ?></p>
				<h2 id="wpfd-title"><?php echo esc_html( $wpfd_user['name'] ); ?></h2>
				<ul class="wpfc-meta">
					<li><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><?php echo esc_html( $wpfd_user['login'] ); ?></li>
					<?php if ( ! empty( $wpfd_user['email'] ) && is_super_admin() ) : ?>
						<li><span class="dashicons dashicons-email" aria-hidden="true"></span><?php echo esc_html( $wpfd_user['email'] ); ?></li>
					<?php endif; ?>
					<li>
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<?php
						/* translators: %s: Date. */
						echo esc_html( sprintf( __( 'Registered %s', 'multisite-fleet-manager' ), View::date( $wpfd_user['registered'] ) ) );
						?>
					</li>
					<?php if ( $wpfd_user['super_admin'] ) : ?>
						<li><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Super administrator', 'multisite-fleet-manager' ); ?></li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</section>

	<?php View::render( 'admin/partials/operation-results', $vars ); ?>

	<?php if ( $wpfd_user['super_admin'] ) : ?>
		<div class="notice notice-info inline wpfc-notice">
			<p>
				<?php esc_html_e( 'As a super administrator, this user can reach every site of the network whatever their roles below. Super administrator status is granted in Network Admin → Users.', 'multisite-fleet-manager' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="wpfx-site-grid">
		<section class="wpfc-panel" aria-labelledby="wpfd-sites-title">
			<div class="wpfc-summary">
				<h2 id="wpfd-sites-title" class="wpfx-panel-title"><?php esc_html_e( 'Sites and roles', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-count">
					<?php
					/* translators: %s: Number of sites. */
					echo esc_html( sprintf( _n( 'Member of %s site', 'Member of %s sites', $wpfd_user['site_count'], 'multisite-fleet-manager' ), View::number( $wpfd_user['site_count'] ) ) );
					?>
				</p>
			</div>

			<div class="wpfc-table-wrap">
				<table class="wpfc-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Site', 'multisite-fleet-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Role', 'multisite-fleet-manager' ); ?></th>
							<th scope="col" class="wpfc-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'multisite-fleet-manager' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $wpfd_user['sites'] ) : ?>
							<tr class="wpfc-empty-row"><td colspan="3"><?php esc_html_e( 'This user does not belong to any site of this network.', 'multisite-fleet-manager' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $wpfd_user['sites'] as $wpfd_site ) : ?>
							<?php
							$wpfd_id    = (int) $wpfd_site['site_id'];
							$wpfd_roles = $vars['site_roles'][ $wpfd_id ] ?? array();
							$wpfd_has   = $wpfd_site['roles'][0] ?? '';
							?>
							<tr class="wpfc-row">
								<td data-label="<?php esc_attr_e( 'Site', 'multisite-fleet-manager' ); ?>">
									<span class="wpfc-site">
										<strong>
											<?php if ( current_user_can( 'manage_sites' ) ) : ?>
												<a href="<?php echo esc_url( network_admin_url( 'site-users.php?id=' . $wpfd_id ) ); ?>"><?php echo esc_html( '' !== $wpfd_site['name'] ? $wpfd_site['name'] : $wpfd_site['address'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( '' !== $wpfd_site['name'] ? $wpfd_site['name'] : $wpfd_site['address'] ); ?>
											<?php endif; ?>
										</strong>
										<span class="wpfc-site__address"><?php echo esc_html( $wpfd_site['address'] ); ?></span>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Role', 'multisite-fleet-manager' ); ?>">
									<?php if ( ! $wpfd_manage || ! $wpfd_roles ) : ?>
										<?php foreach ( $wpfd_site['roles'] ?: array( 'none' ) as $wpfd_role ) : ?>
											<span class="wpfleet-tag"><?php echo esc_html( UserDirectory::role_label( $wpfd_role ) ); ?></span>
										<?php endforeach; ?>
									<?php else : ?>
										<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" class="wpfu-role-form">
											<?php $wpfd_fields( $wpfd_id, (int) $wpfd_user['id'] ); ?>
											<label class="screen-reader-text" for="wpfu-role-<?php echo esc_attr( (string) $wpfd_id ); ?>">
												<?php
												/* translators: %s: Site name. */
												echo esc_html( sprintf( __( 'Role on %s', 'multisite-fleet-manager' ), $wpfd_site['name'] ) );
												?>
											</label>
											<select name="role" id="wpfu-role-<?php echo esc_attr( (string) $wpfd_id ); ?>">
												<?php foreach ( $wpfd_roles as $wpfd_slug => $wpfd_label ) : ?>
													<option value="<?php echo esc_attr( $wpfd_slug ); ?>" <?php selected( $wpfd_has, $wpfd_slug ); ?>><?php echo esc_html( $wpfd_label ); ?></option>
												<?php endforeach; ?>
											</select>
											<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( Operation::USER_ROLE . '|' . $wpfd_user['id'] ); ?>"><?php esc_html_e( 'Change', 'multisite-fleet-manager' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
								<td class="wpfc-col-actions">
									<?php if ( $wpfd_manage ) : ?>
										<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" onsubmit="return window.confirm(this.dataset.confirm);" data-confirm="<?php esc_attr_e( 'Remove this user from the site? Their content stays, but they lose access.', 'multisite-fleet-manager' ); ?>">
											<?php $wpfd_fields( $wpfd_id, (int) $wpfd_user['id'] ); ?>
											<button type="submit" class="button button-small" name="single" value="<?php echo esc_attr( Operation::USER_REMOVE . '|' . $wpfd_user['id'] ); ?>">
												<?php esc_html_e( 'Remove', 'multisite-fleet-manager' ); ?>
												<span class="screen-reader-text">
													<?php
													/* translators: %s: Site name. */
													echo esc_html( sprintf( __( 'from %s', 'multisite-fleet-manager' ), $wpfd_site['name'] ) );
													?>
												</span>
											</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $wpfd_manage && $vars['available'] ) : ?>
				<div class="wpfu-add">
					<h3><?php esc_html_e( 'Add to a site', 'multisite-fleet-manager' ); ?></h3>
					<form method="post" action="<?php echo esc_url( Actions::url( Actions::OPERATION ) ); ?>" class="wpfu-add__form">
						<input type="hidden" name="return" value="<?php echo esc_url( $wpfd_return ); ?>" />
						<?php foreach ( $wpfd_nonces as $wpfd_operation ) : ?>
							<input type="hidden" name="nonce[<?php echo esc_attr( $wpfd_operation ); ?>]" value="<?php echo esc_attr( wp_create_nonce( Operation::nonce_action( $wpfd_operation ) ) ); ?>" />
						<?php endforeach; ?>
						<div class="wpfc-field">
							<label for="wpfu-add-site"><?php esc_html_e( 'Site', 'multisite-fleet-manager' ); ?></label>
							<select id="wpfu-add-site" name="site_id" required>
								<?php foreach ( $vars['available'] as $wpfd_site_id => $wpfd_available ) : ?>
									<option value="<?php echo esc_attr( (string) $wpfd_site_id ); ?>"><?php echo esc_html( $wpfd_available['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wpfc-field">
							<label for="wpfu-add-role"><?php esc_html_e( 'Role', 'multisite-fleet-manager' ); ?></label>
							<select id="wpfu-add-role" name="role" required>
								<?php
								// Roles come from the site chosen on submit; the common set is offered here.
								$wpfd_first = reset( $vars['available'] );
								foreach ( $wpfd_first['roles'] as $wpfd_slug => $wpfd_label ) :
									?>
									<option value="<?php echo esc_attr( $wpfd_slug ); ?>" <?php selected( 'subscriber', $wpfd_slug ); ?>><?php echo esc_html( $wpfd_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wpfc-field wpfc-field--actions">
							<button type="submit" class="button button-primary" name="single" value="<?php echo esc_attr( Operation::USER_ADD . '|' . $wpfd_user['id'] ); ?>"><?php esc_html_e( 'Add to site', 'multisite-fleet-manager' ); ?></button>
						</div>
					</form>
					<p class="wpfc-note"><?php esc_html_e( 'If the chosen site does not have that role, the request is refused and nothing changes.', 'multisite-fleet-manager' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<section class="wpfc-panel" aria-labelledby="wpfd-activity-title">
			<div class="wpfc-summary"><h2 id="wpfd-activity-title" class="wpfx-panel-title"><?php esc_html_e( 'Activity', 'multisite-fleet-manager' ); ?></h2></div>
			<div class="wpfx-theme-card__body">
				<?php View::render( 'admin/partials/user-activity', array( 'activity' => $wpfd_user['activity'] ) ); ?>

				<dl class="wpfleet-facts wpfu-facts">
					<dt><?php esc_html_e( 'Sites', 'multisite-fleet-manager' ); ?></dt>
					<dd><?php echo esc_html( View::number( $wpfd_user['site_count'] ) ); ?></dd>
					<dt><?php esc_html_e( 'Super administrator', 'multisite-fleet-manager' ); ?></dt>
					<dd><?php echo esc_html( $wpfd_user['super_admin'] ? __( 'Yes', 'multisite-fleet-manager' ) : __( 'No', 'multisite-fleet-manager' ) ); ?></dd>
					<dt><?php esc_html_e( 'Network status', 'multisite-fleet-manager' ); ?></dt>
					<dd>
						<?php
						if ( $wpfd_user['spam'] ) {
							esc_html_e( 'Marked as spam', 'multisite-fleet-manager' );
						} elseif ( $wpfd_user['deleted'] ) {
							esc_html_e( 'Marked as deleted', 'multisite-fleet-manager' );
						} else {
							esc_html_e( 'Normal', 'multisite-fleet-manager' );
						}
						?>
					</dd>
					<?php if ( ! empty( $wpfd_user['activity']['site_id'] ) ) : ?>
						<dt><?php esc_html_e( 'Signed in on', 'multisite-fleet-manager' ); ?></dt>
						<dd><?php echo esc_html( (string) get_blog_option( (int) $wpfd_user['activity']['site_id'], 'blogname', '' ) ); ?></dd>
					<?php endif; ?>
				</dl>

				<p class="wpfc-note"><?php esc_html_e( 'Fleet Manager shows no passwords, password hashes, sign-in tokens or application passwords.', 'multisite-fleet-manager' ); ?></p>
			</div>
		</section>
	</div>
</div>
