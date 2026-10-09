<?php
/**
 * File editing configuration.
 *
 * @package FleetManager
 */

namespace FleetManager\Health\Checks;

use FleetManager\Health\Contracts\HealthCheck;
use FleetManager\Health\FleetContext;
use FleetManager\Health\Indicator;
use FleetManager\Sites\Site;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the built-in plugin and theme editors are switched off.
 *
 * With the editor enabled, anyone who reaches a super admin account can
 * run arbitrary PHP from the browser, which turns one stolen password into
 * full server access. DISALLOW_FILE_EDIT is the usual hardening step on a
 * network; DISALLOW_FILE_MODS goes further and blocks installs and updates
 * too (which also stops Fleet Manager's update controls).
 *
 * This reports configuration only. It says nothing about whether files
 * have been modified.
 */
final class FileEditingCheck implements HealthCheck {

	public function id(): string {
		return 'file_editing';
	}

	public function label(): string {
		return __( 'File editing', 'multisite-fleet-manager' );
	}

	public function evaluate( Site $site, FleetContext $context ): Indicator {
		$data = array(
			'edit_disallowed' => $context->file_edit_disallowed,
			'mods_disallowed' => $context->file_mods_disallowed,
		);

		// Blocking file modifications implies the editors are off as well.
		$off = $context->file_edit_disallowed || $context->file_mods_disallowed;

		return new Indicator( $this->id(), $off ? Indicator::GOOD : Indicator::WARNING, $data );
	}

	public function message( Indicator $indicator ): string {
		if ( Indicator::GOOD === $indicator->state ) {
			return ! empty( $indicator->data['mods_disallowed'] )
				? __( 'The built-in editors are off and file changes are blocked entirely (DISALLOW_FILE_MODS).', 'multisite-fleet-manager' )
				: __( 'The built-in plugin and theme editors are off (DISALLOW_FILE_EDIT).', 'multisite-fleet-manager' );
		}

		return __( 'The built-in plugin and theme editors are available to super admins, so a stolen account could run PHP from the browser. Setting DISALLOW_FILE_EDIT to true in wp-config.php is the usual hardening step.', 'multisite-fleet-manager' );
	}
}
