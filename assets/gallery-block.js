( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';

	const config = window.uplinkMbeGalleryBlock || { collections: [], imageSizes: [] };
	const el = element.createElement;
	const { registerBlockType } = blocks;
	const { InspectorControls, PanelColorSettings, useBlockProps } = blockEditor;
	const { Button, Modal, PanelBody, Placeholder, RangeControl, SelectControl, ToggleControl } = components;
	const { __, sprintf } = i18n;

	const collectionOptions = [ { label: __( 'Choose a collection', 'media-bridge-for-etch' ), value: 0 } ].concat(
		config.collections.map( ( collection ) => ( {
			label: `${ collection.parent ? '— ' : '' }${ collection.name }`,
			value: collection.id,
		} ) )
	);

	const layoutOptions = [
		{ label: __( 'Slider', 'media-bridge-for-etch' ), value: 'slider' },
		{ label: __( 'Standard grid', 'media-bridge-for-etch' ), value: 'grid' },
		{ label: __( 'Tiled mosaic', 'media-bridge-for-etch' ), value: 'tiled' },
		{ label: __( 'Circular grid', 'media-bridge-for-etch' ), value: 'circles' },
		{ label: __( 'Square tiles', 'media-bridge-for-etch' ), value: 'square' },
		{ label: __( 'Tiled columns', 'media-bridge-for-etch' ), value: 'columns' },
	];
	const lightboxFontOptions = [
		{ label: __( 'Inherit site font', 'media-bridge-for-etch' ), value: 'inherit' },
		{ label: __( 'System sans-serif', 'media-bridge-for-etch' ), value: 'system' },
		{ label: __( 'Serif', 'media-bridge-for-etch' ), value: 'serif' },
		{ label: __( 'Monospace', 'media-bridge-for-etch' ), value: 'mono' },
	];
	const lightboxWeightOptions = [ 300, 400, 500, 600, 700, 800, 900 ].map( ( weight ) => ( {
		label: String( weight ),
		value: weight,
	} ) );

	registerBlockType( 'uplinkpress/collection-gallery', {
		edit( { attributes, setAttributes } ) {
			const previewRef = element.useRef( null );
			element.useEffect( () => {
				const root = previewRef.current;
				if ( ! root ) return;
				const initialize = () => window.uplinkMbeInitSliders?.( root );
				const observer = new MutationObserver( initialize );
				observer.observe( root, { childList: true, subtree: true } );
				initialize();
				return () => observer.disconnect();
			}, [] );
			const [ previewImages, setPreviewImages ] = element.useState( [] );
			const [ previewIndex, setPreviewIndex ] = element.useState( 0 );
			const blockProps = useBlockProps( { className: 'uplink-mbe-gallery-editor', ref: previewRef } );
			const openLightboxPreview = () => {
				const images = previewRef.current
					? Array.from( previewRef.current.querySelectorAll( '.wp-block-image img' ) ).map( ( image ) => {
						const trigger = image.closest( '.uplink-mbe-custom-lightbox-trigger' );
						return {
							src: trigger ? trigger.dataset.uplinkMbeSrc : ( image.currentSrc || image.src ),
							thumb: image.currentSrc || image.src,
							alt: image.alt || '',
							title: trigger ? trigger.dataset.uplinkMbeTitle : ( image.alt || '' ),
							caption: trigger ? trigger.dataset.uplinkMbeCaption : '',
							exif: JSON.parse( trigger?.dataset.uplinkMbeExif || '[]' ),
						};
					} ).filter( ( image ) => image.src )
					: [];
				setPreviewIndex( 0 );
				setPreviewImages( images );
			};
			const collectionControl = el( SelectControl, {
				label: sprintf( __( '%s folder', 'media-bridge-for-etch' ), config.managerLabel || __( 'Etch Collections', 'media-bridge-for-etch' ) ),
				value: attributes.collectionId,
				options: collectionOptions,
				onChange: ( value ) => setAttributes( { collectionId: Number( value ) } ),
			} );

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Gallery source', 'media-bridge-for-etch' ), initialOpen: false },
						collectionControl,
						el( ToggleControl, {
							label: __( 'Include child collections', 'media-bridge-for-etch' ),
							checked: attributes.includeChildren,
							onChange: ( value ) => setAttributes( { includeChildren: value } ),
						} ),
						el( RangeControl, {
							label: __( 'Maximum images', 'media-bridge-for-etch' ),
							value: attributes.limit,
							min: 1,
							max: 100,
							onChange: ( value ) => setAttributes( { limit: value } ),
						} )
					),
					el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Layout', 'media-bridge-for-etch' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Style', 'media-bridge-for-etch' ),
							value: attributes.layout,
							options: layoutOptions,
							onChange: ( value ) => setAttributes( { layout: value } ),
						} ),
						attributes.layout !== 'slider' && el( RangeControl, {
							label: __( 'Columns', 'media-bridge-for-etch' ),
							value: attributes.columns,
							min: 1,
							max: 8,
							onChange: ( value ) => setAttributes( { columns: value } ),
						} ),
						attributes.layout === 'slider' && el( RangeControl, {
							label: __( 'Images shown — desktop', 'media-bridge-for-etch' ), value: attributes.slidesPerView, min: 1, max: 8,
							help: __( 'Choose one for a single-image slider or several for a carousel.', 'media-bridge-for-etch' ),
							onChange: ( value ) => setAttributes( { slidesPerView: value } ),
						} ),
						attributes.layout === 'slider' && el( RangeControl, {
							label: __( 'Images shown — tablet', 'media-bridge-for-etch' ), value: attributes.carouselTablet, min: 1, max: 8,
							onChange: ( value ) => setAttributes( { carouselTablet: value } ),
						} ),
						attributes.layout === 'slider' && el( RangeControl, {
							label: __( 'Images shown — mobile', 'media-bridge-for-etch' ), value: attributes.carouselMobile, min: 1, max: 8,
							onChange: ( value ) => setAttributes( { carouselMobile: value } ),
						} ),
						el( RangeControl, {
							label: __( 'Spacing', 'media-bridge-for-etch' ),
							value: attributes.gap,
							min: 0,
							max: 40,
							onChange: ( value ) => setAttributes( { gap: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Resolution', 'media-bridge-for-etch' ),
							value: attributes.sizeSlug,
							options: config.imageSizes,
							onChange: ( value ) => setAttributes( { sizeSlug: value } ),
						} ),
						el( ToggleControl, {
							label: __( 'Crop images to fit', 'media-bridge-for-etch' ),
							checked: attributes.imageCrop,
							onChange: ( value ) => setAttributes( { imageCrop: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Aspect ratio', 'media-bridge-for-etch' ),
							value: attributes.aspectRatio,
							options: [
								{ label: __( 'Original', 'media-bridge-for-etch' ), value: 'auto' },
								{ label: __( 'Square', 'media-bridge-for-etch' ), value: '1/1' },
								{ label: __( 'Landscape 4:3', 'media-bridge-for-etch' ), value: '4/3' },
								{ label: __( 'Landscape 3:2', 'media-bridge-for-etch' ), value: '3/2' },
								{ label: __( 'Widescreen 16:9', 'media-bridge-for-etch' ), value: '16/9' },
								{ label: __( 'Portrait 3:4', 'media-bridge-for-etch' ), value: '3/4' },
							],
							onChange: ( value ) => setAttributes( { aspectRatio: value } ),
						} )
					),
					attributes.layout === 'slider' && el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Playback', 'media-bridge-for-etch' ), initialOpen: false },
						el( ToggleControl, { label: __( 'Autoplay', 'media-bridge-for-etch' ), checked: attributes.sliderAutoplay,
							help: __( 'Visitors can play or pause. Automatic playback respects reduced motion and pauses on hover.', 'media-bridge-for-etch' ),
							onChange: ( value ) => setAttributes( { sliderAutoplay: value } ),
						} ),
						el( RangeControl, { label: __( 'Seconds between images', 'media-bridge-for-etch' ), value: attributes.sliderInterval / 1000, min: 2, max: 20, step: 1,
							onChange: ( value ) => setAttributes( { sliderInterval: value * 1000 } ),
						} ),
						el( ToggleControl, { label: __( 'Show progress indicator', 'media-bridge-for-etch' ), checked: attributes.sliderShowProgress, onChange: ( value ) => setAttributes( { sliderShowProgress: value } ) } ),
						attributes.sliderShowProgress && el( SelectControl, { label: __( 'Progress style', 'media-bridge-for-etch' ), value: attributes.sliderProgressStyle, options: [ { value: 'bar', label: __( 'Full-width bar', 'media-bridge-for-etch' ) }, { value: 'segments', label: __( 'Segmented pills', 'media-bridge-for-etch' ) }, { value: 'dots', label: __( 'Dots with active pill', 'media-bridge-for-etch' ) }, { value: 'line', label: __( 'Line beneath Play/Pause', 'media-bridge-for-etch' ) }, { value: 'ring', label: __( 'Circular timer', 'media-bridge-for-etch' ) } ], onChange: ( value ) => setAttributes( { sliderProgressStyle: value } ) } ),
						attributes.sliderShowProgress && el( PanelColorSettings, { title: __( 'Progress color', 'media-bridge-for-etch' ), colorSettings: [ { value: attributes.sliderProgressColor, onChange: ( value ) => setAttributes( { sliderProgressColor: value || '' } ), label: __( 'Progress color', 'media-bridge-for-etch' ) } ] } ),
					),
					attributes.layout === 'slider' && el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Slider Controls', 'media-bridge-for-etch' ), initialOpen: false },
						el( SelectControl, { label: __( 'Button position', 'media-bridge-for-etch' ), value: attributes.navPosition,
							options: [{ label: __( 'Below images', 'media-bridge-for-etch' ), value: 'bottom' }, { label: __( 'Above images', 'media-bridge-for-etch' ), value: 'top' }, { label: __( 'Over images', 'media-bridge-for-etch' ), value: 'overlay' }],
							onChange: ( value ) => setAttributes( { navPosition: value } ),
						} ),
						el( SelectControl, { label: __( 'Button alignment', 'media-bridge-for-etch' ), value: attributes.navAlignment,
							options: [{ label: __( 'Opposite sides', 'media-bridge-for-etch' ), value: 'spread' }, { label: __( 'Start', 'media-bridge-for-etch' ), value: 'start' }, { label: __( 'Center', 'media-bridge-for-etch' ), value: 'center' }, { label: __( 'End', 'media-bridge-for-etch' ), value: 'end' }],
							onChange: ( value ) => setAttributes( { navAlignment: value } ),
						} ),
						el( SelectControl, { label: __( 'Button shape', 'media-bridge-for-etch' ), value: attributes.navShape,
							options: [{ label: __( 'Square', 'media-bridge-for-etch' ), value: 'square' }, { label: __( 'Rounded', 'media-bridge-for-etch' ), value: 'rounded' }, { label: __( 'Pill', 'media-bridge-for-etch' ), value: 'pill' }],
							onChange: ( value ) => setAttributes( { navShape: value } ),
						} ),
						el( SelectControl, { label: __( 'Button content', 'media-bridge-for-etch' ), value: attributes.navContent,
							options: [{ label: __( 'Text', 'media-bridge-for-etch' ), value: 'text' }, { label: __( 'Icons', 'media-bridge-for-etch' ), value: 'icon' }, { label: __( 'Text and icons', 'media-bridge-for-etch' ), value: 'both' }],
							onChange: ( value ) => setAttributes( { navContent: value } ),
						} ),
						el( RangeControl, { label: __( 'Button text size', 'media-bridge-for-etch' ), value: attributes.navTextSize || 0, min: 0, max: 40, help: __( 'Pixels. Set to 0 to inherit the site font size.', 'media-bridge-for-etch' ), onChange: ( value ) => setAttributes( { navTextSize: value === 0 ? 0 : Math.max( 10, value ) } ) } ),
						el( RangeControl, { label: __( 'Button icon size', 'media-bridge-for-etch' ), value: attributes.navIconSize, min: 12, max: 64, help: __( 'Pixels.', 'media-bridge-for-etch' ), onChange: ( value ) => setAttributes( { navIconSize: value } ) } ),
						el( ToggleControl, { label: __( 'Show fullscreen slideshow button', 'media-bridge-for-etch' ), checked: attributes.sliderShowSlideshow, disabled: attributes.lightboxMode !== 'custom' || ! attributes.lightboxSlideshow, help: __( 'Launches from the current image. Requires the custom lightbox and fullscreen slideshow to be enabled.', 'media-bridge-for-etch' ), onChange: ( value ) => setAttributes( { sliderShowSlideshow: value } ) } ),
						el( ToggleControl, { label: __( 'Show image count', 'media-bridge-for-etch' ), checked: attributes.sliderShowStatus,
							onChange: ( value ) => setAttributes( { sliderShowStatus: value } ) } ),
						attributes.sliderShowStatus && el( SelectControl, { label: __( 'Status position', 'media-bridge-for-etch' ), value: attributes.sliderStatusPosition,
							options: [
								{ value: 'left', label: __( 'Status left, buttons right', 'media-bridge-for-etch' ) },
								{ value: 'right', label: __( 'Status right, buttons left', 'media-bridge-for-etch' ) },
								{ value: 'auto', label: __( 'Automatic', 'media-bridge-for-etch' ) },
								{ value: 'before', label: __( 'Before buttons', 'media-bridge-for-etch' ) },
								{ value: 'between', label: __( 'Between buttons', 'media-bridge-for-etch' ) },
								{ value: 'after', label: __( 'After buttons', 'media-bridge-for-etch' ) },
								{ value: 'above', label: __( 'Above buttons', 'media-bridge-for-etch' ) },
								{ value: 'below', label: __( 'Below buttons', 'media-bridge-for-etch' ) },
							], onChange: ( value ) => setAttributes( { sliderStatusPosition: value } ) } )
					),
					el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Gallery options', 'media-bridge-for-etch' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Image order', 'media-bridge-for-etch' ),
							value: attributes.sortOrder || ( attributes.randomOrder ? 'random' : 'date' ),
							options: [
								{ label: __( 'Newest first', 'media-bridge-for-etch' ), value: 'date' },
								{ label: __( 'Custom order', 'media-bridge-for-etch' ), value: 'custom' },
								{ label: __( 'Random', 'media-bridge-for-etch' ), value: 'random' },
							],
							help: __( 'Set custom order by dragging images within this collection in the Enhanced Media Manager.', 'media-bridge-for-etch' ),
							onChange: ( value ) => setAttributes( { sortOrder: value, randomOrder: 'random' === value } ),
						} )
					),
					el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Lightbox', 'media-bridge-for-etch' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Image behavior', 'media-bridge-for-etch' ),
							help: __( 'Images are never linked to an attachment page or directly to a media file.', 'media-bridge-for-etch' ),
							value: attributes.lightboxMode,
							options: [
								{ label: __( 'Custom gallery lightbox', 'media-bridge-for-etch' ), value: 'custom' },
								{ label: __( 'Native WordPress lightbox', 'media-bridge-for-etch' ), value: 'native' },
								{ label: __( 'No interaction', 'media-bridge-for-etch' ), value: 'none' },
							],
							onChange: ( value ) => setAttributes( { lightboxMode: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( ToggleControl, {
							label: __( 'Show title in lightbox', 'media-bridge-for-etch' ),
							checked: attributes.lightboxShowTitle,
							onChange: ( value ) => setAttributes( { lightboxShowTitle: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( ToggleControl, {
							label: __( 'Show caption in lightbox', 'media-bridge-for-etch' ),
							checked: attributes.lightboxShowCaption,
							onChange: ( value ) => setAttributes( { lightboxShowCaption: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( ToggleControl, {
							label: __( 'Show thumbnail strip', 'media-bridge-for-etch' ),
							checked: attributes.lightboxThumbnails,
							onChange: ( value ) => setAttributes( { lightboxThumbnails: value } ),
						} ),
						attributes.lightboxMode === 'custom' && attributes.lightboxThumbnails && el( SelectControl, {
							label: __( 'Thumbnail strip position', 'media-bridge-for-etch' ),
							value: attributes.lightboxThumbnailPosition,
							options: [
								{ label: __( 'Horizontal', 'media-bridge-for-etch' ), value: 'horizontal' },
								{ label: __( 'Vertical', 'media-bridge-for-etch' ), value: 'vertical' },
							],
							onChange: ( value ) => setAttributes( { lightboxThumbnailPosition: value } ),
						} ),
						attributes.lightboxMode === 'custom' && ( attributes.lightboxShowTitle || attributes.lightboxShowCaption ) && el( SelectControl, {
							label: __( 'Information position', 'media-bridge-for-etch' ),
							value: attributes.lightboxInfoPosition,
							options: [
								{ label: __( 'Above image', 'media-bridge-for-etch' ), value: 'top' },
								{ label: __( 'Below image', 'media-bridge-for-etch' ), value: 'bottom' },
							],
							onChange: ( value ) => setAttributes( { lightboxInfoPosition: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( SelectControl, {
							label: __( 'Lightbox image resolution', 'media-bridge-for-etch' ),
							value: attributes.lightboxSizeSlug,
							options: config.imageSizes,
							onChange: ( value ) => setAttributes( { lightboxSizeSlug: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( ToggleControl, {
							label: __( 'Fullscreen control', 'media-bridge-for-etch' ),
							checked: attributes.lightboxFullscreen,
							onChange: ( value ) => setAttributes( { lightboxFullscreen: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( ToggleControl, {
							label: __( 'Zoom controls', 'media-bridge-for-etch' ),
							checked: attributes.lightboxZoom,
							onChange: ( value ) => setAttributes( { lightboxZoom: value } ),
						} ),
						attributes.lightboxMode === 'custom' && el( ToggleControl, { label: __( 'Enable fullscreen slideshow', 'media-bridge-for-etch' ), checked: attributes.lightboxSlideshow, onChange: ( value ) => setAttributes( { lightboxSlideshow: value } ) } ),
						attributes.lightboxMode === 'custom' && attributes.lightboxSlideshow && el( ToggleControl, { label: __( 'Autoplay when lightbox opens', 'media-bridge-for-etch' ), checked: attributes.lightboxAutoplay, onChange: ( value ) => setAttributes( { lightboxAutoplay: value } ), help: __( 'Opens the minimal slideshow view. Reduced-motion preferences start it paused.', 'media-bridge-for-etch' ) } ),
						attributes.lightboxMode === 'custom' && attributes.lightboxSlideshow && el( RangeControl, { label: __( 'Lightbox seconds between images', 'media-bridge-for-etch' ), value: attributes.lightboxInterval / 1000, min: 2, max: 30, step: 1, onChange: ( value ) => setAttributes( { lightboxInterval: value * 1000 } ), help: __( 'Independent of the in-page slider timer.', 'media-bridge-for-etch' ) } ),
						attributes.lightboxMode === 'custom' && attributes.lightboxSlideshow && el( ToggleControl, { label: __( 'Loop slideshow', 'media-bridge-for-etch' ), checked: attributes.lightboxLoop, onChange: ( value ) => setAttributes( { lightboxLoop: value } ) } ),
						attributes.lightboxMode !== 'none' && attributes.collectionId > 0 && el( Button, {
							variant: 'secondary',
							onClick: openLightboxPreview,
							className: 'uplink-mbe-gallery-preview-button',
							'aria-label': __( 'Preview the selected lightbox layout', 'media-bridge-for-etch' ),
						}, __( 'Preview lightbox', 'media-bridge-for-etch' ) ),
					),
					attributes.lightboxMode === 'custom' && el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Lightbox Styles', 'media-bridge-for-etch' ), initialOpen: false },
						attributes.lightboxMode === 'custom' && ( attributes.lightboxShowTitle || attributes.lightboxShowCaption ) && el( SelectControl, {
							label: __( 'Lightbox font family', 'media-bridge-for-etch' ),
							value: attributes.lightboxFontFamily,
							options: lightboxFontOptions,
							onChange: ( value ) => setAttributes( { lightboxFontFamily: value } ),
						} ),
						attributes.lightboxMode === 'custom' && attributes.lightboxShowTitle && el( RangeControl, {
							label: __( 'Lightbox title font size', 'media-bridge-for-etch' ),
							value: attributes.lightboxTitleFontSize,
							min: 12,
							max: 48,
							onChange: ( value ) => setAttributes( { lightboxTitleFontSize: value } ),
						} ),
						attributes.lightboxMode === 'custom' && attributes.lightboxShowTitle && el( SelectControl, {
							label: __( 'Lightbox title font weight', 'media-bridge-for-etch' ),
							value: attributes.lightboxTitleFontWeight,
							options: lightboxWeightOptions,
							onChange: ( value ) => setAttributes( { lightboxTitleFontWeight: Number( value ) } ),
						} ),
						attributes.lightboxMode === 'custom' && attributes.lightboxShowCaption && el( RangeControl, {
							label: __( 'Lightbox caption font size', 'media-bridge-for-etch' ),
							value: attributes.lightboxCaptionFontSize,
							min: 10,
							max: 36,
							onChange: ( value ) => setAttributes( { lightboxCaptionFontSize: value } ),
						} ),
						attributes.lightboxMode === 'custom' && attributes.lightboxShowCaption && el( SelectControl, {
							label: __( 'Lightbox caption font weight', 'media-bridge-for-etch' ),
							value: attributes.lightboxCaptionFontWeight,
							options: lightboxWeightOptions,
							onChange: ( value ) => setAttributes( { lightboxCaptionFontWeight: Number( value ) } ),
						} ),
					el( PanelColorSettings, {
						title: __( 'Lightbox colors', 'media-bridge-for-etch' ),
						initialOpen: false,
						colorSettings: [
							{ value: attributes.lightboxBackgroundColor, onChange: ( value ) => setAttributes( { lightboxBackgroundColor: value || '#111315' } ), label: __( 'Background', 'media-bridge-for-etch' ) },
							{ value: attributes.lightboxPanelColor, onChange: ( value ) => setAttributes( { lightboxPanelColor: value || '#1d2125' } ), label: __( 'Information panel', 'media-bridge-for-etch' ) },
							attributes.lightboxShowTitle && { value: attributes.lightboxTitleColor, onChange: ( value ) => setAttributes( { lightboxTitleColor: value || '#ffffff' } ), label: __( 'Title', 'media-bridge-for-etch' ) },
							attributes.lightboxShowCaption && { value: attributes.lightboxCaptionColor, onChange: ( value ) => setAttributes( { lightboxCaptionColor: value || '#d9dde1' } ), label: __( 'Caption', 'media-bridge-for-etch' ) },
						].filter( Boolean ),
					} ),
					),
					attributes.lightboxMode === 'custom' && el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Lightbox Photo Details', 'media-bridge-for-etch' ), initialOpen: false },
						el( ToggleControl, { label: __( 'Show EXIF popover', 'media-bridge-for-etch' ), checked: attributes.lightboxShowExif, disabled: ! config.exifEnabled,
							help: config.exifEnabled ? __( 'Only selected fields with available data appear.', 'media-bridge-for-etch' ) : __( 'Enable EXIF dynamic data in Media Bridge settings first.', 'media-bridge-for-etch' ),
							onChange: ( value ) => setAttributes( { lightboxShowExif: value } ) } ),
						config.exifEnabled && attributes.lightboxShowExif && Object.entries( config.exifFields || {} ).map( ( [ key, label ] ) => el( ToggleControl, {
							key, label, checked: ( attributes.lightboxExifFields || [] ).includes( key ),
							onChange: ( checked ) => setAttributes( { lightboxExifFields: checked ? [ ...attributes.lightboxExifFields, key ] : attributes.lightboxExifFields.filter( ( field ) => field !== key ) } ),
						} ) )
					),
					el( PanelBody, { className: 'uplink-mbe-settings-panel', title: __( 'Thumbnail title and caption', 'media-bridge-for-etch' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Show title on thumbnails', 'media-bridge-for-etch' ),
							checked: attributes.showTitle,
							onChange: ( value ) => setAttributes( { showTitle: value } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show captions on thumbnails', 'media-bridge-for-etch' ),
							checked: attributes.showCaptions,
							onChange: ( value ) => setAttributes( { showCaptions: value } ),
						} ),
						( attributes.showTitle || attributes.showCaptions ) && el( SelectControl, {
							label: __( 'Text position', 'media-bridge-for-etch' ),
							value: attributes.textPosition,
							options: [
								{ label: __( 'Top', 'media-bridge-for-etch' ), value: 'top' },
								{ label: __( 'Center', 'media-bridge-for-etch' ), value: 'center' },
								{ label: __( 'Bottom', 'media-bridge-for-etch' ), value: 'bottom' },
							],
							onChange: ( value ) => setAttributes( { textPosition: value } ),
						} ),
						( attributes.showTitle || attributes.showCaptions ) && el( SelectControl, {
							label: __( 'Text alignment', 'media-bridge-for-etch' ),
							value: attributes.textAlign,
							options: [
								{ label: __( 'Left', 'media-bridge-for-etch' ), value: 'left' },
								{ label: __( 'Center', 'media-bridge-for-etch' ), value: 'center' },
								{ label: __( 'Right', 'media-bridge-for-etch' ), value: 'right' },
							],
							onChange: ( value ) => setAttributes( { textAlign: value } ),
						} ),
						attributes.showTitle && el( RangeControl, {
							label: __( 'Title font size', 'media-bridge-for-etch' ),
							value: attributes.titleFontSize,
							min: 12,
							max: 40,
							onChange: ( value ) => setAttributes( { titleFontSize: value } ),
						} ),
						attributes.showCaptions && el( RangeControl, {
							label: __( 'Caption font size', 'media-bridge-for-etch' ),
							value: attributes.captionFontSize,
							min: 10,
							max: 32,
							onChange: ( value ) => setAttributes( { captionFontSize: value } ),
						} ),
						( attributes.showTitle || attributes.showCaptions ) && el( RangeControl, {
							label: __( 'Background opacity', 'media-bridge-for-etch' ),
							value: attributes.textBackgroundOpacity,
							min: 0,
							max: 100,
							onChange: ( value ) => setAttributes( { textBackgroundOpacity: value } ),
						} ),
						( attributes.showTitle || attributes.showCaptions ) && el( PanelColorSettings, {
						title: __( 'Title and caption colors', 'media-bridge-for-etch' ),
						initialOpen: false,
						colorSettings: [
							attributes.showTitle && {
								value: attributes.titleColor,
								onChange: ( value ) => setAttributes( { titleColor: value || '#ffffff' } ),
								label: __( 'Title', 'media-bridge-for-etch' ),
							},
							attributes.showCaptions && {
								value: attributes.captionColor,
								onChange: ( value ) => setAttributes( { captionColor: value || '#ffffff' } ),
								label: __( 'Caption', 'media-bridge-for-etch' ),
							},
							{
								value: attributes.textBackgroundColor,
								onChange: ( value ) => setAttributes( { textBackgroundColor: value || '#000000' } ),
								label: __( 'Text background', 'media-bridge-for-etch' ),
							},
						].filter( Boolean ),
					} )
					)
				),
				previewImages.length > 0 && el( Modal, {
					title: attributes.lightboxMode === 'custom' ? __( 'Custom lightbox preview', 'media-bridge-for-etch' ) : __( 'Native lightbox preview', 'media-bridge-for-etch' ),
					className: 'uplink-mbe-lightbox-preview-modal',
					onRequestClose: () => setPreviewImages( [] ),
				},
					el( 'div', {
						className: `uplink-mbe-lightbox-preview is-${ attributes.lightboxMode } has-${ attributes.lightboxThumbnailPosition }-thumbnails info-${ attributes.lightboxInfoPosition }`,
						style: attributes.lightboxMode === 'custom' ? {
							backgroundColor: attributes.lightboxBackgroundColor,
							'--uplink-mbe-preview-panel': attributes.lightboxPanelColor,
							'--uplink-mbe-preview-title': attributes.lightboxTitleColor,
							'--uplink-mbe-preview-caption': attributes.lightboxCaptionColor,
							'--uplink-mbe-preview-title-size': `${ attributes.lightboxTitleFontSize }px`,
							'--uplink-mbe-preview-caption-size': `${ attributes.lightboxCaptionFontSize }px`,
							'--uplink-mbe-preview-font-family': { inherit: 'inherit', system: 'system-ui, sans-serif', serif: 'Georgia, serif', mono: 'ui-monospace, monospace' }[ attributes.lightboxFontFamily ] || 'inherit',
							'--uplink-mbe-preview-title-weight': attributes.lightboxTitleFontWeight,
							'--uplink-mbe-preview-caption-weight': attributes.lightboxCaptionFontWeight,
						} : {},
					},
						el( Button, {
							className: 'uplink-mbe-lightbox-preview-nav is-previous',
							disabled: previewImages.length < 2,
							onClick: () => setPreviewIndex( ( previewIndex - 1 + previewImages.length ) % previewImages.length ),
							'aria-label': __( 'Preview previous image', 'media-bridge-for-etch' ),
						}, '‹' ),
						el( 'div', { className: 'uplink-mbe-lightbox-preview-media' },
							el( 'img', {
								src: previewImages[ previewIndex ].src,
								alt: previewImages[ previewIndex ].alt,
							} ),
							attributes.lightboxMode === 'custom' && previewImages[ previewIndex ].exif.length > 0 && el( 'details', { key: previewIndex, className: 'uplink-mbe-preview-exif' },
								el( 'summary', null, __( 'Photo details', 'media-bridge-for-etch' ) ),
								el( 'dl', null, previewImages[ previewIndex ].exif.map( ( row ) => el( element.Fragment, { key: row.label }, el( 'dt', null, row.label ), el( 'dd', null, row.value ) ) ) )
							),
							attributes.lightboxMode === 'custom' && ( attributes.lightboxShowTitle || attributes.lightboxShowCaption ) && el( 'div', { className: 'uplink-mbe-lightbox-preview-info' },
								attributes.lightboxShowTitle && previewImages[ previewIndex ].title && el( 'strong', null, previewImages[ previewIndex ].title ),
								attributes.lightboxShowCaption && previewImages[ previewIndex ].caption && el( 'span', null, previewImages[ previewIndex ].caption )
							)
						),
						el( Button, {
							className: 'uplink-mbe-lightbox-preview-nav is-next',
							disabled: previewImages.length < 2,
							onClick: () => setPreviewIndex( ( previewIndex + 1 ) % previewImages.length ),
							'aria-label': __( 'Preview next image', 'media-bridge-for-etch' ),
						}, '›' ),
						attributes.lightboxMode === 'custom' && attributes.lightboxThumbnails && el( 'div', { className: 'uplink-mbe-lightbox-preview-thumbnails', 'aria-label': __( 'Preview thumbnails', 'media-bridge-for-etch' ) },
							previewImages.map( ( image, index ) => el( Button, {
								key: `${ image.src }-${ index }`,
								className: index === previewIndex ? 'is-current' : '',
								onClick: () => setPreviewIndex( index ),
								'aria-label': sprintf( __( 'Preview image %d', 'media-bridge-for-etch' ), index + 1 ),
							}, el( 'img', { src: image.thumb, alt: '' } ) ) )
						)
					),
					el( 'p', { className: 'uplink-mbe-lightbox-preview-note' },
						attributes.lightboxMode === 'custom'
							? __( 'Interactive layout preview. Fullscreen and zoom controls are available on the published page.', 'media-bridge-for-etch' )
							: __( 'Preview of the native WordPress lightbox layout. Final colors follow the active theme. WordPress does not display gallery titles or captions inside its lightbox.', 'media-bridge-for-etch' )
					)
				),
				el(
					'div',
					blockProps,
					attributes.collectionId
						? el( ServerSideRender, {
							block: 'uplinkpress/collection-gallery',
							attributes,
							skipBlockSupportAttributes: true,
						} )
						: el( Placeholder, {
							icon: 'format-gallery',
							label: __( 'Collection Gallery', 'media-bridge-for-etch' ),
							instructions: __( 'Choose a collection to display its images.', 'media-bridge-for-etch' ),
						}, collectionControl )
				)
			);
		},
		save() {
			return null;
		},
	} );
	blocks.registerBlockVariation( 'uplinkpress/collection-gallery', {
		name: 'collection-slider',
		title: __( 'Collection Slider', 'media-bridge-for-etch' ),
		description: __( 'Show one or several images at a time from a live Etch collection.', 'media-bridge-for-etch' ),
		icon: el( 'svg', { viewBox: '0 0 24 24', width: 24, height: 24, fill: 'none', 'aria-hidden': true, focusable: false },
			el( 'path', { d: 'M4.5 19.25C5.19036 19.25 5.75 19.8096 5.75 20.5C5.75 21.1904 5.19036 21.75 4.5 21.75C3.80964 21.75 3.25 21.1904 3.25 20.5C3.25 19.8096 3.80964 19.25 4.5 19.25ZM9.5 19.25C10.1904 19.25 10.75 19.8096 10.75 20.5C10.75 21.1904 10.1904 21.75 9.5 21.75C8.80964 21.75 8.25 21.1904 8.25 20.5C8.25 19.8096 8.80964 19.25 9.5 19.25ZM14.5 19.25C15.1904 19.25 15.75 19.8096 15.75 20.5C15.75 21.1904 15.1904 21.75 14.5 21.75C13.8096 21.75 13.25 21.1904 13.25 20.5C13.25 19.8096 13.8096 19.25 14.5 19.25ZM19.5 19.25C20.1904 19.25 20.75 19.8096 20.75 20.5C20.75 21.1904 20.1904 21.75 19.5 21.75C18.8096 21.75 18.25 21.1904 18.25 20.5C18.25 19.8096 18.8096 19.25 19.5 19.25ZM21.75 16.75H2.25V3.25H21.75V16.75ZM3.75 15.25H20.25V4.75H3.75V15.25Z', fill: 'currentColor' } )
		),
		attributes: { layout: 'slider', slidesPerView: 1, columns: 1, aspectRatio: '16/9', sortOrder: 'custom' },
		isActive: [ 'layout' ],
		scope: [ 'inserter' ],
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
