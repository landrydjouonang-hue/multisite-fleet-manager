<?php
/**
 * Result of one health check for one site.
 *
 * @package FleetManager
 */

namespace FleetManager\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the outcome as a state plus structured data, never as a sentence:
 * messages are produced at display time by the check, so they follow the
 * viewer's language and stay correct when wording changes.
 */
final class Indicator {

	public const GOOD    = 'good';
	public const WARNING = 'warning';
	public const ERROR   = 'error';
	public const UNKNOWN = 'unknown';

	public string $id;

	public string $state;

	/**
	 * Structured details (counts, names…), JSON-serialisable.
	 *
	 * @var array<string,mixed>
	 */
	public array $data;

	/**
	 * Constructor.
	 *
	 * @param string              $id    Check ID.
	 * @param string              $state One of the state constants.
	 * @param array<string,mixed> $data  Details.
	 */
	public function __construct( string $id, string $state, array $data = array() ) {
		$this->id    = $id;
		$this->state = in_array( $state, self::states(), true ) ? $state : self::UNKNOWN;
		$this->data  = $data;
	}

	/**
	 * All states, most severe last.
	 *
	 * @return string[]
	 */
	public static function states(): array {
		return array( self::GOOD, self::UNKNOWN, self::WARNING, self::ERROR );
	}

	/**
	 * Storage form.
	 *
	 * @return array{id: string, state: string, data: array<string,mixed>}
	 */
	public function to_array(): array {
		return array(
			'id'    => $this->id,
			'state' => $this->state,
			'data'  => $this->data,
		);
	}

	/**
	 * Restores an indicator from its storage form.
	 *
	 * @param array<string,mixed> $row Stored indicator.
	 * @return self|null
	 */
	public static function from_array( array $row ): ?self {
		if ( empty( $row['id'] ) || empty( $row['state'] ) ) {
			return null;
		}
		return new self( (string) $row['id'], (string) $row['state'], is_array( $row['data'] ?? null ) ? $row['data'] : array() );
	}
}
