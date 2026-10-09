<?php
/**
 * Last activity signals.
 *
 * @package FleetManager
 */

namespace FleetManager\Users;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress does not store a last-login date. Two signals are used instead,
 * and the UI says which one it is showing:
 *
 * - Recorded: from the moment Fleet Manager is active, every successful
 *   login is stored as a timestamp in user meta (`wpfleet_last_login`),
 *   with the site the login happened on.
 * - Derived: existing sessions carry a login time. Their timestamps are read
 *   to fill the gap for users who logged in before this plugin, and to show
 *   who is signed in right now.
 *
 * Session tokens themselves are authentication secrets: they are never
 * exposed, logged or returned by the REST API. Only counts and timestamps
 * derived from them are.
 */
final class UserActivity {

	public const META_LAST_LOGIN = 'wpfleet_last_login';
	public const META_LAST_SITE  = 'wpfleet_last_login_site';

	/**
	 * Hooks login recording.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_login', array( $this, 'record_login' ), 10, 2 );
	}

	/**
	 * Stores the login time of a user.
	 *
	 * @param string         $user_login User login (unused).
	 * @param \WP_User|mixed $user       User.
	 * @return void
	 */
	public function record_login( $user_login, $user = null ): void {
		if ( ! $user instanceof \WP_User || ! $user->ID ) {
			return;
		}
		update_user_meta( $user->ID, self::META_LAST_LOGIN, time() );
		update_user_meta( $user->ID, self::META_LAST_SITE, get_current_blog_id() );
	}

	/**
	 * Activity of several users, in one pass.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return array<int,array{last_login: int|null, source: string, sessions: int, site_id: int}>
	 */
	public function for_users( array $user_ids ): array {
		$user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );
		if ( ! $user_ids ) {
			return array();
		}

		// Primes the meta cache for all three keys in one query.
		update_meta_cache( 'user', $user_ids );

		$out = array();
		foreach ( $user_ids as $user_id ) {
			$recorded = (int) get_user_meta( $user_id, self::META_LAST_LOGIN, true );
			$sessions = $this->sessions( $user_id );

			$last   = $recorded ?: null;
			$source = $recorded ? 'recorded' : '';
			if ( $sessions['latest'] && ( null === $last || $sessions['latest'] > $last ) ) {
				$last   = $sessions['latest'];
				$source = 'session';
			}

			$out[ $user_id ] = array(
				'last_login' => $last,
				'source'     => $source,
				'sessions'   => $sessions['count'],
				'site_id'    => (int) get_user_meta( $user_id, self::META_LAST_SITE, true ),
			);
		}
		return $out;
	}

	/**
	 * Session summary of a user: how many are valid and when the newest one
	 * started. Token values are never read out of this method.
	 *
	 * @param int $user_id User ID.
	 * @return array{count: int, latest: int|null}
	 */
	private function sessions( int $user_id ): array {
		$tokens = get_user_meta( $user_id, 'session_tokens', true );
		if ( ! is_array( $tokens ) ) {
			return array(
				'count'  => 0,
				'latest' => null,
			);
		}

		$now    = time();
		$count  = 0;
		$latest = null;
		foreach ( $tokens as $token ) {
			$token = (array) $token;
			if ( (int) ( $token['expiration'] ?? 0 ) < $now ) {
				continue;
			}
			++$count;
			$login = (int) ( $token['login'] ?? 0 );
			if ( $login && ( null === $latest || $login > $latest ) ) {
				$latest = $login;
			}
		}

		return array(
			'count'  => $count,
			'latest' => $latest,
		);
	}
}
