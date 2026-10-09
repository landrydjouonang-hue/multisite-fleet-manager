<?php
/**
 * Last activity of a user.
 *
 * WordPress keeps no last-login date, so the source is always stated:
 * "recorded" (seen by Fleet Manager) or "session" (derived from a valid
 * session). Session tokens themselves are never shown.
 *
 * @package FleetManager
 *
 * @var array<string,mixed> $vars Must contain 'activity'.
 */

use FleetManager\Admin\View;

defined( 'ABSPATH' ) || exit;

$wpfa = $vars['activity'];
?>
<?php if ( empty( $wpfa['last_login'] ) ) : ?>
	<span class="wpfc-muted"><?php esc_html_e( 'Not seen yet', 'multisite-fleet-manager' ); ?></span>
	<span class="wpfc-note"><?php esc_html_e( 'No sign-in recorded since Fleet Manager was activated', 'multisite-fleet-manager' ); ?></span>
<?php else : ?>
	<?php $wpfa_gmt = gmdate( 'Y-m-d H:i:s', (int) $wpfa['last_login'] ); ?>
	<span title="<?php echo esc_attr( View::datetime( $wpfa_gmt ) ); ?>"><?php echo esc_html( View::ago( $wpfa_gmt ) ); ?></span>
	<span class="wpfc-note">
		<?php
		echo esc_html(
			'session' === $wpfa['source']
				? __( 'from an open session', 'multisite-fleet-manager' )
				: __( 'last sign-in', 'multisite-fleet-manager' )
		);
		?>
	</span>
<?php endif; ?>
<?php if ( ! empty( $wpfa['sessions'] ) ) : ?>
	<span class="wpfu-online">
		<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
		<?php
		/* translators: %s: Number of sessions. */
		echo esc_html( sprintf( _n( '%s open session', '%s open sessions', (int) $wpfa['sessions'], 'multisite-fleet-manager' ), View::number( (int) $wpfa['sessions'] ) ) );
		?>
	</span>
<?php endif; ?>
