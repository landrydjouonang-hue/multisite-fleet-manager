<?php
/**
 * Network users template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by UsersPage::render().
 */

use FleetManager\Admin\Admin;
use FleetManager\Admin\View;
use FleetManager\Operations\Operation;
use FleetManager\Users\UserDirectory;

defined( 'ABSPATH' ) || exit;

/** @var FleetManager\Admin\UsersPage $wpfu_page */
$wpfu_page    = $vars['page'];
$wpfu_state   = $vars['state'];
$wpfu_summary = $vars['summary'];
$wpfu_total   = (int) $vars['total'];
$wpfu_pages   = (int) ceil( $wpfu_total / max( 1, $wpfu_state['per_page'] ) );
$wpfu_first   = $wpfu_total ? ( $wpfu_state['paged'] - 1 ) * $wpfu_state['per_page'] + 1 : 0;

$wpfu_tiles = array(
	array( '', __( 'Network users', 'multisite-fleet-manager' ), $wpfu_summary['total'], 'dashicons-groups', 'all' ),
	array( 'super', __( 'Super administrators', 'multisite-fleet-manager' ), $wpfu_summary['super'], 'dashicons-star-filled', 'plugins' ),
	array( '', __( 'Active in 30 days', 'multisite-fleet-manager' ), $wpfu_summary['active'], 'dashicons-clock', 'healthy' ),
	array( 'no_site', __( 'Without a site', 'multisite-fleet-manager' ), $wpfu_summary['no_site'], 'dashicons-marker', 'attention' ),
	array( 'spam', __( 'Spam or deleted', 'multisite-fleet-manager' ), $wpfu_summary['flagged'], 'dashicons-dismiss', 'error' ),
);
?>
<div class="wrap wpfleet wpfc wpfu">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Network Users', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head" aria-labelledby="wpfu-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( get_network()->site_name ); ?></p>
				<h2 id="wpfu-title"><?php esc_html_e( 'Network Users', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-meta"><?php esc_html_e( 'User accounts are shared by the whole network; roles are per site. Fleet Manager never shows passwords or sign-in secrets.', 'multisite-fleet-manager' ); ?></p>
			</div>
			<div class="wpfc-head__actions">
				<?php if ( current_user_can( 'create_users' ) ) : ?>
					<a class="button wpfc-button-ghost" href="<?php echo esc_url( network_admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add user (Network Admin)', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
				<?php if ( $vars['can_log'] ) : ?>
					<a class="button wpfc-button-ghost" href="<?php echo esc_url( Admin::url( Admin::SLUG_LOG ) ); ?>"><?php esc_html_e( 'Operation log', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<ul class="wpfc-tiles wpfc-tiles--5">
			<?php foreach ( $wpfu_tiles as $wpfu_tile ) : ?>
				<?php $wpfu_current = '' !== $wpfu_tile[0] && $wpfu_state['status'] === $wpfu_tile[0]; ?>
				<li class="wpfc-tile wpfc-tile--<?php echo esc_attr( $wpfu_tile[4] ); ?><?php echo $wpfu_current ? ' is-current' : ''; ?>">
					<a href="<?php echo esc_url( $wpfu_page->url( array( 'status' => '' === $wpfu_tile[0] ? null : $wpfu_tile[0] ) ) ); ?>"<?php echo $wpfu_current ? ' aria-current="true"' : ''; ?>>
						<span class="wpfc-tile__label"><span class="dashicons <?php echo esc_attr( $wpfu_tile[3] ); ?>" aria-hidden="true"></span><?php echo esc_html( $wpfu_tile[1] ); ?></span>
						<span class="wpfc-tile__value"><?php echo esc_html( View::number( (int) $wpfu_tile[2] ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php View::render( 'admin/partials/operation-results', $vars ); ?>

	<?php if ( ! $vars['indexed'] ) : ?>
		<div class="notice notice-info inline wpfc-notice">
			<p><?php esc_html_e( 'Roles and site memberships appear after the first site discovery. Run it from the Fleet Manager dashboard.', 'multisite-fleet-manager' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $vars['skipped'] ) : ?>
		<div class="notice notice-info inline wpfc-notice">
			<p>
				<?php
				/* translators: %s: Number of sites. */
				echo esc_html( sprintf( _n( '%s site has too many users to index, so its memberships are not listed here. Use its own Users screen.', '%s sites have too many users to index, so their memberships are not listed here. Use their own Users screens.', count( $vars['skipped'] ), 'multisite-fleet-manager' ), View::number( count( $vars['skipped'] ) ) ) );
				?>
			</p>
		</div>
	<?php endif; ?>

	<section class="wpfc-panel">
		<form class="wpfc-toolbar" method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) ); ?>" role="search">
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG_USERS ); ?>" />
			<div class="wpfc-field wpfc-field--search">
				<label for="wpfc-search"><?php esc_html_e( 'Search', 'multisite-fleet-manager' ); ?></label>
				<span class="wpfc-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="wpfc-search" name="s" value="<?php echo esc_attr( $wpfu_state['s'] ); ?>" placeholder="<?php esc_attr_e( 'Login, name or e-mail', 'multisite-fleet-manager' ); ?>" />
				</span>
			</div>
			<div class="wpfc-field">
				<label for="wpfu-role"><?php esc_html_e( 'Role', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfu-role" name="role">
					<option value=""><?php esc_html_e( 'Any role', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( $vars['roles'] as $wpfu_role => $wpfu_count ) : ?>
						<?php if ( 'none' === $wpfu_role ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<option value="<?php echo esc_attr( $wpfu_role ); ?>" <?php selected( $wpfu_state['role'], $wpfu_role ); ?>>
							<?php echo esc_html( sprintf( '%s (%s)', UserDirectory::role_label( $wpfu_role ), View::number( (int) $wpfu_count ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field">
				<label for="wpfu-site"><?php esc_html_e( 'Site', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfu-site" name="site_id">
					<option value="0"><?php esc_html_e( 'Any site', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( $vars['sites'] as $wpfu_site ) : ?>
						<option value="<?php echo esc_attr( (string) $wpfu_site->blog_id ); ?>" <?php selected( $wpfu_state['site_id'], $wpfu_site->blog_id ); ?>><?php echo esc_html( $wpfu_site->label() ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field">
				<label for="wpfu-status"><?php esc_html_e( 'Status', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfu-status" name="status">
					<option value=""><?php esc_html_e( 'All users', 'multisite-fleet-manager' ); ?></option>
					<option value="super" <?php selected( $wpfu_state['status'], 'super' ); ?>><?php esc_html_e( 'Super administrators', 'multisite-fleet-manager' ); ?></option>
					<option value="no_site" <?php selected( $wpfu_state['status'], 'no_site' ); ?>><?php esc_html_e( 'Without a site', 'multisite-fleet-manager' ); ?></option>
					<option value="spam" <?php selected( $wpfu_state['status'], 'spam' ); ?>><?php esc_html_e( 'Marked as spam', 'multisite-fleet-manager' ); ?></option>
					<option value="deleted" <?php selected( $wpfu_state['status'], 'deleted' ); ?>><?php esc_html_e( 'Marked as deleted', 'multisite-fleet-manager' ); ?></option>
				</select>
			</div>
			<div class="wpfc-field wpfc-field--narrow">
				<label for="wpfu-per-page"><?php esc_html_e( 'Per page', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfu-per-page" name="per_page">
					<?php foreach ( FleetManager\Admin\UsersPage::PER_PAGE_OPTIONS as $wpfu_value ) : ?>
						<option value="<?php echo esc_attr( (string) $wpfu_value ); ?>" <?php selected( $wpfu_state['per_page'], $wpfu_value ); ?>><?php echo esc_html( (string) $wpfu_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field wpfc-field--actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'multisite-fleet-manager' ); ?></button>
				<?php if ( $wpfu_state['s'] || $wpfu_state['role'] || $wpfu_state['site_id'] || $wpfu_state['status'] ) : ?>
					<a class="button" href="<?php echo esc_url( Admin::url( Admin::SLUG_USERS ) ); ?>"><?php esc_html_e( 'Reset', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<div class="wpfc-summary">
			<p class="wpfc-count" role="status">
				<?php
				if ( $vars['items'] ) {
					/* translators: 1: First item, 2: Last item, 3: Total. */
					echo esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s users', 'multisite-fleet-manager' ), View::number( $wpfu_first ), View::number( $wpfu_first + count( $vars['items'] ) - 1 ), View::number( $wpfu_total ) ) );
				} else {
					esc_html_e( 'No users match these filters.', 'multisite-fleet-manager' );
				}
				?>
			</p>
		</div>

		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- sort_header() escapes.
						echo $wpfu_page->sort_header( 'login', __( 'User', 'multisite-fleet-manager' ) );
						?>
						<th scope="col"><?php esc_html_e( 'Roles', 'multisite-fleet-manager' ); ?></th>
						<?php
						echo $wpfu_page->sort_header( 'sites', __( 'Sites', 'multisite-fleet-manager' ) );
						echo $wpfu_page->sort_header( 'activity', __( 'Last activity', 'multisite-fleet-manager' ) );
						echo $wpfu_page->sort_header( 'registered', __( 'Registered', 'multisite-fleet-manager' ) );
						// phpcs:enable
						?>
						<th scope="col" class="wpfc-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'multisite-fleet-manager' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $vars['items'] ) : ?>
						<tr class="wpfc-empty-row"><td colspan="6"><?php esc_html_e( 'No users match these filters.', 'multisite-fleet-manager' ); ?></td></tr>
					<?php endif; ?>

					<?php foreach ( $vars['items'] as $wpfu_user ) : ?>
						<tr class="wpfc-row<?php echo $wpfu_user['super_admin'] ? ' wpfu-super' : ''; ?>">
							<td data-label="<?php esc_attr_e( 'User', 'multisite-fleet-manager' ); ?>">
								<span class="wpfu-user">
									<span class="wpfu-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( mb_substr( '' !== $wpfu_user['name'] ? $wpfu_user['name'] : $wpfu_user['login'], 0, 1 ) ) ); ?></span>
									<span class="wpfc-site">
										<strong><a href="<?php echo esc_url( $wpfu_page->url( array( 'user' => $wpfu_user['id'] ) ) ); ?>"><?php echo esc_html( $wpfu_user['name'] ); ?></a></strong>
										<span class="wpfc-site__address"><?php echo esc_html( $wpfu_user['login'] ); ?></span>
										<span class="wpfleet-tags">
											<?php if ( $wpfu_user['super_admin'] ) : ?>
												<span class="wpfleet-tag wpfu-tag--super"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Super admin', 'multisite-fleet-manager' ); ?></span>
											<?php endif; ?>
											<?php if ( $wpfu_user['spam'] ) : ?>
												<span class="wpfleet-tag wpfx-tag--error"><?php esc_html_e( 'Spam', 'multisite-fleet-manager' ); ?></span>
											<?php endif; ?>
											<?php if ( $wpfu_user['deleted'] ) : ?>
												<span class="wpfleet-tag wpfx-tag--error"><?php esc_html_e( 'Deleted', 'multisite-fleet-manager' ); ?></span>
											<?php endif; ?>
										</span>
									</span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Roles', 'multisite-fleet-manager' ); ?>">
								<?php if ( ! $wpfu_user['roles'] ) : ?>
									<span class="wpfc-muted"><?php esc_html_e( 'None', 'multisite-fleet-manager' ); ?></span>
								<?php else : ?>
									<ul class="wpfu-roles">
										<?php foreach ( $wpfu_user['roles'] as $wpfu_role => $wpfu_count ) : ?>
											<li>
												<span class="wpfleet-tag"><?php echo esc_html( UserDirectory::role_label( $wpfu_role ) ); ?></span>
												<?php /* translators: %s: Number of sites. */ ?>
												<span class="wpfc-muted"><?php echo esc_html( sprintf( _n( 'on %s site', 'on %s sites', $wpfu_count, 'multisite-fleet-manager' ), View::number( $wpfu_count ) ) ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Sites', 'multisite-fleet-manager' ); ?>">
								<?php if ( ! $wpfu_user['site_count'] ) : ?>
									<span class="wpfc-muted"><?php esc_html_e( 'No site', 'multisite-fleet-manager' ); ?></span>
								<?php else : ?>
									<span class="wpfc-count-badge"><?php echo esc_html( View::number( $wpfu_user['site_count'] ) ); ?></span>
									<span class="wpfc-note">
										<?php
										$wpfu_names = array_slice( array_map( static fn( $s ) => '' !== $s['name'] ? $s['name'] : $s['address'], $wpfu_user['sites'] ), 0, 2 );
										echo esc_html( implode( ', ', $wpfu_names ) );
										if ( $wpfu_user['site_count'] > 2 ) {
											/* translators: %s: Number of further sites. */
											echo esc_html( ' ' . sprintf( __( '+%s more', 'multisite-fleet-manager' ), View::number( $wpfu_user['site_count'] - 2 ) ) );
										}
										?>
									</span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Last activity', 'multisite-fleet-manager' ); ?>">
								<?php View::render( 'admin/partials/user-activity', array( 'activity' => $wpfu_user['activity'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Registered', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( View::date( $wpfu_user['registered'] ) ); ?></td>
							<td class="wpfc-col-actions">
								<a class="button button-small" href="<?php echo esc_url( $wpfu_page->url( array( 'user' => $wpfu_user['id'] ) ) ); ?>">
									<?php echo esc_html( $vars['can_manage'] ? __( 'Manage', 'multisite-fleet-manager' ) : __( 'View', 'multisite-fleet-manager' ) ); ?>
									<span class="screen-reader-text"><?php echo esc_html( $wpfu_user['login'] ); ?></span>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $wpfu_pages > 1 ) : ?>
			<nav class="wpfc-pagination" aria-label="<?php esc_attr_e( 'Users pagination', 'multisite-fleet-manager' ); ?>">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'      => str_replace( '999999999', '%#%', esc_url( $wpfu_page->url( array( 'paged' => 999999999 ) ) ) ),
							'format'    => '',
							'current'   => $wpfu_state['paged'],
							'total'     => $wpfu_pages,
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
