<?php
/**
 * Site discovery service.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

use FleetManager\Users\UserIndex;

defined( 'ABSPATH' ) || exit;

/**
 * Walks every site of the current network and refreshes the inventory.
 *
 * Discovery is split into runs and batches so it scales to large networks
 * without hitting request time limits:
 *
 *  start()  → opens a run (one per network; a lock prevents overlaps)
 *  step()   → inspects the next batch of sites, using a blog_id cursor
 *  complete → removes inventory rows the run did not see, records a summary
 *
 * The dashboard drives step() over REST; cron and the no-JavaScript fallback
 * use run() with a time budget and resume later if needed.
 */
final class SiteDiscovery {

	public const RUN_OPTION  = 'wpfleet_discovery_run';
	public const LAST_OPTION = 'wpfleet_last_discovery';

	/**
	 * Seconds after which an idle run is considered abandoned.
	 */
	public const LOCK_TTL = 300;

	public const SOURCE_MANUAL    = 'manual';
	public const SOURCE_SCHEDULED = 'scheduled';

	private SiteRepository $sites;

	private SiteInspector $inspector;

	private ?UserIndex $users;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $sites     Repository.
	 * @param SiteInspector  $inspector Inspector.
	 * @param UserIndex|null $users     Membership index, refreshed with each site.
	 */
	public function __construct( SiteRepository $sites, SiteInspector $inspector, ?UserIndex $users = null ) {
		$this->sites     = $sites;
		$this->inspector = $inspector;
		$this->users     = $users;
	}

	/**
	 * Opens a run.
	 *
	 * @param string $source SOURCE_* constant.
	 * @param bool   $force  Replace a run that is still active.
	 * @return array<string,mixed> Run state.
	 * @throws DiscoveryInProgressException When another run is active and $force is false.
	 */
	public function start( string $source = self::SOURCE_MANUAL, bool $force = false ): array {
		$current = $this->current();
		if ( $current && ! $force && $this->is_active( $current ) ) {
			throw new DiscoveryInProgressException( $current );
		}

		$now = current_time( 'mysql', true );
		$run = array(
			'id'         => wp_generate_uuid4(),
			'network_id' => (int) get_current_network_id(),
			'source'     => in_array( $source, array( self::SOURCE_MANUAL, self::SOURCE_SCHEDULED ), true ) ? $source : self::SOURCE_MANUAL,
			'user_id'    => (int) get_current_user_id(),
			'started_at' => $now,
			'updated_at' => $now,
			'cursor'     => 0,
			'processed'  => 0,
			'failed'     => 0,
			'total'      => $this->count_network_sites(),
		);

		update_site_option( self::RUN_OPTION, $run );

		/**
		 * Fires when a discovery run starts.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string,mixed> $run Run state.
		 */
		do_action( 'wpfleet_discovery_started', $run );

		return $run;
	}

	/**
	 * Processes the next batch of a run.
	 *
	 * @param string $run_id Run ID.
	 * @return array{run: array<string,mixed>, done: bool, summary: array<string,mixed>|null}
	 * @throws \InvalidArgumentException When the run is unknown or no longer current.
	 */
	public function step( string $run_id ): array {
		$run = $this->current();
		if ( ! $run || $run['id'] !== $run_id ) {
			throw new \InvalidArgumentException( __( 'This discovery run is no longer active. Start a new one.', 'multisite-fleet-manager' ) );
		}

		$batch = $this->next_ids( (int) $run['cursor'], $this->batch_size() );

		foreach ( $batch as $blog_id ) {
			$site = $this->inspector->inspect( $blog_id );
			if ( $site && $this->sites->save( $site, $run['id'] ) ) {
				++$run['processed'];
				if ( $this->users ) {
					$this->users->index_site( $blog_id, $site->network_id );
				}
			} else {
				++$run['failed'];
			}
			$run['cursor'] = $blog_id;
		}

		$run['updated_at'] = current_time( 'mysql', true );
		$run['total']      = max( (int) $run['total'], (int) $run['processed'] + (int) $run['failed'] );

		if ( count( $batch ) < $this->batch_size() ) {
			return array(
				'run'     => $run,
				'done'    => true,
				'summary' => $this->complete( $run ),
			);
		}

		update_site_option( self::RUN_OPTION, $run );

		return array(
			'run'     => $run,
			'done'    => false,
			'summary' => null,
		);
	}

