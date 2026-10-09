<?php
/**
 * Operation log template.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by LogPage::render().
 */

use FleetManager\Admin\Admin;
use FleetManager\Admin\View;
use FleetManager\Operations\Operation;

defined( 'ABSPATH' ) || exit;

/** @var FleetManager\Admin\LogPage $wpfl_page */
$wpfl_page  = $vars['page'];
$wpfl_state = $vars['state'];
$wpfl_total = (int) $vars['total'];
$wpfl_pages = (int) ceil( $wpfl_total / 25 );
?>
<div class="wrap wpfleet wpfc">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Operation Log', 'multisite-fleet-manager' ); ?></h1>
	<hr class="wp-header-end">

	<section class="wpfc-head wpfc-head--compact" aria-labelledby="wpfl-title">
		<div class="wpfc-head__bar">
			<div class="wpfc-head__title">
				<p class="wpfc-eyebrow"><?php echo esc_html( get_network()->site_name ); ?></p>
				<h2 id="wpfl-title"><?php esc_html_e( 'Operation Log', 'multisite-fleet-manager' ); ?></h2>
				<p class="wpfc-meta"><?php esc_html_e( 'Every update and activation change made through Fleet Manager, including refused and denied attempts. Entries cannot be edited.', 'multisite-fleet-manager' ); ?></p>
			</div>
		</div>
	</section>

	<section class="wpfc-panel">
		<form class="wpfc-toolbar" method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) ); ?>" role="search">
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG_LOG ); ?>" />
			<?php if ( $wpfl_state['site_id'] ) : ?>
				<input type="hidden" name="site_id" value="<?php echo esc_attr( (string) $wpfl_state['site_id'] ); ?>" />
			<?php endif; ?>
			<div class="wpfc-field wpfc-field--search">
				<label for="wpfc-search"><?php esc_html_e( 'Search', 'multisite-fleet-manager' ); ?></label>
				<span class="wpfc-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="wpfc-search" name="s" value="<?php echo esc_attr( $wpfl_state['s'] ); ?>" placeholder="<?php esc_attr_e( 'Plugin, theme or message', 'multisite-fleet-manager' ); ?>" />
				</span>
			</div>
			<div class="wpfc-field">
				<label for="wpfl-operation"><?php esc_html_e( 'Operation', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfl-operation" name="operation">
					<option value=""><?php esc_html_e( 'All operations', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( Operation::all() as $wpfl_op ) : ?>
						<option value="<?php echo esc_attr( $wpfl_op ); ?>" <?php selected( $wpfl_state['operation'], $wpfl_op ); ?>><?php echo esc_html( Operation::label( $wpfl_op ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field">
				<label for="wpfl-status"><?php esc_html_e( 'Result', 'multisite-fleet-manager' ); ?></label>
				<select id="wpfl-status" name="status">
					<option value=""><?php esc_html_e( 'All results', 'multisite-fleet-manager' ); ?></option>
					<?php foreach ( Operation::statuses() as $wpfl_status ) : ?>
						<option value="<?php echo esc_attr( $wpfl_status ); ?>" <?php selected( $wpfl_state['status'], $wpfl_status ); ?>><?php echo esc_html( Operation::status_label( $wpfl_status ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpfc-field wpfc-field--actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'multisite-fleet-manager' ); ?></button>
				<?php if ( $wpfl_state['operation'] || $wpfl_state['status'] || $wpfl_state['s'] || $wpfl_state['site_id'] ) : ?>
					<a class="button" href="<?php echo esc_url( Admin::url( Admin::SLUG_LOG ) ); ?>"><?php esc_html_e( 'Reset', 'multisite-fleet-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<div class="wpfc-summary">
			<p class="wpfc-count" role="status">
				<?php
				/* translators: %s: Number of entries. */
				echo esc_html( sprintf( _n( '%s entry', '%s entries', $wpfl_total, 'multisite-fleet-manager' ), View::number( $wpfl_total ) ) );
				if ( $wpfl_state['site_id'] ) {
					/* translators: %s: Site ID. */
					echo ' ' . esc_html( sprintf( __( 'for site #%s', 'multisite-fleet-manager' ), $wpfl_state['site_id'] ) );
				}
				?>
			</p>
		</div>

		<div class="wpfc-table-wrap">
			<table class="wpfc-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'When', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Operation', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Target', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Site', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Version', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'User', 'multisite-fleet-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'multisite-fleet-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $vars['items'] ) : ?>
						<tr class="wpfc-empty-row"><td colspan="8"><?php esc_html_e( 'No operations recorded.', 'multisite-fleet-manager' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $vars['items'] as $wpfl_row ) : ?>
						<?php
						$wpfl_user = $wpfl_row['user_id'] ? get_userdata( $wpfl_row['user_id'] ) : false;
						$wpfl_site = $wpfl_row['site_id'] ? get_site( $wpfl_row['site_id'] ) : null;
						$wpfl_kind = array(
							Operation::SUCCESS  => 'active',
							Operation::FAILED   => 'deleted',
							Operation::REJECTED => 'spam',
							Operation::DENIED   => 'deleted',
						);
						?>
						<tr class="wpfc-row">
							<td data-label="<?php esc_attr_e( 'When', 'multisite-fleet-manager' ); ?>"><span title="<?php echo esc_attr( View::datetime( $wpfl_row['created_at'] ) ); ?>"><?php echo esc_html( View::ago( $wpfl_row['created_at'] ) ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Result', 'multisite-fleet-manager' ); ?>">
								<span class="wpfleet-badge wpfleet-status--<?php echo esc_attr( $wpfl_kind[ $wpfl_row['status'] ] ?? 'archived' ); ?>"><span class="dashicons <?php echo esc_attr( Operation::status_icon( $wpfl_row['status'] ) ); ?>" aria-hidden="true"></span><?php echo esc_html( Operation::status_label( $wpfl_row['status'] ) ); ?></span>
							</td>
							<td data-label="<?php esc_attr_e( 'Operation', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( Operation::label( $wpfl_row['operation'] ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Target', 'multisite-fleet-manager' ); ?>">
								<span class="wpfc-site">
									<strong><?php echo esc_html( '' !== $wpfl_row['target_name'] ? $wpfl_row['target_name'] : ( '' !== $wpfl_row['target'] ? $wpfl_row['target'] : '—' ) ); ?></strong>
									<?php if ( '' !== $wpfl_row['target_name'] && $wpfl_row['target'] !== $wpfl_row['target_name'] ) : ?>
										<span class="wpfc-site__address wpfc-mono"><?php echo esc_html( $wpfl_row['target'] ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Site', 'multisite-fleet-manager' ); ?>">
								<?php if ( ! $wpfl_row['site_id'] ) : ?>
									<span class="wpfc-muted"><?php esc_html_e( 'Whole network', 'multisite-fleet-manager' ); ?></span>
								<?php elseif ( $wpfl_site ) : ?>
									<a href="<?php echo esc_url( $wpfl_page->url( array( 'site_id' => $wpfl_row['site_id'] ) ) ); ?>"><?php echo esc_html( untrailingslashit( $wpfl_site->domain . $wpfl_site->path ) ); ?></a>
								<?php else : ?>
									<?php /* translators: %s: Site ID. */ ?>
									<span class="wpfc-muted"><?php echo esc_html( sprintf( __( 'Site #%s (deleted)', 'multisite-fleet-manager' ), $wpfl_row['site_id'] ) ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Version', 'multisite-fleet-manager' ); ?>" class="wpfc-mono">
								<?php
								if ( Operation::SUCCESS === $wpfl_row['status'] && $wpfl_row['from_version'] && $wpfl_row['to_version'] && $wpfl_row['from_version'] !== $wpfl_row['to_version'] ) {
									echo esc_html( $wpfl_row['from_version'] . ' → ' . $wpfl_row['to_version'] );
								} else {
									echo esc_html( $wpfl_row['from_version'] ? $wpfl_row['from_version'] : '—' );
								}
								?>
							</td>
							<td data-label="<?php esc_attr_e( 'User', 'multisite-fleet-manager' ); ?>"><?php echo esc_html( $wpfl_user ? $wpfl_user->user_login : '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Details', 'multisite-fleet-manager' ); ?>" class="wpfl-message">
								<?php echo esc_html( (string) $wpfl_row['message'] ); ?>
								<?php if ( ! empty( $wpfl_row['context']['source'] ) ) : ?>
									<?php /* translators: %s: Source, e.g. "rest" or "form". */ ?>
									<span class="wpfc-note"><?php echo esc_html( sprintf( __( 'via %s', 'multisite-fleet-manager' ), 'rest' === $wpfl_row['context']['source'] ? __( 'dashboard', 'multisite-fleet-manager' ) : __( 'form', 'multisite-fleet-manager' ) ) ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $wpfl_pages > 1 ) : ?>
			<nav class="wpfc-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'multisite-fleet-manager' ); ?>">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'      => str_replace( '999999999', '%#%', esc_url( $wpfl_page->url( array( 'paged' => 999999999 ) ) ) ),
							'format'    => '',
							'current'   => $wpfl_state['paged'],
							'total'     => $wpfl_pages,
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
