/**
 * Multisite Fleet Manager — site dashboard (fleet console).
 *
 * Progressive enhancement only: search, filters, sorting and pagination are
 * plain links and GET forms. This script collapses the per-site detail rows
 * behind "Details" toggles and adds a "/" shortcut to focus the search box.
 * Filters are applied with the Apply button rather than on change, so
 * keyboard users can move through a select without reloading the page.
 *
 * @package FleetManager
 */
( function () {
	'use strict';

	const { __ } = wp.i18n;
	const root = document.querySelector( '.wpfc' );
	if ( ! root ) {
		return;
	}

	root.classList.add( 'has-js' );

	root.querySelectorAll( '[data-wpfc-toggle]' ).forEach( ( button ) => {
		const detail = document.getElementById( button.getAttribute( 'aria-controls' ) );
		const row = button.closest( 'tr' );
		const text = button.querySelector( '.wpfc-toggle__text' );
		if ( ! detail ) {
			return;
		}

		button.addEventListener( 'click', () => {
			const open = button.getAttribute( 'aria-expanded' ) !== 'true';
			button.setAttribute( 'aria-expanded', String( open ) );
			detail.classList.toggle( 'is-open', open );
			row.classList.toggle( 'is-open', open );
			if ( text ) {
				text.textContent = open ? __( 'Hide', 'multisite-fleet-manager' ) : __( 'Details', 'multisite-fleet-manager' );
			}
		} );
	} );

	const search = document.getElementById( 'wpfc-search' );
	if ( search ) {
		document.addEventListener( 'keydown', ( event ) => {
			const target = event.target;
			const typing = target && ( target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test( target.tagName ) );
			if ( event.key === '/' && ! typing && ! event.ctrlKey && ! event.metaKey && ! event.altKey ) {
				event.preventDefault();
				search.focus();
				search.select();
			}
		} );
	}
}() );
