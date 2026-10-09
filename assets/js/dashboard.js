/**
 * Multisite Fleet Manager — dashboard.
 *
 * Progressive enhancement: without JavaScript the "Discover sites" form posts
 * to network/edit.php and discovery runs within one request. With JavaScript
 * discovery runs one batch per REST request, with a progress bar.
 *
 * @package FleetManager
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const config = window.wpFleetDashboard || {};
	const base = '/' + ( config.namespace || 'multisite-fleet-manager/v1' );

	const form = document.getElementById( 'wpfleet-discovery-form' );
	const button = document.getElementById( 'wpfleet-discovery-button' );
	const progress = document.getElementById( 'wpfleet-progress' );
	const bar = document.getElementById( 'wpfleet-progress-bar' );
	const status = document.getElementById( 'wpfleet-progress-status' );

	if ( ! form || ! button || ! progress || ! bar || ! status || ! apiFetch ) {
		return;
	}

	let running = false;

	const setStatus = ( text, percent ) => {
		status.textContent = text;
		if ( typeof percent === 'number' ) {
			bar.value = Math.max( 0, Math.min( 100, percent ) );
		}
	};

	const warnOnLeave = ( event ) => {
		event.preventDefault();
		event.returnValue = '';
	};

	const fail = ( error ) => {
		running = false;
		button.disabled = false;
		button.removeAttribute( 'aria-busy' );
		progress.classList.add( 'is-error' );
		window.removeEventListener( 'beforeunload', warnOnLeave );
		setStatus( error && error.message ? error.message : __( 'Site discovery could not be completed.', 'multisite-fleet-manager' ) );
	};

	const discover = async () => {
		running = true;
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		progress.hidden = false;
		progress.classList.remove( 'is-error' );
		setStatus( __( 'Starting site discovery…', 'multisite-fleet-manager' ), 0 );
		window.addEventListener( 'beforeunload', warnOnLeave );

		const started = await apiFetch( { path: base + '/discovery', method: 'POST' } );
		let run = started.run;
		let result = { done: false };

		while ( ! result.done ) {
			result = await apiFetch( { path: base + '/discovery/' + run.id + '/batch', method: 'POST' } );
			run = result.run;
			const total = Math.max( run.total, 1 );
			setStatus(
				/* translators: 1: Sites inspected so far, 2: Total number of sites. */
				sprintf( __( 'Inspected %1$d of %2$d sites…', 'multisite-fleet-manager' ), run.processed + run.failed, run.total ),
				( ( run.processed + run.failed ) / total ) * 100
			);
		}

		window.removeEventListener( 'beforeunload', warnOnLeave );
		setStatus( __( 'Site discovery complete. Loading the dashboard…', 'multisite-fleet-manager' ), 100 );
		window.location.assign( config.doneUrl || window.location.href );
	};

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		if ( running ) {
			return;
		}
		discover().catch( fail );
	} );
}() );
