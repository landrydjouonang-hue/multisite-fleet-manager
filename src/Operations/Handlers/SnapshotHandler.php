<?php
/**
 * Manual report snapshot.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Handlers;

use FleetManager\Core\Capabilities;
use FleetManager\Operations\Contracts\OperationHandler;
use FleetManager\Operations\Operation;
use FleetManager\Operations\OperationException;
use FleetManager\Reporting\SnapshotService;

defined( 'ABSPATH' ) || exit;

/**
 * Captures a report snapshot on request.
 *
 * It changes stored state, so it goes through the same pipeline as every
 * other operation: capability, nonce, validation and an audit entry. It
 * touches no site.
 */
final class SnapshotHandler implements OperationHandler {

	private SnapshotService $snapshots;

	/**
	 * Constructor.
	 *
	 * @param SnapshotService $snapshots Snapshot service.
	 */
	public function __construct( SnapshotService $snapshots ) {
		$this->snapshots = $snapshots;
	}

	public function operation(): string {
		return Operation::SNAPSHOT_CAPTURE;
	}

	public function capabilities(): array {
		return array( Capabilities::VIEW_REPORTS, Capabilities::RUN_DISCOVERY );
	}

	public function validate( array $args ): array {
		return array(
			'target_type'  => 'report',
			'target'       => 'snapshot',
			'target_name'  => __( 'Network report', 'multisite-fleet-manager' ),
			'site_id'      => 0,
			'from_version' => '',
			'to_version'   => '',
		);
	}

	public function execute( array $target ): array {
		$id = $this->snapshots->capture( 'manual' );
		if ( ! $id ) {
			throw new OperationException( __( 'The snapshot could not be stored.', 'multisite-fleet-manager' ), 'snapshot_failed', Operation::FAILED, 500 );
		}

		return array(
			'message' => __( 'A report snapshot was captured.', 'multisite-fleet-manager' ),
			'context' => array( 'snapshot_id' => $id ),
		);
	}
}
