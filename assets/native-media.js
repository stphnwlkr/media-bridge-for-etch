( function () {
	'use strict';

	const config = window.uplinkMbeNativeMedia;
	const library = document.getElementById( 'uplink-mbe-native-library' );
	if ( ! config || ! library ) {
		return;
	}

	const elements = {
		wrap: document.querySelector( '.uplink-mbe-native-wrap' ),
		tree: document.getElementById( 'uplink-mbe-collection-tree' ),
		libraryTab: document.getElementById( 'uplink-mbe-library-tab' ),
		healthTab: document.getElementById( 'uplink-mbe-health-tab' ),
		healthTabCount: document.getElementById( 'uplink-mbe-health-tab-count' ),
		libraryNavigation: document.getElementById( 'uplink-mbe-library-navigation' ),
		healthNavigation: document.getElementById( 'uplink-mbe-health-navigation' ),
		healthDescription: document.getElementById( 'uplink-mbe-health-description' ),
		healthSummary: document.getElementById( 'uplink-mbe-health-summary' ),
		healthProgress: document.getElementById( 'uplink-mbe-health-progress-bar' ),
		healthStatus: document.getElementById( 'uplink-mbe-health-status' ),
		healthRescan: document.getElementById( 'uplink-mbe-health-rescan' ),
		grid: document.getElementById( 'uplink-mbe-media-grid' ),
		reorder: document.getElementById( 'uplink-mbe-reorder-media' ),
		gridSize: document.getElementById( 'uplink-mbe-grid-size' ),
		gridSizeValue: document.getElementById( 'uplink-mbe-grid-size-value' ),
		gridRatios: Array.from( document.querySelectorAll( '[data-grid-ratio]' ) ),
		infoToggle: document.getElementById( 'uplink-mbe-info-toggle' ),
		infoPopup: document.getElementById( 'uplink-mbe-info-popup' ),
		pagination: document.getElementById( 'uplink-mbe-media-pagination' ),
		notice: document.getElementById( 'uplink-mbe-native-notice' ),
		toast: document.getElementById( 'uplink-mbe-native-toast' ),
		searchForm: document.getElementById( 'uplink-mbe-media-search' ),
		searchInput: document.getElementById( 'uplink-mbe-search-input' ),
		searchStatus: document.getElementById( 'uplink-mbe-search-status' ),
		typeFilter: document.getElementById( 'uplink-mbe-type-filter' ),
		mimeFilter: document.getElementById( 'uplink-mbe-mime-filter' ),
		uploaderFilter: document.getElementById( 'uplink-mbe-uploader-filter' ),
		attachmentStatusFilter: document.getElementById( 'uplink-mbe-attachment-status-filter' ),
		optimizationStatusFilter: document.getElementById( 'uplink-mbe-optimization-status-filter' ),
		uploadedFromFilter: document.getElementById( 'uplink-mbe-uploaded-from-filter' ),
		uploadedToFilter: document.getElementById( 'uplink-mbe-uploaded-to-filter' ),
		widthMinFilter: document.getElementById( 'uplink-mbe-width-min-filter' ),
		widthMaxFilter: document.getElementById( 'uplink-mbe-width-max-filter' ),
		heightMinFilter: document.getElementById( 'uplink-mbe-height-min-filter' ),
		heightMaxFilter: document.getElementById( 'uplink-mbe-height-max-filter' ),
		fileSizeMinFilter: document.getElementById( 'uplink-mbe-file-size-min-filter' ),
		fileSizeMaxFilter: document.getElementById( 'uplink-mbe-file-size-max-filter' ),
		missingAltFilter: document.getElementById( 'uplink-mbe-missing-alt-filter' ),
		clearFilters: document.getElementById( 'uplink-mbe-clear-filters' ),
		drawer: document.getElementById( 'uplink-mbe-library-drawer' ),
		drawerToggle: document.getElementById( 'uplink-mbe-library-drawer-toggle' ),
		drawerPanel: document.getElementById( 'uplink-mbe-library-drawer-panel' ),
		drawerSummary: document.getElementById( 'uplink-mbe-library-drawer-summary' ),
		displayOptionsToggle: document.getElementById( 'uplink-mbe-display-options-toggle' ),
		displayOptions: document.getElementById( 'uplink-mbe-display-options' ),
		displayToggles: Array.from( document.querySelectorAll( '[data-display-field]' ) ),
		listView: document.getElementById( 'uplink-mbe-list-view' ),
		gridView: document.getElementById( 'uplink-mbe-grid-view' ),
		masonryView: document.getElementById( 'uplink-mbe-masonry-view' ),
		selectionTools: document.getElementById( 'uplink-mbe-selection-tools' ),
		selectionCount: document.getElementById( 'uplink-mbe-selection-count' ),
		bulkCollection: document.getElementById( 'uplink-mbe-bulk-collection' ),
		bulkAdd: document.getElementById( 'uplink-mbe-bulk-add' ),
		bulkRemove: document.getElementById( 'uplink-mbe-bulk-remove' ),
		bulkClear: document.getElementById( 'uplink-mbe-bulk-clear' ),
		bulkDeselect: document.getElementById( 'uplink-mbe-bulk-deselect' ),
		bulkDelete: document.getElementById( 'uplink-mbe-bulk-delete' ),
		upload: document.getElementById( 'uplink-mbe-upload-media' ),
		newCollection: document.getElementById( 'uplink-mbe-new-collection' ),
		manageCollections: document.getElementById( 'uplink-mbe-manage-collections' ),
		appearanceToggle: document.getElementById( 'uplink-mbe-appearance-toggle' ),
		appearancePanel: document.getElementById( 'uplink-mbe-appearance-panel' ),
		appearanceForm: document.getElementById( 'uplink-mbe-appearance-form' ),
		appearanceSelect: document.getElementById( 'uplink-mbe-appearance' ),
		paletteTabs: Array.from( document.querySelectorAll( '[data-palette-tab]' ) ),
		palettePanels: Array.from( document.querySelectorAll( '[data-palette-panel]' ) ),
		colorInputs: Array.from( document.querySelectorAll( '[data-palette-color]' ) ),
		resetPalette: document.getElementById( 'uplink-mbe-reset-palette' ),
		exportAppearance: document.getElementById( 'uplink-mbe-export-appearance' ),
		importAppearance: document.getElementById( 'uplink-mbe-import-appearance' ),
		importAppearanceFile: document.getElementById( 'uplink-mbe-import-appearance-file' ),
		appearanceSpinner: document.getElementById( 'uplink-mbe-appearance-spinner' ),
		appearanceStatus: document.getElementById( 'uplink-mbe-appearance-status' ),
		createGallery: document.getElementById( 'uplink-mbe-create-gallery' ),
		galleryModal: document.getElementById( 'uplink-mbe-gallery-generator-modal' ),
		galleryCollection: document.getElementById( 'uplink-mbe-gallery-collection' ),
		galleryPreview: document.getElementById( 'uplink-mbe-gallery-generator-preview' ),
		galleryShortcode: document.getElementById( 'uplink-mbe-gallery-shortcode' ),
		galleryCopy: document.getElementById( 'uplink-mbe-gallery-copy' ),
		galleryCopyStatus: document.getElementById( 'uplink-mbe-gallery-copy-status' ),
		modal: document.getElementById( 'uplink-mbe-collection-modal' ),
		modalTitle: document.getElementById( 'uplink-mbe-modal-title' ),
		collectionForm: document.getElementById( 'uplink-mbe-collection-form' ),
		collectionId: document.getElementById( 'uplink-mbe-collection-id' ),
		collectionName: document.getElementById( 'uplink-mbe-collection-name' ),
		collectionParent: document.getElementById( 'uplink-mbe-collection-parent' ),
		attachmentModal: document.getElementById( 'uplink-mbe-attachment-modal' ),
		attachmentPreview: document.getElementById( 'uplink-mbe-attachment-preview' ),
		attachmentPrevious: document.getElementById( 'uplink-mbe-attachment-previous' ),
		attachmentNext: document.getElementById( 'uplink-mbe-attachment-next' ),
		attachmentTitle: document.getElementById( 'uplink-mbe-attachment-title' ),
		attachmentFilename: document.getElementById( 'uplink-mbe-attachment-filename' ),
		attachmentTitleInput: document.getElementById( 'uplink-mbe-attachment-title-input' ),
		attachmentPosition: document.getElementById( 'uplink-mbe-attachment-position' ),
		attachmentNotice: document.getElementById( 'uplink-mbe-attachment-notice' ),
		attachmentFullPath: document.getElementById( 'uplink-mbe-attachment-full-path' ),
		attachmentCopyFull: document.getElementById( 'uplink-mbe-attachment-copy-full' ),
		attachmentReplace: document.getElementById( 'uplink-mbe-attachment-replace' ),
		attachmentPath: document.getElementById( 'uplink-mbe-attachment-path' ),
		attachmentCopy: document.getElementById( 'uplink-mbe-attachment-copy' ),
		attachmentType: document.getElementById( 'uplink-mbe-attachment-type' ),
		attachmentSize: document.getElementById( 'uplink-mbe-attachment-size' ),
		attachmentAuthor: document.getElementById( 'uplink-mbe-attachment-author' ),
		attachmentId: document.getElementById( 'uplink-mbe-attachment-id' ),
		attachmentAlt: document.getElementById( 'uplink-mbe-attachment-alt' ),
		attachmentDecorative: document.getElementById( 'uplink-mbe-attachment-decorative' ),
		attachmentCaption: document.getElementById( 'uplink-mbe-attachment-caption' ),
		attachmentDescription: document.getElementById( 'uplink-mbe-attachment-description' ),
		attachmentDimensions: document.getElementById( 'uplink-mbe-attachment-dimensions' ),
		attachmentDate: document.getElementById( 'uplink-mbe-attachment-date' ),
		attachmentCollectionOptions: document.getElementById( 'uplink-mbe-attachment-collection-options' ),
		attachmentCompatibility: document.getElementById( 'uplink-mbe-attachment-compatibility' ),
		attachmentCompatibilityForm: document.getElementById( 'uplink-mbe-attachment-compatibility-form' ),
		attachmentMainTab: document.getElementById( 'uplink-mbe-attachment-main-tab' ),
		attachmentFileTab: document.getElementById( 'uplink-mbe-attachment-file-tab' ),
		attachmentExifTab: document.getElementById( 'uplink-mbe-attachment-exif-tab' ),
		attachmentOptimizationTab: document.getElementById( 'uplink-mbe-attachment-optimization-tab' ),
		attachmentMainPanel: document.getElementById( 'uplink-mbe-attachment-main-panel' ),
		attachmentFilePanel: document.getElementById( 'uplink-mbe-attachment-file-panel' ),
		attachmentExif: document.getElementById( 'uplink-mbe-attachment-exif' ),
		attachmentOptimization: document.getElementById( 'uplink-mbe-attachment-optimization' ),
		attachmentOptimizationList: document.getElementById( 'uplink-mbe-attachment-optimization-list' ),
		attachmentEdit: document.getElementById( 'uplink-mbe-attachment-edit' ),
		attachmentSaveAlt: document.getElementById( 'uplink-mbe-attachment-save-alt' ),
		attachmentDelete: document.getElementById( 'uplink-mbe-attachment-delete' ),
		collectionManagerModal: document.getElementById( 'uplink-mbe-collection-manager-modal' ),
		collectionManagerNotice: document.getElementById( 'uplink-mbe-manager-notice' ),
		bulkCollectionParent: document.getElementById( 'uplink-mbe-bulk-collection-parent' ),
		bulkCollectionNames: document.getElementById( 'uplink-mbe-bulk-collection-names' ),
		bulkCreateCollections: document.getElementById( 'uplink-mbe-bulk-create-collections' ),
		collectionManagerList: document.getElementById( 'uplink-mbe-collection-manager-list' ),
		selectAllCollections: document.getElementById( 'uplink-mbe-select-all-collections' ),
		bulkDeleteCollections: document.getElementById( 'uplink-mbe-bulk-delete-collections' ),
		childDeleteModal: document.getElementById( 'uplink-mbe-child-delete-modal' ),
		childDeleteDescription: document.getElementById( 'uplink-mbe-child-delete-description' ),
		promoteChildren: document.getElementById( 'uplink-mbe-promote-children' ),
	};
	let attachmentTrigger = null;
	let currentAttachment = null;
	let currentCompatSnapshot = '';
	let attachmentSaving = false;
	let attachmentCompatGeneration = 0;
	let attachmentNavigationBusy = false;
	let infiniteLoading = false;
	let infiniteObserver = null;
	let loadGeneration = 0;
	let selectionAnchor = -1;
	let childDeleteResolve = null;
	let previousSelectionCount = 0;
	let childDeleteTrigger = null;
	let galleryTrigger = null;
	let galleryAutoOpened = false;
	let galleryMedia = [];
	let galleryCollectionLoaded = 0;
	let galleryLoadGeneration = 0;
	let galleryLoading = false;
	let draggedMedia = null;
	let mediaDropMarker = null;
	let mediaDragGhost = null;
	let mediaDragFrame = 0;
	let mediaDragPoint = null;
	let mediaOrderSaving = false;
	let mediaOrderRequest = null;
	let draggedCollection = 0;
	let collectionOrderSaving = false;
	let collectionPointerDrag = null;
	let suppressCollectionClick = false;
	let collectionFocusRestoreId = 0;
	let searchTimer = 0;
	let searchComposing = false;
	let savedAppearance = config.appearance || 'auto';
	let savedPalette = JSON.parse( JSON.stringify( config.palette || {} ) );
	let activePaletteMode = 'light';
	const colorSchemeQuery = window.matchMedia?.( '(prefers-color-scheme: dark)' );

	const state = {
		filter: Number( config.initialCollectionId ) ? 'collection' : 'all',
		collection: Number( config.initialCollectionId ) || 0,
		page: 1,
		search: '',
		type: '',
		mimeSubtype: '',
		uploadedBy: '',
		attachmentStatus: '',
		optimizationStatus: '',
		uploadedFrom: '',
		uploadedTo: '',
		widthMin: '',
		widthMax: '',
		heightMin: '',
		heightMax: '',
		fileSizeMin: '',
		fileSizeMax: '',
		missingAlt: false,
		reorderMode: false,
		view: 'grid',
		mode: 'library',
		healthFilter: '',
		health: null,
		collections: [],
		collapsedCollections: new Set(),
		mimeTypes: [],
		uploaders: [],
		media: [],
		counts: { all: 0, uncategorized: 0 },
		pagination: { page: 1, pages: 1, total: 0 },
		selected: new Set(),
		display: {
			filename: false,
			author: true,
			date: true,
			mime: true,
			fileSize: true,
			dimensions: false,
			exif: false,
		},
	};

	function createElement( tag, className, text ) {
		const element = document.createElement( tag );
		if ( className ) {
			element.className = className;
		}
		if ( undefined !== text ) {
			element.textContent = text;
		}
		return element;
	}

	const paletteVariableNames = {
		background: 'background',
		surface: 'surface',
		card: 'card',
		surface_muted: 'surface-muted',
		text: 'text',
		muted: 'muted',
		border: 'border',
		soft_border: 'soft-border',
		control: 'control',
		control_border: 'control-border',
		preview: 'preview',
		preview_text: 'preview-text',
		badge: 'badge',
		badge_text: 'badge-text',
		accent: 'accent',
		accent_text: 'accent-text',
		accent_icon: 'accent-icon',
		accent_soft: 'accent-soft',
		accent_hover: 'accent-hover',
		alt: 'alt',
		alt_text: 'alt-text',
		guide: 'guide',
		guide_border: 'guide-border',
		guide_text: 'guide-text',
		drop_bg: 'drop-bg',
		drop_border: 'drop-border',
		drop_text: 'drop-text',
		danger: 'danger',
		danger_solid: 'danger-solid',
		danger_icon: 'danger-icon',
		scrollbar_track: 'scrollbar-track',
		scrollbar_thumb: 'scrollbar-thumb',
		scrollbar_hover: 'scrollbar-hover',
	};

	function paletteFromInputs( mode ) {
		const palette = {};
		elements.colorInputs.forEach( ( input ) => {
			if ( input.dataset.paletteMode === mode ) {
				palette[ input.dataset.paletteColor ] = input.value;
			}
		} );
		return palette;
	}

	const paletteContrastPairs = {
		text: [ 'background', 'surface', 'card', 'surface_muted', 'control' ],
		muted: [ 'background', 'surface', 'card', 'surface_muted', 'control' ],
		preview_text: [ 'preview', 'surface_muted' ],
		badge_text: [ 'badge' ],
		accent: [ 'background', 'surface', 'card', 'control' ],
		accent_text: [ 'background', 'surface', 'card', 'control', 'accent_soft' ],
		accent_icon: [ 'accent', 'accent_hover' ],
		alt_text: [ 'alt' ],
		guide_text: [ 'guide' ],
		drop_text: [ 'drop_bg' ],
		danger: [ 'background', 'surface', 'card', 'control' ],
		danger_icon: [ 'danger_solid' ],
	};

	function colorLuminance( color ) {
		const channels = color.slice( 1 ).match( /.{2}/g ).map( ( value ) => parseInt( value, 16 ) / 255 );
		const linear = channels.map( ( value ) => value <= 0.04045 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 ) );
		return ( 0.2126 * linear[ 0 ] ) + ( 0.7152 * linear[ 1 ] ) + ( 0.0722 * linear[ 2 ] );
	}

	function colorContrastRatio( foreground, background ) {
		const values = [ colorLuminance( foreground ), colorLuminance( background ) ];
		return ( Math.max( ...values ) + 0.05 ) / ( Math.min( ...values ) + 0.05 );
	}

	function updatePaletteContrast( mode ) {
		const palette = paletteFromInputs( mode );
		const panel = document.querySelector( `[data-palette-panel="${ mode }"]` );
		panel?.querySelectorAll( '[data-contrast-for]' ).forEach( ( indicator ) => {
			const foreground = indicator.dataset.contrastFor;
			const ratios = ( paletteContrastPairs[ foreground ] || [] )
				.map( ( background ) => palette[ background ] ? colorContrastRatio( palette[ foreground ], palette[ background ] ) : null )
				.filter( ( ratio ) => null !== ratio );
			if ( ! ratios.length ) {
				return;
			}
			const ratio = Math.min( ...ratios );
			const passes = ratio >= 4.5;
			const ratioLabel = `${ ratio.toFixed( 2 ) }:1`;
			const status = ( passes ? config.strings.contrastPasses : config.strings.contrastFails ).replace( '%s', ratioLabel );
			indicator.classList.toggle( 'is-pass', passes );
			indicator.classList.toggle( 'is-fail', ! passes );
			indicator.querySelector( '.uplink-mbe-contrast-ratio' ).textContent = ratioLabel;
			indicator.setAttribute( 'aria-label', status );
			indicator.title = status;
		} );
	}

	function setPaletteInputs( palette ) {
		elements.colorInputs.forEach( ( input ) => {
			const value = palette?.[ input.dataset.paletteMode ]?.[ input.dataset.paletteColor ];
			if ( value ) {
				input.value = value;
				const output = input.parentElement?.querySelector( 'output' );
				if ( output ) {
					output.value = value.toUpperCase();
					output.textContent = value.toUpperCase();
				}
			}
		} );
		updatePaletteContrast( 'light' );
		updatePaletteContrast( 'dark' );
	}

	function applyPalette( palette ) {
		Object.entries( palette || {} ).forEach( ( [ name, value ] ) => {
			if ( paletteVariableNames[ name ] ) {
				elements.wrap.style.setProperty( `--mbe-${ paletteVariableNames[ name ] }`, value );
			}
		} );
	}

	function currentAppearanceExport() {
		return {
			schema: 'uplink-media-bridge/appearance',
			version: 1,
			exportedAt: new Date().toISOString(),
			appearance: elements.appearanceSelect.value,
			palette: {
				light: paletteFromInputs( 'light' ),
				dark: paletteFromInputs( 'dark' ),
			},
		};
	}

	function exportAppearance() {
		const blob = new Blob( [ `${ JSON.stringify( currentAppearanceExport(), null, 2 ) }\n` ], { type: 'application/json' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = `uplink-media-bridge-appearance-${ new Date().toISOString().slice( 0, 10 ) }.json`;
		elements.appearancePanel.append( link );
		link.click();
		link.remove();
		URL.revokeObjectURL( url );
		elements.appearanceStatus.textContent = config.strings.appearanceExported;
		showToast( config.strings.appearanceExported );
	}

	function parseAppearanceImport( source ) {
		let data;
		try {
			data = JSON.parse( source );
		} catch {
			throw new Error( config.strings.appearanceImportError );
		}
		if ( 'uplink-media-bridge/appearance' !== data?.schema || 1 !== data?.version || ! [ 'auto', 'light', 'dark' ].includes( data?.appearance ) ) {
			throw new Error( config.strings.appearanceImportError );
		}

		const expectedColors = Object.keys( config.paletteDefaults?.light || {} );
		const palette = { light: {}, dark: {} };
		[ 'light', 'dark' ].forEach( ( mode ) => {
			if ( ! data.palette?.[ mode ] || 'object' !== typeof data.palette[ mode ] ) {
				throw new Error( config.strings.appearanceImportError );
			}
			expectedColors.forEach( ( name ) => {
				const value = data.palette[ mode ][ name ] ?? ( 'card' === name ? data.palette[ mode ].surface : config.paletteDefaults?.[ mode ]?.[ name ] );
				if ( 'string' !== typeof value || ! /^#[0-9a-f]{6}$/i.test( value ) ) {
					throw new Error( config.strings.appearanceImportError );
				}
				palette[ mode ][ name ] = value.toLowerCase();
			} );
		} );

		return { appearance: data.appearance, palette };
	}

	async function importAppearance( file ) {
		try {
			if ( ! file || file.size > 131072 ) {
				throw new Error( config.strings.appearanceImportError );
			}
			const imported = parseAppearanceImport( await file.text() );
			elements.appearanceSelect.value = imported.appearance;
			setPaletteInputs( imported.palette );
			setPaletteMode( resolvedAppearanceMode( imported.appearance ) );
			elements.appearanceStatus.textContent = config.strings.appearanceImported;
			showToast( config.strings.appearanceImported );
		} catch {
			const message = config.strings.appearanceImportError;
			elements.appearanceStatus.textContent = message;
			showToast( message );
		} finally {
			elements.importAppearanceFile.value = '';
			elements.importAppearance.focus();
		}
	}

	function resolvedAppearanceMode( appearance = savedAppearance ) {
		if ( 'auto' === appearance ) {
			return colorSchemeQuery?.matches ? 'dark' : 'light';
		}
		return 'dark' === appearance ? 'dark' : 'light';
	}

	function applySavedAppearance() {
		elements.wrap.classList.remove( 'uplink-mbe-theme-auto', 'uplink-mbe-theme-light', 'uplink-mbe-theme-dark' );
		elements.wrap.classList.add( `uplink-mbe-theme-${ savedAppearance }` );
		applyPalette( savedPalette[ resolvedAppearanceMode() ] );
	}

	function setPaletteMode( mode, moveFocus = false ) {
		activePaletteMode = 'dark' === mode ? 'dark' : 'light';
		elements.paletteTabs.forEach( ( tab ) => {
			const selected = tab.dataset.paletteTab === activePaletteMode;
			tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			tab.tabIndex = selected ? 0 : -1;
			if ( selected && moveFocus ) {
				tab.focus();
			}
		} );
		elements.palettePanels.forEach( ( panel ) => {
			panel.hidden = panel.dataset.palettePanel !== activePaletteMode;
		} );
		elements.wrap.classList.remove( 'uplink-mbe-theme-auto', 'uplink-mbe-theme-light', 'uplink-mbe-theme-dark' );
		elements.wrap.classList.add( `uplink-mbe-theme-${ activePaletteMode }` );
		applyPalette( paletteFromInputs( activePaletteMode ) );
	}

	function closeAppearancePanel( restore = true ) {
		if ( ! elements.appearancePanel || elements.appearancePanel.hidden ) {
			return;
		}
		setPaletteInputs( savedPalette );
		elements.appearanceSelect.value = savedAppearance;
		elements.appearancePanel.hidden = true;
		elements.appearanceToggle.setAttribute( 'aria-expanded', 'false' );
		applySavedAppearance();
		if ( restore ) {
			elements.appearanceToggle.focus();
		}
	}

	function openAppearancePanel() {
		if ( ! elements.appearancePanel ) {
			return;
		}
		setPaletteInputs( savedPalette );
		elements.appearanceSelect.value = savedAppearance;
		elements.appearancePanel.hidden = false;
		elements.appearanceToggle.setAttribute( 'aria-expanded', 'true' );
		setPaletteMode( resolvedAppearanceMode() );
		window.setTimeout( () => elements.appearanceSelect.focus(), 0 );
	}

	async function saveAppearance( event ) {
		event.preventDefault();
		const submitted = { appearance: elements.appearanceSelect.value };
		elements.colorInputs.forEach( ( input ) => {
			submitted[ `${ input.dataset.paletteMode }_${ input.dataset.paletteColor }` ] = input.value;
		} );
		elements.appearanceSpinner.classList.add( 'is-active' );
		try {
			const result = await request( 'uplink_mbe_save_appearance', { settings: JSON.stringify( submitted ) } );
			savedAppearance = result.appearance;
			savedPalette = result.palette;
			config.appearance = savedAppearance;
			config.palette = savedPalette;
			setPaletteInputs( savedPalette );
			elements.appearanceStatus.textContent = result.adjusted ? config.strings.appearanceAdjusted : config.strings.appearanceSaved;
			elements.appearancePanel.hidden = true;
			elements.appearanceToggle.setAttribute( 'aria-expanded', 'false' );
			applySavedAppearance();
			showToast( result.adjusted ? config.strings.appearanceAdjusted : config.strings.appearanceSaved );
			elements.appearanceToggle.focus();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		} finally {
			elements.appearanceSpinner.classList.remove( 'is-active' );
		}
	}

	function createFilePlaceholder( media ) {
		const group = ( media.mime || '' ).split( '/' )[ 0 ];
		const subtype = ( media.mime || '' ).split( '/' )[ 1 ] || '';
		const extension = ( media.filename || '' ).split( '.' ).pop() || media.fileType || subtype || 'file';
		const placeholder = createElement( 'span', `uplink-mbe-file-placeholder is-${ group || 'file' }` );
		const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		svg.setAttribute( 'viewBox', '0 0 64 64' );
		svg.setAttribute( 'aria-hidden', 'true' );
		const artwork = {
			video: '<rect x="9" y="14" width="35" height="36" rx="4"/><path d="m44 27 11-7v24l-11-7z"/><path d="m25 24 12 8-12 8z"/>',
			audio: '<path d="M24 45V17l27-5v28"/><circle cx="17" cy="46" r="7"/><circle cx="44" cy="41" r="7"/><path d="M24 24l27-5"/>',
			application: '<path d="M17 7h20l12 12v38H17z"/><path d="M37 7v12h12M24 31h18M24 39h18M24 47h12"/>',
			text: '<path d="M17 7h20l12 12v38H17z"/><path d="M37 7v12h12M24 29h18M24 37h18M24 45h14"/>',
		};
		svg.innerHTML = artwork[ group ] || artwork.application;
		placeholder.append( svg, createElement( 'strong', '', extension ) );
		return placeholder;
	}

	function applyGridSize( value, remember = true ) {
		const size = Math.max( 180, Math.min( 400, Math.round( Number( value ) / 10 ) * 10 ) );
		elements.wrap.style.setProperty( '--mbe-card-min', `${ size }px` );
		elements.gridSize.value = String( size );
		elements.gridSizeValue.value = `${ size }px`;
		elements.gridSizeValue.textContent = `${ size }px`;
		if ( remember ) {
			try {
				window.localStorage.setItem( 'uplinkMbeGridSize', String( size ) );
			} catch ( error ) {
				// Storage can be unavailable in privacy-restricted browser sessions.
			}
		}
	}

	function applyGridRatio( value, remember = true ) {
		const ratio = [ '16/9', '4/3', '1/1' ].includes( value ) ? value : '4/3';
		elements.wrap.style.setProperty( '--mbe-card-ratio', ratio.replace( '/', ' / ' ) );
		elements.gridRatios.forEach( ( button ) => {
			button.setAttribute( 'aria-pressed', button.dataset.gridRatio === ratio ? 'true' : 'false' );
		} );
		if ( remember ) {
			try {
				window.localStorage.setItem( 'uplinkMbeGridRatio', ratio );
			} catch ( error ) {
				// Storage can be unavailable in privacy-restricted browser sessions.
			}
		}
	}

	function closeInfoPopup( restoreFocus = false ) {
		if ( elements.infoPopup.hidden ) {
			return;
		}
		elements.infoPopup.hidden = true;
		elements.infoToggle.setAttribute( 'aria-expanded', 'false' );
		if ( restoreFocus ) {
			elements.infoToggle.focus();
		}
	}

	function toggleInfoPopup() {
		const opening = elements.infoPopup.hidden;
		elements.infoPopup.hidden = ! opening;
		elements.infoToggle.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
	}

	async function request( action, data = {} ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.entries( data ).forEach( ( [ key, value ] ) => body.append( key, value ) );

		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} );
		const payload = await response.json();
		if ( ! response.ok || ! payload.success ) {
			const error = new Error( payload.data?.message || config.strings.error );
			error.data = payload.data || {};
			throw error;
		}
		return payload.data || {};
	}

	function showNotice( message, type = 'success' ) {
		window.uplinkMbeToast( message, type );
	}

	function showToast( message, type = 'success' ) {
		window.uplinkMbeToast( message, type );
	}

	function showAttachmentNotice( message, type = 'success' ) {
		window.uplinkMbeToast( message, type );
	}

	function clearAttachmentNotice() {
		window.clearTimeout( showAttachmentNotice.timeout );
		elements.attachmentNotice.hidden = true;
		elements.attachmentNotice.querySelector( 'p' ).textContent = '';
	}

	function currentCollectionName() {
		const collection = state.collections.find( ( item ) => item.id === state.collection );
		return collection ? collection.name : '';
	}

	function libraryRequestData( page = state.page ) {
		return {
			filter: state.filter,
			collection: state.collection,
			page,
			search: state.search,
			type: state.type,
			mime_subtype: state.mimeSubtype,
			uploaded_by: state.uploadedBy,
			attachment_status: state.attachmentStatus,
			optimization_status: state.optimizationStatus,
			uploaded_from: state.uploadedFrom,
			uploaded_to: state.uploadedTo,
			width_min: state.widthMin,
			width_max: state.widthMax,
			height_min: state.heightMin,
			height_max: state.heightMax,
			file_size_min: state.fileSizeMin,
			file_size_max: state.fileSizeMax,
			missing_alt: state.missingAlt ? '1' : '',
			health_filter: 'health' === state.mode ? state.healthFilter : '',
		};
	}

	function announceSearchResults() {
		const total = Number( state.pagination.total ) || 0;
		let message;
		if ( state.search ) {
			message = 1 === total ? config.strings.searchResult : config.strings.searchResults;
			message = message.replace( '%1$d', String( total ) ).replace( '%2$s', state.search );
		} else {
			message = 1 === total ? config.strings.resultCount : config.strings.resultsCount;
			message = message.replace( '%d', String( total ) );
		}
		elements.searchStatus.textContent = message;
	}

	async function loadState( options = {} ) {
		if ( ! config.pagination ) {
			state.page = 1;
		}
		const generation = ++loadGeneration;
		elements.grid.setAttribute( 'aria-busy', 'true' );
		elements.grid.replaceChildren( createElement( 'p', 'uplink-mbe-loading', config.strings.loading ) );
		try {
			if ( mediaOrderRequest ) {
				await mediaOrderRequest.catch( () => {} );
			}
			const data = await request( 'uplink_mbe_native_state', libraryRequestData() );
			if ( generation !== loadGeneration ) {
				return;
			}
			applyStateData( data );
			state.selected.clear();
			selectionAnchor = -1;
			render();
			if ( options.announceResults ) {
				announceSearchResults();
			}
		} catch ( error ) {
			if ( generation !== loadGeneration ) {
				return;
			}
			elements.grid.replaceChildren( createElement( 'p', 'uplink-mbe-empty', error.message ) );
			showNotice( error.message, 'error' );
		} finally {
			if ( generation === loadGeneration ) {
				elements.grid.setAttribute( 'aria-busy', 'false' );
			}
		}
	}

	function applyStateData( data, append = false ) {
		state.collections = data.collections || [];
		if ( window.uplinkMbeMediaModal ) {
			window.uplinkMbeMediaModal.uploadDestinations = state.collections.map( ( collection ) => ( {
				id: Number( collection.id ),
				name: collection.name,
				depth: Number( collection.depth ) || 0,
			} ) );
		}
		if ( append ) {
			const existing = new Set( state.media.map( ( media ) => media.id ) );
			state.media = state.media.concat( ( data.media || [] ).filter( ( media ) => ! existing.has( media.id ) ) );
		} else {
			state.media = data.media || [];
		}
		state.counts = data.counts || state.counts;
		state.mimeTypes = data.mimeTypes || state.mimeTypes;
		state.uploaders = data.uploaders || state.uploaders;
		state.pagination = data.pagination || state.pagination;
		state.page = state.pagination.page || state.page;
	}

	function render() {
		renderTree();
		renderHealthNavigation();
		renderLibraryControls();
		renderBulkCollections();
		renderGrid();
		renderPagination();
		updateSelectionTools();
		if ( collectionFocusRestoreId && ( document.body === document.activeElement || ! document.activeElement?.isConnected ) ) {
			elements.tree.querySelector( `[data-reorder-id="${ collectionFocusRestoreId }"]` )?.focus();
		}
		collectionFocusRestoreId = 0;
		if ( elements.createGallery ) {
			elements.createGallery.disabled = ! ( 'collection' === state.filter && state.collection );
		}
		if ( elements.galleryModal && ! elements.galleryModal.hidden ) {
			renderGalleryGenerator();
		}
		if ( config.openGalleryGenerator && ! galleryAutoOpened ) {
			galleryAutoOpened = true;
			openGalleryGenerator( elements.createGallery );
		}
	}

	const galleryControlIds = [
		'layout', 'columns', 'gap', 'limit', 'ratio', 'size', 'crop', 'children', 'random',
		'title', 'caption', 'text-position', 'text-align', 'lightbox', 'strip', 'info',
		'lightbox-title', 'lightbox-caption', 'lightbox-thumbnails', 'fullscreen', 'zoom',
		'title-size', 'caption-size', 'background-opacity', 'text-background', 'title-color', 'caption-color',
		'lightbox-size', 'lightbox-font', 'lightbox-title-size', 'lightbox-caption-size',
		'lightbox-title-weight', 'lightbox-caption-weight', 'lightbox-background', 'lightbox-panel',
		'lightbox-title-color', 'lightbox-caption-color',
	];

	function galleryControl( name ) {
		return document.getElementById( `uplink-mbe-gallery-${ name }` );
	}

	function galleryValue( name ) {
		const control = galleryControl( name );
		return 'checkbox' === control?.type ? control.checked : control?.value;
	}

	function populateGalleryCollections() {
		const selected = Number( elements.galleryCollection.value ) || ( 'collection' === state.filter ? state.collection : 0 );
		elements.galleryCollection.replaceChildren();
		const placeholder = createElement( 'option', '', config.strings.chooseCollection );
		placeholder.value = '';
		elements.galleryCollection.append( placeholder );
		state.collections.forEach( ( collection ) => {
			const option = createElement( 'option', '', collectionOptionLabel( collection ) );
			option.value = String( collection.id );
			elements.galleryCollection.append( option );
		} );
		elements.galleryCollection.value = selected ? String( selected ) : '';
	}

	function galleryShortcode() {
		const collection = Number( elements.galleryCollection.value );
		if ( ! collection ) {
			return '';
		}
		const attributes = [ `collection="${ collection }"` ];
		const defaults = {
			layout: 'grid', columns: '3', gap: '8', limit: '24', ratio: '1/1', size: 'large',
			crop: true, children: false, random: false, title: false, caption: false,
			'text-position': 'bottom', 'text-align': 'center', lightbox: 'custom', strip: 'horizontal',
			info: 'bottom', 'lightbox-title': true, 'lightbox-caption': true,
			'lightbox-thumbnails': true, fullscreen: true, zoom: true,
			'title-size': '16', 'caption-size': '14', 'background-opacity': '72',
			'text-background': '#000000', 'title-color': '#ffffff', 'caption-color': '#ffffff',
			'lightbox-size': 'full', 'lightbox-font': 'inherit', 'lightbox-title-size': '20',
			'lightbox-caption-size': '15', 'lightbox-title-weight': '700', 'lightbox-caption-weight': '400',
			'lightbox-background': '#111315', 'lightbox-panel': '#1d2125',
			'lightbox-title-color': '#ffffff', 'lightbox-caption-color': '#d9dde1',
		};
		const names = {
			ratio: 'aspect_ratio', children: 'include_children', title: 'show_title', caption: 'captions',
			'text-position': 'text_position', 'text-align': 'text_align', strip: 'lightbox_thumbnail_position',
			info: 'lightbox_info_position', 'lightbox-title': 'lightbox_title',
			'lightbox-caption': 'lightbox_caption', 'lightbox-thumbnails': 'lightbox_thumbnails',
			fullscreen: 'lightbox_fullscreen', zoom: 'lightbox_zoom',
			'title-size': 'title_size', 'caption-size': 'caption_size', 'background-opacity': 'background_opacity',
			'text-background': 'text_background', 'title-color': 'title_color', 'caption-color': 'caption_color',
			'lightbox-size': 'lightbox_size', 'lightbox-font': 'lightbox_font',
			'lightbox-title-size': 'lightbox_title_size', 'lightbox-caption-size': 'lightbox_caption_size',
			'lightbox-title-weight': 'lightbox_title_weight', 'lightbox-caption-weight': 'lightbox_caption_weight',
			'lightbox-background': 'lightbox_background', 'lightbox-panel': 'lightbox_panel',
			'lightbox-title-color': 'lightbox_title_color', 'lightbox-caption-color': 'lightbox_caption_color',
		};
		Object.keys( defaults ).forEach( ( key ) => {
			const value = galleryValue( key );
			if ( value !== defaults[ key ] ) {
				attributes.push( `${ names[ key ] || key }="${ 'boolean' === typeof value ? String( value ) : value }"` );
			}
		} );
		return `[etch_collection_gallery ${ attributes.join( ' ' ) }]`;
	}

	function colorWithOpacity( hex, opacity ) {
		const value = String( hex ).replace( '#', '' );
		const number = Number.parseInt( value, 16 );
		return `rgba(${ ( number >> 16 ) & 255 }, ${ ( number >> 8 ) & 255 }, ${ number & 255 }, ${ opacity })`;
	}

	function renderGalleryGenerator() {
		populateGalleryCollections();
		const shortcode = galleryShortcode();
		elements.galleryShortcode.value = shortcode;
		elements.galleryCopy.disabled = ! shortcode;
		elements.galleryPreview.replaceChildren();
		const collection = Number( elements.galleryCollection.value );
		if ( ! collection ) {
			elements.galleryPreview.append( createElement( 'p', 'uplink-mbe-gallery-generator-empty', config.strings.galleryChoose ) );
			return;
		}
		if ( galleryLoading ) {
			elements.galleryPreview.append( createElement( 'p', 'uplink-mbe-gallery-generator-empty', config.strings.loading ) );
			return;
		}
		const images = galleryCollectionLoaded === collection ? galleryMedia.slice( 0, 8 ) : [];
		if ( ! images.length ) {
			elements.galleryPreview.append( createElement( 'p', 'uplink-mbe-gallery-generator-empty', config.strings.empty ) );
			return;
		}
		const originalRatioClass = 'auto' === galleryValue( 'ratio' ) ? ' has-original-aspect-ratio' : '';
		const previewGrid = createElement( 'div', `uplink-mbe-gallery-generator-grid is-${ galleryValue( 'layout' ) } columns-${ galleryValue( 'columns' ) }${ originalRatioClass }` );
		previewGrid.style.setProperty( '--generator-columns', String( galleryValue( 'columns' ) ) );
		previewGrid.style.setProperty( '--generator-gap', `${ galleryValue( 'gap' ) }px` );
		previewGrid.style.setProperty( '--generator-ratio', String( galleryValue( 'ratio' ) ).replace( '/', ' / ' ) );
		previewGrid.style.setProperty( '--generator-title-size', `${ galleryValue( 'title-size' ) }px` );
		previewGrid.style.setProperty( '--generator-caption-size', `${ galleryValue( 'caption-size' ) }px` );
		previewGrid.style.setProperty( '--generator-title-color', galleryValue( 'title-color' ) );
		previewGrid.style.setProperty( '--generator-caption-color', galleryValue( 'caption-color' ) );
		previewGrid.style.setProperty( '--generator-text-overlay', colorWithOpacity( galleryValue( 'text-background' ), Number( galleryValue( 'background-opacity' ) ) / 100 ) );
		images.forEach( ( media ) => {
			const figure = createElement( 'figure', '' );
			const image = document.createElement( 'img' );
			image.src = media.thumbnail;
			image.alt = media.alt || '';
			image.style.objectFit = 'auto' === galleryValue( 'ratio' ) ? 'contain' : ( galleryValue( 'crop' ) ? 'cover' : 'contain' );
			figure.append( image );
			if ( galleryValue( 'title' ) || galleryValue( 'caption' ) ) {
				const text = createElement( 'figcaption', `is-position-${ galleryValue( 'text-position' ) } is-align-${ galleryValue( 'text-align' ) }` );
				if ( galleryValue( 'title' ) ) text.append( createElement( 'strong', '', media.title ) );
				if ( galleryValue( 'caption' ) ) text.append( createElement( 'span', '', media.caption || media.filename ) );
				figure.append( text );
			}
			previewGrid.append( figure );
		} );
		elements.galleryPreview.append( previewGrid );
	}

	async function loadGalleryGeneratorCollection( collection ) {
		const generation = ++galleryLoadGeneration;
		galleryLoading = Boolean( collection );
		galleryCollectionLoaded = 0;
		galleryMedia = [];
		renderGalleryGenerator();
		if ( ! collection ) {
			galleryLoading = false;
			return;
		}

		try {
			const data = await request( 'uplink_mbe_native_state', {
				filter: 'collection',
				collection,
				page: 1,
				search: '',
				type: 'image',
				extension: '',
				date: '',
			} );
			if ( generation !== galleryLoadGeneration || collection !== Number( elements.galleryCollection.value ) ) {
				return;
			}
			galleryMedia = ( data.media || [] ).filter( ( item ) => item.isImage );
			galleryCollectionLoaded = collection;
		} catch ( error ) {
			if ( generation !== galleryLoadGeneration ) {
				return;
			}
			galleryCollectionLoaded = collection;
			galleryMedia = [];
			elements.galleryPreview.replaceChildren( createElement( 'p', 'uplink-mbe-gallery-generator-empty', error.message ) );
			return;
		} finally {
			if ( generation === galleryLoadGeneration ) {
				galleryLoading = false;
			}
		}
		renderGalleryGenerator();
	}

	function openGalleryGenerator( trigger ) {
		galleryTrigger = trigger || document.activeElement;
		populateGalleryCollections();
		// Opening the generator from the Media Manager should always start with
		// the collection currently selected in the manager, not the collection
		// retained from the previous generator session.
		if ( 'collection' === state.filter && state.collection ) {
			elements.galleryCollection.value = String( state.collection );
		}
		elements.galleryModal.hidden = false;
		const collection = Number( elements.galleryCollection.value );
		if ( collection && 'collection' === state.filter && state.collection === collection ) {
			galleryMedia = state.media.filter( ( item ) => item.isImage );
			galleryCollectionLoaded = collection;
			galleryLoading = false;
			renderGalleryGenerator();
		} else {
			loadGalleryGeneratorCollection( collection );
		}
		elements.galleryCollection.focus();
	}

	function closeGalleryGenerator() {
		elements.galleryModal.hidden = true;
		elements.galleryCopyStatus.textContent = '';
		galleryTrigger?.focus?.();
	}

	async function copyGalleryShortcode() {
		if ( ! elements.galleryShortcode.value ) return;
		try {
			await navigator.clipboard.writeText( elements.galleryShortcode.value );
		} catch ( error ) {
			elements.galleryShortcode.select();
			document.execCommand( 'copy' );
		}
		showToast( config.strings.shortcodeCopied );
	}

	function renderLibraryControls() {
		elements.grid.classList.toggle( 'is-list-view', 'list' === state.view );
		elements.grid.classList.toggle( 'is-masonry-view', 'masonry' === state.view );
		elements.listView.setAttribute( 'aria-pressed', 'list' === state.view ? 'true' : 'false' );
		elements.gridView.setAttribute( 'aria-pressed', 'grid' === state.view ? 'true' : 'false' );
		elements.masonryView.setAttribute( 'aria-pressed', 'masonry' === state.view ? 'true' : 'false' );
		elements.listView.classList.toggle( 'is-active', 'list' === state.view );
		elements.gridView.classList.toggle( 'is-active', 'grid' === state.view );
		elements.masonryView.classList.toggle( 'is-active', 'masonry' === state.view );
		elements.gridSize.disabled = 'list' === state.view;
		elements.gridSize.closest( '.uplink-mbe-grid-size-control' )?.classList.toggle( 'is-disabled', 'list' === state.view );
		elements.gridRatios.forEach( ( button ) => {
			button.disabled = 'grid' !== state.view;
		} );
		elements.gridRatios[ 0 ]?.closest( '.uplink-mbe-grid-ratio-control' )?.classList.toggle( 'is-disabled', 'grid' !== state.view );
		elements.displayToggles.forEach( ( toggle ) => {
			toggle.checked = Boolean( state.display[ toggle.dataset.displayField ] );
		} );
		elements.typeFilter.value = state.type;

		const selectedMime = state.mimeSubtype;
		elements.mimeFilter.replaceChildren();
		const allMimeTypes = createElement( 'option', '', elements.mimeFilter.dataset.allMimeTypes || 'All' );
		allMimeTypes.value = '';
		elements.mimeFilter.append( allMimeTypes );
		state.mimeTypes.filter( ( mime ) => ! state.type || mime.type === state.type ).forEach( ( mime ) => {
			const option = createElement( 'option', '', mime.label );
			option.value = mime.value;
			elements.mimeFilter.append( option );
		} );
		elements.mimeFilter.value = selectedMime;

		const selectedUploader = String( state.uploadedBy );
		elements.uploaderFilter.replaceChildren();
		const allUploaders = createElement( 'option', '', elements.uploaderFilter.dataset.allUploaders || 'All' );
		allUploaders.value = '';
		elements.uploaderFilter.append( allUploaders );
		state.uploaders.forEach( ( uploader ) => {
			const option = createElement( 'option', '', uploader.label );
			option.value = String( uploader.value );
			elements.uploaderFilter.append( option );
		} );
		elements.uploaderFilter.value = selectedUploader;
		elements.attachmentStatusFilter.value = state.attachmentStatus;
		elements.optimizationStatusFilter.value = state.optimizationStatus;
		elements.uploadedFromFilter.value = state.uploadedFrom;
		elements.uploadedToFilter.value = state.uploadedTo;
		elements.widthMinFilter.value = state.widthMin;
		elements.widthMaxFilter.value = state.widthMax;
		elements.heightMinFilter.value = state.heightMin;
		elements.heightMaxFilter.value = state.heightMax;
		elements.fileSizeMinFilter.value = state.fileSizeMin;
		elements.fileSizeMaxFilter.value = state.fileSizeMax;
		elements.missingAltFilter.checked = state.missingAlt;
		elements.searchInput.value = state.search;
		const hasActiveFilters = activeFilterCount() > 0;
		elements.clearFilters.disabled = ! hasActiveFilters;
		elements.clearFilters.classList.toggle( 'is-active', hasActiveFilters );
		updateDrawerSummary();
	}

	function setDrawerOpen( open ) {
		elements.drawer.classList.toggle( 'is-open', open );
		elements.drawerPanel.hidden = ! open;
		elements.drawerToggle.setAttribute( 'aria-expanded', String( open ) );
		if ( open ) {
			setDisplayOptionsOpen( false );
		}
	}

	function setDisplayOptionsOpen( open ) {
		elements.displayOptions.hidden = ! open;
		elements.displayOptionsToggle.setAttribute( 'aria-expanded', String( open ) );
		if ( open ) {
			setDrawerOpen( false );
		}
	}

	function updateDrawerSummary() {
		const filterCount = activeFilterCount();
		elements.drawerSummary.textContent = filterCount ? String( filterCount ) : '';
		elements.drawerSummary.hidden = ! filterCount;
		elements.drawerToggle.classList.toggle( 'is-active', Boolean( filterCount ) );
	}

	function activeFilterCount() {
		return [
			state.type,
			state.mimeSubtype,
			state.uploadedBy,
			state.attachmentStatus,
			state.optimizationStatus,
			state.uploadedFrom,
			state.uploadedTo,
			state.widthMin || state.widthMax,
			state.heightMin || state.heightMax,
			state.fileSizeMin || state.fileSizeMax,
			state.missingAlt,
		].filter( Boolean ).length;
	}

	function makeFilterButton( label, count, filter, collection = 0, icon = 'portfolio' ) {
		const button = createElement( 'button', 'uplink-mbe-collection-filter' );
		button.type = 'button';
		button.dataset.filter = filter;
		button.dataset.collection = String( collection );
		button.classList.toggle( 'is-active', state.filter === filter && state.collection === collection );
		if ( state.filter === filter && state.collection === collection ) {
			button.setAttribute( 'aria-current', 'page' );
		}

		const isActive = state.filter === filter && state.collection === collection;
		const isFolder = 'collection' === filter && null !== icon;
		const displayIcon = isFolder ? ( isActive ? 'open-folder' : 'category' ) : icon;
		button.classList.toggle( 'has-icon', Boolean( displayIcon ) );
		if ( displayIcon ) {
			const iconElement = createElement( 'span', `dashicons dashicons-${ displayIcon }` );
			iconElement.setAttribute( 'aria-hidden', 'true' );
			button.append( iconElement );
		}
		button.append( createElement( 'span', 'uplink-mbe-collection-name', label ), createElement( 'span', 'uplink-mbe-collection-count', String( count ) ) );
		button.addEventListener( 'click', ( event ) => {
			if ( suppressCollectionClick ) {
				event.preventDefault();
				return;
			}
			selectFilter( filter, collection );
		} );
		return button;
	}

	function addDropTarget( element, collection, mode = 'add' ) {
		element.addEventListener( 'dragover', ( event ) => {
			if ( draggedCollection || state.reorderMode || mediaOrderSaving ) {
				return;
			}
			event.preventDefault();
			if ( event.dataTransfer ) {
				event.dataTransfer.dropEffect = 'move';
			}
			element.classList.add( 'is-drop-target' );
		} );
		element.addEventListener( 'dragleave', () => element.classList.remove( 'is-drop-target' ) );
		element.addEventListener( 'drop', async ( event ) => {
			if ( draggedCollection || state.reorderMode || mediaOrderSaving ) {
				return;
			}
			event.preventDefault();
			element.classList.remove( 'is-drop-target' );
			const transferred = event.dataTransfer.getData( 'text/plain' ).split( ',' ).map( Number ).filter( Boolean );
			const ids = transferred.length ? transferred : Array.from( state.selected );
			if ( ids.length ) {
				await assignMedia( ids, collection, mode );
			}
		} );
	}

	function siblingCollections( parent ) {
		return state.collections.filter( ( collection ) => Number( collection.parent ) === Number( parent ) );
	}

	function collectionDepth( collection ) {
		return Math.max( 0, Number( collection?.depth ) || 0 );
	}

	function collectionOptionLabel( collection ) {
		return `${ '\u00a0\u00a0'.repeat( collectionDepth( collection ) ) }${ collection.name }`;
	}

	function collectionDescendantIds( collectionId ) {
		const descendants = new Set();
		const visit = ( parent ) => {
			siblingCollections( parent ).forEach( ( child ) => {
				const childId = Number( child.id );
				if ( descendants.has( childId ) ) {
					return;
				}
				descendants.add( childId );
				visit( childId );
			} );
		};
		visit( Number( collectionId ) );
		return descendants;
	}

	function collectionSubtreeHeight( collectionId, visited = new Set() ) {
		const normalizedId = Number( collectionId );
		if ( visited.has( normalizedId ) ) {
			return 1;
		}
		const nextVisited = new Set( visited );
		nextVisited.add( normalizedId );
		return 1 + siblingCollections( normalizedId ).reduce( ( height, child ) => Math.max( height, collectionSubtreeHeight( child.id, nextVisited ) ), 0 );
	}

	function eligibleCollectionParents( collection = null ) {
		const excluded = collection ? collectionDescendantIds( collection.id ) : new Set();
		const subtreeHeight = collection ? collectionSubtreeHeight( collection.id ) : 1;
		if ( collection ) {
			excluded.add( Number( collection.id ) );
		}
		return state.collections.filter( ( item ) => collectionDepth( item ) + 1 + subtreeHeight <= Number( config.maxCollectionDepth || 2 ) && ! excluded.has( Number( item.id ) ) );
	}

	function applyCollectionOrder( parent, orderedIds ) {
		const rank = new Map( orderedIds.map( ( id, index ) => [ Number( id ), index ] ) );
		const groups = new Map();
		state.collections.forEach( ( collection ) => {
			const collectionParent = Number( collection.parent );
			if ( ! groups.has( collectionParent ) ) {
				groups.set( collectionParent, [] );
			}
			groups.get( collectionParent ).push( collection );
		} );
		const target = groups.get( Number( parent ) ) || [];
		target.sort( ( first, second ) => ( rank.get( Number( first.id ) ) ?? Number.MAX_SAFE_INTEGER ) - ( rank.get( Number( second.id ) ) ?? Number.MAX_SAFE_INTEGER ) );

		const flattened = [];
		const seen = new Set();
		const append = ( collectionParent ) => {
			( groups.get( collectionParent ) || [] ).forEach( ( collection ) => {
				if ( seen.has( Number( collection.id ) ) ) {
					return;
				}
				seen.add( Number( collection.id ) );
				flattened.push( collection );
				append( Number( collection.id ) );
			} );
		};
		append( 0 );
		state.collections.forEach( ( collection ) => {
			if ( ! seen.has( Number( collection.id ) ) ) {
				flattened.push( collection );
			}
		} );
		state.collections = flattened;
	}

	function clearCollectionDropIndicators() {
		elements.tree.querySelectorAll( '.is-reorder-before, .is-reorder-after, .is-reordering' ).forEach( ( item ) => {
			item.classList.remove( 'is-reorder-before', 'is-reorder-after', 'is-reordering' );
		} );
	}

	async function saveCollectionOrder( parent, orderedIds, focusId ) {
		if ( collectionOrderSaving || state.reorderMode ) {
			return;
		}
		collectionOrderSaving = true;
		try {
			const data = await request( 'uplink_mbe_reorder_collections', {
				parent,
				ordered_ids: JSON.stringify( orderedIds ),
			} );
			applyCollectionOrder( parent, data.ordered_ids || orderedIds );
			renderTree();
			renderBulkCollections();
			showToast( config.strings.orderSaved );
			window.setTimeout( () => elements.tree.querySelector( `[data-reorder-id="${ Number( focusId ) }"]` )?.focus(), 0 );
		} catch ( error ) {
			showNotice( error.message, 'error' );
		} finally {
			collectionOrderSaving = false;
			clearCollectionDropIndicators();
		}
	}

	function moveCollectionByKeyboard( collection, direction ) {
		const siblings = siblingCollections( collection.parent );
		const ids = siblings.map( ( sibling ) => Number( sibling.id ) );
		const current = ids.indexOf( Number( collection.id ) );
		const target = current + direction;
		if ( current < 0 || target < 0 || target >= ids.length ) {
			return;
		}
		[ ids[ current ], ids[ target ] ] = [ ids[ target ], ids[ current ] ];
		saveCollectionOrder( collection.parent, ids, collection.id );
	}

	function enableCollectionPointerReorder( row, filter, collection ) {
		filter.addEventListener( 'pointerdown', ( event ) => {
			if ( 0 !== event.button || collectionOrderSaving || state.reorderMode ) {
				return;
			}
			collectionPointerDrag = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startY: event.clientY,
				source: collection,
				sourceRow: row,
				target: null,
				placeAfter: false,
				moved: false,
			};
			filter.setPointerCapture( event.pointerId );
		} );
		filter.addEventListener( 'pointermove', ( event ) => {
			const drag = collectionPointerDrag;
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}
			if ( ! drag.moved && Math.hypot( event.clientX - drag.startX, event.clientY - drag.startY ) < 6 ) {
				return;
			}
			drag.moved = true;
			draggedCollection = Number( collection.id );
			clearCollectionDropIndicators();
			drag.sourceRow.classList.add( 'is-reordering' );
			const targetRow = document.elementFromPoint( event.clientX, event.clientY )?.closest( '.uplink-mbe-collection-row' );
			if ( ! targetRow || Number( targetRow.dataset.parent ) !== Number( collection.parent ) || Number( targetRow.dataset.collectionId ) === Number( collection.id ) ) {
				drag.target = null;
				return;
			}
			drag.target = state.collections.find( ( item ) => Number( item.id ) === Number( targetRow.dataset.collectionId ) ) || null;
			drag.placeAfter = event.clientY > targetRow.getBoundingClientRect().top + targetRow.offsetHeight / 2;
			targetRow.classList.add( drag.placeAfter ? 'is-reorder-after' : 'is-reorder-before' );
			event.preventDefault();
		} );
		const finishPointerReorder = ( event ) => {
			const drag = collectionPointerDrag;
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}
			collectionPointerDrag = null;
			draggedCollection = 0;
			if ( filter.hasPointerCapture( event.pointerId ) ) {
				filter.releasePointerCapture( event.pointerId );
			}
			if ( drag.moved ) {
				suppressCollectionClick = true;
				window.setTimeout( () => {
					suppressCollectionClick = false;
				}, 0 );
			}
			if ( drag.moved && drag.target ) {
				const ids = siblingCollections( collection.parent ).map( ( sibling ) => Number( sibling.id ) ).filter( ( id ) => id !== Number( collection.id ) );
				let targetIndex = ids.indexOf( Number( drag.target.id ) );
				if ( drag.placeAfter ) {
					targetIndex += 1;
				}
				ids.splice( targetIndex, 0, Number( collection.id ) );
				saveCollectionOrder( collection.parent, ids, collection.id );
			} else {
				clearCollectionDropIndicators();
			}
		};
		filter.addEventListener( 'pointerup', finishPointerReorder );
		filter.addEventListener( 'pointercancel', ( event ) => {
			if ( ! collectionPointerDrag || collectionPointerDrag.pointerId !== event.pointerId ) {
				return;
			}
			collectionPointerDrag = null;
			draggedCollection = 0;
			if ( filter.hasPointerCapture( event.pointerId ) ) {
				filter.releasePointerCapture( event.pointerId );
			}
			clearCollectionDropIndicators();
		} );
	}

	function renderTree() {
		if ( 'collection' !== state.filter || 'library' !== state.mode ) state.reorderMode = false;
		if ( elements.newCollection ) elements.newCollection.disabled = state.reorderMode;
		if ( elements.manageCollections ) elements.manageCollections.disabled = state.reorderMode;
		elements.tree.replaceChildren();

		const special = createElement( 'div', 'uplink-mbe-special-collections' );
		special.append( makeFilterButton( config.strings.allMedia, state.counts.all, 'all', 0, 'format-gallery' ) );
		const uncategorized = makeFilterButton( config.strings.uncategorized, state.counts.uncategorized, 'uncategorized', 0, 'category' );
		addDropTarget( uncategorized, 0, 'clear' );
		special.append( uncategorized );
		elements.tree.append( special );

		const list = createElement( 'ul', 'uplink-mbe-collection-list' );
		const appendBranch = ( collection, parentList ) => {
			const collectionId = Number( collection.id );
			const children = siblingCollections( collectionId );
			const isCollapsed = state.collapsedCollections.has( collectionId );
			const item = createElement( 'li', 'uplink-mbe-collection-item' );
			item.classList.toggle( 'is-collapsed', isCollapsed );
			item.append( collectionRow( collection, children.length > 0 ) );
			if ( children.length ) {
				const childList = createElement( 'ul', 'uplink-mbe-subcollection-list' );
				childList.id = `uplink-mbe-subcollections-${ collectionId }`;
				childList.hidden = isCollapsed;
				children.forEach( ( child ) => appendBranch( child, childList ) );
				item.append( childList );
			}
			parentList.append( item );
		};
		state.collections.filter( ( item ) => 0 === Number( item.parent ) ).forEach( ( collection ) => appendBranch( collection, list ) );
		elements.tree.append( list );
	}

	function renderHealthNavigation() {
		if ( ! state.health ) {
			return;
		}
		const filters = [
			[ 'all_issues', config.strings.allIssues, config.strings.allIssuesDescription ],
			[ 'broken', config.strings.brokenFile, config.strings.brokenFileDescription ],
			[ 'missing_alt', config.strings.missingAlt, config.strings.missingAltDescription ],
			[ 'missing_sizes', config.strings.missingSizes, config.strings.missingSizesDescription ],
			[ 'oversized', config.strings.oversized, config.strings.oversizedDescription ],
			[ 'suspected_duplicates', config.strings.suspectedDuplicates, config.strings.suspectedDuplicatesDescription ],
			[ 'obsolete_format', config.strings.obsoleteFormat, config.strings.obsoleteFormatDescription ],
			[ 'decorative', config.strings.decorative, config.strings.decorativeDescription ],
			[ 'healthy', config.strings.healthy, config.strings.healthyDescription ],
		];
		elements.healthNavigation.replaceChildren();
		filters.forEach( ( [ key, label, description ] ) => {
			const button = createElement( 'button', 'uplink-mbe-health-filter' );
			button.type = 'button';
			const labelElement = createElement( 'span', 'uplink-mbe-health-label', label );
			button.classList.toggle( 'is-active', state.healthFilter === key );
			button.append( labelElement, createElement( 'span', 'uplink-mbe-health-count', String( state.health.counts?.[ key ] || 0 ) ) );
			button.addEventListener( 'click', () => {
				state.healthFilter = key;
				state.page = 1;
				renderHealthNavigation();
				loadState();
			} );
			elements.healthNavigation.append( button );
		} );
		const activeFilter = filters.find( ( [ key ] ) => key === state.healthFilter ) || filters[ 0 ];
		elements.healthDescription.textContent = activeFilter[ 2 ];
		elements.healthDescription.hidden = false;
		const issueCount = Number( state.health.counts?.all_issues || 0 );
		elements.healthTabCount.textContent = String( issueCount );
		const total = Number( state.health.total || 0 );
		const healthy = Number( state.health.counts?.healthy || 0 );
		elements.healthProgress.style.width = `${ total ? Math.round( ( healthy / total ) * 100 ) : 100 }%`;
		elements.healthStatus.textContent = `${ healthy } of ${ total } ${ config.strings.healthy } · ${ config.strings.scannedNow }`;
	}

	async function loadHealth( force = false ) {
		elements.healthRescan.disabled = true;
		try {
			state.health = await request( 'uplink_mbe_media_health', { force: force ? '1' : '0' } );
			renderHealthNavigation();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		} finally {
			elements.healthRescan.disabled = false;
		}
	}

	async function setWorkspaceMode( mode ) {
		const nextMode = 'health' === mode ? 'health' : 'library';
		const modeChanged = state.mode !== nextMode;
		state.mode = nextMode;
		const healthMode = 'health' === state.mode;
		state.filter = 'all';
		state.collection = 0;
		if ( modeChanged && state.search ) {
			state.search = '';
			elements.searchInput.value = '';
			elements.searchStatus.textContent = '';
		}
		elements.libraryTab.setAttribute( 'aria-selected', String( ! healthMode ) );
		elements.healthTab.setAttribute( 'aria-selected', String( healthMode ) );
		elements.libraryNavigation.hidden = healthMode;
		elements.healthNavigation.hidden = ! healthMode;
		elements.healthDescription.hidden = ! healthMode;
		elements.healthSummary.hidden = ! healthMode;
		state.healthFilter = healthMode ? ( state.healthFilter || 'all_issues' ) : '';
		state.page = 1;
		if ( healthMode ) {
			await loadHealth();
		}
		loadState();
	}

	function collectionRow( collection, hasChildren = false ) {
		const row = createElement( 'div', 'uplink-mbe-collection-row' );
		row.dataset.collectionId = String( collection.id );
		row.dataset.parent = String( collection.parent );
		row.classList.toggle( 'has-children', hasChildren );
		if ( config.canManage && ! state.reorderMode ) {
			row.classList.add( 'can-reorder' );
		}
		if ( hasChildren ) {
			const collectionId = Number( collection.id );
			const isCollapsed = state.collapsedCollections.has( collectionId );
			const action = isCollapsed ? config.strings.expandCollection : config.strings.collapseCollection;
			const toggle = createElement( 'button', 'uplink-mbe-collection-toggle' );
			toggle.type = 'button';
			toggle.title = action;
			toggle.setAttribute( 'aria-expanded', String( ! isCollapsed ) );
			toggle.setAttribute( 'aria-controls', `uplink-mbe-subcollections-${ collectionId }` );
			toggle.setAttribute( 'aria-label', `${ action }: ${ collection.name }` );
			const icon = createElement( 'span', 'dashicons dashicons-arrow-down-alt2' );
			icon.setAttribute( 'aria-hidden', 'true' );
			toggle.append( icon );
			toggle.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				if ( isCollapsed ) {
					state.collapsedCollections.delete( collectionId );
				} else {
					state.collapsedCollections.add( collectionId );
				}
				renderTree();
			} );
			row.append( toggle );
		}
		const filter = makeFilterButton(
			collection.name,
			collectionCount( collection, hasChildren ),
			'collection',
			collection.id,
			'category'
		);
		if ( config.canManage && ! state.reorderMode ) {
			filter.dataset.reorderId = String( collection.id );
			filter.setAttribute( 'aria-keyshortcuts', 'Alt+ArrowUp Alt+ArrowDown' );
			filter.setAttribute( 'aria-description', config.strings.reorderInstructions );
			filter.addEventListener( 'keydown', ( event ) => {
				if ( ! event.altKey || ( 'ArrowUp' !== event.key && 'ArrowDown' !== event.key ) ) {
					return;
				}
				event.preventDefault();
				moveCollectionByKeyboard( collection, 'ArrowUp' === event.key ? -1 : 1 );
			} );
			enableCollectionPointerReorder( row, filter, collection );
		}
		addDropTarget( filter, collection.id );
		row.append( filter );

		if ( config.canManage && ! state.reorderMode ) {
			const actions = createElement( 'div', 'uplink-mbe-collection-actions' );
			if ( collectionDepth( collection ) < Number( config.maxCollectionDepth || 2 ) - 1 ) {
				const add = createElement( 'button', 'button-link', '+' );
				add.type = 'button';
				add.title = config.strings.newSubcollection;
				add.setAttribute( 'aria-label', `${ config.strings.newSubcollection }: ${ collection.name }` );
				add.addEventListener( 'click', () => openCollectionModal( null, collection.id ) );
				actions.append( add );
			}

			const edit = createElement( 'button', 'button-link dashicons dashicons-edit' );
			edit.type = 'button';
			edit.title = config.strings.editCollection;
			edit.setAttribute( 'aria-label', `${ config.strings.editCollection }: ${ collection.name }` );
			edit.addEventListener( 'click', () => openCollectionModal( collection ) );

			const remove = createElement( 'button', 'button-link dashicons dashicons-trash' );
			remove.type = 'button';
			remove.title = config.strings.deleteCollection;
			remove.setAttribute( 'aria-label', `${ config.strings.deleteCollection }: ${ collection.name }` );
			remove.addEventListener( 'click', () => deleteCollection( collection, remove ) );
			actions.append( edit, remove );
			row.append( actions );
		}

		return row;
	}

	function collectionCount( collection, hasChildren = false ) {
		const direct = Number( collection.count ) || 0;
		const total = Number.isFinite( Number( collection.totalCount ) ) ? Number( collection.totalCount ) : direct;
		if ( ! hasChildren || 'direct' === config.parentCountDisplay ) {
			return direct;
		}
		if ( 'direct_total' === config.parentCountDisplay ) {
			return `${ direct }/${ total }`;
		}
		return total;
	}

	function selectFilter( filter, collection ) {
		state.reorderMode = false;
		state.filter = filter;
		state.collection = collection;
		state.page = 1;
		collectionFocusRestoreId = 'collection' === filter ? Number( collection ) : 0;
		loadState();
	}

	function renderBulkCollections() {
		elements.bulkCollection.replaceChildren();
		const placeholder = createElement( 'option', '', config.strings.chooseCollection );
		placeholder.value = '';
		elements.bulkCollection.append( placeholder );
		state.collections.forEach( ( collection ) => {
			const option = createElement( 'option', '', collectionOptionLabel( collection ) );
			option.value = String( collection.id );
			elements.bulkCollection.append( option );
		} );
		updateBulkCollectionActions();
	}

	function updateBulkCollectionActions() {
		const collectionChosen = Boolean( elements.bulkCollection.value );
		elements.bulkAdd.disabled = ! collectionChosen;
		elements.bulkRemove.disabled = ! collectionChosen;
	}

	function selectMedia( event, media, index ) {
		if ( event.shiftKey && selectionAnchor >= 0 ) {
			if ( ! event.ctrlKey && ! event.metaKey ) {
				state.selected.clear();
			}
			const start = Math.min( selectionAnchor, index );
			const end = Math.max( selectionAnchor, index );
			state.media.slice( start, end + 1 ).forEach( ( item ) => state.selected.add( item.id ) );
		} else if ( state.selected.has( media.id ) ) {
			state.selected.delete( media.id );
		} else {
			state.selected.add( media.id );
		}
		selectionAnchor = index;
		renderGrid();
		updateSelectionTools();
	}

	document.getElementById( 'uplink-mbe-open-loop-generator' )?.addEventListener( 'click', () => {
		document.dispatchEvent( new CustomEvent( 'uplink-mbe-open-loop', { detail: {
			collection: 'collection' === state.filter ? state.collection : 0,
			collections: state.collections,
		} } ) );
	} );

	function canReorderMedia() {
		return state.reorderMode && 'library' === state.mode && 'collection' === state.filter && state.collection && ! mediaOrderSaving && ! infiniteLoading && 'true' !== elements.grid.getAttribute( 'aria-busy' );
	}

	function clearMediaDropMarkers() {
		window.cancelAnimationFrame( mediaDragFrame );
		mediaDragFrame = 0;
		mediaDragPoint = null;
		mediaDropMarker?.remove();
		mediaDropMarker = null;
	}

	function finishMediaDrag() {
		draggedMedia = null;
		clearMediaDropMarkers();
		mediaDragGhost?.remove();
		mediaDragGhost = null;
		elements.grid.querySelectorAll( '.is-dragging' ).forEach( ( card ) => card.classList.remove( 'is-dragging' ) );
	}

	function mediaDropAt( x, y ) {
		if ( ! canReorderMedia() || ! draggedMedia || draggedMedia.collection !== state.collection ) return null;
		let nearest = null;
		let distance = Infinity;
		// Include the gaps between cards, so the insertion line remains a usable drop target.
		for ( const card of elements.grid.querySelectorAll( '.uplink-mbe-media-card' ) ) {
			if ( draggedMedia.ids.includes( Number( card.dataset.mediaId ) ) ) continue;
			const rect = card.getBoundingClientRect();
			const dx = Math.max( rect.left - x, 0, x - rect.right );
			const dy = Math.max( rect.top - y, 0, y - rect.bottom );
			const nextDistance = dx * dx + dy * dy;
			if ( nextDistance < distance ) {
				distance = nextDistance;
				nearest = { card, rect };
			}
		}
		if ( ! nearest ) return null;
		const vertical = 'grid' === state.view;
		const after = vertical ? x > nearest.rect.left + nearest.rect.width / 2 : y > nearest.rect.top + nearest.rect.height / 2;
		return { ...nearest, vertical, placement: after ? 'after' : 'before' };
	}

	function showMediaDropMarker( target ) {
		if ( ! target ) {
			clearMediaDropMarkers();
			return;
		}
		if ( ! mediaDropMarker ) {
			mediaDropMarker = createElement( 'div', 'uplink-mbe-media-drop-marker' );
			mediaDropMarker.setAttribute( 'aria-hidden', 'true' );
			const count = draggedMedia.ids.length;
			const label = ( 1 === count ? config.strings.moveItemHere : config.strings.moveItemsHere ).replace( '%d', String( count ) );
			mediaDropMarker.append( createElement( 'span', 'uplink-mbe-media-drop-label', label ) );
			library.append( mediaDropMarker );
		}
		const { rect, vertical, placement } = target;
		const gap = Number.parseFloat( window.getComputedStyle( elements.grid )[ vertical ? 'columnGap' : 'rowGap' ] ) || 16;
		const after = 'after' === placement;
		const x = vertical ? ( after ? rect.right + gap / 2 : rect.left - gap / 2 ) : rect.left;
		const y = vertical ? rect.top : ( after ? rect.bottom + gap / 2 : rect.top - gap / 2 );
		mediaDropMarker.classList.toggle( 'is-vertical', vertical );
		mediaDropMarker.classList.toggle( 'is-label-left', vertical && x + 180 > window.innerWidth );
		mediaDropMarker.style.transform = `translate3d(${ x }px, ${ y }px, 0)`;
		mediaDropMarker.style.width = `${ vertical ? 3 : rect.width }px`;
		mediaDropMarker.style.height = `${ vertical ? rect.height : 3 }px`;
	}

	elements.grid.addEventListener( 'dragover', ( event ) => {
		if ( ! canReorderMedia() || ! draggedMedia ) return;
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
		mediaDragPoint = { x: event.clientX, y: event.clientY };
		if ( mediaDragFrame ) return;
		mediaDragFrame = window.requestAnimationFrame( () => {
			mediaDragFrame = 0;
			if ( mediaDragPoint ) showMediaDropMarker( mediaDropAt( mediaDragPoint.x, mediaDragPoint.y ) );
		} );
	} );
	elements.grid.addEventListener( 'dragleave', ( event ) => {
		if ( ! elements.grid.contains( event.relatedTarget ) ) clearMediaDropMarkers();
	} );
	elements.grid.addEventListener( 'drop', ( event ) => {
		const target = mediaDropAt( event.clientX, event.clientY );
		if ( ! target ) return;
		event.preventDefault();
		event.stopPropagation();
		const ids = draggedMedia.ids;
		finishMediaDrag();
		reorderMedia( ids, Number( target.card.dataset.mediaId ), target.placement );
	} );
	window.addEventListener( 'scroll', clearMediaDropMarkers, true );
	window.addEventListener( 'resize', clearMediaDropMarkers );

	async function reorderMedia( ids, target, placement, focusId = 0 ) {
		if ( ! canReorderMedia() || ! ids.length || ids.includes( target ) ) {
			return;
		}
		const generation = loadGeneration;
		mediaOrderSaving = true;
		elements.reorder.disabled = true;
		elements.grid.setAttribute( 'aria-busy', 'true' );
		try {
			mediaOrderRequest = request( 'uplink_mbe_reorder_media', {
				collection: state.collection,
				media_ids: JSON.stringify( ids ),
				target,
				placement,
			} );
			const data = await mediaOrderRequest;
			if ( generation !== loadGeneration ) {
				return;
			}
			const previousRects = new Map( Array.from( elements.grid.querySelectorAll( '.uplink-mbe-media-card' ), ( card ) => [ card.dataset.mediaId, card.getBoundingClientRect() ] ) );
			const ranks = new Map( data.ordered_ids.map( ( id, index ) => [ Number( id ), index ] ) );
			state.media.sort( ( a, b ) => ranks.get( a.id ) - ranks.get( b.id ) );
			selectionAnchor = -1;
			renderGrid();
			if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				elements.grid.querySelectorAll( '.uplink-mbe-media-card' ).forEach( ( card ) => {
					const before = previousRects.get( card.dataset.mediaId );
					if ( ! before ) return;
					const after = card.getBoundingClientRect();
					card.animate( [ { transform: `translate(${ before.left - after.left }px, ${ before.top - after.top }px)` }, { transform: 'translate(0, 0)' } ], { duration: 180, easing: 'ease-out' } );
				} );
			}
			if ( focusId ) {
				elements.grid.querySelector( `[data-media-id="${ focusId }"] .uplink-mbe-media-preview` )?.focus();
			}
			showToast( config.strings.mediaOrderSaved );
		} catch ( error ) {
			showToast( error.message, 'error' );
		} finally {
			mediaOrderRequest = null;
			mediaOrderSaving = false;
			elements.reorder.disabled = false;
			if ( generation === loadGeneration ) {
				elements.grid.setAttribute( 'aria-busy', 'false' );
				renderPagination();
			}
		}
	}

	function bindMediaReordering( card, media ) {
		card.addEventListener( 'keydown', ( event ) => {
			if ( ! event.altKey || ! [ 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight' ].includes( event.key ) || ! canReorderMedia() || ! media.canEdit ) {
				return;
			}
			event.preventDefault();
			const before = [ 'ArrowUp', 'ArrowLeft' ].includes( event.key );
			const index = state.media.findIndex( ( item ) => item.id === media.id );
			const target = state.media[ index + ( before ? -1 : 1 ) ];
			if ( target ) {
				reorderMedia( [ media.id ], target.id, before ? 'before' : 'after', media.id );
			}
		} );
	}

	function renderGrid() {
		finishMediaDrag();
		const inCollection = 'collection' === state.filter && 'library' === state.mode;
		if ( ! inCollection ) state.reorderMode = false;
		elements.reorder.hidden = ! inCollection;
		elements.reorder.disabled = mediaOrderSaving;
		const reorderLabel = state.reorderMode ? config.strings.doneReordering : config.strings.reorderMedia;
		elements.reorder.setAttribute( 'aria-label', reorderLabel );
		elements.reorder.title = reorderLabel;
		elements.reorder.setAttribute( 'aria-pressed', String( state.reorderMode ) );
		elements.grid.classList.toggle( 'is-reorder-mode', state.reorderMode );
		elements.grid.replaceChildren();
		if ( ! state.media.length ) {
			elements.grid.append( createElement( 'p', 'uplink-mbe-empty', config.strings.empty ) );
			return;
		}

		if ( state.reorderMode ) {
			const help = createElement( 'p', 'uplink-mbe-media-order-help', config.strings.mediaOrderHelp );
			help.id = 'uplink-mbe-media-order-help';
			elements.grid.append( help );
		}

		state.media.forEach( ( media, mediaIndex ) => {
			const card = createElement( 'article', 'uplink-mbe-media-card' );
			card.draggable = true;
			card.dataset.mediaId = String( media.id );
			card.classList.toggle( 'is-selected', state.selected.has( media.id ) );

			const selectLabel = createElement( 'label', 'uplink-mbe-media-select' );
			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.value = String( media.id );
			checkbox.checked = state.selected.has( media.id );
			checkbox.setAttribute( 'aria-label', `Select ${ media.title }` );
			selectLabel.title = config.strings.selectHelp;
			checkbox.addEventListener( 'click', ( event ) => {
				if ( ! event.shiftKey ) {
					return;
				}
				event.preventDefault();
				selectMedia( event, media, mediaIndex );
			} );
			checkbox.addEventListener( 'change', () => {
				if ( checkbox.checked ) {
					state.selected.add( media.id );
				} else {
					state.selected.delete( media.id );
				}
				selectionAnchor = mediaIndex;
				card.classList.toggle( 'is-selected', checkbox.checked );
				updateSelectionTools();
			} );
			selectLabel.append( checkbox, createElement( 'span', 'uplink-mbe-media-select-text', config.strings.selectMedia ) );
			let removeMedia = null;
			if ( media.canDelete ) {
				removeMedia = createElement( 'button', 'uplink-mbe-media-delete dashicons dashicons-trash' );
				removeMedia.type = 'button';
				removeMedia.title = config.strings.deleteMedia;
				removeMedia.setAttribute( 'aria-label', `${ config.strings.deleteMedia }: ${ media.title }` );
				removeMedia.addEventListener( 'click', ( event ) => {
					event.stopPropagation();
					deleteMedia( [ media.id ] );
				} );
			}

			const preview = createElement( 'button', 'uplink-mbe-media-preview' );
			preview.type = 'button';
			if ( state.reorderMode ) {
				preview.setAttribute( 'aria-describedby', 'uplink-mbe-media-order-help' );
			}
			preview.draggable = true;
			preview.classList.toggle( 'is-image', Boolean( media.isImage || media.hasPreview ) );
			preview.setAttribute( 'aria-label', `${ config.strings.attachmentDetails }: ${ media.title }` );
			if ( media.isImage || media.hasPreview ) {
				const image = document.createElement( 'img' );
				image.src = media.thumbnail;
				image.alt = media.alt || '';
				image.draggable = false;
				image.loading = 'lazy';
				preview.append( image );
			} else {
				preview.append( createFilePlaceholder( media ) );
			}
			preview.addEventListener( 'click', ( event ) => activateMedia( event, media, mediaIndex, preview ) );
			card.append( preview );

			const details = createElement( 'div', 'uplink-mbe-media-details' );
			details.draggable = true;
			const title = createElement( 'button', 'uplink-mbe-media-title', media.title );
			title.type = 'button';
			title.draggable = true;
			title.id = `uplink-mbe-media-title-${ media.id }`;
			card.setAttribute( 'aria-labelledby', title.id );
			title.addEventListener( 'click', ( event ) => activateMedia( event, media, mediaIndex, title ) );
			details.append( title );
			if ( 'health' === state.mode && media.filename ) {
				const filename = createElement( 'div', 'uplink-mbe-media-filename', media.filename );
				filename.title = media.filename;
				details.append( filename );
			}
			const metadata = createElement( 'div', 'uplink-mbe-media-card-meta' );
			const metadataValues = {
				filename: 'health' === state.mode ? '' : media.filename,
				author: media.author,
				date: media.date,
				mime: media.mime,
				fileSize: media.fileSize,
				dimensions: media.dimensions,
			};
			Object.entries( metadataValues ).forEach( ( [ field, value ] ) => {
				if ( state.display[ field ] && value && '—' !== value ) {
					metadata.append( createElement( 'span', `uplink-mbe-media-meta-${ field }`, value ) );
				}
			} );
			if ( metadata.childElementCount ) {
				details.append( metadata );
			}

			const badges = createElement( 'div', 'uplink-mbe-media-badges' );
			media.collections.forEach( ( id ) => {
				const collection = state.collections.find( ( item ) => item.id === id );
				if ( collection ) {
					badges.append( createElement( 'span', 'uplink-mbe-media-badge', collection.name ) );
				}
			} );
			details.append( badges );
			card.append( details );

			const footer = createElement( 'footer', 'uplink-mbe-media-footer' );
			const footerPrimary = createElement( 'div', 'uplink-mbe-media-footer-primary' );
			footerPrimary.append( selectLabel );
			footer.append( footerPrimary );
			const footerStatus = createElement( 'div', 'uplink-mbe-media-footer-status' );
			if ( media.alt ) {
				const altStatus = createElement( 'span', 'uplink-mbe-alt-status' );
				altStatus.setAttribute( 'role', 'img' );
				altStatus.setAttribute( 'aria-label', config.strings.altTextPresent );
				altStatus.title = config.strings.altTextPresent;
				const altIcon = document.createElement( 'img' );
				altIcon.src = config.altIconUrl;
				altIcon.alt = '';
				altIcon.setAttribute( 'aria-hidden', 'true' );
				altStatus.append( altIcon );
				footerStatus.append( altStatus );
			}
			if ( Array.isArray( media.exif ) && media.exif.length ) {
				const exifStatus = createElement( 'span', 'uplink-mbe-exif-status' );
				exifStatus.setAttribute( 'role', 'img' );
				exifStatus.setAttribute( 'aria-label', config.strings.exifAvailable );
				exifStatus.title = config.strings.exifAvailable;
				const exifIcon = document.createElement( 'img' );
				exifIcon.src = config.exifIconUrl;
				exifIcon.alt = '';
				exifIcon.setAttribute( 'aria-hidden', 'true' );
				exifStatus.append( exifIcon );
				footerStatus.append( exifStatus );
			}
			if ( media.optimization?.optimized ) {
				const optimizationStatus = createElement( 'span', 'uplink-mbe-optimization-status' );
				optimizationStatus.setAttribute( 'role', 'img' );
				optimizationStatus.setAttribute( 'aria-label', config.strings.optimizedLabel );
				optimizationStatus.title = media.optimization.summary || config.strings.optimizedLabel;
				const optimizationIcon = document.createElement( 'img' );
				optimizationIcon.src = config.optimizationIconUrl;
				optimizationIcon.alt = '';
				optimizationIcon.setAttribute( 'aria-hidden', 'true' );
				optimizationStatus.append( optimizationIcon );
				footerStatus.append( optimizationStatus );
			}
			if ( media.attached ) {
				const attachedStatus = createElement( 'span', 'uplink-mbe-attached-status dashicons dashicons-paperclip' );
				attachedStatus.setAttribute( 'role', 'img' );
				attachedStatus.setAttribute( 'aria-label', config.strings.attachedToContent );
				attachedStatus.title = config.strings.attachedToContent;
				footerStatus.append( attachedStatus );
			}
			if ( removeMedia ) {
				footerStatus.append( removeMedia );
			}
			footer.append( footerStatus );
			card.append( footer );

			bindMediaReordering( card, media );
			card.addEventListener( 'dragstart', ( event ) => {
				if ( mediaOrderSaving ) {
					event.preventDefault();
					return;
				}
				if ( ! state.selected.has( media.id ) ) {
					state.selected.clear();
					state.selected.add( media.id );
					document.querySelectorAll( '.uplink-mbe-media-card' ).forEach( ( item ) => item.classList.toggle( 'is-selected', item === card ) );
					document.querySelectorAll( '.uplink-mbe-media-select input' ).forEach( ( input ) => {
						input.checked = Number( input.value ) === media.id;
					} );
					updateSelectionTools();
				}
				draggedMedia = { collection: state.collection, ids: state.media.filter( ( item ) => state.selected.has( item.id ) ).map( ( item ) => item.id ) };
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData( 'text/plain', Array.from( state.selected ).join( ',' ) );
				if ( state.reorderMode ) {
					const count = draggedMedia.ids.length;
					mediaDragGhost = createElement( 'div', 'uplink-mbe-media-drag-ghost', count > 1 ? config.strings.movingItems.replace( '%d', String( count ) ) : media.title );
					library.append( mediaDragGhost );
					event.dataTransfer.setDragImage( mediaDragGhost, 18, 18 );
					elements.grid.querySelectorAll( '.uplink-mbe-media-card' ).forEach( ( item ) => item.classList.toggle( 'is-dragging', draggedMedia.ids.includes( Number( item.dataset.mediaId ) ) ) );
				} else {
					card.classList.add( 'is-dragging' );
				}
			} );
			card.addEventListener( 'dragend', finishMediaDrag );

			elements.grid.append( card );
		} );
	}

	function activateMedia( event, media, index, trigger ) {
		if ( state.reorderMode ) {
			event.preventDefault();
			selectMedia( event, media, index );
			return;
		}
		if ( ! event.ctrlKey && ! event.metaKey && ! event.shiftKey ) {
			openAttachmentModal( media, trigger );
			return;
		}

		event.preventDefault();
		selectMedia( event, media, index );
	}

	function updateSelectionTools() {
		const count = state.selected.size;
		elements.selectionTools.hidden = state.reorderMode || 0 === count;
		elements.selectionCount.textContent = `${ count } ${ config.strings.selected }`;
		previousSelectionCount = count;
		updateDrawerSummary();
	}

	function renderPagination() {
		elements.pagination.replaceChildren();
		elements.pagination.classList.remove( 'is-infinite-loading' );

		if ( ! config.pagination ) {
			setupInfiniteScroll();
			return;
		}

		if ( infiniteObserver ) {
			infiniteObserver.disconnect();
		}
		if ( state.pagination.pages <= 1 ) {
			return;
		}

		const previous = createElement( 'button', 'button', '‹' );
		previous.type = 'button';
		previous.disabled = state.pagination.page <= 1;
		previous.setAttribute( 'aria-label', 'Previous page' );
		previous.addEventListener( 'click', () => {
			state.page -= 1;
			loadState();
		} );

		const next = createElement( 'button', 'button', '›' );
		next.type = 'button';
		next.disabled = state.pagination.page >= state.pagination.pages;
		next.setAttribute( 'aria-label', 'Next page' );
		next.addEventListener( 'click', () => {
			state.page += 1;
			loadState();
		} );

		elements.pagination.append( previous, createElement( 'span', '', `${ state.pagination.page } / ${ state.pagination.pages }` ), next );
	}

	function setupInfiniteScroll() {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			if ( state.pagination.page < state.pagination.pages ) {
				const more = createElement( 'button', 'button', config.strings.loadingMore );
				more.type = 'button';
				more.addEventListener( 'click', loadNextPage );
				elements.pagination.append( more );
			}
			return;
		}
		if ( ! infiniteObserver ) {
			infiniteObserver = new IntersectionObserver( ( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					loadNextPage();
				}
			}, { rootMargin: '300px 0px' } );
		}
		infiniteObserver.disconnect();
		if ( state.pagination.page < state.pagination.pages ) {
			infiniteObserver.observe( elements.pagination );
		}
	}

	async function loadNextPage() {
		if ( config.pagination || infiniteLoading || mediaOrderSaving || draggedMedia || state.pagination.page >= state.pagination.pages ) {
			return;
		}

		const generation = loadGeneration;
		infiniteLoading = true;
		elements.pagination.classList.add( 'is-infinite-loading' );
		elements.pagination.textContent = config.strings.loadingMore;
		try {
			const data = await request( 'uplink_mbe_native_state', libraryRequestData( state.pagination.page + 1 ) );
			if ( generation !== loadGeneration ) {
				return;
			}
			applyStateData( data, true );
			renderGrid();
			updateSelectionTools();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		} finally {
			infiniteLoading = false;
			renderPagination();
			if ( currentAttachment ) {
				updateAttachmentNavigation();
			}
		}
	}

	async function assignMedia( ids, collection, mode ) {
		try {
			await request( 'uplink_mbe_assign_media', {
				media_ids: JSON.stringify( ids ),
				collection,
				mode,
			} );
			const name = state.collections.find( ( item ) => item.id === Number( collection ) )?.name || config.strings.uncategorized;
			showNotice( 'clear' === mode ? config.strings.clear : `${ 'remove' === mode ? config.strings.remove : config.strings.add }: ${ name }` );
			await loadState();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		}
	}

	function openCollectionModal( collection = null, parent = 0 ) {
		if ( state.reorderMode ) return;
		if ( ! config.canManage ) {
			return;
		}
		elements.collectionId.value = collection ? String( collection.id ) : '0';
		elements.collectionName.value = collection ? collection.name : '';
		elements.modalTitle.textContent = collection ? config.strings.editCollection : config.strings.newCollection;
		elements.collectionParent.replaceChildren();

		const top = createElement( 'option', '', config.strings.noParent );
		top.value = '0';
		elements.collectionParent.append( top );
		eligibleCollectionParents( collection ).forEach( ( item ) => {
			const option = createElement( 'option', '', collectionOptionLabel( item ) );
			option.value = String( item.id );
			elements.collectionParent.append( option );
		} );
		elements.collectionParent.value = String( collection ? collection.parent : parent );

		elements.modal.hidden = false;
		document.body.classList.add( 'uplink-mbe-modal-open' );
		window.setTimeout( () => elements.collectionName.focus(), 0 );
	}

	function closeCollectionModal() {
		elements.modal.hidden = true;
		document.body.classList.remove( 'uplink-mbe-modal-open' );
	}

	function chooseChildDeleteAction( description, trigger = null ) {
		if ( ! elements.childDeleteModal ) {
			return Promise.resolve( null );
		}
		elements.childDeleteDescription.textContent = description;
		childDeleteTrigger = trigger;
		elements.childDeleteModal.hidden = false;
		document.body.classList.add( 'uplink-mbe-modal-open' );
		window.setTimeout( () => elements.promoteChildren.focus(), 0 );
		return new Promise( ( resolve ) => {
			childDeleteResolve = resolve;
		} );
	}

	function closeChildDeleteModal( action = null ) {
		if ( ! elements.childDeleteModal || elements.childDeleteModal.hidden ) {
			return;
		}
		elements.childDeleteModal.hidden = true;
		if ( elements.modal.hidden && elements.attachmentModal.hidden && elements.collectionManagerModal?.hidden ) {
			document.body.classList.remove( 'uplink-mbe-modal-open' );
		}
		const resolve = childDeleteResolve;
		childDeleteResolve = null;
		if ( childDeleteTrigger ) {
			childDeleteTrigger.focus();
		}
		childDeleteTrigger = null;
		if ( resolve ) {
			resolve( 'promote' === action || 'delete' === action ? action : null );
		}
	}

	function showManagerNotice( message, type = 'success' ) {
		window.uplinkMbeToast( message, type );
	}

	function renderCollectionManager() {
		elements.bulkCollectionParent.replaceChildren();
		const top = createElement( 'option', '', config.strings.noParent );
		top.value = '0';
		elements.bulkCollectionParent.append( top );
		eligibleCollectionParents().forEach( ( collection ) => {
			const option = createElement( 'option', '', collectionOptionLabel( collection ) );
			option.value = String( collection.id );
			elements.bulkCollectionParent.append( option );
		} );

		elements.collectionManagerList.replaceChildren();
		state.collections.forEach( ( collection ) => {
			const label = createElement( 'label', 'uplink-mbe-collection-manager-item' );
			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.value = String( collection.id );
			checkbox.addEventListener( 'change', updateCollectionManagerSelection );
			label.append( checkbox, createElement( 'span', '', `${ '— '.repeat( collectionDepth( collection ) ) }${ collection.name }` ), createElement( 'span', 'uplink-mbe-collection-count', String( collection.count ) ) );
			elements.collectionManagerList.append( label );
		} );
		elements.selectAllCollections.checked = false;
		updateCollectionManagerSelection();
	}

	function selectedCollectionIds() {
		return Array.from( elements.collectionManagerList.querySelectorAll( 'input:checked' ) ).map( ( input ) => Number( input.value ) ).filter( Boolean );
	}

	function updateCollectionManagerSelection() {
		const checkboxes = Array.from( elements.collectionManagerList.querySelectorAll( 'input[type="checkbox"]' ) );
		const selected = checkboxes.filter( ( checkbox ) => checkbox.checked ).length;
		elements.bulkDeleteCollections.disabled = 0 === selected;
		elements.selectAllCollections.checked = Boolean( checkboxes.length ) && selected === checkboxes.length;
		elements.selectAllCollections.indeterminate = selected > 0 && selected < checkboxes.length;
	}

	function openCollectionManager() {
		if ( state.reorderMode ) return;
		elements.collectionManagerNotice.hidden = true;
		renderCollectionManager();
		elements.collectionManagerModal.hidden = false;
		document.body.classList.add( 'uplink-mbe-modal-open' );
		window.setTimeout( () => elements.bulkCollectionNames.focus(), 0 );
	}

	function closeCollectionManager() {
		elements.collectionManagerModal.hidden = true;
		document.body.classList.remove( 'uplink-mbe-modal-open' );
		if ( elements.manageCollections ) {
			elements.manageCollections.focus();
		}
	}

	async function createCollections() {
		if ( ! elements.bulkCollectionNames.value.trim() ) {
			showManagerNotice( config.strings.error, 'error' );
			return;
		}
		try {
			const data = await request( 'uplink_mbe_bulk_collections', {
				mode: 'create',
				parent: elements.bulkCollectionParent.value,
				names: elements.bulkCollectionNames.value,
			} );
			elements.bulkCollectionNames.value = '';
			await loadState();
			renderCollectionManager();
			showManagerNotice( `${ config.strings.createdCollections } ${ data.created || 0 }` );
		} catch ( error ) {
			showManagerNotice( error.message, 'error' );
		}
	}

	async function deleteCollections() {
		const ids = selectedCollectionIds();
		if ( ! ids.length || ! window.confirm( config.strings.confirmBulkCollections ) ) {
			return;
		}
		const unselectedChildren = state.collections.filter( ( collection ) => ids.includes( collection.parent ) && ! ids.includes( collection.id ) );
		let childAction = '';
		if ( unselectedChildren.length ) {
			const description = config.strings.childDeletePromptBulk.replace( '%d', String( unselectedChildren.length ) );
			childAction = await chooseChildDeleteAction( description, elements.bulkDeleteCollections );
			if ( ! childAction ) {
				return;
			}
		}
		try {
			const data = await request( 'uplink_mbe_bulk_collections', {
				mode: 'delete',
				term_ids: JSON.stringify( ids ),
				child_action: childAction,
			} );
			const deletedIds = 'delete' === childAction
				? ids.concat( unselectedChildren.map( ( child ) => child.id ) )
				: ids;
			if ( deletedIds.includes( state.collection ) ) {
				state.filter = 'all';
				state.collection = 0;
			}
			await loadState();
			renderCollectionManager();
			showManagerNotice( `${ config.strings.deletedCollections } ${ data.deleted || 0 }` );
		} catch ( error ) {
			showManagerNotice( error.message, 'error' );
		}
	}

	function openAttachmentModal( media, trigger ) {
		attachmentTrigger = trigger;
		showAttachment( media );
		setAttachmentMetadataTab( 'main' );
		elements.attachmentModal.hidden = false;
		elements.attachmentModal.classList.add( 'is-expanded' );
		elements.wrap.classList.add( 'has-attachment-inspector' );
		document.body.classList.add( 'uplink-mbe-modal-open' );
		window.setTimeout( () => elements.attachmentModal.querySelector( '[data-attachment-close]' ).focus(), 0 );
	}

	function compatibilitySnapshot() {
		return elements.attachmentCompatibilityForm
			? new URLSearchParams( new FormData( elements.attachmentCompatibilityForm ) ).toString()
			: '';
	}

	function renderAttachmentCompatibility( media ) {
		if ( ! elements.attachmentCompatibility || ! elements.attachmentCompatibilityForm ) {
			return;
		}
		elements.attachmentCompatibilityForm.innerHTML = media.compat?.html || '';
		elements.attachmentCompatibilityForm.querySelector( '[data-mbe-replace-id]' )?.closest( 'tr' )?.remove();
		elements.attachmentCompatibility.hidden = ! elements.attachmentCompatibilityForm.childElementCount;
		if ( ! elements.attachmentCompatibility.hidden && window.jQuery && window.rwmb ) {
			window.jQuery( document ).trigger( 'mb_ready' );
		}
		currentCompatSnapshot = compatibilitySnapshot();
		updateAttachmentSaveState();
	}

	async function loadAttachmentCompatibility( media ) {
		const generation = ++attachmentCompatGeneration;
		if ( media.compat ) {
			renderAttachmentCompatibility( media );
			return;
		}
		try {
			const detailed = await request( 'uplink_mbe_get_attachment', { attachment_id: media.id } );
			if ( generation !== attachmentCompatGeneration || currentAttachment?.id !== media.id ) {
				return;
			}
			Object.assign( media, detailed );
			renderAttachmentCompatibility( media );
		} catch ( error ) {
			if ( generation === attachmentCompatGeneration && currentAttachment?.id === media.id ) {
				showAttachmentNotice( error.message, 'error' );
			}
		}
	}

	function setAttachmentMetadataTab( requestedTab, moveFocus = false ) {
		const exifAvailable = ! elements.attachmentExifTab.hidden;
		const optimizationAvailable = ! elements.attachmentOptimizationTab.hidden;
		const activeTab = 'file' === requestedTab
			|| ( 'exif' === requestedTab && exifAvailable )
			|| ( 'optimization' === requestedTab && optimizationAvailable )
			? requestedTab
			: 'main';
		const tabs = [
			[ 'main', elements.attachmentMainTab, elements.attachmentMainPanel ],
			[ 'file', elements.attachmentFileTab, elements.attachmentFilePanel ],
			[ 'exif', elements.attachmentExifTab, elements.attachmentExif ],
			[ 'optimization', elements.attachmentOptimizationTab, elements.attachmentOptimization ],
		];
		tabs.forEach( ( [ name, tab, panel ] ) => {
			const selected = name === activeTab;
			tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			tab.tabIndex = selected ? 0 : -1;
			panel.hidden = ! selected;
		} );
		if ( moveFocus ) {
			tabs.find( ( [ name ] ) => name === activeTab )?.[ 1 ]?.focus();
		}
	}

	document.addEventListener( 'uplink-mbe-file-replaced', async ( event ) => {
		try {
			await loadState();
			if ( currentAttachment?.id === event.detail.id ) {
				const media = await request( 'uplink_mbe_get_attachment', { attachment_id: event.detail.id } );
				media.url += `${ media.url.includes( '?' ) ? '&' : '?' }mbe=${ Date.now() }`;
				showAttachment( media );
			}
		} catch ( error ) { showToast( error.message, 'error' ); }
	} );

	function showAttachment( media ) {
		currentAttachment = media;
		clearAttachmentNotice();
		elements.attachmentPreview.replaceChildren();
		if ( media.isImage || media.hasPreview ) {
			const image = document.createElement( 'img' );
			image.src = media.isImage ? ( media.url || media.thumbnail ) : media.thumbnail;
			image.alt = media.alt || '';
			elements.attachmentPreview.append( image );
		} else {
			elements.attachmentPreview.append( createFilePlaceholder( media ) );
		}
		elements.attachmentTitle.textContent = media.title;
		elements.attachmentTitleInput.value = media.title || '';
		elements.attachmentFilename.textContent = media.filename || '—';
		elements.attachmentPath.textContent = media.filePath || media.filename;
		elements.attachmentFullPath.textContent = media.fullFilePath || media.url || '';
		elements.attachmentReplace.hidden = ! media.canEdit || ! window.uplinkMbeReplacement;
		elements.attachmentType.textContent = media.mime || media.fileType || '—';
		elements.attachmentSize.textContent = media.fileSize || '—';
		elements.attachmentAuthor.textContent = media.author || '—';
		elements.attachmentId.textContent = String( media.id );
		elements.attachmentAlt.value = media.alt || '';
		elements.attachmentDecorative.checked = Boolean( media.decorative );
		elements.attachmentAlt.disabled = Boolean( media.decorative );
		elements.attachmentCaption.value = media.caption || '';
		elements.attachmentDescription.value = media.description || '';
		if ( elements.attachmentCompatibilityForm ) {
			elements.attachmentCompatibilityForm.replaceChildren();
			elements.attachmentCompatibility.hidden = true;
			currentCompatSnapshot = '';
		}
		loadAttachmentCompatibility( media );
		elements.attachmentDate.textContent = media.date || '—';
		elements.attachmentDimensions.textContent = media.dimensions || '—';
		elements.attachmentEdit.hidden = ! media.isImage || ! media.imageEditUrl;
		elements.attachmentEdit.href = media.imageEditUrl || '#';
		const exifList = elements.attachmentExif.querySelector( 'dl' );
		exifList.replaceChildren();
		( media.exif || [] ).forEach( ( item ) => {
			const row = createElement( 'div' );
			row.append( createElement( 'dt', '', item.label ), createElement( 'dd', '', item.value ) );
			exifList.append( row );
		} );
		const hasExif = Boolean( state.display.exif && exifList.childElementCount );
		elements.attachmentExifTab.hidden = ! hasExif;
		if ( ! hasExif && 'true' === elements.attachmentExifTab.getAttribute( 'aria-selected' ) ) {
			setAttachmentMetadataTab( 'main' );
		}
		elements.attachmentCollectionOptions.replaceChildren();
		const renderedCollectionIds = new Set();
		const createCollectionOption = ( collection, isChild = false ) => {
			const label = createElement( 'label', 'uplink-mbe-attachment-collection-option' );
			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.value = String( collection.id );
			checkbox.checked = media.collections.includes( Number( collection.id ) );
			label.classList.toggle( 'is-child', isChild );
			label.append( checkbox, createElement( 'span', '', collection.name ) );
			renderedCollectionIds.add( Number( collection.id ) );
			return label;
		};
		const appendCollectionGroup = ( collection, container, isChild = false ) => {
			const group = createElement( 'div', 'uplink-mbe-attachment-collection-group' );
			const children = siblingCollections( collection.id );
			group.classList.toggle( 'has-children', Boolean( children.length ) );
			const parentOption = createCollectionOption( collection, isChild );
			parentOption.classList.toggle( 'has-children', Boolean( children.length ) );
			group.append( parentOption );
			if ( children.length ) {
				const childOptions = createElement( 'div', 'uplink-mbe-attachment-collection-children' );
				childOptions.setAttribute( 'role', 'group' );
				childOptions.setAttribute( 'aria-label', `${ config.strings.subcollections }: ${ collection.name }` );
				childOptions.append( createElement( 'span', 'uplink-mbe-attachment-collection-children-label', config.strings.subcollections ) );
				children.forEach( ( child ) => appendCollectionGroup( child, childOptions, true ) );
				group.append( childOptions );
			}
			container.append( group );
		};
		state.collections.filter( ( collection ) => ! Number( collection.parent ) ).forEach( ( collection ) => {
			appendCollectionGroup( collection, elements.attachmentCollectionOptions );
		} );
		state.collections.filter( ( collection ) => ! renderedCollectionIds.has( Number( collection.id ) ) ).forEach( ( collection ) => {
			const group = createElement( 'div', 'uplink-mbe-attachment-collection-group' );
			group.append( createCollectionOption( collection, Boolean( collection.parent ) ) );
			elements.attachmentCollectionOptions.append( group );
		} );
		if ( elements.attachmentOptimization && elements.attachmentOptimizationList ) {
			elements.attachmentOptimizationList.replaceChildren();
			let optimizationList = null;
			( media.optimization?.details || [] ).forEach( ( item ) => {
				if ( item.section || ! optimizationList ) {
					const group = createElement( 'section', 'uplink-mbe-optimization-group' );
					group.append( createElement( 'h4', '', item.section || config.strings.optimization ) );
					optimizationList = createElement( 'dl', 'uplink-mbe-attachment-data-list' );
					group.append( optimizationList );
					elements.attachmentOptimizationList.append( group );
				}
				const row = createElement( 'div' );
				row.append( createElement( 'dt', '', item.label ), createElement( 'dd', '', item.value ) );
				optimizationList.append( row );
			} );
			const hasOptimization = Boolean( elements.attachmentOptimizationList.childElementCount );
			elements.attachmentOptimizationTab.hidden = ! hasOptimization;
			if ( ! hasOptimization && 'true' === elements.attachmentOptimizationTab.getAttribute( 'aria-selected' ) ) {
				setAttachmentMetadataTab( 'main' );
			}
		}

		elements.attachmentDelete.hidden = ! media.canDelete;
		updateAttachmentSaveState();
		updateAttachmentNavigation();
	}

	async function deleteMedia( ids, fromModal = false ) {
		if ( ! ids.length ) {
			return;
		}
		const message = 1 === ids.length
			? config.strings.confirmDeleteMedia
			: config.strings.confirmDeleteMediaBulk.replace( '%d', String( ids.length ) );
		if ( ! window.confirm( message ) ) {
			return;
		}

		try {
			let data;
			try {
				data = await request( 'uplink_mbe_delete_media', {
					media_ids: JSON.stringify( ids ),
					acknowledge_used: '0',
				} );
			} catch ( error ) {
				if ( ! error.data?.requiresAcknowledgement ) {
					throw error;
				}

				const usedCount = Number( error.data.usedCount || 1 );
				const warning = 1 === ids.length
					? config.strings.confirmDeleteUsed
					: config.strings.confirmDeleteUsedBulk
						.replace( '%1$d', String( usedCount ) )
						.replace( '%2$d', String( ids.length ) );
				if ( ! window.confirm( warning ) ) {
					return;
				}

				data = await request( 'uplink_mbe_delete_media', {
					media_ids: JSON.stringify( ids ),
					acknowledge_used: '1',
				} );
			}
			if ( fromModal ) {
				closeAttachmentModal();
			}
			state.selected.clear();
			selectionAnchor = -1;
			state.page = 1;
			showNotice( data.failed?.length ? config.strings.deletedMediaPartial : config.strings.deletedMedia, data.failed?.length ? 'warning' : 'success' );
			await loadState();
		} catch ( error ) {
			if ( fromModal ) {
				showAttachmentNotice( error.message, 'error' );
			} else {
				showNotice( error.message, 'error' );
			}
		}
	}

	function updateAttachmentNavigation() {
		const index = currentAttachment ? state.media.findIndex( ( media ) => media.id === currentAttachment.id ) : -1;
		if ( index < 0 ) {
			elements.attachmentPosition.textContent = '';
			elements.attachmentPrevious.disabled = true;
			elements.attachmentNext.disabled = true;
			return;
		}
		const pageSize = Number( config.pageSize ) || 40;
		const absoluteIndex = config.pagination ? ( ( state.pagination.page - 1 ) * pageSize ) + index : index;
		const total = Number( state.pagination.total ) || state.media.length;
		elements.attachmentPosition.textContent = total ? `${ absoluteIndex + 1 } of ${ total }` : '';
		elements.attachmentPrevious.disabled = attachmentNavigationBusy || absoluteIndex <= 0;
		elements.attachmentNext.disabled = attachmentNavigationBusy || infiniteLoading || absoluteIndex >= total - 1;
	}

	async function navigateAttachment( direction ) {
		if ( attachmentNavigationBusy || ! currentAttachment ) {
			return;
		}

		if ( attachmentIsDirty() ) {
			const saved = await saveAttachmentAlt( false );
			if ( ! saved ) {
				return;
			}
		}

		const index = state.media.findIndex( ( media ) => media.id === currentAttachment.id );
		const adjacent = index + direction;
		if ( adjacent >= 0 && adjacent < state.media.length ) {
			showAttachment( state.media[ adjacent ] );
			return;
		}

		if ( ! config.pagination && direction < 0 ) {
			return;
		}

		const targetPage = state.pagination.page + direction;
		if ( targetPage < 1 || targetPage > state.pagination.pages ) {
			return;
		}

		attachmentNavigationBusy = true;
		updateAttachmentNavigation();
		try {
			const data = await request( 'uplink_mbe_native_state', libraryRequestData( targetPage ) );
			const previousLength = state.media.length;
			applyStateData( data, ! config.pagination );
			if ( config.pagination ) {
				state.selected.clear();
				render();
			} else {
				renderGrid();
				renderPagination();
				updateSelectionTools();
			}
			const target = config.pagination
				? ( direction > 0 ? state.media[ 0 ] : state.media[ state.media.length - 1 ] )
				: state.media[ previousLength ];
			if ( target ) {
				showAttachment( target );
			}
		} catch ( error ) {
			showAttachmentNotice( error.message, 'error' );
		} finally {
			attachmentNavigationBusy = false;
			updateAttachmentNavigation();
		}
	}

	function closeAttachmentModal() {
		attachmentCompatGeneration++;
		clearAttachmentNotice();
		elements.attachmentModal.hidden = true;
		elements.attachmentModal.classList.remove( 'is-expanded' );
		elements.wrap.classList.remove( 'has-attachment-inspector' );
		if ( elements.modal.hidden && elements.collectionManagerModal?.hidden && elements.childDeleteModal?.hidden && elements.galleryModal?.hidden ) {
			document.body.classList.remove( 'uplink-mbe-modal-open' );
		}
		if ( attachmentTrigger ) {
			attachmentTrigger.focus();
		}
		attachmentTrigger = null;
		currentAttachment = null;
	}

	function handleAttachmentClose() {
		closeAttachmentModal();
	}

	function updateAttachmentSaveState() {
		elements.attachmentSaveAlt.disabled = attachmentSaving || ! attachmentIsDirty();
	}

	function attachmentIsDirty() {
		const selectedCollections = Array.from( elements.attachmentCollectionOptions.querySelectorAll( 'input:checked' ) ).map( ( input ) => Number( input.value ) ).sort( ( a, b ) => a - b );
		const currentCollections = ( currentAttachment?.collections || [] ).map( Number ).sort( ( a, b ) => a - b );
		return Boolean( currentAttachment ) && (
			elements.attachmentTitleInput.value !== ( currentAttachment.title || '' ) ||
			elements.attachmentAlt.value !== ( currentAttachment.alt || '' ) ||
			elements.attachmentDecorative.checked !== Boolean( currentAttachment.decorative ) ||
			elements.attachmentCaption.value !== ( currentAttachment.caption || '' ) ||
			elements.attachmentDescription.value !== ( currentAttachment.description || '' ) ||
			selectedCollections.length !== currentCollections.length ||
			selectedCollections.some( ( collection, index ) => collection !== currentCollections[ index ] ) ||
			compatibilitySnapshot() !== currentCompatSnapshot
		);
	}

	async function saveAttachmentCompatibility() {
		if ( ! currentAttachment?.compat?.nonce || ! elements.attachmentCompatibilityForm || elements.attachmentCompatibility.hidden ) {
			return;
		}
		const body = new FormData( elements.attachmentCompatibilityForm );
		body.append( 'action', 'save-attachment-compat' );
		body.append( 'id', String( currentAttachment.id ) );
		body.append( 'nonce', currentAttachment.compat.nonce );
		body.append( 'post_id', '0' );
		const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
		const payload = await response.json();
		if ( ! response.ok || ! payload.success ) {
			throw new Error( config.strings.error );
		}
		currentCompatSnapshot = compatibilitySnapshot();
		updateAttachmentSaveState();
	}

	async function saveAttachmentAlt( announce = true ) {
		if ( ! currentAttachment ) {
			return false;
		}
		if ( attachmentSaving ) return false;
		if ( ! attachmentIsDirty() ) return true;
		attachmentSaving = true;
		updateAttachmentSaveState();
		try {
			const collections = Array.from( elements.attachmentCollectionOptions.querySelectorAll( 'input:checked' ) ).map( ( input ) => Number( input.value ) );
			const data = await request( 'uplink_mbe_update_attachment', {
				attachment_id: currentAttachment.id,
				title: elements.attachmentTitleInput.value,
				alt_text: elements.attachmentAlt.value,
				decorative: elements.attachmentDecorative.checked ? '1' : '0',
				caption: elements.attachmentCaption.value,
				description: elements.attachmentDescription.value,
				collections: JSON.stringify( collections ),
			} );
			await saveAttachmentCompatibility();
			Object.assign( currentAttachment, data );
			elements.attachmentTitle.textContent = currentAttachment.title;
			const cardImage = elements.grid.querySelector( `[data-media-id="${ currentAttachment.id }"] .uplink-mbe-media-preview img` );
			if ( cardImage ) {
				cardImage.alt = currentAttachment.alt;
			}
			await loadState();
			if ( announce ) {
				showAttachmentNotice( config.strings.attachmentSaved );
			}
			return true;
		} catch ( error ) {
			showAttachmentNotice( error.message, 'error' );
			return false;
		} finally {
			attachmentSaving = false;
			updateAttachmentSaveState();
		}
	}

	async function copyAttachmentPath( full = false ) {
		try {
			await navigator.clipboard.writeText( ( full ? elements.attachmentFullPath : elements.attachmentPath ).textContent );
			showAttachmentNotice( config.strings.copied );
		} catch ( error ) {
			showAttachmentNotice( config.strings.error, 'error' );
		}
	}

	async function saveCollection( event ) {
		event.preventDefault();
		try {
			await request( 'uplink_mbe_save_collection', {
				term_id: elements.collectionId.value,
				name: elements.collectionName.value,
				parent: elements.collectionParent.value,
			} );
			closeCollectionModal();
			showNotice( config.strings.saveCollection );
			await loadState();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		}
	}

	async function deleteCollection( collection, trigger = null ) {
		const message = config.deletionSync ? config.strings.confirmDeleteSynced : config.strings.confirmDelete;
		if ( ! window.confirm( message ) ) {
			return;
		}
		const children = state.collections.filter( ( child ) => child.parent === collection.id );
		let childAction = '';
		if ( children.length ) {
			const description = config.strings.childDeletePrompt
				.replace( '%1$s', collection.name )
				.replace( '%2$d', String( children.length ) );
			childAction = await chooseChildDeleteAction( description, trigger );
			if ( ! childAction ) {
				return;
			}
		}
		try {
			await request( 'uplink_mbe_delete_collection', { term_id: collection.id, child_action: childAction } );
			const deletingCurrentChild = 'delete' === childAction && children.some( ( child ) => child.id === state.collection );
			if ( state.collection === collection.id || deletingCurrentChild ) {
				state.filter = 'all';
				state.collection = 0;
			}
			showNotice( config.strings.collectionDeleted );
			await loadState();
		} catch ( error ) {
			showNotice( error.message, 'error' );
		}
	}

	function runSearch() {
		window.clearTimeout( searchTimer );
		const search = elements.searchInput.value.trim();
		if ( search === state.search ) {
			return;
		}
		state.search = search;
		state.page = 1;
		loadState( { announceResults: true } );
	}

	function scheduleSearch() {
		window.clearTimeout( searchTimer );
		searchTimer = window.setTimeout( runSearch, 350 );
	}

	elements.searchForm.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		runSearch();
	} );
	elements.searchInput.addEventListener( 'input', () => {
		if ( ! searchComposing ) {
			scheduleSearch();
		}
	} );
	elements.searchInput.addEventListener( 'compositionstart', () => {
		searchComposing = true;
		window.clearTimeout( searchTimer );
	} );
	elements.searchInput.addEventListener( 'compositionend', () => {
		searchComposing = false;
		scheduleSearch();
	} );
	elements.searchInput.addEventListener( 'search', () => {
		runSearch();
	} );
	elements.typeFilter.addEventListener( 'change', () => {
		state.type = elements.typeFilter.value;
		if ( state.mimeSubtype && ! state.mimeSubtype.startsWith( `${ state.type }/` ) ) {
			state.mimeSubtype = '';
		}
		state.page = 1;
		loadState();
	} );
	[
		[ elements.mimeFilter, 'mimeSubtype', false ],
		[ elements.uploaderFilter, 'uploadedBy', false ],
		[ elements.attachmentStatusFilter, 'attachmentStatus', false ],
		[ elements.optimizationStatusFilter, 'optimizationStatus', false ],
		[ elements.uploadedFromFilter, 'uploadedFrom', false ],
		[ elements.uploadedToFilter, 'uploadedTo', false ],
		[ elements.widthMinFilter, 'widthMin', false ],
		[ elements.widthMaxFilter, 'widthMax', false ],
		[ elements.heightMinFilter, 'heightMin', false ],
		[ elements.heightMaxFilter, 'heightMax', false ],
		[ elements.fileSizeMinFilter, 'fileSizeMin', false ],
		[ elements.fileSizeMaxFilter, 'fileSizeMax', false ],
		[ elements.missingAltFilter, 'missingAlt', true ],
	].filter( ( [ control ] ) => control ).forEach( ( [ control, key, checkbox ] ) => {
		control.addEventListener( 'change', () => {
			state[ key ] = checkbox ? control.checked : control.value;
			state.page = 1;
			loadState();
		} );
	} );
	elements.clearFilters.addEventListener( 'click', () => {
		state.type = '';
		state.mimeSubtype = '';
		state.uploadedBy = '';
		state.attachmentStatus = '';
		state.optimizationStatus = '';
		state.uploadedFrom = '';
		state.uploadedTo = '';
		state.widthMin = '';
		state.widthMax = '';
		state.heightMin = '';
		state.heightMax = '';
		state.fileSizeMin = '';
		state.fileSizeMax = '';
		state.missingAlt = false;
		state.page = 1;
		loadState();
	} );
	elements.drawerToggle.addEventListener( 'click', () => {
		setDrawerOpen( 'true' !== elements.drawerToggle.getAttribute( 'aria-expanded' ) );
	} );
	elements.libraryTab.addEventListener( 'click', () => setWorkspaceMode( 'library' ) );
	elements.healthTab.addEventListener( 'click', () => setWorkspaceMode( 'health' ) );
	elements.healthRescan.addEventListener( 'click', async () => {
		await loadHealth( true );
		state.page = 1;
		loadState();
	} );
	elements.displayOptionsToggle.addEventListener( 'click', () => {
		setDisplayOptionsOpen( 'true' !== elements.displayOptionsToggle.getAttribute( 'aria-expanded' ) );
	} );
	elements.drawerPanel.querySelector( '[data-filter-close]' )?.addEventListener( 'click', () => setDrawerOpen( false ) );
	elements.displayOptions.querySelector( '[data-display-close]' )?.addEventListener( 'click', () => setDisplayOptionsOpen( false ) );
	elements.displayToggles.forEach( ( toggle ) => {
			toggle.addEventListener( 'change', () => {
				state.display[ toggle.dataset.displayField ] = toggle.checked;
			try {
				window.localStorage.setItem( 'uplinkMbeDisplay', JSON.stringify( state.display ) );
			} catch ( error ) {
				// Storage can be unavailable in privacy-restricted browser sessions.
			}
			if ( 'exif' === toggle.dataset.displayField && currentAttachment ) {
				const hasExif = Boolean( toggle.checked && elements.attachmentExif.querySelector( 'dl' )?.childElementCount );
				elements.attachmentExifTab.hidden = ! hasExif;
				if ( ! hasExif ) {
					setAttachmentMetadataTab( 'main' );
				}
			} else {
				renderGrid();
			}
		} );
	} );
	function setView( view ) {
		state.view = [ 'grid', 'list', 'masonry' ].includes( view ) ? view : 'grid';
		try {
			window.localStorage.setItem( 'uplinkMbeView', state.view );
		} catch ( error ) {
			// Storage can be unavailable in privacy-restricted browser sessions.
		}
		renderLibraryControls();
	}
	elements.listView.addEventListener( 'click', () => setView( 'list' ) );
	elements.gridView.addEventListener( 'click', () => setView( 'grid' ) );
	elements.masonryView.addEventListener( 'click', () => setView( 'masonry' ) );

	elements.bulkAdd.addEventListener( 'click', () => {
		if ( elements.bulkCollection.value ) {
			assignMedia( Array.from( state.selected ), Number( elements.bulkCollection.value ), 'add' );
		}
	} );
	elements.bulkRemove.addEventListener( 'click', () => {
		if ( elements.bulkCollection.value ) {
			assignMedia( Array.from( state.selected ), Number( elements.bulkCollection.value ), 'remove' );
		}
	} );
	elements.bulkCollection.addEventListener( 'change', updateBulkCollectionActions );
	elements.bulkClear.addEventListener( 'click', () => assignMedia( Array.from( state.selected ), 0, 'clear' ) );
	elements.bulkDeselect.addEventListener( 'click', () => {
		state.selected.clear();
		selectionAnchor = -1;
		renderGrid();
		updateSelectionTools();
	} );
	elements.bulkDelete.addEventListener( 'click', () => deleteMedia( Array.from( state.selected ) ) );
	if ( elements.newCollection ) {
		elements.newCollection.addEventListener( 'click', () => openCollectionModal() );
	}
	if ( elements.manageCollections ) {
		elements.manageCollections.addEventListener( 'click', openCollectionManager );
	}
	if ( elements.appearanceToggle ) {
		elements.appearanceToggle.addEventListener( 'click', () => {
			if ( elements.appearancePanel.hidden ) {
				openAppearancePanel();
			} else {
				closeAppearancePanel();
			}
		} );
		elements.appearancePanel.querySelectorAll( '[data-appearance-close]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => closeAppearancePanel() );
		} );
		elements.paletteTabs.forEach( ( tab ) => {
			tab.addEventListener( 'click', () => setPaletteMode( tab.dataset.paletteTab ) );
			tab.addEventListener( 'keydown', ( event ) => {
				if ( ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) {
					return;
				}
				event.preventDefault();
				setPaletteMode( 'ArrowLeft' === event.key || 'Home' === event.key ? 'light' : 'dark', true );
			} );
		} );
		elements.colorInputs.forEach( ( input ) => {
			input.addEventListener( 'input', () => {
				const output = input.parentElement?.querySelector( 'output' );
				if ( output ) {
					output.value = input.value.toUpperCase();
					output.textContent = input.value.toUpperCase();
				}
				if ( input.dataset.paletteMode === activePaletteMode ) {
					elements.wrap.style.setProperty( `--mbe-${ paletteVariableNames[ input.dataset.paletteColor ] }`, input.value );
				}
				updatePaletteContrast( input.dataset.paletteMode );
			} );
		} );
		elements.resetPalette.addEventListener( 'click', () => {
			const palette = config.paletteDefaults?.[ activePaletteMode ] || {};
			elements.colorInputs.forEach( ( input ) => {
				if ( input.dataset.paletteMode === activePaletteMode && palette[ input.dataset.paletteColor ] ) {
					input.value = palette[ input.dataset.paletteColor ];
					const output = input.parentElement?.querySelector( 'output' );
					if ( output ) {
						output.value = input.value.toUpperCase();
						output.textContent = input.value.toUpperCase();
					}
				}
			} );
			applyPalette( paletteFromInputs( activePaletteMode ) );
			updatePaletteContrast( activePaletteMode );
		} );
		elements.exportAppearance.addEventListener( 'click', exportAppearance );
		elements.importAppearance.addEventListener( 'click', () => elements.importAppearanceFile.click() );
		elements.importAppearanceFile.addEventListener( 'change', () => importAppearance( elements.importAppearanceFile.files?.[ 0 ] ) );
		elements.appearanceForm.addEventListener( 'submit', saveAppearance );
		updatePaletteContrast( 'light' );
		updatePaletteContrast( 'dark' );
	}
	if ( elements.createGallery ) {
		elements.createGallery.addEventListener( 'click', () => openGalleryGenerator( elements.createGallery ) );
	}
	elements.galleryCollection?.addEventListener( 'change', async () => {
		const collection = Number( elements.galleryCollection.value );
		await loadGalleryGeneratorCollection( collection );
	} );
	galleryControlIds.forEach( ( name ) => {
		const control = galleryControl( name );
		control?.addEventListener( 'input', renderGalleryGenerator );
		control?.addEventListener( 'change', renderGalleryGenerator );
	} );
	elements.galleryCopy?.addEventListener( 'click', copyGalleryShortcode );
	elements.bulkCreateCollections?.addEventListener( 'click', createCollections );
	elements.bulkDeleteCollections?.addEventListener( 'click', deleteCollections );
	elements.selectAllCollections?.addEventListener( 'change', () => {
		elements.collectionManagerList.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( checkbox ) => {
			checkbox.checked = elements.selectAllCollections.checked;
		} );
		updateCollectionManagerSelection();
	} );
	elements.collectionForm.addEventListener( 'submit', saveCollection );
	elements.attachmentSaveAlt.addEventListener( 'click', () => saveAttachmentAlt() );
	elements.attachmentModal.addEventListener( 'input', updateAttachmentSaveState );
	elements.attachmentModal.addEventListener( 'change', updateAttachmentSaveState );
	elements.attachmentDecorative.addEventListener( 'change', () => {
		elements.attachmentAlt.disabled = elements.attachmentDecorative.checked;
		if ( elements.attachmentDecorative.checked ) {
			elements.attachmentAlt.value = '';
		}
	} );
	elements.gridSize.addEventListener( 'input', () => applyGridSize( elements.gridSize.value ) );
	elements.gridRatios.forEach( ( button ) => {
		button.addEventListener( 'click', () => applyGridRatio( button.dataset.gridRatio ) );
	} );
	elements.infoToggle.addEventListener( 'click', toggleInfoPopup );
	document.addEventListener( 'click', ( event ) => {
		if ( ! elements.infoPopup.hidden && ! event.target.closest( '.uplink-mbe-native-title-group' ) ) {
			closeInfoPopup();
		}
		if ( ! event.target.closest( '#uplink-mbe-library-drawer' ) ) {
			setDrawerOpen( false );
			setDisplayOptionsOpen( false );
		}
		if ( elements.appearancePanel && ! elements.appearancePanel.hidden && ! event.target.closest( '#uplink-mbe-appearance-panel' ) && ! event.target.closest( '#uplink-mbe-appearance-toggle' ) ) {
			closeAppearancePanel( false );
		}
	} );
	elements.attachmentCopy.addEventListener( 'click', () => copyAttachmentPath() );
	elements.attachmentCopyFull.addEventListener( 'click', () => copyAttachmentPath( true ) );
	elements.attachmentReplace.addEventListener( 'click', async () => {
		if ( ! currentAttachment || ! await saveAttachmentAlt( false ) ) return;
		const id = currentAttachment.id;
		if ( await window.uplinkMbeReplaceFile( currentAttachment ) ) {
			document.dispatchEvent( new CustomEvent( 'uplink-mbe-file-replaced', { detail: { id } } ) );
		}
	} );
	elements.attachmentDelete.addEventListener( 'click', () => currentAttachment && deleteMedia( [ currentAttachment.id ], true ) );
	elements.attachmentPrevious.addEventListener( 'click', () => navigateAttachment( -1 ) );
	elements.attachmentNext.addEventListener( 'click', () => navigateAttachment( 1 ) );
	[ elements.attachmentMainTab, elements.attachmentFileTab, elements.attachmentExifTab, elements.attachmentOptimizationTab ].forEach( ( tab ) => {
		tab.addEventListener( 'click', () => setAttachmentMetadataTab( tab.id.replace( 'uplink-mbe-attachment-', '' ).replace( '-tab', '' ) ) );
		tab.addEventListener( 'keydown', ( event ) => {
			if ( ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) {
				return;
			}
			event.preventDefault();
			const tabs = [ elements.attachmentMainTab, elements.attachmentFileTab, elements.attachmentExifTab, elements.attachmentOptimizationTab ].filter( ( item ) => ! item.hidden );
			const current = tabs.indexOf( tab );
			let next = current;
			if ( 'Home' === event.key ) next = 0;
			if ( 'End' === event.key ) next = tabs.length - 1;
			if ( 'ArrowLeft' === event.key ) next = ( current - 1 + tabs.length ) % tabs.length;
			if ( 'ArrowRight' === event.key ) next = ( current + 1 ) % tabs.length;
			setAttachmentMetadataTab( tabs[ next ].id.replace( 'uplink-mbe-attachment-', '' ).replace( '-tab', '' ), true );
		} );
	} );
	elements.modal.querySelectorAll( '[data-modal-close]' ).forEach( ( button ) => button.addEventListener( 'click', closeCollectionModal ) );
	elements.attachmentModal.querySelectorAll( '[data-attachment-close]' ).forEach( ( button ) => button.addEventListener( 'click', handleAttachmentClose ) );
	elements.collectionManagerModal?.querySelectorAll( '[data-collection-manager-close]' ).forEach( ( button ) => button.addEventListener( 'click', closeCollectionManager ) );
	elements.childDeleteModal?.querySelectorAll( '[data-child-delete-action]' ).forEach( ( button ) => button.addEventListener( 'click', () => closeChildDeleteModal( button.dataset.childDeleteAction ) ) );
	elements.galleryModal?.querySelectorAll( '[data-gallery-generator-close]' ).forEach( ( button ) => button.addEventListener( 'click', closeGalleryGenerator ) );
	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && elements.appearancePanel && ! elements.appearancePanel.hidden ) {
			closeAppearancePanel();
			return;
		}
		if ( 'Escape' === event.key && ( ! elements.drawerPanel.hidden || ! elements.displayOptions.hidden ) ) {
			setDrawerOpen( false );
			setDisplayOptionsOpen( false );
			return;
		}
		if ( 'Escape' === event.key && ! elements.infoPopup.hidden ) {
			closeInfoPopup( true );
			return;
		}
		const editing = event.target.matches?.( 'input, textarea, select, [contenteditable="true"]' );
		if ( ! elements.attachmentModal.hidden && ! editing && ( 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) ) {
			event.preventDefault();
			navigateAttachment( 'ArrowLeft' === event.key ? -1 : 1 );
			return;
		}
		if ( 'Escape' === event.key ) {
			if ( elements.galleryModal && ! elements.galleryModal.hidden ) {
				closeGalleryGenerator();
			} else if ( elements.childDeleteModal && ! elements.childDeleteModal.hidden ) {
				closeChildDeleteModal();
			} else if ( ! elements.attachmentModal.hidden ) {
				handleAttachmentClose();
			} else if ( ! elements.modal.hidden ) {
				closeCollectionModal();
			} else if ( elements.collectionManagerModal && ! elements.collectionManagerModal.hidden ) {
				closeCollectionManager();
			}
		}
	} );

	function openUploadFlow() {
		if ( ! elements.upload || ! window.wp?.media ) {
			return null;
		}
		const frame = window.wp.media( {
			title: config.strings.upload,
			button: { text: config.strings.finishUpload },
			multiple: true,
		} );
		const previousMediaFrame = window.wp.media.frame;
		let createdAttachmentChain = Promise.resolve();
		frame.uplinkMbeManagerUpload = true;
		frame.uplinkMbeUploadDestinationId = 'collection' === state.filter ? Number( state.collection ) || 0 : 0;
		frame.uplinkMbeFinishSuccessfulUploadBatch = async ( ids ) => {
			const completedIds = ( ids || frame.uplinkMbeCompletedUploadIds || [] ).map( Number ).filter( Boolean );
			if ( ! completedIds.length || 'closed' === frame.uplinkMbeManagerUploadState ) return;

			frame.uplinkMbeManagerUploadState = 'closed';
			frame.close();
			await loadState();
			const attachment = await request( 'uplink_mbe_get_attachment', { attachment_id: completedIds[ 0 ] } );
			openAttachmentModal( attachment, elements.upload );
		};
		frame.uplinkMbeRefreshUploadResults = () => loadState();
		frame.uplinkMbeHandleCreatedAttachment = ( id ) => {
			createdAttachmentChain = createdAttachmentChain.then( async () => {
				const attachmentId = Number( id );
				if ( ! attachmentId ) return;
				const destinationId = Number( frame.uplinkMbeUploadDestinationId ) || 0;

				if ( destinationId ) {
					try {
						await request( 'uplink_mbe_assign_media', {
							media_ids: JSON.stringify( [ attachmentId ] ),
							collection: destinationId,
							mode: 'add',
						} );
					} catch ( error ) {
						showNotice( error.message, 'error' );
					}
				}
				frame.uplinkMbeCompletedUploadIds = frame.uplinkMbeCompletedUploadIds || [];
				if ( ! frame.uplinkMbeCompletedUploadIds.includes( attachmentId ) ) frame.uplinkMbeCompletedUploadIds.push( attachmentId );
				if ( 'function' === typeof frame.uplinkMbeRecordUploadSuccess && await frame.uplinkMbeRecordUploadSuccess( attachmentId ) ) return;

				// Imports from another media tab do not pass through the staged upload
				// workspace. Keep their existing one-file completion behavior.
				const expectedUploads = Number( frame.uplinkMbeStagedUploadCount ) || 1;
				if ( frame.uplinkMbeCompletedUploadIds.length < expectedUploads ) return;
				await frame.uplinkMbeFinishSuccessfulUploadBatch( frame.uplinkMbeCompletedUploadIds );
			} ).catch( ( error ) => showNotice( error.message, 'error' ) );
			return createdAttachmentChain;
		};
		frame.on( 'open', () => {
			// Instant Images hands an imported attachment back through the global
			// media frame. The manager creates its frame directly, so expose it for
			// the lifetime of this upload flow and restore any prior frame on close.
			window.wp.media.frame = frame;
			window.setTimeout( () => frame.content.mode( 'upload' ), 0 );
		} );
		frame.on( 'close', () => {
			if ( window.wp.media.frame === frame ) {
				window.wp.media.frame = previousMediaFrame;
			}
		} );
		frame.on( 'select', async () => {
			const ids = frame.state().get( 'selection' ).map( ( attachment ) => attachment.id );
			if ( ids.length && 'collection' === state.filter && state.collection ) {
				await assignMedia( ids, state.collection, 'add' );
			} else {
				await loadState();
			}
		} );
		frame.open();
		return frame;
	}

	if ( elements.upload && window.wp?.media ) {
		elements.upload.addEventListener( 'click', openUploadFlow );
	}

	let savedGridSize = 240;
	let savedGridRatio = '4/3';
	try {
		savedGridSize = Number( window.localStorage.getItem( 'uplinkMbeGridSize' ) ) || 240;
		savedGridRatio = window.localStorage.getItem( 'uplinkMbeGridRatio' ) || '4/3';
	} catch ( error ) {
		// Use the default size and ratio when browser storage is unavailable.
	}
	try {
		const savedView = window.localStorage.getItem( 'uplinkMbeView' );
		state.view = [ 'grid', 'list', 'masonry' ].includes( savedView ) ? savedView : 'grid';
	} catch ( error ) {
		state.view = 'grid';
	}
	try {
		const savedDisplay = JSON.parse( window.localStorage.getItem( 'uplinkMbeDisplay' ) || '{}' );
		if ( savedDisplay && 'object' === typeof savedDisplay ) {
			Object.keys( state.display ).forEach( ( field ) => {
				if ( 'boolean' === typeof savedDisplay[ field ] ) {
					state.display[ field ] = savedDisplay[ field ];
				}
			} );
		}
	} catch ( error ) {
		// Keep the default display fields when saved preferences are invalid.
	}
	applyGridSize( savedGridSize, false );
	applyGridRatio( savedGridRatio, false );
	applySavedAppearance();
	colorSchemeQuery?.addEventListener?.( 'change', () => {
		if ( 'auto' === savedAppearance && ( ! elements.appearancePanel || elements.appearancePanel.hidden ) ) {
			applySavedAppearance();
		}
	} );
	elements.reorder.addEventListener( 'click', () => {
		if ( mediaOrderSaving || collectionOrderSaving || 'collection' !== state.filter || 'library' !== state.mode ) return;
		state.reorderMode = ! state.reorderMode;
		renderTree();
		state.selected.clear();
		selectionAnchor = -1;
		renderGrid();
		updateSelectionTools();
	} );
	async function initializeLibrary() {
		await loadState();
		if ( Number( config.initialAttachmentId ) ) {
			try {
				const attachment = await request( 'uplink_mbe_get_attachment', { attachment_id: Number( config.initialAttachmentId ) } );
				openAttachmentModal( attachment, null );
			} catch ( error ) {
				showNotice( error.message, 'error' );
			}
		} else if ( 'upload' === config.initialAction ) {
			openUploadFlow();
		} else if ( 'new_collection' === config.initialAction ) {
			openCollectionModal();
		} else if ( 'manage_collections' === config.initialAction ) {
			openCollectionManager();
		}

		library.dataset.ready = 'true';
		document.dispatchEvent( new CustomEvent( 'uplink-mbe:library-ready', {
			detail: {
				library,
				attachmentId: Number( config.initialAttachmentId ) || 0,
				action: config.initialAction || '',
			},
		} ) );
	}
	initializeLibrary();
}() );