	/**
	 * Runs (or resumes) discovery within a time budget.
	 *
	 * @param string $source         SOURCE_* constant.
	 * @param float  $budget_seconds Stop starting new batches after this many seconds.
	 * @return array{run: array<string,mixed>, done: bool, summary: array<string,mixed>|null}
	 */
	public function run( string $source, float $budget_seconds = 20.0 ): array {
		$started = microtime( true );
		$run     = $this->current();

		if ( ! $run || ! $this->is_active( $run ) ) {
			$run = $this->start( $source, true );
		}

		do {
			$result = $this->step( (string) $run['id'] );
			$run    = $result['run'];
		} while ( ! $result['done'] && ( microtime( true ) - $started ) < $budget_seconds );

		return $result;
	}

	/**
	 * Run in progress on the current network, if any.
	 *
	 * @return array<string,mixed>|null
	 */
	public function current(): ?array {
		$run = get_site_option( self::RUN_OPTION, null );
		return is_array( $run ) && ! empty( $run['id'] ) ? $run : null;
	}

	/**
	 * Summary of the last completed run on the current network.
	 *
	 * @return array<string,mixed>|null
	 */
	public function last(): ?array {
		$last = get_site_option( self::LAST_OPTION, null );
		return is_array( $last ) && ! empty( $last['finished_at'] ) ? $last : null;
	}

	/**
	 * Whether a run has been updated recently enough to count as active.
	 *
	 * @param array<string,mixed> $run Run state.
	 * @return bool
	 */
	public function is_active( array $run ): bool {
		$updated = strtotime( ( $run['updated_at'] ?? '' ) . ' UTC' );
		return $updated && ( time() - $updated ) < self::LOCK_TTL;
	}

	/**
	 * Closes a run.
	 *
	 * @param array<string,mixed> $run Run state.
	 * @return array<string,mixed> Summary.
	 */
	private function complete( array $run ): array {
		$removed  = $this->sites->delete_stale( (string) $run['id'], (string) $run['started_at'], (int) $run['network_id'] );
		$finished = current_time( 'mysql', true );

		$summary = array(
			'id'          => $run['id'],
			'source'      => $run['source'],
			'user_id'     => $run['user_id'],
			'started_at'  => $run['started_at'],
			'finished_at' => $finished,
			'duration'    => max( 0, (int) strtotime( $finished . ' UTC' ) - (int) strtotime( $run['started_at'] . ' UTC' ) ),
			'sites'       => (int) $run['processed'],
			'failed'      => (int) $run['failed'],
			'removed'     => $removed,
		);

		update_site_option( self::LAST_OPTION, $summary );
		delete_site_option( self::RUN_OPTION );

		/**
		 * Fires when a discovery run completes.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string,mixed> $summary Summary.
		 */
		do_action( 'wpfleet_discovery_completed', $summary );

		return $summary;
	}

	/**
	 * Next blog IDs of the current network after a cursor.
	 *
	 * A keyset cursor (blog_id > n) stays correct when sites are created or
	 * deleted during a run, unlike an offset.
	 *
	 * @param int $after Last processed blog ID.
	 * @param int $limit Batch size.
	 * @return int[]
	 */
	private function next_ids( int $after, int $limit ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = %d AND blog_id > %d ORDER BY blog_id ASC LIMIT %d",
				get_current_network_id(),
				$after,
				$limit
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Number of sites on the current network, straight from core's table.
	 *
	 * @return int
	 */
	public function count_network_sites(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->blogs} WHERE site_id = %d", get_current_network_id() )
		);
	}

	/**
	 * Sites inspected per batch.
	 *
	 * @return int
	 */
	public function batch_size(): int {
		/**
		 * Filters the number of sites inspected per discovery batch.
		 *
		 * @since 0.1.0
		 *
		 * @param int $size Batch size (1–200).
		 */
		return max( 1, min( 200, (int) apply_filters( 'wpfleet_discovery_batch_size', 25 ) ) );
	}
}
