( function () {
	'use strict';

	const storageKey = 'uplink-mbe-etch-asset-uniform-grid';
	const sizeStorageKey = 'uplink-mbe-etch-asset-thumbnail-width';
	const ratioStorageKey = 'uplink-mbe-etch-asset-thumbnail-ratio';
	const originStorageKey = 'uplink-mbe-etch-asset-origin-view';
	const sidebarWidthStorageKey = 'uplink-mbe-etch-asset-sidebar-width';
	const activeClass = 'uplink-mbe-uniform-grid';
	const buttonClass = 'uplink-mbe-uniform-grid-toggle';
	const sizeButtonClass = 'uplink-mbe-asset-size-toggle';
	const sizeWidgetClass = 'uplink-mbe-asset-size-widget';
	const sizeControlClass = 'uplink-mbe-uniform-grid-size';
	const sidebarResizeClass = 'uplink-mbe-etch-sidebar-resize';
	const fontItemClass = 'uplink-mbe-etch-font-item';
	const fontPreviewClass = 'uplink-mbe-etch-font-preview';
	const config = window.uplinkMbeEtchAssetManager || {};
	const label = config.uniformGridLabel || 'Uniform grid';
	const sizeLabel = config.thumbnailWidthLabel || 'Thumbnail width';
	const ratioLabel = config.thumbnailRatioLabel || 'Aspect ratio';
	const resizeSidebarLabel = config.resizeSidebarLabel || 'Resize collections panel';
	const sidebarWidthDefault = 300;
	const sidebarWidthMin = 220;
	const sidebarWidthMax = 480;
	let observer;
	let syncing = false;
	let pendingUniform = false;
	let uniformPreference;
	let uniformOrigin;
	let sizeControlId = 0;
	const coreObservers = new Map();
	const dirtyCores = new Set();
	const previewJobs = new WeakMap();
	const identifiedTiles = new WeakMap();
	let refreshFrame = 0;
	const fontFacePromises = new Map();
	const loadedFontFaces = new Set();
	let fontRevision = 0;
	const fontPreviewData = new WeakMap();
	const fontPreviewObserver = 'IntersectionObserver' in window ? new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( ! entry.isIntersecting ) {
				return;
			}
			fontPreviewObserver.unobserve( entry.target );
			loadFontPreview( entry.target, fontPreviewData.get( entry.target ) );
		} );
	}, { rootMargin: '160px' } ) : null;

	function fontExtension( asset ) {
		// Etch's fileName can be an editable title without an extension.
		return ( asset?.fontUrl || asset?.fileName || '' ).split( /[?#]/ )[ 0 ].split( '.' ).pop().toLowerCase();
	}

	function isFontAsset( asset ) {
		return Boolean( asset?.fontUrl && ( ( asset.mimeType || '' ).startsWith( 'font/' ) || [ 'woff', 'woff2', 'ttf', 'otf' ].includes( fontExtension( asset ) ) ) );
	}

	async function loadFontPreview( preview, asset ) {
		if ( ! preview?.isConnected || ! isFontAsset( asset ) || 'function' !== typeof window.FontFace ) {
			return;
		}
		const revision = fontRevision;
		const key = asset.id + ':' + asset.fontUrl;
		const family = 'uplink-mbe-etch-font-' + String( asset.id ) + '-' + revision;
		let promise = fontFacePromises.get( key );
		if ( ! promise ) {
			const face = new FontFace( family, 'url(' + JSON.stringify( asset.fontUrl ) + ')' );
			promise = face.load().then( function ( loadedFace ) {
				if ( revision === fontRevision ) {
					document.fonts.add( loadedFace );
					loadedFontFaces.add( loadedFace );
				}
				return family;
			} );
			fontFacePromises.set( key, promise );
		}
		try {
			const loadedFamily = await promise;
			if ( revision !== fontRevision || ! preview.isConnected ) return;
			preview.style.fontFamily = loadedFamily;
			preview.classList.add( 'is-loaded' );
		} catch ( error ) {
			if ( fontFacePromises.get( key ) === promise ) fontFacePromises.delete( key );
			preview.classList.add( 'is-failed' );
		}
	}

	function nativeAssetId( element ) {
		if ( ! element ) return 0;
		for ( const name of [ 'data-asset-id', 'data-media-id', 'data-attachment-id' ] ) {
			const id = Number( element.getAttribute( name ) );
			if ( Number.isSafeInteger( id ) && id > 0 ) return id;
		}
		return 0;
	}

	function detailAsset( core, manifest ) {
		const details = core.querySelector( '.details-content' );
		if ( ! details ) return undefined;
		const badge = details.querySelector( '.id-badge-content' )?.textContent.match( /^\s*ID\s*(\d+)\s*$/ );
		const id = nativeAssetId( details ) || Number( badge?.[ 1 ] || 0 );
		if ( id ) return manifest.byId.get( id );
		const path = details.querySelector( '.details-path-basename' )?.textContent.trim();
		const extension = details.querySelector( '.details-path-ext' )?.textContent.trim() || '';
		return path ? manifest.byPath.get( path + extension ) : undefined;
	}

	function tileAsset( tile, manifest ) {
		const id = nativeAssetId( tile ) || nativeAssetId( tile.parentElement );
		if ( id ) return manifest.byId.get( id );
		const src = tile.querySelector( 'img' )?.getAttribute( 'src' );
		if ( src ) {
			try { return manifest.byUrl.get( new URL( src, window.location.href ).href ); } catch ( error ) { return undefined; }
		}
		// Etch keys grid wrappers by attachment ID. Remember only identities
		// established by native attachment details, never by array position.
		return manifest.byId.get( identifiedTiles.get( tile ) );
	}

	function visibleAssets( core, manifest ) {
		const selected = core.querySelector( '.collection-list__item--selected[data-collection-id]' );
		let collectionId = selected ? Number( selected.dataset.collectionId ) : 0;
		if ( ! selected ) {
			const sidebar = Array.from( core.querySelectorAll( '.asset-sidebar > .asset-sidebar__item' ) );
			if ( sidebar.findIndex( function ( item ) { return item.getAttribute( 'selected' ) === 'true'; } ) === 1 ) collectionId = -1;
		}
		const family = new Set( [ collectionId ] );
		if ( collectionId > 0 ) {
			if ( ! Array.isArray( manifest.collections ) ) return null;
			let previousSize;
			do {
				previousSize = family.size;
				manifest.collections.forEach( function ( collection ) {
					if ( family.has( Number( collection.parentId ) ) ) family.add( Number( collection.id ) );
				} );
			} while ( family.size !== previousSize );
		}
		if ( collectionId && manifest.assets.some( function ( asset ) { return ! asset.collectionIds; } ) ) return null;
		const search = core.querySelector( '.asset-core__search input' )?.value.trim().toLowerCase() || '';
		// Etch's grid preserves the native media request's descending ID order.
		// The public list API returns a different order, so normalize explicitly.
		return manifest.assets.filter( function ( asset ) {
			if ( collectionId === -1 && asset.collectionIds.length ) return false;
			if ( collectionId > 0 && ! asset.collectionIds.some( function ( id ) { return family.has( id ); } ) ) return false;
			return ! search || [ asset.searchName, asset.altText, asset.mimeType ].some( function ( value ) { return value.toLowerCase().includes( search ); } );
		} ).sort( function ( a, b ) { return b.id - a.id; } );
	}

	function gridSnapshot( core, manifest, tiles, detail ) {
		const assets = visibleAssets( core, manifest );
		// Opaque font placeholders expose no ID or URL. Restore their previews
		// only when the complete native view agrees with the current snapshot.
		if ( ! assets || assets.length !== tiles.length ) return null;
		const selectionCount = tiles.filter( function ( tile ) { return tile.classList.contains( 'asset-grid-item--selected' ); } ).length;
		for ( let index = 0; index < tiles.length; index += 1 ) {
			const tile = tiles[ index ];
			const asset = assets[ index ];
			const nativeId = nativeAssetId( tile ) || nativeAssetId( tile.parentElement );
			if ( nativeId && nativeId !== asset.id ) return null;
			const known = tileAsset( tile, manifest );
			if ( known && known.id !== asset.id ) return null;
			const image = tile.querySelector( 'img' );
			if ( image && image.getAttribute( 'alt' ) !== asset.searchName ) return null;
			if ( detail && tile.classList.contains( 'asset-grid-item--selected' ) && selectionCount === 1 && detail.id !== asset.id ) return null;
		}
		return assets;
	}

	function removeFontPreview( tile ) {
		tile.classList.remove( fontItemClass );
		const preview = tile.querySelector( '.' + fontPreviewClass );
		if ( preview ) {
			fontPreviewObserver?.unobserve( preview );
			fontPreviewData.delete( preview );
			preview.remove();
		}
		delete tile.dataset.uplinkMbeFontId;
	}

	function syncFontPreview( tile, asset ) {
		if ( ! isFontAsset( asset ) ) {
			removeFontPreview( tile );
			return;
		}
		if ( tile.dataset.uplinkMbeFontId === String( asset.id ) && tile.querySelector( '.' + fontPreviewClass ) ) {
			tile.classList.add( fontItemClass );
			return;
		}
		removeFontPreview( tile );
		const preview = document.createElement( 'span' );
		const sample = document.createElement( 'span' );
		const extension = document.createElement( 'span' );
		preview.className = fontPreviewClass;
		sample.className = 'uplink-mbe-etch-font-sample';
		sample.textContent = 'Aa';
		extension.className = 'uplink-mbe-etch-font-extension';
		extension.textContent = fontExtension( asset );
		preview.append( sample, extension );
		tile.append( preview );
		tile.classList.add( fontItemClass );
		tile.dataset.uplinkMbeFontId = String( asset.id );
		if ( fontPreviewObserver ) {
			fontPreviewData.set( preview, asset );
			fontPreviewObserver.observe( preview );
		} else {
			loadFontPreview( preview, asset );
		}
	}

	function removeDetailFontPreview( core ) {
		const details = core.querySelector( '.details-preview' );
		if ( ! details ) {
			return;
		}
		details.classList.remove( 'uplink-mbe-etch-detail-font' );
		const preview = details.querySelector( '.uplink-mbe-etch-detail-font-preview' );
		if ( preview ) {
			fontPreviewObserver?.unobserve( preview );
			fontPreviewData.delete( preview );
			preview.remove();
		}
		delete details.dataset.uplinkMbeFontId;
	}

	function syncDetailFontPreview( core, asset ) {
		const details = core.querySelector( '.details-preview' );
		if ( ! details || ! isFontAsset( asset ) ) {
			removeDetailFontPreview( core );
			return;
		}
		if ( details.dataset.uplinkMbeFontId === String( asset.id ) && details.querySelector( '.uplink-mbe-etch-detail-font-preview' ) ) {
			return;
		}
		removeDetailFontPreview( core );
		const preview = document.createElement( 'span' );
		const sample = document.createElement( 'span' );
		const extension = document.createElement( 'span' );
		preview.className = 'uplink-mbe-etch-detail-font-preview';
		sample.className = 'uplink-mbe-etch-detail-font-sample';
		sample.textContent = 'Aa';
		extension.className = 'uplink-mbe-etch-font-extension';
		extension.textContent = fontExtension( asset );
		preview.append( sample, extension );
		details.append( preview );
		details.classList.add( 'uplink-mbe-etch-detail-font' );
		details.dataset.uplinkMbeFontId = String( asset.id );
		if ( fontPreviewObserver ) {
			fontPreviewData.set( preview, asset );
			fontPreviewObserver.observe( preview );
		} else {
			loadFontPreview( preview, asset );
		}
	}

	function enhanceFontPreviews( core ) {
		if ( ! window.uplinkMbeEtchAssetIndex ) return;
		if ( ! core.querySelector( '.asset-grid-item, .details-content' ) ) return;
		const job = {};
		previewJobs.set( core, job );
		window.uplinkMbeEtchAssetIndex.getIndex().then( function ( manifest ) {
			if ( ! manifest || ! core.isConnected || previewJobs.get( core ) !== job ) return;
			const selected = core.querySelectorAll( '.asset-grid-item--selected:not(.asset-grid-item--uploading)' );
			const detail = detailAsset( core, manifest );
			if ( selected.length === 1 && detail ) identifiedTiles.set( selected[ 0 ], detail.id );
			const tiles = Array.from( core.querySelectorAll( '.asset-grid-item:not(.asset-grid-item--uploading)' ) );
			const snapshot = gridSnapshot( core, manifest, tiles, detail );
			core.querySelectorAll( '.asset-grid-item--uploading' ).forEach( removeFontPreview );
			tiles.forEach( function ( tile, index ) {
				const asset = tileAsset( tile, manifest ) || snapshot?.[ index ];
				syncFontPreview( tile, asset );
			} );
			syncDetailFontPreview( core, detail );
		} );
	}

	function clampSidebarWidth( width ) {
		return Math.min( sidebarWidthMax, Math.max( sidebarWidthMin, Math.round( Number( width ) || sidebarWidthDefault ) ) );
	}

	function sidebarWidth() {
		try {
			const savedWidth = window.localStorage.getItem( sidebarWidthStorageKey );
			return null === savedWidth || '' === savedWidth ? sidebarWidthDefault : clampSidebarWidth( savedWidth );
		} catch ( error ) {
			return sidebarWidthDefault;
		}
	}

	function setupSidebarResize( core ) {
		const sidebar = core.querySelector( ':scope > .asset-core__sidebar' );
		if ( ! sidebar ) {
			return;
		}

		let handle = core.querySelector( ':scope > .' + sidebarResizeClass );
		if ( handle ) {
			return;
		}

		let width = sidebarWidth();
		let resizing = false;
		let pointerId = null;
		const applyWidth = function ( nextWidth, remember ) {
			width = clampSidebarWidth( nextWidth );
			core.style.setProperty( '--uplink-mbe-etch-asset-sidebar-width', width + 'px' );
			handle.setAttribute( 'aria-valuenow', String( width ) );
			if ( remember ) {
				try {
					window.localStorage.setItem( sidebarWidthStorageKey, String( width ) );
				} catch ( error ) {
					// Resizing still works when browser storage is unavailable.
				}
			}
		};
		const widthFromPointer = function ( event ) {
			const bounds = core.getBoundingClientRect();
			return window.getComputedStyle( core ).direction === 'rtl' ? bounds.right - event.clientX : event.clientX - bounds.left;
		};
		const finish = function ( event ) {
			if ( ! resizing || event.pointerId !== pointerId ) {
				return;
			}
			resizing = false;
			handle.classList.remove( 'is-active' );
			document.body.classList.remove( 'uplink-mbe-etch-asset-resizing' );
			if ( handle.hasPointerCapture?.( pointerId ) ) {
				handle.releasePointerCapture( pointerId );
			}
			pointerId = null;
			applyWidth( width, true );
		};

		handle = document.createElement( 'div' );
		handle.className = sidebarResizeClass;
		handle.setAttribute( 'role', 'separator' );
		handle.setAttribute( 'aria-orientation', 'vertical' );
		handle.setAttribute( 'aria-label', resizeSidebarLabel );
		handle.setAttribute( 'aria-valuemin', String( sidebarWidthMin ) );
		handle.setAttribute( 'aria-valuemax', String( sidebarWidthMax ) );
		handle.tabIndex = 0;
		handle.addEventListener( 'pointerdown', function ( event ) {
			if ( 0 !== event.button ) {
				return;
			}
			resizing = true;
			pointerId = event.pointerId;
			handle.setPointerCapture?.( pointerId );
			handle.classList.add( 'is-active' );
			document.body.classList.add( 'uplink-mbe-etch-asset-resizing' );
			event.preventDefault();
		} );
		handle.addEventListener( 'pointermove', function ( event ) {
			if ( resizing && event.pointerId === pointerId ) {
				applyWidth( widthFromPointer( event ), false );
			}
		} );
		handle.addEventListener( 'pointerup', finish );
		handle.addEventListener( 'pointercancel', finish );
		handle.addEventListener( 'dblclick', function () {
			applyWidth( sidebarWidthDefault, true );
		} );
		handle.addEventListener( 'keydown', function ( event ) {
			const direction = window.getComputedStyle( core ).direction === 'rtl' ? -1 : 1;
			let nextWidth = width;
			if ( event.key === 'ArrowLeft' ) {
				nextWidth -= 10 * direction;
			} else if ( event.key === 'ArrowRight' ) {
				nextWidth += 10 * direction;
			} else if ( event.key === 'Home' ) {
				nextWidth = sidebarWidthMin;
			} else if ( event.key === 'End' ) {
				nextWidth = sidebarWidthMax;
			} else {
				return;
			}
			event.preventDefault();
			applyWidth( nextWidth, true );
		} );
		sidebar.after( handle );
		applyWidth( width, false );
	}

	function preference() {
		if ( typeof uniformPreference === 'boolean' ) {
			return uniformPreference;
		}
		try {
			uniformPreference = window.localStorage.getItem( storageKey ) === '1';
		} catch ( error ) {
			uniformPreference = false;
		}
		return uniformPreference;
	}

	function setPreference( enabled ) {
		uniformPreference = enabled;
		try {
			window.localStorage.setItem( storageKey, enabled ? '1' : '0' );
		} catch ( error ) {
			// The view still works when browser storage is unavailable.
		}
	}

	function originView() {
		if ( uniformOrigin === 'list' || uniformOrigin === 'masonry' ) {
			return uniformOrigin;
		}
		try {
			uniformOrigin = window.localStorage.getItem( originStorageKey );
		} catch ( error ) {
			uniformOrigin = null;
		}
		if ( uniformOrigin !== 'list' && uniformOrigin !== 'masonry' ) {
			uniformOrigin = 'masonry';
		}
		return uniformOrigin;
	}

	function setOriginView( view ) {
		uniformOrigin = view === 'list' ? 'list' : 'masonry';
		try {
			window.localStorage.setItem( originStorageKey, uniformOrigin );
		} catch ( error ) {
			// The current session still remembers the originating Etch view.
		}
	}

	function thumbnailWidth() {
		let value = 240;
		try {
			value = Number( window.localStorage.getItem( sizeStorageKey ) || value );
		} catch ( error ) {
			// Use the default when browser storage is unavailable.
		}
		return Math.max( 180, Math.min( 400, Math.round( value / 10 ) * 10 ) );
	}

	function thumbnailRatio() {
		let value = '1/1';
		try {
			value = window.localStorage.getItem( ratioStorageKey ) || value;
		} catch ( error ) {
			// Use the default when browser storage is unavailable.
		}
		return [ '16/9', '4/3', '1/1' ].includes( value ) ? value : '1/1';
	}

	function applyThumbnailWidth( core, control, value, remember ) {
		const size = Math.max( 180, Math.min( 400, Math.round( Number( value ) / 10 ) * 10 ) );
		const input = control.querySelector( 'input' );
		const output = control.querySelector( 'output' );
		core.style.removeProperty( '--uplink-mbe-asset-tile-width' );
		core.querySelectorAll( '.asset-grid' ).forEach( function ( grid ) {
			grid.style.setProperty( '--uplink-mbe-asset-tile-width', size + 'px' );
		} );
		input.value = String( size );
		if ( output.value !== size + 'px' ) {
			output.value = size + 'px';
		}
		if ( output.textContent !== size + 'px' ) {
			output.textContent = size + 'px';
		}
		if ( remember ) {
			try {
				window.localStorage.setItem( sizeStorageKey, String( size ) );
			} catch ( error ) {
				// The size still applies when browser storage is unavailable.
			}
		}
	}

	function applyThumbnailRatio( core, control, value, remember ) {
		const ratio = [ '16/9', '4/3', '1/1' ].includes( value ) ? value : '1/1';
		core.querySelectorAll( '.asset-grid' ).forEach( function ( grid ) {
			grid.style.setProperty( '--uplink-mbe-asset-tile-ratio', ratio.replace( '/', ' / ' ) );
		} );
		control.querySelectorAll( '[data-ratio]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.dataset.ratio === ratio ? 'true' : 'false' );
		} );
		if ( remember ) {
			try {
				window.localStorage.setItem( ratioStorageKey, ratio );
			} catch ( error ) {
				// The ratio still applies when browser storage is unavailable.
			}
		}
	}

	function setActive( core, button, enabled ) {
		const canEnable = enabled && Boolean( core.querySelector( '.asset-grid' ) );
		const control = core.querySelector( '.' + sizeControlClass );
		core.classList.toggle( activeClass, canEnable );
		button.setAttribute( 'aria-pressed', canEnable ? 'true' : 'false' );
		button.setAttribute( 'selected', canEnable ? 'true' : 'false' );
		if ( control ) {
			syncSizeControl( core, control );
		}
	}

	function syncSizeControl( core, control ) {
		const widget = control.closest( '.' + sizeWidgetClass );
		const resizeButton = widget?.querySelector( '.' + sizeButtonClass );
		const canResize = core.classList.contains( activeClass ) &&
			Boolean( core.querySelector( '.asset-grid' ) );
		if ( resizeButton ) {
			resizeButton.disabled = ! canResize;
			resizeButton.setAttribute( 'aria-disabled', canResize ? 'false' : 'true' );
		}
		if ( ! canResize ) {
			setSizePopover( widget, false );
		}
		control.querySelector( 'input' ).disabled = ! canResize;
		control.querySelectorAll( '[data-ratio]' ).forEach( function ( button ) {
			button.disabled = ! canResize;
		} );
	}

	function setSizePopover( widget, open ) {
		if ( ! widget ) {
			return;
		}
		const resizeButton = widget.querySelector( '.' + sizeButtonClass );
		const control = widget.querySelector( '.' + sizeControlClass );
		if ( ! resizeButton || ! control ) {
			return;
		}
		resizeButton.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		resizeButton.setAttribute( 'selected', open ? 'true' : 'false' );
		control.hidden = ! open;
	}

	function closeSizePopovers( except ) {
		document.querySelectorAll( '.' + sizeWidgetClass ).forEach( function ( widget ) {
			if ( widget !== except ) {
				setSizePopover( widget, false );
			}
		} );
	}

	function makeButton() {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'etch-builder-button etch-builder-button--variant-plain-icon ' + buttonClass;
		button.setAttribute( 'aria-label', label );
		button.setAttribute( 'aria-pressed', 'false' );
		button.setAttribute( 'title', label );
		button.innerHTML =
			'<span class="uplink-mbe-uniform-grid-icon" aria-hidden="true">' +
			'<svg viewBox="0 0 24 24" focusable="false">' +
			'<rect x="3" y="3" width="7" height="7" rx="1"></rect>' +
			'<rect x="14" y="3" width="7" height="7" rx="1"></rect>' +
			'<rect x="3" y="14" width="7" height="7" rx="1"></rect>' +
			'<rect x="14" y="14" width="7" height="7" rx="1"></rect>' +
			'</svg></span>';
		return button;
	}

	function makeSizeControl( core ) {
		const control = document.createElement( 'div' );
		const sizeRow = document.createElement( 'div' );
		const ratioRow = document.createElement( 'div' );
		const ratioText = document.createElement( 'span' );
		const ratioOptions = document.createElement( 'div' );
		const input = document.createElement( 'input' );
		const output = document.createElement( 'output' );
		control.className = sizeControlClass;
		control.hidden = true;
		input.type = 'range';
		input.min = '180';
		input.max = '400';
		input.step = '10';
		input.setAttribute( 'aria-label', sizeLabel );
		input.setAttribute( 'title', sizeLabel );
		input.addEventListener( 'input', function () {
			applyThumbnailWidth( core, control, input.value, true );
		} );
		sizeRow.className = 'uplink-mbe-asset-size-row';
		sizeRow.append( input, output );
		ratioRow.className = 'uplink-mbe-asset-ratio-row';
		ratioText.textContent = ratioLabel;
		ratioOptions.className = 'uplink-mbe-asset-ratio-options';
		ratioOptions.setAttribute( 'role', 'group' );
		ratioOptions.setAttribute( 'aria-label', ratioLabel );
		[ '16/9', '4/3', '1/1' ].forEach( function ( ratio ) {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.dataset.ratio = ratio;
			button.setAttribute( 'aria-pressed', 'false' );
			button.textContent = ratio.replace( '/', ':' );
			button.addEventListener( 'click', function () {
				applyThumbnailRatio( core, control, ratio, true );
			} );
			ratioOptions.append( button );
		} );
		ratioRow.append( ratioText, ratioOptions );
		control.append( sizeRow, ratioRow );
		applyThumbnailWidth( core, control, thumbnailWidth(), false );
		applyThumbnailRatio( core, control, thumbnailRatio(), false );
		return control;
	}

	function makeSizeWidget( core ) {
		const widget = document.createElement( 'div' );
		const resizeButton = document.createElement( 'button' );
		const control = makeSizeControl( core );
		const controlId = 'uplink-mbe-asset-size-' + String( ++sizeControlId );
		widget.className = sizeWidgetClass;
		resizeButton.type = 'button';
		resizeButton.className = 'etch-builder-button etch-builder-button--variant-plain-icon ' + sizeButtonClass;
		resizeButton.disabled = true;
		resizeButton.setAttribute( 'aria-disabled', 'true' );
		resizeButton.setAttribute( 'aria-label', sizeLabel );
		resizeButton.setAttribute( 'aria-controls', controlId );
		resizeButton.setAttribute( 'aria-expanded', 'false' );
		resizeButton.setAttribute( 'title', sizeLabel );
		resizeButton.innerHTML = '<span class="uplink-mbe-asset-size-icon" aria-hidden="true"></span>';
		control.id = controlId;
		control.setAttribute( 'role', 'group' );
		control.setAttribute( 'aria-label', sizeLabel );
		resizeButton.addEventListener( 'click', function () {
			const open = resizeButton.getAttribute( 'aria-expanded' ) !== 'true';
			closeSizePopovers( open ? widget : null );
			setSizePopover( widget, open );
		} );
		widget.append( resizeButton, control );
		return widget;
	}

	function activateWhenReady( attempts ) {
		let activated = false;
		document.querySelectorAll( '.asset-core' ).forEach( function ( currentCore ) {
			const currentButton = currentCore.querySelector( '.' + buttonClass );
			if ( currentButton && currentCore.querySelector( '.asset-grid' ) ) {
				setPreference( true );
				setActive( currentCore, currentButton, true );
				activated = true;
			}
		} );
		if ( activated ) {
			pendingUniform = false;
			syncing = false;
			return;
		}

		if ( attempts <= 0 ) {
			syncing = false;
			return;
		}

		window.requestAnimationFrame( function () {
			activateWhenReady( attempts - 1 );
		} );
	}

	function setupCore( core ) {
		setupSidebarResize( core );
		enhanceFontPreviews( core );
		const actions = core.querySelector( '.asset-core__content-actions' );
		const divider = actions?.querySelector( '.asset-core__divider' );
		if ( ! actions || ! divider ) {
			return;
		}

		let button = actions.querySelector( '.' + buttonClass );
		let sizeControl = actions.querySelector( '.' + sizeControlClass );
		if ( ! button ) {
			const nativeToggle = divider.previousElementSibling;
			if ( ! nativeToggle || nativeToggle.tagName !== 'BUTTON' ) {
				return;
			}

			button = makeButton();
			const sizeWidget = makeSizeWidget( core );
			sizeControl = sizeWidget.querySelector( '.' + sizeControlClass );
			button.addEventListener( 'click', function () {
				if ( core.classList.contains( activeClass ) ) {
					pendingUniform = false;
					setPreference( false );
					setActive( core, button, false );
					if ( originView() === 'list' && nativeToggle.getAttribute( 'selected' ) === 'true' ) {
						nativeToggle.click();
					}
					return;
				}

				const nativeMasonry = nativeToggle.getAttribute( 'selected' ) === 'true';
				setOriginView( nativeMasonry ? 'masonry' : 'list' );
				pendingUniform = true;
				setPreference( true );
				if ( nativeMasonry && core.querySelector( '.asset-grid' ) ) {
					setActive( core, button, true );
					pendingUniform = false;
					return;
				}

				syncing = true;
				if ( ! nativeMasonry ) {
					nativeToggle.click();
				}
				activateWhenReady( 300 );
			} );

			nativeToggle.addEventListener( 'click', function ( event ) {
				if ( syncing || pendingUniform ) {
					return;
				}
				if ( core.classList.contains( activeClass ) ) {
					pendingUniform = false;
					setPreference( false );
					setActive( core, button, false );
					if ( originView() === 'masonry' ) {
						event.preventDefault();
						event.stopImmediatePropagation();
					}
					refresh();
					return;
				}
				setPreference( false );
				setActive( core, button, false );
				refresh();
			}, true );
			divider.before( button, sizeWidget );
		}

		if ( sizeControl ) {
			applyThumbnailWidth( core, sizeControl, thumbnailWidth(), false );
			applyThumbnailRatio( core, sizeControl, thumbnailRatio(), false );
			syncSizeControl( core, sizeControl );
		}
		setActive( core, button, pendingUniform || preference() );
		if ( pendingUniform && core.querySelector( '.asset-grid' ) ) {
			pendingUniform = false;
			syncing = false;
		}
	}

	function queueCore( core ) {
		if ( ! core.isConnected ) return;
		dirtyCores.add( core );
		if ( refreshFrame ) return;
		refreshFrame = window.requestAnimationFrame( function () {
			refreshFrame = 0;
			const batch = Array.from( dirtyCores );
			dirtyCores.clear();
			batch.forEach( function ( current ) {
				if ( current.isConnected ) setupCore( current );
			} );
		} );
	}

	function refresh() {
		coreObservers.forEach( function ( currentObserver, core ) { queueCore( core ); } );
	}

	function ownNode( node ) {
		const element = node.nodeType === 1 ? node : node.parentElement;
		return element?.closest( '.' + fontPreviewClass + ', .uplink-mbe-etch-detail-font-preview, .' + sizeWidgetClass + ', .' + buttonClass + ', .' + sidebarResizeClass );
	}

	function releaseFontPreviews( root ) {
		const selector = '.' + fontPreviewClass + ', .uplink-mbe-etch-detail-font-preview';
		const previews = Array.from( root.querySelectorAll?.( selector ) || [] );
		if ( root.matches?.( selector ) ) previews.push( root );
		previews.forEach( function ( preview ) {
			fontPreviewObserver?.unobserve( preview );
			fontPreviewData.delete( preview );
		} );
	}

	function relevantMutation( record ) {
		if ( ownNode( record.target ) ) return false;
		if ( record.type === 'attributes' && record.attributeName === 'class' ) {
			const nativeClasses = function ( value ) { return ( value || '' ).split( /\s+/ ).filter( function ( name ) { return name && ! name.startsWith( 'uplink-mbe-' ); } ).sort().join( ' ' ); };
			return nativeClasses( record.oldValue ) !== nativeClasses( record.target.getAttribute( 'class' ) );
		}
		if ( record.type === 'childList' ) {
			return Array.from( record.addedNodes ).concat( Array.from( record.removedNodes ) ).some( function ( node ) { return ! ownNode( node ); } );
		}
		return true;
	}

	function discoverCores( root ) {
		const cores = Array.from( root.querySelectorAll?.( '.asset-core' ) || [] );
		if ( root.matches?.( '.asset-core' ) ) cores.push( root );
		cores.forEach( function ( core ) {
			if ( coreObservers.has( core ) || ! core.isConnected ) return;
			const currentObserver = new MutationObserver( function ( records ) {
				records.forEach( function ( record ) {
					Array.from( record.removedNodes || [] ).forEach( function ( node ) {
						if ( ! node.isConnected ) releaseFontPreviews( node );
					} );
				} );
				if ( records.some( relevantMutation ) ) queueCore( core );
			} );
			coreObservers.set( core, currentObserver );
			currentObserver.observe( core, { childList: true, subtree: true, characterData: true, attributes: true, attributeOldValue: true, attributeFilter: [ 'class', 'src', 'data-asset-id', 'data-media-id', 'data-attachment-id' ] } );
			queueCore( core );
		} );
	}

	function start() {
		discoverCores( document );
		// The page observer only discovers manager mounts. Builder edits do not
		// trigger a full-page query or refresh of an already mounted manager.
		observer = new MutationObserver( function ( records ) {
			records.forEach( function ( record ) {
				Array.from( record.addedNodes ).forEach( function ( node ) {
					if ( node.nodeType === 1 && ! node.parentElement?.closest( '.asset-core' ) ) discoverCores( node );
				} );
			} );
			coreObservers.forEach( function ( currentObserver, core ) {
				if ( ! core.isConnected ) {
					releaseFontPreviews( core );
					currentObserver.disconnect();
					coreObservers.delete( core );
					dirtyCores.delete( core );
					previewJobs.delete( core );
				}
			} );
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
		window.uplinkMbeEtchAssetIndex?.subscribe( function () {
			fontRevision += 1;
			fontFacePromises.clear();
			loadedFontFaces.forEach( function ( face ) { document.fonts.delete( face ); } );
			loadedFontFaces.clear();
			coreObservers.forEach( function ( currentObserver, core ) {
				previewJobs.delete( core );
				core.querySelectorAll( '.' + fontItemClass ).forEach( removeFontPreview );
				removeDetailFontPreview( core );
				queueCore( core );
			} );
		} );
		document.addEventListener( 'pointerdown', function ( event ) {
			if ( ! event.target.closest( '.' + sizeWidgetClass ) ) {
				closeSizePopovers();
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			const core = event.target.closest( '.asset-core' );
			if ( core ) queueCore( core );
		} );
		document.addEventListener( 'input', function ( event ) {
			const core = event.target.closest( '.asset-core' );
			if ( core ) queueCore( core );
		} );
		window.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Escape' || ! document.querySelector( '.' + sizeButtonClass + '[aria-expanded="true"]' ) ) {
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			closeSizePopovers();
		}, true );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
}() );
