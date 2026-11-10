( () => {
	'use strict';
	const config = window.uplinkMbeImageSizes;
	if ( ! config ) return;
	const strings = config.strings;
	let busy = false;
	let panel;
	let pending = [];
	async function request( id, mode, data = {} ) {
		const body = new FormData();
		Object.entries( { action: 'uplink_mbe_image_sizes', nonce: config.nonce, attachment_id: id, mode, ...data } ).forEach( ( [ key, value ] ) => body.append( key, value ) );
		const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
		const result = await response.json();
		if ( ! response.ok || ! result.success ) throw new Error( result.data?.message || strings.error );
		return result.data;
	}
	function encode( worker, data ) {
		return new Promise( ( resolve, reject ) => {
			const timeout = setTimeout( () => reject( new Error( strings.unsupported ) ), 120000 );
			worker.onmessage = ( { data: result } ) => { clearTimeout( timeout ); result.error ? reject( new Error( result.error ) ) : resolve( result.blob ); };
			worker.onerror = () => { clearTimeout( timeout ); reject( new Error( strings.unsupported ) ); };
			worker.postMessage( data );
		} );
	}
	async function repair( id, progress, automatic = false ) {
		const plan = await request( id, automatic ? 'auto_plan' : 'plan' );
		if ( ! plan.sizes.length ) return 0;
		if ( ! window.Worker || ! window.WebAssembly || ! window.createImageBitmap || ! window.OffscreenCanvas ) throw new Error( strings.unsupported );
		const response = await fetch( plan.url, { credentials: 'same-origin' } );
		if ( ! response.ok ) throw new Error( strings.fetchFailed );
		const blob = await response.blob();
		if ( blob.size > 32 * 1024 * 1024 ) throw new Error( strings.error );
		const worker = new Worker( config.worker, { type: 'module' } );
		try {
			for ( const [ index, size ] of plan.sizes.entries() ) {
				progress( `${ size.name } (${ index + 1 }/${ plan.sizes.length })` );
				const file = await encode( worker, { blob, width: plan.width, height: plan.height, size } );
				await request( id, automatic ? 'auto_save' : 'save', { token: plan.token, size: size.name, file } );
			}
		} finally { worker.terminate(); }
		return plan.sizes.length;
	}
	// One queue is shared by standard WordPress uploads and the enhanced manager.
	const jobs = new Map();
	let chain = Promise.resolve();
	let active = 0;
	let notice;
	function autoRow( id ) {
		if ( ! notice || ! notice.isConnected ) {
			notice = document.createElement( 'div' );
			notice.className = 'notice notice-info';
			notice.setAttribute( 'aria-label', strings.autoWorking );
			( document.getElementById( 'wpbody-content' ) || document.body ).prepend( notice );
		}
		const row = document.createElement( 'p' );
		const status = document.createElement( 'span' );
		status.setAttribute( 'role', 'status' ); status.setAttribute( 'aria-live', 'polite' );
		row.append( status ); notice.append( row );
		return { row, status };
	}
	function afterUpload( id, progress, retry = false, filename = '' ) {
		id = Number( id );
		if ( ! config.hostNeedsFallback || ! Number.isInteger( id ) || id < 1 ) return Promise.resolve( { skipped: true } );
		if ( retry && jobs.get( id )?.failed ) jobs.delete( id );
		if ( jobs.has( id ) ) {
			if ( filename ) jobs.get( id ).label = filename;
			if ( progress && ! jobs.get( id ).settled ) jobs.get( id ).listeners.add( progress );
			return jobs.get( id ).promise;
		}
		const job = { label: filename || `#${ id }`, listeners: new Set( progress ? [ progress ] : [] ), failed: false };
		jobs.set( id, job ); active++;
		const run = async () => {
			let ui;
			const update = ( detail ) => {
				ui ||= autoRow( id );
				const text = `${ strings.autoWorking } ${ job.label } ${ detail }`;
				ui.status.textContent = text;
				job.listeners.forEach( listener => listener( text ) );
			};
			try {
				const generated = await repair( id, update, true );
				if ( generated ) {
					ui.status.textContent = `${ strings.autoDone } ${ job.label }`;
					// Refresh cached media sizes after the final derivative is registered.
					const model = window.wp?.media?.attachment?.( id );
					if ( model?.fetch ) { try { await model.fetch(); } catch ( error ) { /* Saved sizes are authoritative even if the media-model refresh fails. */ } }
					document.dispatchEvent( new CustomEvent( 'uplink-mbe-image-sizes-saved' ) );
				}
				return { completed: true, generated };
			} catch ( error ) {
				job.failed = true; ui ||= autoRow( id );
				ui.status.textContent = `${ strings.autoFailed } ${ job.label }: ${ error.message }`;
				notice.classList.replace( 'notice-info', 'notice-warning' );
				const retryButton = document.createElement( 'button' );
				retryButton.type = 'button'; retryButton.className = 'button'; retryButton.textContent = strings.retry;
				retryButton.addEventListener( 'click', () => { ui.row.remove(); afterUpload( id, undefined, true, job.label ); } );
				ui.row.append( document.createTextNode( ' ' ), retryButton );
				return { completed: false, error: error.message };
			} finally { active--; job.settled = true; job.listeners.clear(); }
		};
		job.promise = chain.then( run, run );
		chain = job.promise;
		return job.promise;
	}
	window.uplinkMbeImageSizeRepair = { afterUpload };
	if ( config.hostNeedsFallback ) {
		// Direct block-editor uploads use REST rather than the media-modal queue.
		window.wp?.apiFetch?.use( async ( options, next ) => {
			const response = await next( options );
			const path = options.path || options.url || '';
			const generation = options.body instanceof FormData ? options.body.get( 'generate_sub_sizes' ) : options.data?.generate_sub_sizes;
			// WordPress's own browser pipeline uploads its variants after the main file. Let it finish that work.
			if ( 'POST' !== String( options.method || 'GET' ).toUpperCase() || ! /\/wp\/v2\/media(?:\?|$)/.test( path ) || [ false, 'false', '0', 0 ].includes( generation ) || response?.mime_type !== 'image/avif' || ! response?.id ) return response;
			const result = await afterUpload( response.id, undefined, false, ( response.media_details?.file || '' ).split( '/' ).pop() );
			if ( result.completed && result.generated ) {
				try {
					const updated = await next( { path: `/wp/v2/media/${ response.id }?context=edit`, method: 'GET' } );
					if ( updated?.media_details ) response.media_details = updated.media_details;
				} catch ( error ) { /* Persisted sizes remain available if the REST readback fails. */ }
			}
			return response;
		} );
		const queue = window.wp?.Uploader?.queue;
		const watch = ( model ) => {
			let handled = false;
			const complete = () => {
				const id = Number( model.get( 'id' ) || model.id );
				if ( handled || model.get( 'uploading' ) || ! Number.isInteger( id ) || id < 1 ) return;
				handled = true; model.off( 'change:uploading change:id change sync', complete );
				const mime = model.get( 'mime' );
				if ( ! mime || 'image/avif' === mime ) afterUpload( id, undefined, false, model.get( 'filename' ) || '' );
			};
			model.on( 'change:uploading change:id change sync', complete );
			complete();
		};
		queue?.on( 'add', watch );
		// Standalone Add Media uses uploadSuccess() in both classic and REST paths.
		window.jQuery( () => {
			if ( typeof window.uploadSuccess !== 'function' ) return;
			const success = window.uploadSuccess;
			window.uploadSuccess = function ( file, response ) {
				const result = success.apply( this, arguments );
				if ( /^\d+$/.test( String( response ).trim() ) ) afterUpload( Number( response ), undefined, false, file?.name || '' );
				return result;
			};
		} );
		window.addEventListener( 'beforeunload', event => {
			if ( active ) { event.preventDefault(); event.returnValue = ''; }
		} );
	}

	document.addEventListener( 'uplink-mbe-health-render', ( event ) => {
		if ( ! config.manualAllowed ) return;
		pending = event.detail.ids || [];
		const target = document.getElementById( 'uplink-mbe-health-description' );
		if ( ! target ) return;
		if ( ! panel ) {
			panel = document.createElement( 'span' );
			panel.style.display = 'block';
			panel.style.overflowWrap = 'anywhere';
			panel.style.marginTop = '0.75em';
			const button = document.createElement( 'button' );
			button.type = 'button'; button.className = 'button'; button.textContent = strings.repair;
			Object.assign( button.style, { whiteSpace: 'normal', width: '100%', minWidth: '0', height: 'auto', lineHeight: '1.5', padding: '6px 8px' } );
			const explanation = document.createElement( 'details' );
			const summary = document.createElement( 'summary' ); summary.textContent = strings.about;
			const help = document.createElement( 'span' );
			help.id = 'uplink-mbe-size-repair-help'; help.textContent = strings.help; help.style.display = 'block';
			button.setAttribute( 'aria-describedby', help.id );
			const status = document.createElement( 'span' );
			status.setAttribute( 'role', 'status' ); status.setAttribute( 'aria-live', 'polite' ); status.style.display = 'block';
			const errors = document.createElement( 'span' ); errors.style.display = 'block';
			explanation.append( summary, help );
			panel.append( button, explanation, status, errors );
			button.addEventListener( 'click', async () => {
				if ( busy ) return;
				busy = true; button.disabled = true; panel.setAttribute( 'aria-busy', 'true' ); errors.replaceChildren();
				const ids = [ ...pending ];
				let failed = 0;
				try {
					for ( const [ index, id ] of ids.entries() ) {
						const progress = ( detail = '' ) => { status.textContent = `${ strings.working } ${ index + 1 }/${ ids.length } · #${ id } ${ detail }`; };
						progress();
						try { await repair( id, progress ); }
						catch ( error ) { failed++; const line = document.createElement( 'span' ); line.style.display = 'block'; line.textContent = `#${ id }: ${ error.message }`; errors.append( line ); }
					}
					status.textContent = failed ? strings.failed : strings.done;
				} finally {
					busy = false; button.disabled = false; panel.removeAttribute( 'aria-busy' );
					document.dispatchEvent( new CustomEvent( 'uplink-mbe-image-sizes-saved' ) );
				}
			} );
		}
		if ( pending.length || busy || panel.querySelector( '[role="status"]' ).textContent ) target.append( panel );
		panel.querySelector( 'button' ).disabled = busy || ! pending.length;
	} );
} )();
