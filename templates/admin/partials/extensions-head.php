<?php
/**
 * Plugins & Themes: shared header (band, tabs, environment and results notices).
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Provided by ExtensionsPage::render(); may include 'tiles' and 'subtitle'.
 */

use FleetManager\Admin\Admin;
use FleetManager\Admin\View;
use FleetManager\Operations\Operation;

defined( 'ABSPATH' ) || exit;

/** @var FleetManager\Admin\ExtensionsPage $wpfx_page */
$wpfx_page  = $vars['page'];
$wpfx_state = $vars['state'];
$wpfx_tabs  = array(
	'plugins' => __( 'Plugins', 'multisite-fleet-manager' ),
	'themes'  => __( 'Themes', 'multisite-fleet-manager' ),
);
?>
<h1 class="screen-reader-text"><?php esc_html_e( 'Plugins & Themes', 'multisite-fleet-manager' ); ?></h1>
<hr class="wp-header-end">

<section class="wpfc-head" aria-labelledby="wpfx-title">
	<div class="wpfc-head__bar">
		<div class="wpfc-head__title">
			<p class="wpfc-eyebrow"><?php echo esc_html( get_network()->site_name ); ?></p>
			<h2 id="wpfx-title"><?php echo esc_html( $vars['title'] ?? __( 'Plugins & Themes', 'multisite-fleet-manager' ) ); ?></h2>
			<?php if ( ! empty( $vars['subtitle'] ) ) : ?>
				<p class="wpfc-meta"><?php echo esc_html( $vars['subtitle'] ); ?></p>
			<?php else : ?>
				<p class="wpfc-meta"><?php esc_html_e( 'Plugin and theme files are shared by every site of the network: an update applies to all of them. Activation is per site.', 'multisite-fleet-manager' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="wpfc-head__actions">
			<?php if ( $vars['can_log'] ) : ?>
				<a class="button wpfc-button-ghost" href="<?php echo esc_url( Admin::url( Admin::SLUG_LOG ) ); ?>"><?php esc_html_e( 'Operation log', 'multisite-fleet-manager' ); ?></a>
			<?php endif; ?>
			<?php if ( current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) : ?>
				<a class="button wpfc-button-ghost" href="<?php echo esc_url( network_admin_url( 'update-core.php?force-check=1' ) ); ?>"><?php esc_html_e( 'Check for updates', 'multisite-fleet-manager' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $vars['tiles'] ) ) : ?>
		<ul class="wpfc-tiles wpfc-tiles--5">
			<?php foreach ( $vars['tiles'] as $wpfx_tile ) : ?>
				<li class="wpfc-tile wpfc-tile--<?php echo esc_attr( $wpfx_tile['accent'] ); ?><?php echo $wpfx_tile['current'] ? ' is-current' : ''; ?>">
					<a href="<?php echo esc_url( $wpfx_tile['url'] ); ?>"<?php echo $wpfx_tile['current'] ? ' aria-current="true"' : ''; ?>>
						<span class="wpfc-tile__label"><span class="dashicons <?php echo esc_attr( $wpfx_tile['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $wpfx_tile['label'] ); ?></span>
						<span class="wpfc-tile__value"><?php echo esc_html( View::number( (int) $wpfx_tile['value'] ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ( 'site' !== $wpfx_state['tab'] ) : ?>
	<nav class="nav-tab-wrapper wpfx-tabs" aria-label="<?php esc_attr_e( 'Extension type', 'multisite-fleet-manager' ); ?>">
		<?php foreach ( $wpfx_tabs as $wpfx_key => $wpfx_label ) : ?>
			<a href="<?php echo esc_url( Admin::url( Admin::SLUG_EXTENSIONS, 'plugins' === $wpfx_key ? array() : array( 'tab' => $wpfx_key ) ) ); ?>" class="nav-tab<?php echo $wpfx_state['tab'] === $wpfx_key ? ' nav-tab-active' : ''; ?>"<?php echo $wpfx_state['tab'] === $wpfx_key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $wpfx_label ); ?></a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php View::render( 'admin/partials/operation-results', $vars ); ?>

<?php
// Core itself removes update_plugins/update_themes when file changes are disallowed,
// so the explanation is shown to everyone holding Fleet Manager's update capability.
if ( $vars['blocker'] && current_user_can( FleetManager\Core\Capabilities::MANAGE_UPDATES ) ) :
	?>
	<div class="notice notice-info inline wpfc-notice">
		<p><strong><?php esc_html_e( 'Updates are not available from Fleet Manager here.', 'multisite-fleet-manager' ); ?></strong> <?php echo esc_html( $vars['blocker'] ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! $vars['inventoried'] ) : ?>
	<div class="notice notice-info inline wpfc-notice">
		<p><?php esc_html_e( 'Site counts appear after the first site discovery. Run it from the Fleet Manager dashboard.', 'multisite-fleet-manager' ); ?></p>
	</div>
<?php endif; ?>
