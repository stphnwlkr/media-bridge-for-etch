( function () {
	'use strict';

	const config = window.uplinkMbeEtchGallery || {};
	const strings = config.strings || {};
	const defaults = {
		collectionId: 0,
		includeChildren: false,
		layout: 'grid',
		slidesPerView: 1,
		sliderAutoplay: false,
		sliderInterval: 5000,
		lightboxSlideshow: true,
		lightboxAutoplay: false,
		lightboxInterval: 5000,
		lightboxLoop: true,
		sliderProgressColor: '',
		sliderShowStatus: true,
		sliderShowSlideshow: false,
		sliderStatusPosition: 'auto',
		lightboxShowExif: true,
		lightboxExifFields: ['camera_model', 'lens', 'aperture_display', 'exposure_display', 'iso', 'focal_display'],
		carouselTablet: 2,
		carouselMobile: 1,
		navPosition: "bottom",
		navAlignment: "spread",
		navShape: "rounded",
		navContent: "text",
		columns: 3,
		sizeSlug: 'large',
		imageCrop: true,
		aspectRatio: '1/1',
		randomOrder: false,
		sortOrder: '',
		showTitle: false,
		showCaptions: false,
		lightboxMode: 'custom',
		limit: 24,
		gap: 8,
	};
	const processed = new WeakMap();
	let iframeDocument = null;
	let observer = null;
	let modal = null;

	function etchApi() {
		return window.etch && window.etch.blocks ? window.etch : null;
	}

	function getBlock( blockId ) {
		const etch = etchApi();
		if ( ! etch || ! blockId ) {
			return null;
		}
		try {
			return etch.blocks.getJson( blockId );
		} catch ( error ) {
			return null;
		}
	}

	function galleryBlock( blockId ) {
		const block = getBlock( blockId );
		if ( ! block || block.type !== 'etch/passthrough' || block.gutenbergBlock?.blockName !== config.blockName ) {
			return null;
		}
		return block;
	}

	function copyGalleryStyles() {
		if ( ! iframeDocument ) {
			return;
		}
		Array.from( document.querySelectorAll( 'link[rel="stylesheet"]' ) )
			.filter( ( link ) => /\/assets\/(?:gallery|gallery-etch-passthrough)\.css(?:\?|$)/.test( link.href ) )
			.forEach( ( source ) => {
				if ( Array.from( iframeDocument.querySelectorAll( 'link[rel="stylesheet"]' ) ).some( ( link ) => link.href === source.href ) ) {
					return;
				}
				const link = iframeDocument.createElement( 'link' );
				link.rel = 'stylesheet';
				link.href = source.href;
				link.dataset.uplinkMbeGalleryStyle = 'true';
				iframeDocument.head.appendChild( link );
			} );
	}

	function statusElement( className, message ) {
		const element = iframeDocument.createElement( 'div' );
		element.className = className;
		element.textContent = message;
		return element;
	}

	async function fetchPreview( attributes ) {
		const response = await fetch( config.previewUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( { attributes } ),
		} );
		if ( ! response.ok ) {
			throw new Error( `Preview request failed with ${ response.status }` );
		}
		return response.json();
	}

	async function renderPlaceholder( placeholder, blockId, attributes ) {
		const token = Symbol( 'preview' );
		processed.set( placeholder, token );
		placeholder.classList.add( 'uplink-mbe-etch-gallery-placeholder' );
		placeholder.dataset.uplinkMbeEtchGallery = 'loading';
		placeholder.replaceChildren( statusElement( 'uplink-mbe-etch-gallery-status', strings.loading || 'Loading gallery preview…' ) );

		try {
			const result = await fetchPreview( attributes );
			if ( processed.get( placeholder ) !== token || ! placeholder.isConnected ) {
				return;
			}

			const shell = iframeDocument.createElement( 'div' );
			shell.className = 'uplink-mbe-etch-gallery-shell';
			const preview = iframeDocument.createElement( 'div' );
			preview.className = 'uplink-mbe-etch-gallery-preview';
			if ( result.success && result.html ) {
				preview.innerHTML = result.html;
			} else {
				preview.appendChild( statusElement( 'uplink-mbe-etch-gallery-status', strings.emptyPreview || 'Choose a populated collection to preview the gallery.' ) );
			}

			// The builder preview is intentionally non-interactive. Selection and editing
			// belong to Etch; the published gallery retains its configured lightbox.
			preview.addEventListener( 'click', ( event ) => event.preventDefault(), true );
			const toolbar = iframeDocument.createElement( 'div' );
			toolbar.className = 'uplink-mbe-etch-gallery-toolbar';
			const label = iframeDocument.createElement( 'span' );
			label.textContent = 'Collection Gallery';
			const edit = iframeDocument.createElement( 'button' );
			edit.type = 'button';
			edit.className = 'uplink-mbe-etch-gallery-edit';
			edit.textContent = strings.edit || 'Edit gallery';
			edit.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				openEditor( blockId, edit );
			} );
			toolbar.append( label, edit );
			shell.append( preview, toolbar );
			placeholder.replaceChildren( shell );
			placeholder.dataset.uplinkMbeEtchGallery = 'ready';
		} catch ( error ) {
			if ( processed.get( placeholder ) === token && placeholder.isConnected ) {
				placeholder.dataset.uplinkMbeEtchGallery = 'error';
				placeholder.replaceChildren( statusElement( 'uplink-mbe-etch-gallery-status is-error', strings.previewError || 'The gallery preview could not be loaded.' ) );
			}
		}
	}

	function processPlaceholder( placeholder ) {
		if ( ! placeholder.matches?.( '.etch-passthrough-block' ) || placeholder.classList.contains( 'ee-converted' ) ) {
			return;
		}
		const blockId = placeholder.getAttribute( 'data-etch-id' );
		const block = galleryBlock( blockId );
		if ( ! block ) {
			return;
		}
		const signature = JSON.stringify( block.gutenbergBlock.attrs || {} );
		if ( placeholder.dataset.uplinkMbeEtchGallerySignature === signature && [ 'loading', 'ready' ].includes( placeholder.dataset.uplinkMbeEtchGallery ) ) {
			return;
		}
		placeholder.dataset.uplinkMbeEtchGallerySignature = signature;
		renderPlaceholder( placeholder, blockId, { ...defaults, ...( block.gutenbergBlock.attrs || {} ) } );
	}

	function scan( root = iframeDocument ) {
		if ( ! root ) {
			return;
		}
		if ( root.matches?.( '.etch-passthrough-block' ) ) {
			processPlaceholder( root );
		}
		root.querySelectorAll?.( '.etch-passthrough-block' ).forEach( processPlaceholder );
	}

	function attachToIframe( frame ) {
		const nextDocument = frame.contentDocument;
		if ( ! nextDocument || ! nextDocument.body ) {
			return false;
		}
		if ( iframeDocument === nextDocument && observer ) {
			return true;
		}
		observer?.disconnect();
		iframeDocument = nextDocument;
		copyGalleryStyles();
		observer = new MutationObserver( ( mutations ) => {
			mutations.forEach( ( mutation ) => {
				mutation.addedNodes.forEach( ( node ) => {
					if ( node.nodeType === Node.ELEMENT_NODE ) {
						scan( node );
					}
				} );
				if ( mutation.type === 'attributes' ) {
					processPlaceholder( mutation.target );
				}
			} );
		} );
		observer.observe( iframeDocument.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'data-etch-id', 'class' ] } );
		scan();
		return true;
	}

	function waitForIframe() {
		const find = () => {
			const frame = document.querySelector( 'iframe[title="Etch Iframe"]' );
			if ( ! frame || ! attachToIframe( frame ) ) {
				window.setTimeout( find, 250 );
			}
		};
		find();
	}

	function field( form, type, key, labelText, options = [] ) {
		const wrapper = document.createElement( 'label' );
		wrapper.className = `uplink-mbe-etch-field is-${ type }`;
		const label = document.createElement( 'span' );
		label.textContent = labelText;
		let control;
		if ( type === 'select' ) {
			control = document.createElement( 'select' );
			options.forEach( ( option ) => {
				const item = document.createElement( 'option' );
				item.value = String( option.value );
				item.textContent = option.label;
				control.appendChild( item );
			} );
		} else {
			control = document.createElement( 'input' );
			control.type = type;
			if ( type === 'number' ) {
				const limits = options[ 0 ] || {};
				Object.entries( limits ).forEach( ( [ name, value ] ) => control.setAttribute( name, String( value ) ) );
			}
		}
		control.name = key;
		control.id = `uplink-mbe-etch-${ key }`;
		wrapper.htmlFor = control.id;
		if ( type === 'checkbox' ) {
			wrapper.append( control, label );
		} else {
			wrapper.append( label, control );
		}
		form.appendChild( wrapper );
		return control;
	}

	function collectionOptions() {
		return [ { value: 0, label: 'Choose a collection' } ].concat(
			( config.collections || [] ).map( ( collection ) => ( {
				value: collection.id,
				label: `${ collection.parent ? '— ' : '' }${ collection.name }`,
			} ) )
		);
	}

	function setFormValues( form, attributes ) {
		Object.entries( { ...defaults, ...attributes, sortOrder: attributes.sortOrder || ( attributes.randomOrder ? 'random' : 'date' ) } ).forEach( ( [ key, value ] ) => {
			const control = form.elements.namedItem( key );
			if ( ! control ) {
				return;
			}
			if ( control.multiple ) {
				Array.from( control.options ).forEach( ( option ) => { option.selected = Array.isArray( value ) && value.includes( option.value ); } );
			} else if ( control.type === 'checkbox' ) {
				control.checked = Boolean( value );
			} else {
				control.value = String( [ 'sliderInterval', 'lightboxInterval' ].includes( key ) ? Number( value ) / 1000 : value );
			}
		} );
	}

	function formValues( form ) {
		const values = {};
		Array.from( form.elements ).forEach( ( control ) => {
			if ( ! control.name ) {
				return;
			}
			if ( control.multiple ) {
				values[ control.name ] = Array.from( control.selectedOptions, ( option ) => option.value );
			} else if ( control.type === 'checkbox' ) {
				values[ control.name ] = control.checked;
			} else if ( control.type === 'number' ) {
				values[ control.name ] = Number( control.value ) * ( [ 'sliderInterval', 'lightboxInterval' ].includes( control.name ) ? 1000 : 1 );
			} else {
				values[ control.name ] = control.value;
			}
		} );
		values.collectionId = Number( values.collectionId );
		return values;
	}

	function authoringChild( block ) {
		const output = {
			type: block.type,
			version: block.version || 1,
			context: block.context || {},
			children: ( block.children || [] ).map( authoringChild ),
		};
		[ 'script', 'options' ].forEach( ( key ) => {
			if ( block[ key ] !== undefined ) {
				output[ key ] = block[ key ];
			}
		} );
		if ( block.type === 'etch/passthrough' ) {
			output.gutenbergBlock = block.gutenbergBlock;
		}
		return output;
	}

	async function saveAttributes( blockId, values ) {
		const etch = etchApi();
		const current = galleryBlock( blockId );
		if ( ! etch || ! current || typeof etch.blocks.replace !== 'function' ) {
			throw new Error( 'The Etch passthrough API is unavailable.' );
		}
		const replacement = authoringChild( current );
		replacement.gutenbergBlock = {
			...current.gutenbergBlock,
			attrs: { ...( current.gutenbergBlock.attrs || {} ), ...values },
		};
		const newId = etch.blocks.replace( blockId, replacement );
		await etch.saveAsync();
		return newId;
	}

	function closeEditor() {
		if ( ! modal ) {
			return;
		}
		const closing = modal;
		document.removeEventListener( 'keydown', closing.onKeydown );
		closing.overlay.remove();
		closing.inertSiblings.forEach( ( item ) => {
			item.element.inert = item.wasInert;
		} );
		modal = null;
		let returnFocus = closing.returnFocus;
		if ( ! returnFocus?.isConnected && iframeDocument ) {
			returnFocus = Array.from( iframeDocument.querySelectorAll( '.uplink-mbe-etch-gallery-edit' ) ).find( ( button ) => (
				button.closest( '.etch-passthrough-block' )?.getAttribute( 'data-etch-id' ) === closing.returnBlockId
			) );
		}
		returnFocus?.focus( { preventScroll: true } );
	}

	function openEditor( blockId, trigger = null ) {
		const block = galleryBlock( blockId );
		if ( ! block ) {
			return;
		}
		closeEditor();
		const overlay = document.createElement( 'div' );
		overlay.className = 'uplink-mbe-etch-modal-overlay';
		const dialog = document.createElement( 'section' );
		dialog.className = 'uplink-mbe-etch-modal';
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', 'uplink-mbe-etch-modal-title' );
		dialog.tabIndex = -1;
		const heading = document.createElement( 'h2' );
		heading.id = 'uplink-mbe-etch-modal-title';
		heading.textContent = strings.editing || 'Collection Gallery settings';
		const form = document.createElement( 'form' );
		form.className = 'uplink-mbe-etch-form';
		field( form, 'select', 'collectionId', `${ config.managerLabel || 'Etch Collections' } folder`, collectionOptions() );
		field( form, 'checkbox', 'includeChildren', 'Include child collections' );
		field( form, 'select', 'layout', 'Layout', [
			{ value: 'slider', label: 'Slider' },
			{ value: 'grid', label: 'Standard grid' },
			{ value: 'tiled', label: 'Tiled mosaic' },
			{ value: 'circles', label: 'Circular grid' },
			{ value: 'square', label: 'Square tiles' },
			{ value: 'columns', label: 'Tiled columns' },
		] );
		field( form, 'number', 'columns', 'Gallery columns', [ { min: 1, max: 8, step: 1 } ] );
		field( form, 'checkbox', 'sliderAutoplay', 'Autoplay' );
		field( form, 'text', 'sliderProgressColor', 'Progress bar color (hex, blank to inherit)' );
		field( form, 'checkbox', 'sliderShowSlideshow', 'Show fullscreen slideshow button' );
		field( form, 'checkbox', 'sliderShowStatus', 'Show slider image count' );
		field( form, 'select', 'sliderStatusPosition', 'Status position', [ { value: 'auto', label: 'Automatic' }, { value: 'before', label: 'Before buttons' }, { value: 'between', label: 'Between buttons' }, { value: 'after', label: 'After buttons' }, { value: 'above', label: 'Above buttons' }, { value: 'below', label: 'Below buttons' } ] );
		field( form, 'number', 'sliderInterval', 'Seconds between images', [ { min: 2, max: 20, step: 1 } ] );
		field( form, 'number', 'slidesPerView', 'Slider images on desktop', [ { min: 1, max: 8, step: 1 } ] );
		field( form, 'number', 'carouselTablet', 'Slider images on tablet', [ { min: 1, max: 8, step: 1 } ] );
		field( form, 'number', 'carouselMobile', 'Slider images on mobile', [ { min: 1, max: 8, step: 1 } ] );
		field( form, 'select', 'navPosition', 'Button position', [ { value: 'bottom', label: 'Below images' }, { value: 'top', label: 'Above images' }, { value: 'overlay', label: 'Over images' } ] );
		field( form, 'select', 'navAlignment', 'Button alignment', [ { value: 'spread', label: 'Opposite sides' }, { value: 'start', label: 'Start' }, { value: 'center', label: 'Center' }, { value: 'end', label: 'End' } ] );
		field( form, 'select', 'navShape', 'Button shape', [ { value: 'square', label: 'Square' }, { value: 'rounded', label: 'Rounded' }, { value: 'pill', label: 'Pill' } ] );
		field( form, 'select', 'navContent', 'Button content', [ { value: 'text', label: 'Text' }, { value: 'icon', label: 'Icons' }, { value: 'both', label: 'Text and icons' } ] );
		field( form, 'number', 'gap', 'Spacing', [ { min: 0, max: 40, step: 1 } ] );
		field( form, 'select', 'sizeSlug', 'Image resolution', config.imageSizes || [] );
		field( form, 'checkbox', 'imageCrop', 'Crop images to fit' );
		field( form, 'select', 'aspectRatio', 'Aspect ratio', [
			{ value: 'auto', label: 'Original' },
			{ value: '1/1', label: 'Square' },
			{ value: '4/3', label: 'Landscape 4:3' },
			{ value: '3/2', label: 'Landscape 3:2' },
			{ value: '16/9', label: 'Widescreen 16:9' },
			{ value: '3/4', label: 'Portrait 3:4' },
		] );
		field( form, 'select', 'sortOrder', 'Image order', [
			{ value: 'date', label: 'Newest first' },
			{ value: 'custom', label: 'Custom order' },
			{ value: 'random', label: 'Random' },
		] );
		field( form, 'checkbox', 'showTitle', 'Show image titles' );
		field( form, 'checkbox', 'showCaptions', 'Show image captions' );
		field( form, 'select', 'lightboxMode', 'Image behavior', [
			{ value: 'custom', label: 'Custom gallery lightbox' },
			{ value: 'native', label: 'Native WordPress lightbox' },
			{ value: 'none', label: 'No interaction' },
		] );
		field( form, 'checkbox', 'lightboxSlideshow', 'Enable fullscreen slideshow' );
		field( form, 'checkbox', 'lightboxAutoplay', 'Autoplay when lightbox opens' );
		field( form, 'number', 'lightboxInterval', 'Lightbox seconds between images', [ { min: 2, max: 30, step: 1 } ] );
		field( form, 'checkbox', 'lightboxLoop', 'Loop slideshow' );
		field( form, 'checkbox', 'lightboxShowExif', 'Show EXIF photo details (requires EXIF enabled)' );
		field( form, 'select', 'lightboxExifFields', 'Photo detail fields (select multiple)', Object.entries( config.exifFields || {} ).map( ( [ value, label ] ) => ( { value, label } ) ) );
		form.elements.namedItem( 'lightboxExifFields' ).multiple = true;
		form.elements.namedItem( 'lightboxExifFields' ).size = 6;
		field( form, 'number', 'limit', 'Maximum images', [ { min: 1, max: 100, step: 1 } ] );
		setFormValues( form, block.gutenbergBlock.attrs || {} );
		const updateLayoutFields = () => {
			const slider = form.elements.namedItem( 'layout' ).value === 'slider';
			for ( const name of [ 'sliderProgressColor', 'sliderShowSlideshow', 'sliderShowStatus', 'sliderStatusPosition', 'sliderAutoplay', 'sliderInterval', 'slidesPerView', 'carouselTablet', 'carouselMobile', 'navPosition', 'navAlignment', 'navShape', 'navContent' ] ) {
				form.elements.namedItem( name ).closest( 'label' ).hidden = ! slider;
			}
			form.elements.namedItem( 'sliderStatusPosition' ).closest( 'label' ).hidden = ! slider || ! form.elements.namedItem( 'sliderShowStatus' ).checked;
			form.elements.namedItem( 'columns' ).closest( 'label' ).hidden = slider;
			const customLightbox = form.elements.namedItem( 'lightboxMode' ).value === 'custom';
			form.elements.namedItem( 'sliderShowSlideshow' ).disabled = ! customLightbox || ! form.elements.namedItem( 'lightboxSlideshow' ).checked;
			form.elements.namedItem( 'lightboxSlideshow' ).closest( 'label' ).hidden = ! customLightbox;
			for ( const name of [ 'lightboxAutoplay', 'lightboxInterval', 'lightboxLoop' ] ) form.elements.namedItem( name ).closest( 'label' ).hidden = ! customLightbox || ! form.elements.namedItem( 'lightboxSlideshow' ).checked;
		};
		form.elements.namedItem( 'layout' ).addEventListener( 'change', updateLayoutFields );
		form.elements.namedItem( 'sliderShowStatus' ).addEventListener( 'change', updateLayoutFields );
		form.elements.namedItem( 'lightboxMode' ).addEventListener( 'change', updateLayoutFields );
		form.elements.namedItem( 'lightboxSlideshow' ).addEventListener( 'change', updateLayoutFields );
		updateLayoutFields();


		const notice = document.createElement( 'p' );
		notice.id = 'uplink-mbe-etch-modal-description';
		notice.className = 'uplink-mbe-etch-modal-notice';
		notice.textContent = strings.advancedNotice || 'Additional settings remain available in the WordPress block editor.';
		dialog.setAttribute( 'aria-describedby', notice.id );
		const status = document.createElement( 'p' );
		status.className = 'uplink-mbe-etch-modal-status screen-reader-text';
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		status.setAttribute( 'aria-atomic', 'true' );
		const actions = document.createElement( 'div' );
		actions.className = 'uplink-mbe-etch-modal-actions';
		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'uplink-mbe-etch-secondary';
		cancel.textContent = strings.cancel || 'Cancel';
		cancel.addEventListener( 'click', closeEditor );
		const apply = document.createElement( 'button' );
		apply.type = 'submit';
		apply.className = 'uplink-mbe-etch-primary';
		apply.textContent = strings.apply || 'Apply and save';
		actions.append( cancel, apply );
		form.append( notice, status, actions );
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			apply.disabled = true;
			cancel.disabled = true;
			form.setAttribute( 'aria-busy', 'true' );
			status.classList.remove( 'is-error' );
			status.textContent = strings.saving || 'Saving gallery…';
			try {
				const newId = await saveAttributes( blockId, formValues( form ) );
				if ( modal?.overlay === overlay ) {
					modal.returnBlockId = newId || blockId;
				}
				status.textContent = '';
				window.uplinkMbeToast( strings.saved || 'Gallery saved.' );
				window.setTimeout( closeEditor, 350 );
			} catch ( error ) {
				form.removeAttribute( 'aria-busy' );
				status.classList.add( 'is-error' );
				status.textContent = '';
				window.uplinkMbeToast( strings.saveError || 'The gallery changes could not be saved.', 'error' );
				apply.disabled = false;
				cancel.disabled = false;
			}
		} );

		dialog.append( heading, form );
		overlay.appendChild( dialog );
		overlay.addEventListener( 'mousedown', ( event ) => {
			if ( event.target === overlay ) {
				closeEditor();
			}
		} );
		const onKeydown = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				closeEditor();
				return;
			}
			if ( event.key !== 'Tab' ) {
				return;
			}
			const focusable = Array.from( dialog.querySelectorAll( 'button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])' ) )
				.filter( ( element ) => ! element.hidden && element.getClientRects().length );
			if ( ! focusable.length ) {
				event.preventDefault();
				dialog.focus();
				return;
			}
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			if ( event.shiftKey && ( document.activeElement === first || ! dialog.contains( document.activeElement ) ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		};
		const inertSiblings = Array.from( document.body.children )
			.filter( ( element ) => element !== overlay )
			.map( ( element ) => ( { element, wasInert: element.inert } ) );
		inertSiblings.forEach( ( item ) => {
			item.element.inert = true;
		} );
		modal = { overlay, onKeydown, inertSiblings, returnFocus: trigger, returnBlockId: blockId };
		document.addEventListener( 'keydown', onKeydown );
		document.body.appendChild( overlay );
		form.querySelector( 'select, input' )?.focus();
	}

	waitForIframe();
}() );
