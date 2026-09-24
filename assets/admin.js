( function () {
	'use strict';

	const config = window.uplinkMbeAdmin || {};
	const fields = {
		'uplink-mbe-light-accent': () => '#ffffff',
		'uplink-mbe-light-accent-icon': () => valueOf( 'uplink-mbe-light-accent', '#2271b1' ),
		'uplink-mbe-dark-accent': () => valueOf( 'uplink-mbe-dark-surface', '#23272b' ),
		'uplink-mbe-dark-accent-icon': () => valueOf( 'uplink-mbe-dark-accent', '#72aee6' ),
		'uplink-mbe-dark-background': () => '#f0f0f1',
		'uplink-mbe-dark-surface': () => '#f0f0f1',
	};

	function valueOf( id, fallback ) {
		const field = document.getElementById( id );
		return field && /^#[0-9a-f]{6}$/i.test( field.value ) ? field.value : fallback;
	}

	function luminance( hex ) {
		const channels = hex.slice( 1 ).match( /.{2}/g ).map( ( value ) => {
			const channel = parseInt( value, 16 ) / 255;
			return channel <= 0.04045
				? channel / 12.92
				: Math.pow( ( channel + 0.055 ) / 1.055, 2.4 );
		} );

		return ( 0.2126 * channels[ 0 ] ) + ( 0.7152 * channels[ 1 ] ) + ( 0.0722 * channels[ 2 ] );
	}

	function contrastRatio( foreground, background ) {
		const first = luminance( foreground );
		const second = luminance( background );
		return ( Math.max( first, second ) + 0.05 ) / ( Math.min( first, second ) + 0.05 );
	}

	function validate( id ) {
		const field = document.getElementById( id );
		const output = document.querySelector( `[data-contrast-for="${ id }"]` );
		if ( ! field || ! output ) {
			return;
		}

		const ratio = contrastRatio( field.value, fields[ id ]() );
		const minimumRatio = id.endsWith( '-accent-icon' ) ? 3 : 4.5;
		const passes = ratio >= minimumRatio;
		const message = passes
			? ( config.passesContrast || 'Passes contrast' )
			: ( config.failsContrast || 'Needs more contrast' );

		output.textContent = `${ message } · ${ ratio.toFixed( 2 ) }:1`;
		output.classList.toggle( 'is-pass', passes );
		output.classList.toggle( 'is-fail', ! passes );
		field.setAttribute( 'aria-invalid', passes ? 'false' : 'true' );
	}

	function validateAll() {
		Object.keys( fields ).forEach( validate );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		Object.keys( fields ).forEach( ( id ) => {
			const field = document.getElementById( id );
			const output = document.querySelector( `[data-contrast-for="${ id }"]` );
			if ( ! field || ! output ) {
				return;
			}

			if ( ! output.id ) {
				output.id = `${ id }-contrast`;
			}
			output.setAttribute( 'aria-live', 'polite' );
			field.setAttribute( 'aria-describedby', output.id );
			field.addEventListener( 'input', () => {
				validate( id );
				if ( 'uplink-mbe-light-accent' === id ) {
					validate( 'uplink-mbe-light-accent-icon' );
				}
				if ( 'uplink-mbe-dark-accent' === id ) {
					validate( 'uplink-mbe-dark-accent-icon' );
				}
				if ( 'uplink-mbe-dark-surface' === id ) {
					validate( 'uplink-mbe-dark-accent' );
				}
			} );
		} );

		validateAll();
	} );
}() );
