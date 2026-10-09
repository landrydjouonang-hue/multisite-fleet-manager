<?php
/**
 * Operation log screen.
 *
 * @package FleetManager
 */

namespace FleetManager\Admin;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Operation;
use FleetManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only audit trail of every state-changing operation, including
 * refused and denied attempts.
 */
final class LogPage {

	private Plugin $plugin;

	/**
	 * Sanitised request state.
	 *
	 * @var array<string,mixed>
	 */
	private array $state = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Request state.
	 *
	 * @return array<string,mixed>
	 */
	public function state(): array {
		if ( ! $this->state ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
			$get = static fn( string $key ): string => isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			// phpcs:enable
			$this->state = array(
				'operation' => in_array( $get( 'operation' ), Operation::all(), true ) ? $get( 'operation' ) : '',
				'status'    => in_array( $get( 'status' ), Operation::statuses(), true ) ? $get( 'status' ) : '',
				'site_id'   => max( 0, (int) $get( 'site_id' ) ),
				's'         => $get( 's' ),
				'paged'     => max( 1, (int) $get( 'paged' ) ),
			);
		}
		return $this->state;
	}

	/**
	 * URL with the current state changed by $args.
	 *
	 * @param array<string,mixed> $args Changes (null removes).
	 * @return string
	 */
	public function url( array $args = array() ): string {
		$query = array();
		foreach ( array_merge( $this->state(), array( 'paged' => 1 ), $args ) as $key => $value ) {
			if ( null !== $value && '' !== $value && 0 !== $value && ! ( 'paged' === $key && 1 === (int) $value ) ) {
				$query[ $key ] = $value;
			}
		}
		return Admin::url( Admin::SLUG_LOG, $query );
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_LOG ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'multisite-fleet-manager' ), '', array( 'response' => 403 ) );
		}
		$state  = $this->state();
		$result = $this->plugin->operation_log()->query(
			array(
				'operation' => $state['operation'],
				'status'    => $state['status'],
				'site_id'   => $state['site_id'],
				'search'    => $state['s'],
				'page'      => $state['paged'],
				'per_page'  => 25,
			)
		);

		View::render(
			'admin/log',
			array(
				'page'  => $this,
				'state' => $state,
				'items' => $result['items'],
				'total' => $result['total'],
			)
		);
	}
}
