/**
 * Multisite Fleet Manager — Plugins & Themes.
 *
 * Progressive enhancement: without JavaScript the forms post to
 * network/edit.php and operations run server-side. With JavaScript each
 * operation is a separate REST request (POST /operations), run one after
 * another, with per-row status. The server enforces permissions, nonces,
 * target validation and logging either way.
 *
 * @package FleetManager
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const root = document.querySelector( '.wpfx[data-wpfx-namespace]' );
	if ( ! root || ! apiFetch ) {
		return;
	}

	const base = '/' + root.dataset.wpfxNamespace + '/operations';
	const form = root.querySelector( 'form[data-wpfx-ops]' );
	if ( ! form ) {
		return;
	}

	const live = document.createElement( 'p' );
	live.className = 'screen-reader-text';
	live.setAttribute( 'role', 'status' );
	live.setAttribute( 'aria-live', 'polite' );
	root.appendChild( live );

	const siteField = form.querySelector( 'input[name="site_id"]' );
	const siteId = siteField ? parseInt( siteField.value, 10 ) : 0;
	const bulkButton = form.querySelector( '[data-wpfx-bulk]' );
	const selectAll = form.querySelector( '[data-wpfx-select-all]' );
	const checks = () => Array.from( form.querySelectorAll( '[data-wpfx-check]' ) );

	let busy = false;
	const warnOnLeave = ( event ) => {
		event.preventDefault();
		event.returnValue = '';
	};

	const nonceFor = ( operation ) => {
		const field = form.querySelector( '[data-wpfx-nonce="' + operation + '"]' );
		return field ? field.value : '';
	};

	const rowFor = ( target ) =>
		Array.from( root.querySelectorAll( '[data-wpfx-item]' ) ).find( ( el ) => el.dataset.wpfxItem === target );

	const setRowStatus = ( row, state, text ) => {
		const cell = row && row.querySelector( '[data-wpfx-status]' );
		if ( ! cell ) {
			return;
		}
		cell.textContent = '';
		const span = document.createElement( 'span' );
		span.className = 'wpfx-op wpfx-op--' + state;
		span.textContent = text;
		cell.appendChild( span );
	};

	const runOne = async ( operation, target ) => {
		const row = rowFor( target );
		setRowStatus( row, 'working', __( 'Working…', 'multisite-fleet-manager' ) );

		const data = { operation, nonce: nonceFor( operation ) };
		data[ operation === 'theme_update' ? 'theme' : 'plugin' ] = target;
		if ( siteId ) {
			data.site_id = siteId;
		}

		try {
			const result = await apiFetch( { path: base, method: 'POST', data } );
			setRowStatus( row, 'success', result.message );
			if ( row && result.to_version && /_update$/.test( operation ) ) {
				const version = row.querySelector( '[data-wpfx-version]' );
				const update = row.querySelector( '[data-wpfx-update]' );
				if ( version ) {
					version.textContent = result.to_version;
				}
				if ( update ) {
					update.textContent = __( 'Up to date', 'multisite-fleet-manager' );
				}
				const check = row.querySelector( '[data-wpfx-check]' );
				if ( check ) {
					check.remove();
				}
			}
			live.textContent = result.message;
			return true;
		} catch ( error ) {
			const message = ( error && error.message ) || __( 'The operation failed.', 'multisite-fleet-manager' );
			setRowStatus( row, 'error', message );
			live.textContent = message;
			return false;
		}
	};

	const runMany = async ( jobs ) => {
		busy = true;
		window.addEventListener( 'beforeunload', warnOnLeave );
		form.querySelectorAll( 'button' ).forEach( ( b ) => ( b.disabled = true ) );

		let ok = 0;
		for ( let i = 0; i < jobs.length; i++ ) {
			if ( jobs.length > 1 ) {
				/* translators: 1: Current item, 2: Total items. */
				live.textContent = sprintf( __( 'Processing %1$d of %2$d…', 'multisite-fleet-manager' ), i + 1, jobs.length );
			}
			ok += ( await runOne( jobs[ i ][ 0 ], jobs[ i ][ 1 ] ) ) ? 1 : 0;
		}

		busy = false;
		window.removeEventListener( 'beforeunload', warnOnLeave );

		// Activation changes alter the page's state: reload to show it.
		if ( siteId && ok > 0 && jobs.some( ( job ) => /activate$/.test( job[ 0 ] ) ) ) {
			window.location.reload();
			return;
		}

		form.querySelectorAll( 'button' ).forEach( ( b ) => ( b.disabled = false ) );
		updateBulk();
		if ( jobs.length > 1 ) {
			/* translators: 1: Successful operations, 2: Total operations. */
			live.textContent = sprintf( __( '%1$d of %2$d operations succeeded.', 'multisite-fleet-manager' ), ok, jobs.length );
		}
	};

	const updateBulk = () => {
		if ( bulkButton ) {
			bulkButton.disabled = busy || ! checks().some( ( c ) => c.checked );
		}
		if ( selectAll ) {
			const all = checks();
			selectAll.checked = all.length > 0 && all.every( ( c ) => c.checked );
		}
	};

	if ( selectAll ) {
		selectAll.addEventListener( 'change', () => {
			checks().forEach( ( c ) => ( c.checked = selectAll.checked ) );
			updateBulk();
		} );
	}
	form.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( '[data-wpfx-check]' ) ) {
			updateBulk();
		}
	} );
	updateBulk();

	form.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-wpfx-single]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		if ( busy ) {
			return;
		}
		if ( button.dataset.wpfxConfirm && ! window.confirm( button.dataset.wpfxConfirm ) ) {
			return;
		}
		const [ operation, target ] = button.value.split( /\|(.*)/s );
		runMany( [ [ operation, target ] ] );
	} );

	if ( bulkButton ) {
		bulkButton.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			const operation = form.querySelector( 'input[name="operation"]' ).value;
			const selected = checks().filter( ( c ) => c.checked ).map( ( c ) => [ operation, c.value ] );
			if ( ! selected.length || busy ) {
				return;
			}
			const question = sprintf(
				/* translators: %d: Number of items. */
				__( 'Update %d items? Files are shared, so each update applies to every site of the network.', 'multisite-fleet-manager' ),
				selected.length
			);
			if ( window.confirm( question ) ) {
				runMany( selected );
			}
		} );
	}
}() );
