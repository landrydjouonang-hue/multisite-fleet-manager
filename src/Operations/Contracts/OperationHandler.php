<?php
/**
 * Operation handler contract.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations\Contracts;

use FleetManager\Operations\OperationException;

defined( 'ABSPATH' ) || exit;

/**
 * Handlers never check permissions or nonces themselves: OperationService
 * does that before calling validate(), so no handler can be reached without
 * them. Handlers validate and normalise the target, then execute.
 */
interface OperationHandler {

	/**
	 * Operation name (Operation::* constant).
	 *
	 * @return string
	 */
	public function operation(): string;

	/**
	 * Capabilities the current user must all hold (plugin + core).
	 *
	 * @return string[]
	 */
	public function capabilities(): array;

	/**
	 * Validates the request and returns the normalised target.
	 *
	 * @param array<string,mixed> $args Raw arguments.
	 * @return array{target_type: string, target: string, target_name: string, site_id: int, from_version: string, to_version: string}
	 * @throws OperationException When the target is invalid or the operation is unsupported.
	 */
	public function validate( array $args ): array;

	/**
	 * Performs the operation on a validated target.
	 *
	 * @param array<string,mixed> $target Target from validate().
	 * @return array{message: string, to_version?: string, context?: array<string,mixed>}
	 * @throws OperationException When WordPress reports a failure.
	 */
	public function execute( array $target ): array;
}
