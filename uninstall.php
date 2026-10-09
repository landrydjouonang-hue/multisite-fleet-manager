<?php
/**
 * Uninstall: removes the plugin's tables, network options and cron events.
 *
 * @package FleetManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Autoloader.php';

FleetManager\Autoloader::register( 'FleetManager\\', __DIR__ . '/src/' );

FleetManager\Core\Uninstaller::uninstall();
