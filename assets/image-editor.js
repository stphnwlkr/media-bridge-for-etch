( function ( $ ) {
	'use strict';

	const config = window.uplinkMbeImageEditor;
	if ( ! config?.attachmentId ) {
		return;
	}

	$( function () {
		const heading = document.querySelector( '.wrap > h1' );
		if ( heading && config.returnUrl ) {
			const back = document.createElement( 'a' );
			back.className = 'page-title-action uplink-mbe-image-editor-back';
			back.href = config.returnUrl;
			back.textContent = config.returnLabel;
			heading.insertAdjacentElement( 'afterend', back );
		}
		const addMediaLink = document.querySelector( '.wrap > a.page-title-action[href*="media-new.php"]' );
		if ( addMediaLink && config.uploadUrl ) {
			addMediaLink.href = config.uploadUrl;
		}

		const attachmentId = Number( config.attachmentId );
		const editor = document.getElementById( `image-editor-${ attachmentId }` );
		const openButton = document.getElementById( `imgedit-open-btn-${ attachmentId }` );
		if ( editor && openButton && ! $( editor ).is( ':visible' ) ) {
			openButton.click();
		}
	} );
}( window.jQuery ) );
