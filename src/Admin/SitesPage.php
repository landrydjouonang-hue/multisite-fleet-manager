<?php
/**
 * Site inventory screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the inventory with WP_List_Table.
 */
final class SitesPage {

	private Plugin $plugin;

	private ?SitesListTable $table = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Runs on load-{page}: screen options and the table.
	 *
	 * @return void
	 */
	public function load(): void {
		if ( ! current_user_can( Capabilities::VIEW_SITES ) ) {
			return;
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Sites per page', 'multisite-fleet-manager' ),
				'default' => 20,
				'option'  => 'wpfleet_sites_per_page',
			)
		);

		$this->table = new SitesListTable( $this->plugin->sites() );
		$this->table->prepare_items();
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->table || ! current_user_can( Capabilities::VIEW_SITES ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}

		$last = $this->plugin->discovery()->last();
		?>
		<div class="wrap wpfleet">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Site Inventory', 'multisite-fleet-manager' ); ?></h1>
			<?php
			if ( $this->table->search_term() ) {
				printf(
					'<span class="subtitle">%s</span>',
					/* translators: %s: Search query. */
					esc_html( sprintf( __( 'Search results for: %s', 'multisite-fleet-manager' ), $this->table->search_term() ) )
				);
			}
			?>
			<hr class="wp-header-end">

			<p class="wpfleet-meta">
				<?php
				if ( $last ) {
					printf(
						/* translators: 1: Date and time, 2: Relative time, e.g. "5 minutes ago". */
						esc_html__( 'Inventory from site discovery on %1$s (%2$s). Changes made since then are picked up automatically for most site settings.', 'multisite-fleet-manager' ),
						esc_html( View::datetime( $last['finished_at'] ) ),
						esc_html( View::ago( $last['finished_at'] ) )
					);
				} else {
					printf(
						/* translators: %s: Link to the Fleet Manager dashboard. */
						esc_html__( 'The inventory is empty until site discovery has run once. Start it from the %s.', 'multisite-fleet-manager' ),
						'<a href="' . esc_url( Admin::url() ) . '">' . esc_html__( 'dashboard', 'multisite-fleet-manager' ) . '</a>'
					);
				}
				?>
			</p>

			<?php $this->table->views(); ?>

			<form method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG_SITES ); ?>" />
				<?php if ( $this->table->status_filter() ) : ?>
					<input type="hidden" name="site_status" value="<?php echo esc_attr( $this->table->status_filter() ); ?>" />
				<?php endif; ?>
				<?php
				$this->table->search_box( __( 'Search sites', 'multisite-fleet-manager' ), 'wpfleet-site' );
				$this->table->display();
				?>
			</form>
		</div>
		<?php
	}
}
