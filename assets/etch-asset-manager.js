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
	let manifestPromise;
	const fontFacePromises = new Map();
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

	function loadManifest() {
		if ( manifestPromise ) {
			return manifestPromise;
		}
		if ( ! config.ajaxUrl || ! config.nonce ) {
			manifestPromise = Promise.resolve( null );
			return manifestPromise;
		}
		const body = new URLSearchParams( {
			action: 'uplink_mbe_etch_asset_manifest',
			nonce: config.nonce,
		} );
		manifestPromise = window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Font preview index request failed.' );
			}
			return response.json();
		} ).then( function ( response ) {
			if ( ! response?.success || ! Array.isArray( response.data?.assets ) ) {
				throw new Error( 'Font preview index is unavailable.' );
			}
			return response.data;
		} ).catch( function () {
			return null;
		} );
		return manifestPromise;
	}

	function fontExtension( asset ) {
		return ( asset?.fileName || asset?.fontUrl || '' ).split( '.' ).pop().split( /[?#]/ )[ 0 ].toLowerCase();
	}

	function isFontAsset( asset ) {
		return Boolean( asset?.fontUrl && ( ( asset.mimeType || '' ).startsWith( 'font/' ) || [ 'woff', 'woff2', 'ttf', 'otf' ].includes( fontExtension( asset ) ) ) );
	}

	async function loadFontPreview( preview, asset ) {
		if ( ! preview?.isConnected || ! isFontAsset( asset ) || 'function' !== typeof window.FontFace ) {
			return;
		}
		const family = 'uplink-mbe-etch-font-' + String( asset.id );
		let promise = fontFacePromises.get( asset.id );
		if ( ! promise ) {
			const face = new FontFace( family, 'url(' + JSON.stringify( asset.fontUrl ) + ')' );
			promise = face.load().then( function ( loadedFace ) {
				document.fonts.add( loadedFace );
				return family;
			} );
			fontFacePromises.set( asset.id, promise );
		}
		try {
			preview.style.fontFamily = await promise;
			preview.classList.add( 'is-loaded' );
		} catch ( error ) {
			fontFacePromises.delete( asset.id );
			preview.classList.add( 'is-failed' );
		}
	}

	function visibleSearchQuery( core ) {
		const inputs = core.querySelectorAll( 'input[type="search"], input[type="text"]' );
		for ( const input of inputs ) {
			if ( ! input.disabled && null !== input.offsetParent ) {
				return input.value.trim().toLowerCase();
			}
		}
		return '';
	}

	function selectedCollectionId( core ) {
		const collection = core.querySelector( '.collection-list__item--selected[data-collection-id]' );
		if ( collection ) {
			return Number( collection.dataset.collectionId );
		}
		const sidebarItems = Array.from( core.querySelectorAll( '.asset-sidebar > .asset-sidebar__item' ) );
		const selected = sidebarItems.findIndex( function ( item ) {
			return item.getAttribute( 'selected' ) === 'true';
		} );
		return 1 === selected ? -1 : null;
	}

	function collectionFamily( collectionId, collections ) {
		const ids = new Set( [ collectionId ] );
		let changed = true;
		while ( changed ) {
			changed = false;
			collections.forEach( function ( collection ) {
				if ( ids.has( Number( collection.parentId ) ) && ! ids.has( Number( collection.id ) ) ) {
					ids.add( Number( collection.id ) );
					changed = true;
				}
			} );
		}
		return ids;
	}

	function filteredAssets( core, manifest ) {
		const collectionId = selectedCollectionId( core );
		const collectionIds = collectionId && collectionId > 0 ? collectionFamily( collectionId, manifest.collections || [] ) : null;
		const query = visibleSearchQuery( core );
		return manifest.assets.filter( function ( asset ) {
			if ( -1 === collectionId && asset.collectionIds.length ) {
				return false;
			}
			if ( collectionIds && ! asset.collectionIds.some( function ( id ) { return collectionIds.has( Number( id ) ); } ) ) {
				return false;
			}
			if ( query ) {
				const haystack = [ asset.fileName, asset.altText, asset.mimeType ].join( ' ' ).toLowerCase();
				return haystack.includes( query );
			}
			return true;
		} );
	}

	function removeFontPreview( tile ) {
		tile.classList.remove( fontItemClass );
		tile.querySelector( '.' + fontPreviewClass )?.remove();
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
		details.querySelector( '.uplink-mbe-etch-detail-font-preview' )?.remove();
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
		loadManifest().then( function ( manifest ) {
			if ( ! manifest || ! core.isConnected ) {
				return;
			}
			const assets = filteredAssets( core, manifest );
			let assetIndex = 0;
			core.querySelectorAll( '.asset-grid > div' ).forEach( function ( wrapper ) {
				const tile = wrapper.querySelector( ':scope > .asset-grid-item' );
				if ( ! tile ) {
					return;
				}
				if ( tile.classList.contains( 'asset-grid-item--uploading' ) ) {
					removeFontPreview( tile );
					return;
				}
				syncFontPreview( tile, assets[ assetIndex ] );
				assetIndex += 1;
			} );
			const selectedTile = core.querySelector( '.asset-grid-item--selected[data-uplink-mbe-font-id]' );
			const selectedId = Number( selectedTile?.dataset.uplinkMbeFontId || 0 );
			syncDetailFontPreview( core, manifest.assets.find( function ( asset ) { return asset.id === selectedId; } ) );
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
					window.requestAnimationFrame( refresh );
					return;
				}
				setPreference( false );
				setActive( core, button, false );
				window.requestAnimationFrame( refresh );
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

	function refresh() {
		document.querySelectorAll( '.asset-core' ).forEach( setupCore );
	}

	function start() {
		refresh();
		observer = new MutationObserver( refresh );
		observer.observe( document.body, { childList: true, subtree: true } );
		document.addEventListener( 'pointerdown', function ( event ) {
			if ( ! event.target.closest( '.' + sizeWidgetClass ) ) {
				closeSizePopovers();
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.asset-core' ) ) {
				window.requestAnimationFrame( refresh );
			}
		} );
		document.addEventListener( 'input', function ( event ) {
			if ( event.target.closest( '.asset-core' ) ) {
				window.requestAnimationFrame( refresh );
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
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
