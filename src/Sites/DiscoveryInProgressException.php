<?php
/**
 * Raised when a discovery run is already in progress.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the run that is currently in progress.
 */
final class DiscoveryInProgressException extends \RuntimeException {

	/**
	 * Run in progress.
	 *
	 * @var array<string,mixed>
	 */
	private array $run;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $run Run in progress.
	 */
	public function __construct( array $run ) {
		parent::__construct( __( 'Site discovery is already running. Wait for it to finish, then try again.', 'multisite-fleet-manager' ) );
		$this->run = $run;
	}

	/**
	 * Run in progress.
	 *
	 * @return array<string,mixed>
	 */
	public function run(): array {
		return $this->run;
	}
}
