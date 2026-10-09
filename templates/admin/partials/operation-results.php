<?php
/**
 * Results of a no-JavaScript operation run.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Must contain 'results'.
 */

use FleetManager\Admin\View;
use FleetManager\Operations\Operation;

defined( 'ABSPATH' ) || exit;

if ( empty( $vars['results'] ) ) {
	return;
}

$wpfr_ok  = count( array_filter( $vars['results'], static fn( $r ) => Operation::SUCCESS === $r['status'] ) );
$wpfr_bad = count( $vars['results'] ) - $wpfr_ok;
?>
<div class="notice <?php echo $wpfr_bad ? 'notice-warning' : 'notice-success'; ?> is-dismissible wpfc-notice">
	<p><strong>
		<?php
		/* translators: 1: Number of successful operations, 2: Number of unsuccessful operations. */
		echo esc_html( sprintf( __( 'Operations finished: %1$s succeeded, %2$s did not.', 'multisite-fleet-manager' ), View::number( $wpfr_ok ), View::number( $wpfr_bad ) ) );
		?>
	</strong></p>
	<ul class="wpfx-results">
		<?php foreach ( $vars['results'] as $wpfr_result ) : ?>
			<li class="wpfx-result wpfx-result--<?php echo esc_attr( $wpfr_result['status'] ); ?>">
				<span class="dashicons <?php echo esc_attr( Operation::status_icon( $wpfr_result['status'] ) ); ?>" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php echo esc_html( Operation::status_label( $wpfr_result['status'] ) ); ?>:</span>
				<?php echo esc_html( $wpfr_result['message'] ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
