( function () {
	'use strict';

	let active = null;
	let previousFocus = null;
	let zoom = 1;
	const strings = window.uplinkMbeGalleryLightbox || {};

	const selector = '.wp-block-uplinkpress-collection-gallery[data-uplink-mbe-lightbox="custom"]';
	const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
	const format = ( template, values ) => values.reduce( ( output, value, index ) => output.replace( `%${ index + 1 }$d`, value ).replace( `%${ index + 1 }$s`, value ), template );

	function button( className, label, text ) {
		const element = document.createElement( 'button' );
		element.type = 'button';
		element.className = className;
		element.setAttribute( 'aria-label', label );
		element.textContent = text;
		return element;
	}

	function collectItems( gallery ) {
		return Array.from( gallery.querySelectorAll( '.uplink-mbe-custom-lightbox-trigger' ) ).map( ( trigger ) => {
			const image = trigger.querySelector( 'img' );
			let exif = [];
			try { const rows = JSON.parse( trigger.dataset.uplinkMbeExif || '[]' ); if ( Array.isArray( rows ) ) exif = rows.filter( ( row ) => row && typeof row.label === 'string' && typeof row.value === 'string' ); } catch ( error ) {}
			return {
				trigger, exif,
				src: trigger.dataset.uplinkMbeSrc,
				thumb: image ? ( image.currentSrc || image.src ) : trigger.dataset.uplinkMbeSrc,
				alt: image ? image.alt : '',
				title: trigger.dataset.uplinkMbeTitle || '',
				caption: trigger.dataset.uplinkMbeCaption || '',
			};
		} ).filter( ( item ) => item.src );
	}

	function copyThemeVariables( gallery, overlay ) {
		const styles = window.getComputedStyle( gallery );
		[
			'--uplink-mbe-lightbox-background-rgb',
			'--uplink-mbe-lightbox-panel-rgb',
			'--uplink-mbe-lightbox-title-color',
			'--uplink-mbe-lightbox-caption-color',
			'--uplink-mbe-lightbox-title-size',
			'--uplink-mbe-lightbox-caption-size',
			'--uplink-mbe-lightbox-font-family',
			'--uplink-mbe-lightbox-title-weight',
			'--uplink-mbe-lightbox-caption-weight',
		].forEach( ( property ) => overlay.style.setProperty( property, styles.getPropertyValue( property ) ) );
	}

	function syncInfoWidth() {
		if ( ! active || active.info.hidden || ! active.image.naturalWidth || ! active.image.naturalHeight ) {
			return;
		}
		const bounds = active.media.getBoundingClientRect();
		const scale = Math.min( bounds.width / active.image.naturalWidth, bounds.height / active.image.naturalHeight );
		active.info.style.width = `${ Math.max( 0, Math.round( active.image.naturalWidth * scale ) ) }px`;
		active.info.style.marginInline = 'auto';
	}

	function createLightbox( gallery, items, index ) {
		const overlay = document.createElement( 'div' );
		overlay.className = 'uplink-mbe-custom-lightbox';
		overlay.classList.add( `has-${ gallery.dataset.uplinkMbeLightboxStrip || 'horizontal' }-thumbnails` );
		overlay.classList.add( `info-${ gallery.dataset.uplinkMbeLightboxInfo || 'bottom' }` );
		overlay.hidden = true;
		copyThemeVariables( gallery, overlay );

		const dialog = document.createElement( 'div' );
		dialog.className = 'uplink-mbe-custom-lightbox-dialog';
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-label', strings.dialogLabel || 'Image gallery lightbox' );
		dialog.tabIndex = -1;

		const live = document.createElement( 'p' );
		live.className = 'screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );
		live.setAttribute( 'aria-atomic', 'true' );

		const toolbar = document.createElement( 'div' );
		toolbar.className = 'uplink-mbe-custom-lightbox-toolbar';
		const counter = document.createElement( 'span' );
		counter.className = 'uplink-mbe-custom-lightbox-counter';
		counter.setAttribute( 'aria-hidden', 'true' );
		toolbar.appendChild( counter );

		let fullscreenButton = null;
		if ( gallery.dataset.uplinkMbeLightboxFullscreen === 'true' && document.fullscreenEnabled ) {
			fullscreenButton = button( 'uplink-mbe-custom-lightbox-control', strings.fullscreenEnter || 'Enter fullscreen', '⛶' );
			toolbar.appendChild( fullscreenButton );
		}

		let zoomOut = null;
		let zoomIn = null;
		let zoomReset = null;
		if ( gallery.dataset.uplinkMbeLightboxZoom === 'true' ) {
			zoomOut = button( 'uplink-mbe-custom-lightbox-control', strings.zoomOut || 'Zoom out', '−' );
			zoomReset = button( 'uplink-mbe-custom-lightbox-control is-zoom-reset', strings.zoomReset || 'Reset zoom', '100%' );
			zoomIn = button( 'uplink-mbe-custom-lightbox-control', strings.zoomIn || 'Zoom in', '+' );
			toolbar.append( zoomOut, zoomReset, zoomIn );
		}

		const exifWrap = document.createElement( 'div' );
		exifWrap.className = 'uplink-mbe-exif';
		const exifButton = button( 'uplink-mbe-custom-lightbox-control uplink-mbe-exif-button', strings.exifLabel || 'Photo details', 'EXIF' );
		exifButton.setAttribute( 'aria-expanded', 'false' );
		exifButton.setAttribute( 'aria-controls', 'uplink-mbe-exif-panel' );
		const exifPanel = document.createElement( 'section' );
		exifPanel.id = 'uplink-mbe-exif-panel';
		exifPanel.className = 'uplink-mbe-exif-panel';
		exifPanel.hidden = true;
		exifPanel.setAttribute( 'aria-label', strings.exifLabel || 'Photo details' );
		exifWrap.append( exifButton, exifPanel );
		toolbar.appendChild( exifWrap );
		exifButton.addEventListener( 'click', () => {
			exifPanel.hidden = ! exifPanel.hidden;
			exifButton.setAttribute( 'aria-expanded', String( ! exifPanel.hidden ) );
		} );
		const close = button( 'uplink-mbe-custom-lightbox-close', strings.closeLabel || 'Close gallery lightbox', '×' );
		toolbar.appendChild( close );

		const stage = document.createElement( 'div' );
		stage.className = 'uplink-mbe-custom-lightbox-stage';
		const previous = button( 'uplink-mbe-custom-lightbox-navigation is-previous', strings.previousLabel || 'Previous image', '‹' );
		const next = button( 'uplink-mbe-custom-lightbox-navigation is-next', strings.nextLabel || 'Next image', '›' );
		const content = document.createElement( 'div' );
		content.className = 'uplink-mbe-custom-lightbox-content';
		const media = document.createElement( 'div' );
		media.className = 'uplink-mbe-custom-lightbox-media';
		const image = document.createElement( 'img' );
		image.className = 'uplink-mbe-custom-lightbox-image';
		const info = document.createElement( 'div' );
		info.className = 'uplink-mbe-custom-lightbox-info';
		const title = document.createElement( 'h2' );
		title.className = 'uplink-mbe-custom-lightbox-title';
		const caption = document.createElement( 'p' );
		caption.className = 'uplink-mbe-custom-lightbox-caption';
		info.append( title, caption );
		media.appendChild( image );
		image.addEventListener( 'load', syncInfoWidth );
		if ( gallery.dataset.uplinkMbeLightboxInfo === 'top' ) {
			content.append( info, media );
		} else {
			content.append( media, info );
		}
		stage.append( previous, content, next );

		const thumbnails = document.createElement( 'div' );
		thumbnails.className = 'uplink-mbe-custom-lightbox-thumbnails';
		thumbnails.setAttribute( 'role', 'tablist' );
		thumbnails.setAttribute( 'aria-label', strings.thumbnailsLabel || 'Gallery thumbnails' );
		if ( gallery.dataset.uplinkMbeLightboxThumbnails !== 'true' ) {
			thumbnails.hidden = true;
		}
		items.forEach( ( item, itemIndex ) => {
			const thumbnailLabel = format( strings.showImage || 'Show image %1$d of %2$d', [ itemIndex + 1, items.length ] );
			const thumbnail = button( 'uplink-mbe-custom-lightbox-thumbnail', thumbnailLabel, '' );
			thumbnail.setAttribute( 'role', 'tab' );
			thumbnail.dataset.index = String( itemIndex );
			const thumbnailImage = document.createElement( 'img' );
			thumbnailImage.src = item.thumb;
			thumbnailImage.alt = '';
			thumbnail.appendChild( thumbnailImage );
			thumbnails.appendChild( thumbnail );
		} );

		dialog.append( live, toolbar, stage, thumbnails );
		overlay.appendChild( dialog );
		document.body.appendChild( overlay );

		active = { exifWrap, exifButton, exifPanel, gallery, items, index, overlay, dialog, media, image, title, caption, info, counter, live, thumbnails, previous, next, close, fullscreenButton, zoomOut, zoomIn, zoomReset, resizeObserver: null };
		if ( 'ResizeObserver' in window ) {
			active.resizeObserver = new ResizeObserver( syncInfoWidth );
			active.resizeObserver.observe( media );
		}
		return active;
	}

	function setZoom( value ) {
		if ( ! active ) {
			return;
		}
		zoom = Math.max( 1, Math.min( 3, value ) );
		active.image.style.transform = `scale(${ zoom })`;
		active.media.classList.toggle( 'is-zoomed', zoom > 1 );
		if ( 1 === zoom ) {
			active.media.scrollTop = 0;
			active.media.scrollLeft = 0;
		}
		if ( active.zoomReset ) {
			active.zoomReset.textContent = `${ Math.round( zoom * 100 ) }%`;
			active.zoomOut.disabled = zoom <= 1;
			active.zoomIn.disabled = zoom >= 3;
		}
	}

	function showItem( index ) {
		if ( ! active ) {
			return;
		}
		active.index = ( index + active.items.length ) % active.items.length;
		const item = active.items[ active.index ];
		if ( active.exifWrap.contains( document.activeElement ) ) active.close.focus();
		active.exifWrap.hidden = ! item.exif.length;
		active.exifPanel.hidden = true;
		active.exifButton.setAttribute( 'aria-expanded', 'false' );
		const header = document.createElement( 'header' );
		header.className = 'uplink-mbe-exif-header';
		const eyebrow = document.createElement( 'span' );
		eyebrow.className = 'uplink-mbe-exif-eyebrow';
		eyebrow.textContent = strings.exifLabel || 'Photo details';
		const heading = document.createElement( 'h3' );
		heading.id = 'uplink-mbe-exif-title';
		heading.textContent = item.title || strings.exifLabel || 'Photo details';
		heading.title = heading.textContent;
		if ( item.title ) header.appendChild( eyebrow );
		header.appendChild( heading );
		active.exifPanel.setAttribute( 'aria-labelledby', heading.id );
		const body = document.createElement( 'div' );
		body.className = 'uplink-mbe-exif-body';
		body.tabIndex = 0;
		body.setAttribute( 'role', 'region' );
		body.setAttribute( 'aria-label', strings.exifLabel || 'Photo details' );
		const list = document.createElement( 'dl' );
		item.exif.forEach( ( row ) => {
			const label = document.createElement( 'dt' ); label.textContent = row.label;
			const value = document.createElement( 'dd' ); value.textContent = row.value;
			list.append( label, value );
		} );
		body.appendChild( list );
		active.exifPanel.replaceChildren( header, body );
		active.image.src = item.src;
		active.image.alt = item.alt;
		active.title.textContent = item.title;
		active.caption.textContent = item.caption;
		active.title.hidden = active.gallery.dataset.uplinkMbeLightboxTitle !== 'true' || ! item.title;
		active.caption.hidden = active.gallery.dataset.uplinkMbeLightboxCaption !== 'true' || ! item.caption;
		active.info.hidden = active.title.hidden && active.caption.hidden;
		if ( active.info.hidden ) {
			active.info.style.width = '';
		} else if ( active.image.complete ) {
			window.requestAnimationFrame( syncInfoWidth );
		}
		active.counter.textContent = `${ active.index + 1 } / ${ active.items.length }`;
		active.live.textContent = format( strings.imageStatus || 'Image %1$d of %2$d%3$s', [ active.index + 1, active.items.length, item.title ? `: ${ item.title }` : '' ] );
		active.previous.disabled = active.items.length < 2;
		active.next.disabled = active.items.length < 2;
		Array.from( active.thumbnails.children ).forEach( ( thumbnail, thumbnailIndex ) => {
			const current = thumbnailIndex === active.index;
			thumbnail.classList.toggle( 'is-current', current );
			thumbnail.setAttribute( 'aria-selected', current ? 'true' : 'false' );
			thumbnail.tabIndex = current ? 0 : -1;
			if ( current ) {
				thumbnail.scrollIntoView( { behavior: 'smooth', block: 'nearest', inline: 'nearest' } );
			}
		} );
		setZoom( 1 );
	}

	function open( gallery, trigger ) {
		const items = collectItems( gallery );
		const index = items.findIndex( ( item ) => item.trigger === trigger );
		if ( ! items.length || index < 0 ) {
			return;
		}
		previousFocus = trigger;
		const lightbox = createLightbox( gallery, items, index );
		lightbox.overlay.hidden = false;
		document.documentElement.classList.add( 'uplink-mbe-lightbox-open' );
		showItem( index );
		lightbox.close.focus();
	}

	function close() {
		if ( ! active ) {
			return;
		}
		if ( document.fullscreenElement === active.dialog ) {
			document.exitFullscreen();
		}
		if ( active.resizeObserver ) {
			active.resizeObserver.disconnect();
		}
		active.overlay.remove();
		active = null;
		document.documentElement.classList.remove( 'uplink-mbe-lightbox-open' );
		if ( previousFocus && document.contains( previousFocus ) ) {
			previousFocus.focus();
		}
		previousFocus = null;
	}

	function trapFocus( event ) {
		const focusable = Array.from( active.dialog.querySelectorAll( focusableSelector ) ).filter( ( element ) => ! element.hidden && element.offsetParent !== null );
		if ( ! focusable.length ) {
			return;
		}
		const first = focusable[0];
		const last = focusable[ focusable.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest( '.uplink-mbe-custom-lightbox-trigger' );
		if ( trigger ) {
			const gallery = trigger.closest( selector );
			if ( gallery ) {
				event.preventDefault();
				open( gallery, trigger );
			}
			return;
		}
		if ( ! active ) {
			return;
		}
		if ( ! active.exifWrap.contains( event.target ) ) { active.exifPanel.hidden = true; active.exifButton.setAttribute( 'aria-expanded', 'false' ); }
		if ( event.target === active.overlay || event.target === active.close ) {
			close();
		} else if ( event.target.closest( '.uplink-mbe-custom-lightbox-navigation.is-previous' ) ) {
			showItem( active.index - 1 );
		} else if ( event.target.closest( '.uplink-mbe-custom-lightbox-navigation.is-next' ) ) {
			showItem( active.index + 1 );
		} else if ( event.target.closest( '.uplink-mbe-custom-lightbox-thumbnail' ) ) {
			showItem( Number( event.target.closest( '.uplink-mbe-custom-lightbox-thumbnail' ).dataset.index ) );
		} else if ( active.zoomOut && event.target.closest( 'button' ) === active.zoomOut ) {
			setZoom( zoom - 0.25 );
		} else if ( active.zoomIn && event.target.closest( 'button' ) === active.zoomIn ) {
			setZoom( zoom + 0.25 );
		} else if ( active.zoomReset && event.target.closest( 'button' ) === active.zoomReset ) {
			setZoom( 1 );
		} else if ( active.fullscreenButton && event.target.closest( 'button' ) === active.fullscreenButton ) {
			if ( document.fullscreenElement ) {
				document.exitFullscreen();
			} else {
				active.dialog.requestFullscreen();
			}
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( ! active ) {
			return;
		}
		// Let the focused metadata region handle its own scrolling keys.
		if ( active.exifPanel.contains( event.target ) && ! [ 'Escape', 'Tab' ].includes( event.key ) ) return;
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			if ( ! active.exifPanel.hidden ) { active.exifPanel.hidden = true; active.exifButton.setAttribute( 'aria-expanded', 'false' ); active.exifButton.focus(); return; }
			close();
		} else if ( event.key === 'ArrowLeft' ) {
			event.preventDefault();
			showItem( active.index - 1 );
		} else if ( event.key === 'ArrowRight' ) {
			event.preventDefault();
			showItem( active.index + 1 );
		} else if ( event.key === 'Home' ) {
			event.preventDefault();
			showItem( 0 );
		} else if ( event.key === 'End' ) {
			event.preventDefault();
			showItem( active.items.length - 1 );
		} else if ( event.key === '+' || event.key === '=' ) {
			if ( active.zoomIn ) {
				event.preventDefault();
				setZoom( zoom + 0.25 );
			}
		} else if ( event.key === '-' ) {
			if ( active.zoomOut ) {
				event.preventDefault();
				setZoom( zoom - 0.25 );
			}
		} else if ( event.key === '0' ) {
			if ( active.zoomReset ) {
				event.preventDefault();
				setZoom( 1 );
			}
		} else if ( event.key === 'Tab' ) {
			trapFocus( event );
		}
	} );

	document.addEventListener( 'fullscreenchange', () => {
		if ( active && active.fullscreenButton ) {
			const fullscreen = document.fullscreenElement === active.dialog;
			active.fullscreenButton.setAttribute( 'aria-label', fullscreen ? ( strings.fullscreenExit || 'Exit fullscreen' ) : ( strings.fullscreenEnter || 'Enter fullscreen' ) );
		}
	} );
} )();
