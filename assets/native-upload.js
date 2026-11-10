( function ( $, wp ) {
	'use strict';
	const config = window.uplinkMbeNativeUpload;
	if ( ! config ) return;
	let bypassActive = false;
	let previousSetting;
	const frames = new WeakSet();

	function setBypass( enabled ) {
		if ( ! window.cimoSettings ) return;
		if ( enabled && ! bypassActive ) {
			previousSetting = window.cimoSettings.disableOptimization;
			window.cimoSettings.disableOptimization = true;
			bypassActive = true;
		} else if ( ! enabled && bypassActive ) {
			window.cimoSettings.disableOptimization = previousSetting;
			bypassActive = false;
		}
		document.querySelectorAll( '.uplink-mbe-native-keep-originals input' ).forEach( input => { input.checked = bypassActive; } );
	}
	function restore() { setBypass( false ); }

	function addControl( root, frame ) {
		if ( ! root || ! window.cimoSettings || root.querySelector( '.uplink-mbe-native-keep-originals' ) ) return;
		const field = document.createElement( 'div' );
		field.className = 'uplink-mbe-native-keep-originals';
		const label = document.createElement( 'label' );
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.checked = bypassActive;
		input.addEventListener( 'change', () => setBypass( input.checked ) );
		label.append( input, document.createTextNode( config.keepOriginals ) );
		const help = document.createElement( 'p' );
		help.className = 'description';
		help.textContent = config.help;
		field.append( label, help );
		root.prepend( field );
		if ( frame?.on && ! frames.has( frame ) ) {
			frames.add( frame );
			frame.on( 'close', restore );
		}
	}

	const inline = wp?.media?.view?.UploaderInline?.prototype;
	if ( inline?.render ) {
		const originalRender = inline.render;
		inline.render = function () {
			const result = originalRender.apply( this, arguments );
			addControl( this.el.querySelector( '.uploader-inline-content' ) || this.el, this.controller );
			return result;
		};
	}
	const uploader = wp?.Uploader?.prototype;
	if ( uploader?.init ) {
		const originalInit = uploader.init;
		uploader.init = function () {
			const result = originalInit.apply( this, arguments );
			this.uploader?.bind?.( 'UploadComplete', restore );
			return result;
		};
	}
	// WordPress 7.2 can finish REST uploads without a Plupload UploadComplete event.
	const queue = wp?.Uploader?.queue;
	let batchStarted = false;
	queue?.on( 'add', () => { batchStarted = true; } );
	queue?.on( 'change:uploading sync error remove reset', () => {
		if ( batchStarted && ! queue.some( attachment => attachment.get( 'uploading' ) ) ) {
			batchStarted = false;
			restore();
		}
	} );
	// Closing the native inline uploader should not leave Cimo disabled elsewhere.
	document.addEventListener( 'click', event => {
		if ( event.target instanceof Element && event.target.closest( '.uploader-inline .close, .media-modal-close' ) ) restore();
	}, true );
	window.addEventListener( 'pagehide', restore );
	$( () => {
		addControl( document.querySelector( '#plupload-upload-ui' ) );
		window.uploader?.bind?.( 'UploadComplete', restore );
		// The standalone 7.2 REST pipeline calls this helper directly.
		if ( typeof window.uploadComplete === 'function' ) {
			const originalComplete = window.uploadComplete;
			window.uploadComplete = function () {
				try { return originalComplete.apply( this, arguments ); }
				finally { restore(); }
			};
		}
	} );
}( window.jQuery, window.wp ) );
