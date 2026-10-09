( function () {
	'use strict';
	const form = document.getElementById( 'uplink-mbe-loop-generator' );
	const config = window.uplinkMbeLoopGenerator;
	if ( ! form || ! config ) return;
	const collection = form.elements.collection;
	const name = form.elements.name;
	const all = form.elements.all_images;
	const limit = form.elements.limit;
	const submit = form.querySelector( '[type="submit"]' );
	const summary = form.querySelector( '[data-loop-summary]' );
	const query = form.querySelector( '[data-loop-query]' );
	const result = form.querySelector( '[data-loop-result]' );
	const copy = form.querySelector( '[data-loop-copy]' );
	const copyStatus = form.querySelector( '[data-loop-copy-status]' );
	let queryText = '';
	let generation = 0;
	let timer;
	let saving = false;
	const dialog = document.getElementById( 'uplink-mbe-loop-dialog' );
	if ( dialog ) {
		dialog.querySelector( '.uplink-mbe-loop-close' ).addEventListener( 'click', () => dialog.close() );
		document.addEventListener( 'uplink-mbe-open-loop', ( event ) => {
			if ( ! saving ) {
				const previous = collection.value;
				collection.replaceChildren( new Option( config.strings.choose, '' ) );
				const terms = new Map( event.detail.collections.map( ( term ) => [ Number( term.id ), term ] ) );
				for ( const term of terms.values() ) {
					const path = [ term.name ];
					const seen = new Set( [ Number( term.id ) ] );
					let parent = Number( term.parent );
					while ( terms.has( parent ) && ! seen.has( parent ) ) {
						seen.add( parent );
						path.unshift( terms.get( parent ).name );
						parent = Number( terms.get( parent ).parent );
					}
					collection.add( new Option( path.join( ' / ' ), String( term.id ) ) );
				}
				collection.value = event.detail.collection ? String( event.detail.collection ) : previous;
				submit.disabled = ! terms.size;
				collection.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
			dialog.showModal();
		} );
	}

	async function request( action, data ) {
		data.set( 'action', action );
		data.set( 'nonce', config.nonce );
		const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } );
		const payload = await response.json();
		if ( ! response.ok || ! payload.success ) throw new Error( payload.data?.message || config.strings.error );
		return payload.data;
	}

	function renderQuery( text ) {
		queryText = text;
		copy.disabled = ! text;
		copyStatus.textContent = '';
		query.replaceChildren();
		if ( ! text ) {
			query.textContent = config.strings.choose;
			return;
		}
		const tokens = /("(?:\\.|[^"\\])*"\s*:?)|\b(true|false|null)\b|(-?\d+(?:\.\d+)?)/g;
		let offset = 0;
		for ( const match of text.matchAll( tokens ) ) {
			query.append( document.createTextNode( text.slice( offset, match.index ) ) );
			const token = document.createElement( 'span' );
			token.className = match[1] ? ( match[0].endsWith( ':' ) ? 'is-key' : 'is-string' ) : 'is-value';
			token.textContent = match[0];
			query.append( token );
			offset = match.index + match[0].length;
		}
		query.append( document.createTextNode( text.slice( offset ) ) );
	}

	copy.addEventListener( 'click', async () => {
		if ( ! queryText ) return;
		try {
			await navigator.clipboard.writeText( queryText );
			window.uplinkMbeToast( config.strings.copied );
		} catch ( error ) {
			const range = document.createRange();
			range.selectNodeContents( query );
			const selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange( range );
			window.uplinkMbeToast( config.strings.copyFailed, 'error' );
		}
	} );

	async function preview( version ) {
		if ( ! collection.value ) {
			summary.textContent = config.strings.choose;
			renderQuery( '' );
			return;
		}
		summary.textContent = config.strings.loading;
		try {
			const data = await request( 'uplink_mbe_preview_loop', new FormData( form ) );
			if ( version !== generation ) return;
			summary.textContent = data.summary;
			renderQuery( JSON.stringify( data.args, null, 2 ) );
		} catch ( error ) {
			if ( version !== generation ) return;
			summary.textContent = error.message;
			renderQuery( '' );
		}
	}

	form.addEventListener( 'input', ( event ) => {
		if ( saving ) return;
		result.textContent = '';
		if ( event.target === name ) return;
		if ( event.target === collection && collection.value ) {
			const next = config.strings.name.replace( '%s', collection.selectedOptions[0].textContent );
			name.placeholder = next;
		}
		renderQuery( '' );
		limit.disabled = all.checked;
		const version = ++generation;
		window.clearTimeout( timer );
		timer = window.setTimeout( () => preview( version ), 180 );
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( saving || ! form.reportValidity() ) return;
		const data = new FormData( form );
		saving = true;
		const controls = Array.from( form.querySelectorAll( 'input, select, button[type="submit"]' ) );
		const disabled = controls.map( ( control ) => control.disabled );
		controls.forEach( ( control ) => { control.disabled = true; } );
		submit.textContent = config.strings.saving;
		result.textContent = '';
		try {
			const saved = await request( 'uplink_mbe_save_loop', data );
			window.uplinkMbeToast( saved.message );
		} catch ( error ) {
			window.uplinkMbeToast( error.message, 'error' );
		} finally {
			controls.forEach( ( control, index ) => { control.disabled = disabled[index]; } );
			submit.textContent = config.strings.save;
			saving = false;
		}
	} );
}() );
