<?php
/**
 * Single entry point for every state-changing operation.
 *
 * @package FleetManager
 */

namespace FleetManager\Operations;

use FleetManager\Operations\Contracts\OperationHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces, in this order, for every operation and every transport (REST,
 * form post, bulk):
 *
 *  1. Permission — every capability the handler lists (plugin + core).
 *  2. Nonce      — Operation::nonce_action( $operation ).
 *  3. Target     — the handler validates site, plugin or theme.
 *  4. Lock       — one operation at a time per network.
 *  5. Execution  — then logs the outcome, whatever it is.
 *
 * Every outcome is written to the operation log: success, failed,
 * rejected (bad nonce, invalid target, unsupported, busy) and denied.
 * A fatal error during execution is logged by a shutdown handler.
 */
final class OperationService {

	private const LOCK = 'wpfleet_operation_lock';

	private OperationLog $log;

	/**
	 * Handlers keyed by operation.
	 *
	 * @var array<string,OperationHandler>
	 */
	private array $handlers = array();

	/**
	 * Operation in progress, for the fatal-error guard.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $running = null;

	/**
	 * Constructor.
	 *
	 * @param OperationLog       $log      Log.
	 * @param OperationHandler[] $handlers Handlers.
	 */
	public function __construct( OperationLog $log, array $handlers ) {
		$this->log = $log;
		foreach ( $handlers as $handler ) {
			$this->handlers[ $handler->operation() ] = $handler;
		}
	}

