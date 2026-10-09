<?php
/**
 * Health distribution bar and table.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Must contain 'health' and 'total'.
 */

use FleetManager\Admin\ReportsPage;
use FleetManager\Admin\View;
use FleetManager\Health\Health;

defined( 'ABSPATH' ) || exit;

$wpfd_health = $vars['health'];
$wpfd_total  = (int) $vars['total'];
$wpfd_rows   = array(
	array( Health::HEALTHY, Health::label( Health::HEALTHY ), (int) $wpfd_health['healthy'], 'healthy' ),
	array( Health::ATTENTION, Health::label( Health::ATTENTION ), (int) $wpfd_health['attention'], 'attention' ),
	array( Health::ERROR, Health::label( Health::ERROR ), (int) $wpfd_health['error'], 'error' ),
	array( '', Health::label( '' ), (int) $wpfd_health['unchecked'], 'unchecked' ),
);
?>
<div class="wpfr-distribution">
	<span class="wpfr-distribution__bar" aria-hidden="true">
		<?php foreach ( $wpfd_rows as $wpfd_row ) : ?>
			<?php if ( $wpfd_row[2] > 0 ) : ?>
				<span class="wpfr-distribution__part wpfr-distribution__part--<?php echo esc_attr( $wpfd_row[3] ); ?>" style="width: <?php echo esc_attr( (string) ReportsPage::percent( $wpfd_row[2], $wpfd_total ) ); ?>%"></span>
			<?php endif; ?>
		<?php endforeach; ?>
	</span>

	<table class="wpfr-distribution__table">
		<caption class="screen-reader-text"><?php esc_html_e( 'Sites by health status', 'multisite-fleet-manager' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Status', 'multisite-fleet-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Sites', 'multisite-fleet-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Share', 'multisite-fleet-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $wpfd_rows as $wpfd_row ) : ?>
				<tr>
					<th scope="row">
						<span class="wpfr-dot wpfr-dot--<?php echo esc_attr( $wpfd_row[3] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $wpfd_row[1] ); ?>
					</th>
					<td><?php echo esc_html( View::number( $wpfd_row[2] ) ); ?></td>
					<td><?php echo esc_html( View::number( ReportsPage::percent( $wpfd_row[2], $wpfd_total ) ) . '%' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
