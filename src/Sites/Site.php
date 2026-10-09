<?php
/**
 * Site inventory record.
 *
 * @package FleetManager
 */

namespace FleetManager\Sites;

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot of one network site, as captured by discovery.
 *
 * Immutable by convention: properties are public for cheap reads in list
 * tables and templates, and only set through the named constructors.
 */
final class Site {

	public int $network_id = 0;
	public int $blog_id    = 0;
	public string $domain  = '';
	public string $path    = '/';
	public string $name    = '';
	public string $site_url    = '';
	public string $home_url    = '';
	public string $admin_email = '';
	public string $status      = SiteStatus::ACTIVE;
	public bool $is_main       = false;
	public bool $is_public     = true;
	public bool $is_archived   = false;
	public bool $is_mature     = false;
	public bool $is_spam       = false;
	public bool $is_deleted    = false;
	public string $locale      = '';
	public string $theme_stylesheet = '';
	public string $theme_name       = '';
	public int $active_plugins      = 0;
	public int $user_count          = 0;
	public int $post_count          = 0;
	public int $page_count          = 0;
	public int $db_version          = 0;
	public ?string $registered_at   = null;
	public ?string $last_updated_at = null;
	public ?string $discovered_at   = null;
	public ?string $synced_at       = null;
	public string $theme_template   = '';

	/**
	 * Site-level active plugins (basenames). Network-activated plugins are not included.
	 *
	 * @var string[]
	 */
	public array $active_plugin_list = array();

	/**
	 * Available plugin updates affecting the site; null when update data is unavailable.
	 */
	public ?int $plugin_updates = null;

	/**
	 * Available theme updates for the active theme and its parent; null when unknown.
	 */
	public ?int $theme_updates = null;

	/**
	 * Health roll-up (see Health\Health); '' until evaluated.
	 */
	public string $health = '';

	/**
	 * Health indicators: list of array{id: string, state: string, data: array}.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public array $health_indicators = array();

	public ?string $health_checked_at = null;

	/**
	 * Scheduled events on the site, and how many are overdue.
	 */
	public int $cron_events = 0;

	public int $cron_overdue = 0;

	public int $cron_late_seconds = 0;

	/**
	 * Database indicators; null when they could not be measured.
	 */
	public ?int $autoload_bytes = null;

	public ?int $db_size_bytes = null;

	/**
	 * Upload space used and allowed, in bytes; null when quotas are not enforced.
	 */
	public ?int $upload_used_bytes = null;

	public ?int $upload_quota_bytes = null;

	/**
	 * Users with the administrator role on this site.
	 */
	public int $admin_count = 0;

	/**
	 * Column → type map shared by the repository (format strings) and hydration.
	 *
	 * @return array<string,string> Column name => 'int'|'?int'|'bool'|'string'|'datetime'|'json'.
	 */
	public static function columns(): array {
		return array(
			'network_id'       => 'int',
			'blog_id'          => 'int',
			'domain'           => 'string',
			'path'             => 'string',
			'name'             => 'string',
			'site_url'         => 'string',
			'home_url'         => 'string',
			'admin_email'      => 'string',
			'status'           => 'string',
			'is_main'          => 'bool',
			'is_public'        => 'bool',
			'is_archived'      => 'bool',
			'is_mature'        => 'bool',
			'is_spam'          => 'bool',
			'is_deleted'       => 'bool',
			'locale'           => 'string',
			'theme_stylesheet' => 'string',
			'theme_name'       => 'string',
			'active_plugins'   => 'int',
			'user_count'       => 'int',
			'post_count'       => 'int',
			'page_count'       => 'int',
			'db_version'       => 'int',
			'registered_at'    => 'datetime',
			'last_updated_at'  => 'datetime',
			'discovered_at'    => 'datetime',
			'synced_at'        => 'datetime',
			'theme_template'     => 'string',
			'active_plugin_list' => 'json',
			'plugin_updates'     => '?int',
			'theme_updates'      => '?int',
			'health'             => 'string',
			'health_indicators'  => 'json',
			'health_checked_at'  => 'datetime',
			'cron_events'        => 'int',
			'cron_overdue'       => 'int',
			'cron_late_seconds'  => 'int',
			'autoload_bytes'     => '?int',
			'db_size_bytes'      => '?int',
			'upload_used_bytes'  => '?int',
			'upload_quota_bytes' => '?int',
			'admin_count'        => 'int',
		);
	}

	/**
	 * Builds a record from a database row or an array of values.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return self
	 */
	public static function from_array( array $row ): self {
		$site = new self();
		foreach ( self::columns() as $column => $type ) {
			if ( ! array_key_exists( $column, $row ) ) {
				continue;
			}
			$value = $row[ $column ];
			switch ( $type ) {
				case 'int':
					$site->{$column} = (int) $value;
					break;
				case '?int':
					$site->{$column} = null === $value || '' === $value ? null : (int) $value;
					break;
				case 'json':
					$decoded         = is_array( $value ) ? $value : json_decode( (string) $value, true );
					$site->{$column} = is_array( $decoded ) ? $decoded : array();
					break;
				case 'bool':
					$site->{$column} = (bool) (int) $value;
					break;
				case 'datetime':
					$site->{$column} = self::normalize_datetime( $value );
					break;
				default:
					$site->{$column} = (string) $value;
			}
		}
		return $site;
	}

	/**
	 * Admin URL of the site, built from the stored address.
	 *
	 * Core's get_admin_url( $blog_id ) resolves the address with
	 * switch_to_blog(), which costs a switch — and, for a site that is not in
	 * the object cache, a query — for every row drawn. The inventory already
	 * holds the address, so list screens use this instead and stay O(page).
	 *
	 * @return string Empty when the address was never captured.
	 */
	public function admin_url(): string {
		return '' === $this->site_url ? '' : trailingslashit( $this->site_url ) . 'wp-admin/';
	}

	/**
	 * Values keyed by column (arrays kept as arrays), for the REST API.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$data = array();
		foreach ( array_keys( self::columns() ) as $column ) {
			$data[ $column ] = $this->{$column};
		}
		return $data;
	}

	/**
	 * Values keyed by column with JSON columns encoded, for storage.
	 *
	 * @return array<string,mixed>
	 */
	public function to_row(): array {
		$data = $this->to_array();
		foreach ( self::columns() as $column => $type ) {
			if ( 'json' === $type ) {
				$data[ $column ] = (string) wp_json_encode( array_values( (array) $data[ $column ] ) );
			}
		}
		return $data;
	}

	/**
	 * Display name, falling back to the address.
	 *
	 * @return string
	 */
	public function label(): string {
		return '' !== $this->name ? $this->name : untrailingslashit( $this->domain . $this->path );
	}

	/**
	 * Normalises a datetime; core uses "0000-00-00 00:00:00" for "never".
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	public static function normalize_datetime( $value ): ?string {
		if ( ! is_string( $value ) || '' === $value || 0 === strpos( $value, '0000-00-00' ) ) {
			return null;
		}
		return $value;
	}
}
