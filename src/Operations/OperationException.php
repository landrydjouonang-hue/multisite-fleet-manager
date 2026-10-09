<?php
/**
 * Operation refused or failed.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the logged outcome (rejected, denied, failed), an HTTP status and
 * a machine-readable error code.
 */
final class OperationException extends \RuntimeException {

	private string $status;

	private int $http_status;

	private string $error_code;

	private int $log_id = 0;

	/**
	 * Constructor.
	 *
	 * @param string $message     Human-readable message.
	 * @param string $error_code  Machine-readable code, e.g. "invalid_site".
	 * @param string $status      Operation::REJECTED|DENIED|FAILED.
	 * @param int    $http_status HTTP status.
	 */
	public function __construct( string $message, string $error_code, string $status = Operation::REJECTED, int $http_status = 400 ) {
		parent::__construct( $message );
		$this->error_code  = $error_code;
		$this->status      = $status;
		$this->http_status = $http_status;
	}

	public function status(): string {
		return $this->status;
	}

	public function http_status(): int {
		return $this->http_status;
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function log_id(): int {
		return $this->log_id;
	}

	/**
	 * Records the log entry written for this outcome.
	 *
	 * @param int $id Log ID.
	 * @return self
	 */
	public function with_log_id( int $id ): self {
		$this->log_id = $id;
		return $this;
	}

	/**
	 * WP_Error form for REST responses.
	 *
	 * @return \WP_Error
	 */
	public function to_wp_error(): \WP_Error {
		return new \WP_Error(
			'wpfleet_' . $this->error_code,
			$this->getMessage(),
			array(
				'status' => $this->http_status,
				'log_id' => $this->log_id,
			)
		);
	}
}