	/**
	 * Whether the current user may run an operation (for showing controls).
	 *
	 * @param string $operation Operation.
	 * @return bool
	 */
	public function can( string $operation ): bool {
		$handler = $this->handlers[ $operation ] ?? null;
		if ( ! $handler ) {
			return false;
		}
		foreach ( $handler->capabilities() as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether the current user may run any operation at all.
	 *
	 * The REST entry point uses this as its permission callback, so a user who
	 * could not run anything is refused before the pipeline — and before the
	 * audit log — is involved. The capabilities of the requested operation are
	 * still checked in run().
	 *
	 * @return bool
	 */
	public function can_any(): bool {
		foreach ( array_keys( $this->handlers ) as $operation ) {
			if ( $this->can( $operation ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Runs an operation.
	 *
	 * @param string              $operation Operation::* constant.
	 * @param array<string,mixed> $args      Target arguments (plugin, theme, site_id).
	 * @param string              $nonce     Nonce for Operation::nonce_action( $operation ).
	 * @param string              $source    Transport, for the log ('rest', 'form').
	 * @return array{log_id: int, message: string, target: array<string,mixed>, to_version: string}
	 * @throws OperationException When the operation is refused or fails (already logged).
	 */
	public function run( string $operation, array $args, string $nonce, string $source = 'rest' ): array {
		$handler = $this->handlers[ $operation ] ?? null;
		$base    = array(
			'operation'   => $operation,
			'target_type' => '',
			'target'      => (string) ( $args['plugin'] ?? $args['theme'] ?? '' ),
			'site_id'     => (int) ( $args['site_id'] ?? 0 ),
			'context'     => array( 'source' => $source ),
		);

		// A refusal before validation still names the user it was about.
		if ( '' === $base['target'] && ! empty( $args['user_id'] ) ) {
			$user                = get_userdata( (int) $args['user_id'] );
			$base['target_type'] = 'user';
			$base['target']      = $user ? $user->user_login : (string) (int) $args['user_id'];
			$base['target_name'] = $user ? $user->display_name : '';
		}

		if ( ! $handler ) {
			throw $this->refuse( $base, new OperationException( __( 'Unknown operation.', 'multisite-fleet-manager' ), 'unknown_operation', Operation::REJECTED, 400 ) );
		}

		// 1. Permission.
		foreach ( $handler->capabilities() as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				throw $this->refuse( $base, new OperationException( __( 'You are not allowed to perform this operation.', 'multisite-fleet-manager' ), 'forbidden', Operation::DENIED, 403 ) );
			}
		}

		// 2. Nonce.
		if ( ! wp_verify_nonce( $nonce, Operation::nonce_action( $operation ) ) ) {
			throw $this->refuse( $base, new OperationException( __( 'The security check failed. Reload the page and try again.', 'multisite-fleet-manager' ), 'invalid_nonce', Operation::REJECTED, 403 ) );
		}

		// 3. Target.
		try {
			$target = $handler->validate( $args );
		} catch ( OperationException $e ) {
			throw $this->refuse( $base, $e );
		}
		$entry = array_merge( $base, $target );

		// 4. Lock.
		if ( ! $this->acquire_lock( $operation, $target ) ) {
			throw $this->refuse( $entry, new OperationException( __( 'Another Fleet Manager operation is running. Try again when it has finished.', 'multisite-fleet-manager' ), 'busy', Operation::REJECTED, 409 ) );
		}

		// 5. Execution.
		$this->running = $entry;
		register_shutdown_function( array( $this, 'guard_fatal' ) );

		try {
			$result = $handler->execute( $target );
		} catch ( OperationException $e ) {
			$this->running = null;
			$this->release_lock();
			throw $this->refuse( $entry, $e );
		} catch ( \Throwable $e ) {
			$this->running = null;
			$this->release_lock();
			throw $this->refuse( $entry, new OperationException( $e->getMessage(), 'exception', Operation::FAILED, 500 ) );
		}

		$this->running = null;
		$this->release_lock();

		$entry['status']     = Operation::SUCCESS;
		$entry['message']    = $result['message'];
		$entry['to_version'] = $result['to_version'] ?? $entry['to_version'];
		$entry['context']    = array_merge( $entry['context'], (array) ( $result['context'] ?? array() ) );
		$log_id              = $this->log->write( $entry );

		/**
		 * Fires after a Fleet Manager operation succeeded.
		 *
		 * @since 0.3.0
		 *
		 * @param string              $operation Operation.
		 * @param array<string,mixed> $entry     Logged entry.
		 */
		do_action( 'wpfleet_operation_succeeded', $operation, $entry );

		return array(
			'log_id'     => $log_id,
			'message'    => $entry['message'],
			'target'     => $target,
			'to_version' => (string) $entry['to_version'],
		);
	}

	/**
	 * Logs a refusal or failure and returns the exception to throw.
	 *
	 * @param array<string,mixed> $entry Entry.
	 * @param OperationException  $e     Outcome.
	 * @return OperationException
	 */
	private function refuse( array $entry, OperationException $e ): OperationException {
		$entry['status']  = $e->status();
		$entry['message'] = $e->getMessage();
		$entry['context'] = array_merge( (array) ( $entry['context'] ?? array() ), array( 'error' => $e->error_code() ) );
		return $e->with_log_id( $this->log->write( $entry ) );
	}

	/**
	 * Logs an operation that was cut short by a fatal error.
	 *
	 * @return void
	 */
	public function guard_fatal(): void {
		if ( null === $this->running ) {
			return;
		}
		$error = error_get_last();
		$entry = $this->running;

		$this->running    = null;
		$entry['status']  = Operation::FAILED;
		$entry['message'] = __( 'The operation was interrupted by a fatal error.', 'multisite-fleet-manager' ) . ( $error ? ' ' . wp_strip_all_tags( (string) $error['message'] ) : '' );
		$entry['context'] = array_merge( (array) $entry['context'], array( 'error' => 'fatal' ) );
		$this->log->write( $entry );
		$this->release_lock();
	}

	/**
	 * Takes the network-wide operation lock.
	 *
	 * @param string              $operation Operation.
	 * @param array<string,mixed> $target    Target.
	 * @return bool
	 */
	private function acquire_lock( string $operation, array $target ): bool {
		$lock = get_site_transient( self::LOCK );
		if ( is_array( $lock ) && ( (int) ( $lock['time'] ?? 0 ) ) > time() - 10 * MINUTE_IN_SECONDS ) {
			return false;
		}
		set_site_transient(
			self::LOCK,
			array(
				'operation' => $operation,
				'target'    => $target['target'],
				'user'      => get_current_user_id(),
				'time'      => time(),
			),
			10 * MINUTE_IN_SECONDS
		);
		return true;
	}

	/**
	 * Releases the lock.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		delete_site_transient( self::LOCK );
	}
}
