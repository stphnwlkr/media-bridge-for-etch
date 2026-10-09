( function () {
	'use strict';
	let region;
	const timers = new Map();
	function placeRegion() {
		const parent = Array.from( document.querySelectorAll( 'dialog[open]' ) ).pop() || document.body;
		if ( region.parentElement !== parent ) parent.append( region );
		if ( region.showPopover && ! region.matches( ':popover-open' ) ) region.showPopover();
	}
	window.uplinkMbeToast = function ( message, type = 'success' ) {
		if ( ! message ) return;
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.className = 'uplink-mbe-toasts';
			region.setAttribute( 'popover', 'manual' );
		}
		placeRegion();
		const toast = document.createElement( 'div' );
		toast.className = `uplink-mbe-toast is-${ [ 'success', 'error', 'warning' ].includes( type ) ? type : 'success' }`;
		const text = document.createElement( 'span' );
		text.setAttribute( 'role', type === 'error' ? 'alert' : 'status' );
		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'uplink-mbe-toast-dismiss';
		close.setAttribute( 'aria-label', window.uplinkMbeToastStrings?.dismiss || 'Dismiss notification' );
		close.textContent = '×';
		const dismiss = () => {
			window.clearTimeout( timers.get( toast ) );
			timers.delete( toast );
			toast.remove();
			if ( ! region.childElementCount ) region.remove();
		};
		close.addEventListener( 'click', dismiss );
		toast.append( text, close );
		region.append( toast );
		text.textContent = message;
		timers.set( toast, window.setTimeout( dismiss, 5000 ) );
	};
	// A toast triggered inside a modal must remain clickable above that modal.
	document.addEventListener( 'close', () => { if ( region?.childElementCount ) placeRegion(); }, true );
	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-uplink-mbe-toast]' ).forEach( ( notice ) => {
			window.uplinkMbeToast( notice.textContent.trim(), notice.dataset.uplinkMbeToast );
			notice.remove();
		} );
	} );
}() );
