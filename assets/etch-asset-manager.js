( function () {
	'use strict';

	const storageKey = 'uplink-mbe-etch-asset-uniform-grid';
	const sizeStorageKey = 'uplink-mbe-etch-asset-thumbnail-width';
	const originStorageKey = 'uplink-mbe-etch-asset-origin-view';
	const activeClass = 'uplink-mbe-uniform-grid';
	const buttonClass = 'uplink-mbe-uniform-grid-toggle';
	const sizeButtonClass = 'uplink-mbe-asset-size-toggle';
	const sizeWidgetClass = 'uplink-mbe-asset-size-widget';
	const sizeControlClass = 'uplink-mbe-uniform-grid-size';
	const config = window.uplinkMbeEtchAssetManager || {};
	const label = config.uniformGridLabel || 'Uniform grid';
	const sizeLabel = config.thumbnailWidthLabel || 'Thumbnail width';
	let observer;
	let syncing = false;
	let pendingUniform = false;
	let uniformPreference;
	let uniformOrigin;
	let sizeControlId = 0;

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
		control.append( input, output );
		applyThumbnailWidth( core, control, thumbnailWidth(), false );
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
