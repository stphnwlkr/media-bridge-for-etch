( function () {
	'use strict';
	const config = window.uplinkMbeReplacement;
	if ( ! config ) return;
	const strings = config.strings;
	function element( tag, text, className ) {
		const node = document.createElement( tag );
		if ( text ) node.textContent = text;
		if ( className ) node.className = className;
		return node;
	}
	window.uplinkMbeReplaceFile = ( media ) => new Promise( ( resolve ) => {
		const trigger = document.activeElement;
		const dialog = element( 'dialog', '', 'uplink-mbe-replace-dialog' );
		const themeSource = trigger?.closest( '.uplink-mbe-native-wrap, .media-modal.uplink-mbe-frame, .uplink-mbe-modal-browser' ) || document.querySelector( '.uplink-mbe-native-wrap' );
		const appearance = [ 'dark', 'light', 'auto' ].find( mode => themeSource?.classList.contains( `uplink-mbe-theme-${ mode }` ) ) || config.appearance || 'auto';
		const dark = appearance === 'dark' || ( appearance === 'auto' && window.matchMedia( '(prefers-color-scheme: dark)' ).matches );
		dialog.classList.add( `uplink-mbe-theme-${ dark ? 'dark' : 'light' }` );
		if ( themeSource ) {
			const theme = getComputedStyle( themeSource );
			const native = themeSource.classList.contains( 'uplink-mbe-native-wrap' );
			for ( const name of [ 'bg', 'surface', 'card', 'border', 'soft-border', 'text', 'muted', 'control', 'accent', 'accent-text', 'accent-icon', 'accent-soft', 'danger', 'scrollbar-track', 'scrollbar-thumb' ] ) {
				const sourceName = native ? `--mbe-${ name === 'bg' ? 'background' : name }` : `--mbe-modal-${ name }`;
				const value = theme.getPropertyValue( sourceName ).trim();
				if ( value ) dialog.style.setProperty( `--mbe-modal-${ name }`, value );
			}
		}
		dialog.setAttribute( 'aria-labelledby', 'uplink-mbe-replace-title' );
		dialog.addEventListener( 'keydown', ( event ) => event.stopPropagation() );
		const title = element( 'h2', strings.title );
		title.id = 'uplink-mbe-replace-title';
		const form = element( 'form' );
		for ( const [ value, text, help ] of [ [ 'keep', strings.keep, strings.keepHelp ], [ 'new', strings.new, strings.newHelp ] ] ) {
			const choice = element( 'label', '', 'uplink-mbe-replace-choice' );
			const input = element( 'input' );
			input.type = 'radio'; input.name = 'mode'; input.value = value; input.checked = value === 'keep';
			const description = element( 'span' );
			description.append( element( 'strong', text ), element( 'small', help ) );
			choice.append( input, description );
			form.append( choice );
		}
		form.append( element( 'p', strings.preserve ) );
		const workspace = element( 'div', '', 'media-frame-content' );
		workspace.append( element( 'div', '', 'uploader-inline' ) );
		dialog.append( title, element( 'p', media.filename || media.title || '' ), form, workspace );
		let saving = false;
		let saved = null;
		dialog.addEventListener( 'cancel', ( event ) => { if ( saving ) event.preventDefault(); } );
		dialog.addEventListener( 'close', () => {
			closeHandlers.forEach( callback => callback() );
			dialog.remove();
			trigger?.focus();
			resolve( saved );
		}, { once: true } );
		const closeHandlers = [];
		const errorNotice = element( 'div', '', 'uplink-mbe-replace-error' );
		errorNotice.setAttribute( 'role', 'alert' );
		errorNotice.tabIndex = -1;
		errorNotice.hidden = true;
		const replace = async ( file ) => {
			if ( saving ) return;
			errorNotice.hidden = true;
			errorNotice.textContent = '';
			const data = new FormData( form );
			data.set( 'file', file, file.name );
			data.set( 'action', 'uplink_mbe_replace_file' );
			data.set( 'nonce', config.nonce );
			data.set( 'attachment_id', String( media.id ) );
			saving = true;
			form.querySelectorAll( 'input, button' ).forEach( ( node ) => { node.disabled = true; } );

			try {
				const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } );
				const payload = await response.json();
				if ( ! response.ok || ! payload.success ) throw new Error( payload.data?.message || strings.error );
				saved = payload.data;
				window.wp?.media?.attachment( media.id )?.fetch();
				dialog.close();
				window.uplinkMbeToast( strings.saved );
			} catch ( error ) {
				errorNotice.textContent = error.message || strings.error;
				errorNotice.hidden = false;
				errorNotice.focus();
				errorNotice.scrollIntoView( { block: 'nearest' } );
			} finally {
				saving = false;
				form.querySelectorAll( 'input, button' ).forEach( ( node ) => { node.disabled = false; } );

			}
		};
		document.body.append( dialog ); dialog.showModal();
		window.uplinkMbeUploadWorkspace.render( {
			$el: window.jQuery( dialog ),
			once: ( event, callback ) => { if ( event === 'close' ) closeHandlers.push( callback ); },
			close: () => { if ( ! saving ) dialog.close(); },
			uplinkMbeReplaceUpload: replace,
			uplinkMbeKeptFilename: { filename: media.filename || '', label: strings.savedAs, help: strings.keptNameHelp },
			uplinkMbeCanRename: () => form.querySelector( 'input[name="mode"]:checked' )?.value === 'new',
		}, window.uplinkMbeUploadConfig );
		workspace.querySelector( '.uplink-mbe-upload-footer' ).before( errorNotice );
	} );
	document.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( '[data-mbe-replace-id]' );
		if ( ! button ) return;
		event.preventDefault();
		if ( await window.uplinkMbeReplaceFile( { id: Number( button.dataset.mbeReplaceId ), filename: button.dataset.mbeReplaceName } ) ) {
			document.dispatchEvent( new CustomEvent( 'uplink-mbe-file-replaced', { detail: { id: Number( button.dataset.mbeReplaceId ) } } ) );
		}
	} );
}() );
