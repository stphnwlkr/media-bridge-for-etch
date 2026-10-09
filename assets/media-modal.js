( function ( $, wp ) {
	'use strict';

	const config = window.uplinkMbeMediaModal;
	if ( ! config || ! wp?.media?.view?.MediaFrame?.Select ) {
		return;
	}

	function patchManagerUploadTransition() {
		const libraryPrototype = wp.media.controller?.Library?.prototype;
		if ( ! libraryPrototype || libraryPrototype.uplinkMbeManagerUploadPatched || 'function' !== typeof libraryPrototype.uploading ) {
			return;
		}
		const originalUploading = libraryPrototype.uploading;
		libraryPrototype.uploading = function ( attachment ) {
			// WordPress normally replaces the Upload view with its native Browse
			// view as soon as a file enters the queue. The manager upload flow owns
			// this transition and closes into its attachment editor when complete.
			if ( this.frame?.uplinkMbeManagerUpload && 'upload' === this.frame.content.mode() ) {
				return;
			}
			return originalUploading.apply( this, arguments );
		};
		libraryPrototype.uplinkMbeManagerUploadPatched = true;
	}

	patchManagerUploadTransition();

	function formatBytes( bytes ) {
		const value = Number( bytes ) || 0;
		if ( value < 1024 ) return `${ value } B`;
		if ( value < 1024 * 1024 ) return `${ ( value / 1024 ).toFixed( 1 ) } KB`;
		if ( value < 1024 * 1024 * 1024 ) return `${ ( value / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
		return `${ ( value / ( 1024 * 1024 * 1024 ) ).toFixed( 1 ) } GB`;
	}

	function formatString( template, values ) {
		let output = template || '';
		values.forEach( ( value, index ) => {
			output = output.replace( `%${ index + 1 }$d`, value ).replace( `%${ index + 1 }$s`, value );
		} );
		return output.replace( '%d', values[ 0 ] ?? '' ).replace( '%s', values[ 0 ] ?? '' );
	}

	function currentFeaturedImageId() {
		try {
			const editorStore = wp?.data?.select?.( 'core/editor' );
			if ( 'function' === typeof editorStore?.getEditedPostAttribute ) {
				return Number( editorStore.getEditedPostAttribute( 'featured_media' ) ) || 0;
			}
		} catch ( error ) {
			// The data store is not present in every WordPress editor context.
		}
		return null;
	}

	function contextualAttachmentId( frame ) {
		const options = frame?.options || {};
		const directCandidates = [
			options.attachment,
			options.attachmentId,
			options.attachment_id,
			options.model,
			options.editImage,
		];
		for ( const candidate of directCandidates ) {
			const id = Number( candidate?.get?.( 'id' ) || candidate?.id || candidate );
			if ( id ) return id;
		}

		const editCollection = options.edit;
		if ( 1 === Number( editCollection?.length ) ) {
			const attachment = editCollection.first?.() || editCollection.models?.[ 0 ];
			return Number( attachment?.get?.( 'id' ) || attachment?.id ) || 0;
		}

		return 0;
	}

	function normalizeAttachmentIdentity( attachment, fallbackId = 0 ) {
		const id = Number( attachment?.get?.( 'id' ) || attachment?.id || fallbackId );
		if ( id ) {
			attachment.set( 'id', id );
			attachment.id = id;
			attachment.attributes.id = id;
		}
		return attachment;
	}

	function hydrateAttachment( attachment, fallbackId = 0 ) {
		return new Promise( ( resolve, reject ) => {
			attachment.fetch().done( () => {
				// Some WordPress attachment responses populate Backbone's model ID
				// without mirroring it into attributes. ACF and Meta Box render from
				// attachment.attributes and require the ID in both places.
				resolve( normalizeAttachmentIdentity( attachment, fallbackId ) );
			} ).fail( reject );
		} );
	}

	function selectionAttachment( attachment, id ) {
		return normalizeAttachmentIdentity(
			new wp.media.model.Attachment( {
				...attachment.toJSON(),
				id: Number( id ),
			} ),
			id
		);
	}

	function openAttachmentDetails( attachmentId, mediaType = '' ) {
		const attachment = wp.media.attachment( attachmentId );
		const frame = wp.media( {
			title: config.strings.attachmentDetails,
			multiple: false,
			library: mediaType ? { type: mediaType } : {},
			attachment: attachmentId,
		} );
		const markMetadataOnly = () => frame.$el.closest( '.media-modal' ).addClass( 'uplink-mbe-metadata-only' );
		const reveal = () => {
			markMetadataOnly();
			const view = frame.uplinkMbeCollectionsView;
			if ( view ) view.revealExternalAttachment( attachmentId, true );
		};
		frame.on( 'open', function () {
			this.state().get( 'selection' )?.reset( [ attachment ] );
			window.requestAnimationFrame( reveal );
		} );
		frame.on( 'content:render:uplink-mbe-collections', function () {
			window.requestAnimationFrame( reveal );
		} );
		frame.open();
	}

	function renderUploadWorkspace( frame ) {
		window.uplinkMbeUploadWorkspace.render( frame, config, request );
	}
	function restoreCimoOptimization( frame ) {
		window.uplinkMbeUploadWorkspace.restore( frame );
	}

	async function request( action, data ) {
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
			throw new Error( payload.data?.message || config.strings.error );
		}
		return payload.data || {};
	}

	function filePlaceholder( media ) {
		const group = ( media.mime || '' ).split( '/' )[ 0 ];
		const extension = ( media.filename || '' ).split( '.' ).pop() || media.fileType || 'file';
		const artwork = {
			video: '<rect x="9" y="14" width="35" height="36" rx="4"/><path d="m44 27 11-7v24l-11-7z"/><path d="m25 24 12 8-12 8z"/>',
			audio: '<path d="M24 45V17l27-5v28"/><circle cx="17" cy="46" r="7"/><circle cx="44" cy="41" r="7"/><path d="M24 24l27-5"/>',
			application: '<path d="M17 7h20l12 12v38H17z"/><path d="M37 7v12h12M24 31h18M24 39h18M24 47h12"/>',
			text: '<path d="M17 7h20l12 12v38H17z"/><path d="M37 7v12h12M24 29h18M24 37h18M24 45h14"/>',
		};
		return $( '<span class="uplink-mbe-modal-file-placeholder" />' ).append(
			$( '<svg viewBox="0 0 64 64" aria-hidden="true" />' ).html( artwork[ group ] || artwork.application ),
			$( '<strong />' ).text( extension )
		);
	}

	function applyFrameTheme( frame ) {
		const apply = () => {
			const appearance = [ 'light', 'dark', 'auto' ].includes( config.appearance ) ? config.appearance : 'auto';
			const $modal = frame.$el?.closest( '.media-modal' );
			if ( ! $modal?.length ) {
				return false;
			}

			$modal
				.removeClass( 'uplink-mbe-theme-light uplink-mbe-theme-dark uplink-mbe-theme-auto' )
				.addClass( `uplink-mbe-frame uplink-mbe-theme-${ appearance }` )
				.toggleClass( 'uplink-mbe-default-screen', Boolean( config.defaultMediaScreen ) );
			return true;
		};

		if ( ! apply() ) {
			window.requestAnimationFrame( apply );
		}
	}

	async function refreshAfterUpload( frame, attachment ) {
		const id = Number( attachment.get( 'id' ) || attachment.id );
		if ( ! id ) {
			return;
		}
		if ( 'function' === typeof frame.uplinkMbeHandleCreatedAttachment ) {
			await frame.uplinkMbeHandleCreatedAttachment( id );
			return;
		}

		const hasUploadDestination = undefined !== frame.uplinkMbeUploadDestinationId;
		const collection = hasUploadDestination ? Number( frame.uplinkMbeUploadDestinationId ) || 0 : Number( frame.uplinkMbeCollectionId ) || 0;
		const shouldAssign = hasUploadDestination ? Boolean( collection ) : 'collection' === frame.uplinkMbeCollectionFilter && collection;
		try {
			if ( shouldAssign ) {
				await request( 'uplink_mbe_assign_media', {
					media_ids: JSON.stringify( [ id ] ),
					collection,
					mode: 'add',
				} );
			}
		} catch ( error ) {
			frame.uplinkMbeUploadError = error.message;
		} finally {
			const currentView = frame.uplinkMbeCollectionsView;
			if ( currentView ) {
				currentView.resetAndFetch();
			}
		}
	}

	function watchUpload( frame, attachment ) {
		let handled = false;
		const complete = () => {
			if ( handled || attachment.get( 'uploading' ) || ! ( attachment.get( 'id' ) || attachment.id ) ) {
				return;
			}
			handled = true;
			attachment.off( 'change:uploading change:id', complete );
			refreshAfterUpload( frame, attachment );
		};

		attachment.on( 'change:uploading change:id', complete );
		complete();
	}

	const CollectionsView = wp.media.View.extend( {
		className: 'uplink-mbe-modal-browser',

			events: {
			'click .uplink-mbe-modal-collection-toggle': 'toggleCollection',
			'click .uplink-mbe-modal-collection': 'selectCollection',
			'click .uplink-mbe-modal-view-button': 'changeView',
			'click .uplink-mbe-modal-select': 'toggleAttachment',
			'click .uplink-mbe-modal-item': 'inspectAttachment',
			'click .uplink-mbe-modal-inspector-close': 'closeInspector',
			'click .uplink-mbe-modal-inspector-previous': 'previousInspectorItem',
			'click .uplink-mbe-modal-inspector-next': 'nextInspectorItem',
			'click .uplink-mbe-modal-inspector-save': 'saveInspector',
			'click .uplink-mbe-modal-inspector-edit-image': 'editInspectedImage',
			'click .uplink-mbe-modal-inspector-insert': 'insertInspected',
			'click .uplink-mbe-modal-inspector-tab': 'switchInspectorTab',
			'keydown .uplink-mbe-modal-inspector-tab': 'navigateInspectorTabs',
			'input .uplink-mbe-modal-inspector-title, .uplink-mbe-modal-inspector-alt, .uplink-mbe-modal-inspector-caption, .uplink-mbe-modal-inspector-description': 'scheduleInspectorSave',
			'change .uplink-mbe-modal-inspector-title, .uplink-mbe-modal-inspector-alt, .uplink-mbe-modal-inspector-caption, .uplink-mbe-modal-inspector-description': 'saveInspectorChange',
			'change .uplink-mbe-modal-inspector-decorative': 'toggleDecorative',
			'submit .uplink-mbe-modal-search': 'searchMedia',
			'input #uplink-mbe-modal-search-input': 'scheduleSearch',
			'compositionstart #uplink-mbe-modal-search-input': 'startSearchComposition',
			'compositionend #uplink-mbe-modal-search-input': 'endSearchComposition',
			'search #uplink-mbe-modal-search-input': 'searchMedia',
			'change .uplink-mbe-modal-type-filter': 'changeTypeFilter',
			'change [data-uplink-mbe-filter]': 'changeAdvancedFilter',
			'click .uplink-mbe-modal-filter-toggle': 'toggleFilterPanel',
			'click .uplink-mbe-modal-filter-close': 'closeFilterPanel',
			'click .uplink-mbe-modal-clear-filters': 'clearFilters',
		},

		initialize( options ) {
			this.controller = options.controller;
			this.selection = this.controller.state().get( 'selection' );
			const library = this.controller.state().get( 'library' );
			// ACF customizes the native attachment browser through its toolbar.
			// The enhanced browser owns its filters, but exposing the same minimal
			// interface lets ACF finish opening image and file field frames.
			this.toolbar = { get: () => null };
			this.filter = this.controller.uplinkMbeCollectionFilter || 'all';
			this.collectionId = Number( this.controller.uplinkMbeCollectionId ) || 0;
			// Gutenberg's specialized media pickers (including Site Icon) expect the
			// active content view to expose WordPress's full attachments collection.
			// Keep that native contract instead of using a partial refresh shim.
			this.collection = library || wp.media.query( this.controller.options?.library || {} );
			const nativeRequery = 'function' === typeof this.collection._requery
				? this.collection._requery.bind( this.collection )
				: null;
			this.collection._requery = ( ...args ) => {
				const result = nativeRequery ? nativeRequery( ...args ) : this.collection.more();
				this.resetAndFetch();
				return result;
			};
			this.controller.uplinkMbeCollectionsView = this;
			this.search = '';
			this.searchTimer = null;
			this.searchComposing = false;
			this.page = 1;
			this.pages = 1;
			this.media = [];
			this.collections = [];
			this.counts = { all: 0, uncategorized: 0 };
			this.mimeTypes = [];
			this.uploaders = [];
			this.mimeSubtype = '';
			this.uploadedBy = '';
			this.attachmentStatus = '';
			this.optimizationStatus = '';
			this.uploadedFrom = '';
			this.uploadedTo = '';
			this.widthMin = '';
			this.widthMax = '';
			this.heightMin = '';
			this.heightMax = '';
			this.fileSizeMin = '';
			this.fileSizeMax = '';
			this.missingAlt = false;
			this.collapsedCollections = new Set();
			this.loading = false;
			this.requestId = 0;
			this.inspectedId = 0;
			this.inspectorTab = 'details';
			this.inspectorDirty = false;
			this.inspectorSaveTimer = null;
			this.inspectorSaveChain = Promise.resolve( true );
			this.inspectorSavesPending = 0;
			this.pendingSelectionId = 0;
			this.view = 'grid';
			try {
				const savedView = window.localStorage.getItem( 'uplinkMbeView' );
				if ( [ 'list', 'grid', 'masonry' ].includes( savedView ) ) {
					this.view = savedView;
				}
			} catch ( error ) {
				// Storage may be unavailable in privacy-restricted browsers.
			}

			const type = library?.props?.get( 'type' );
			const acfFieldKey = this.controller.acf?.get?.( 'field' ) || '';
			const acfFieldType = acfFieldKey
				? $( '.acf-field[data-key]' ).filter( ( index, field ) => $( field ).data( 'key' ) === acfFieldKey ).first().data( 'type' )
				: '';
			const requestedType = type
				|| this.controller.acf?.get?.( 'type' )
				|| ( 'image' === acfFieldType ? 'image' : '' )
				|| this.controller.options?.library?.type
				|| '';
			const libraryMediaType = 'string' === typeof requestedType ? requestedType.split( '/' )[ 0 ] : '';
			this.requiredMediaType = 'featured-image' === this.controller.state()?.get( 'id' )
				? 'image'
				: ( [ 'image', 'audio', 'video', 'application' ].includes( libraryMediaType ) ? libraryMediaType : '' );
			this.mediaType = this.requiredMediaType;
			if ( this.selection ) {
				this.listenTo( this.selection, 'add remove', this.updateSelected );
				this.listenTo( this.selection, 'reset', this.handleExternalSelection );
			}
		},

		render() {
			applyFrameTheme( this.controller );
			this.controller.$el.closest( '.media-modal' ).addClass( 'uplink-mbe-collections-active' );
			this.$el.empty().attr( {
				role: 'region',
				'aria-labelledby': 'uplink-mbe-modal-heading',
			} ).addClass( `uplink-mbe-theme-${ config.appearance || 'auto' }` );

			const header = $( '<header class="uplink-mbe-modal-header" />' );
			header.append( $( '<div />' ).append(
				$( '<h2 id="uplink-mbe-modal-heading" />' ).text( config.strings.heading ),
				$( '<p />' ).text( config.strings.instructions )
			) );
			const tools = $( '<div class="uplink-mbe-modal-tools" />' );
			const filters = $( '<div class="uplink-mbe-modal-filters" />' );
			this.$filterToggle = $( '<button type="button" class="button uplink-mbe-modal-filter-toggle" aria-expanded="false" aria-controls="uplink-mbe-modal-filter-panel" />' )
				.attr( 'aria-label', config.strings.filter )
				.append( $( '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8z"></path></svg>' ), $( '<span class="uplink-mbe-modal-filter-count" hidden />' ) );
			this.$filterPanel = $( '<div id="uplink-mbe-modal-filter-panel" class="uplink-mbe-modal-filter-panel" hidden />' );
			this.$clearFilters = $( '<button type="button" class="button uplink-mbe-modal-clear-filters" />' ).text( config.strings.clearFilters );
			this.$filterPanel.append( $( '<div class="uplink-mbe-modal-filter-heading" />' ).append( $( '<h3 />' ).text( config.strings.filter ), $( '<div class="uplink-mbe-modal-filter-heading-actions" />' ).append( this.$clearFilters, $( '<button type="button" class="uplink-mbe-modal-filter-close" aria-label="Close filters">×</button>' ) ) ) );
			const filterGrid = $( '<div class="uplink-mbe-modal-filter-grid" />' );
			const filterField = ( label, control ) => $( '<label />' ).append( $( '<span />' ).text( label ), control );
			const rangeField = ( label, key ) => {
				const minimum = $( '<input type="number" min="0" />' ).attr( { placeholder: config.strings.minimum, 'aria-label': `${ label }: ${ config.strings.minimum }`, 'data-uplink-mbe-filter': `${ key }Min` } );
				const maximum = $( '<input type="number" min="0" />' ).attr( { placeholder: config.strings.maximum, 'aria-label': `${ label }: ${ config.strings.maximum }`, 'data-uplink-mbe-filter': `${ key }Max` } );
				if ( 'fileSize' === key ) minimum.add( maximum ).attr( 'step', '0.1' );
				this[ `$${ key }MinFilter` ] = minimum;
				this[ `$${ key }MaxFilter` ] = maximum;
				return $( '<fieldset class="uplink-mbe-modal-filter-range" />' ).append( $( '<legend />' ).text( label ), $( '<div />' ).append( minimum, maximum ) );
			};
			this.$typeFilter = $( '<select class="uplink-mbe-modal-type-filter" />' ).attr( 'aria-label', config.strings.filterByType );
			[
				[ '', config.strings.all ],
				[ 'image', config.strings.images ],
				[ 'audio', config.strings.audio ],
				[ 'video', config.strings.video ],
				[ 'application', config.strings.documents ],
			].forEach( ( option ) => this.$typeFilter.append( $( '<option />' ).val( option[ 0 ] ).text( option[ 1 ] ) ) );
			this.$typeFilter.prop( 'disabled', Boolean( this.requiredMediaType ) );
			this.$mimeFilter = $( '<select data-uplink-mbe-filter="mimeSubtype" />' );
			this.$uploaderFilter = $( '<select data-uplink-mbe-filter="uploadedBy" />' );
			this.$attachmentStatusFilter = $( '<select data-uplink-mbe-filter="attachmentStatus" />' ).append( $( '<option />' ).val( '' ).text( config.strings.all ), $( '<option />' ).val( 'attached' ).text( config.strings.attached ), $( '<option />' ).val( 'unattached' ).text( config.strings.unattached ) );
			this.$optimizationStatusFilter = config.optimizationAvailable ? $( '<select data-uplink-mbe-filter="optimizationStatus" />' ).append( $( '<option />' ).val( '' ).text( config.strings.all ), $( '<option />' ).val( 'optimized' ).text( config.strings.cimoOptimized ), $( '<option />' ).val( 'not_optimized' ).text( config.strings.cimoNotOptimized ) ) : null;
			this.$uploadedFromFilter = $( '<input type="date" data-uplink-mbe-filter="uploadedFrom" />' );
			this.$uploadedToFilter = $( '<input type="date" data-uplink-mbe-filter="uploadedTo" />' );
			this.$missingAltFilter = $( '<input type="checkbox" data-uplink-mbe-filter="missingAlt" />' );
			filterGrid.append(
				filterField( config.strings.mediaType, this.$typeFilter ),
				filterField( config.strings.mimeSubtype, this.$mimeFilter ),
				filterField( config.strings.uploadedBy, this.$uploaderFilter ),
				filterField( config.strings.attachmentStatus, this.$attachmentStatusFilter ),
				...( this.$optimizationStatusFilter ? [ filterField( config.strings.optimizationStatus, this.$optimizationStatusFilter ) ] : [] ),
				filterField( config.strings.uploadedFrom, this.$uploadedFromFilter ),
				filterField( config.strings.uploadedTo, this.$uploadedToFilter ),
				rangeField( config.strings.widthPixels, 'width' ),
				rangeField( config.strings.heightPixels, 'height' ),
				rangeField( config.strings.fileSizeMb, 'fileSize' ),
				$( '<label class="uplink-mbe-modal-filter-check" />' ).append( $( '<span />' ).text( config.strings.missingAltText ), this.$missingAltFilter )
			);
			this.$filterPanel.append( filterGrid );
			filters.append( this.$filterToggle, this.$filterPanel );

			const form = $( '<form class="uplink-mbe-modal-search" role="search" />' );
			form.append(
				$( '<label class="screen-reader-text" for="uplink-mbe-modal-search-input" />' ).text( config.strings.searchLabel ),
				$( '<input id="uplink-mbe-modal-search-input" type="search" />' ).attr( {
					placeholder: config.strings.searchPlaceholder,
					title: config.strings.searchWildcardHelp,
				} ),
				$( '<button type="submit" class="button uplink-mbe-modal-search-button" />' )
					.attr( { 'aria-label': config.strings.search, title: config.strings.search } )
					.html( '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>' )
			);
			const listIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"></path><circle cx="4" cy="6" r="1"></circle><circle cx="4" cy="12" r="1"></circle><circle cx="4" cy="18" r="1"></circle></svg>';
			const gridIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>';
			const masonryIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="5" rx="1"></rect><rect x="14" y="3" width="7" height="9" rx="1"></rect><rect x="3" y="11" width="8" height="10" rx="1"></rect><rect x="14" y="15" width="7" height="6" rx="1"></rect></svg>';
			this.$viewControls = $( '<div class="uplink-mbe-modal-view-controls" role="group" />' ).attr( 'aria-label', config.strings.viewMode );
			[
				[ 'list', config.strings.listView, listIcon ],
				[ 'grid', config.strings.gridView, gridIcon ],
				[ 'masonry', config.strings.masonryView, masonryIcon ],
			].forEach( ( [ view, label, icon ] ) => {
				this.$viewControls.append(
					$( '<button type="button" class="button uplink-mbe-modal-view-button" />' )
						.attr( { 'data-view': view, 'aria-label': label, title: label } )
						.html( icon )
				);
			} );
			tools.append( form, filters, this.$viewControls );
			header.append( tools );

			this.$collections = $( '<nav class="uplink-mbe-modal-collections" />' ).attr( 'aria-label', config.strings.tab );
			this.$grid = $( '<div class="uplink-mbe-modal-grid" aria-live="polite" />' );
			this.renderViewControls();
			this.$loading = $( '<p class="uplink-mbe-modal-scroll-status" aria-live="polite" />' );
			this.$results = $( '<main class="uplink-mbe-modal-results" />' ).append( this.$grid, this.$loading );
			this.$results.on( 'scroll.uplinkMbe', () => this.maybeLoadMore() );
			this.$inspector = $( '<aside id="uplink-mbe-modal-asset-inspector" class="uplink-mbe-modal-inspector" aria-labelledby="uplink-mbe-modal-inspector-heading" hidden />' );
			this.$inspectorHeader = $( '<header class="uplink-mbe-modal-inspector-header" />' ).append(
				$( '<h3 id="uplink-mbe-modal-inspector-heading" />' ).text( config.strings.attachmentDetails ),
				$( '<span class="uplink-mbe-modal-inspector-position" />' ),
				$( '<button type="button" class="uplink-mbe-modal-inspector-nav uplink-mbe-modal-inspector-previous" />' ).attr( 'aria-label', config.strings.previousAttachment ).text( '‹' ),
				$( '<button type="button" class="uplink-mbe-modal-inspector-nav uplink-mbe-modal-inspector-next" />' ).attr( 'aria-label', config.strings.nextAttachment ).text( '›' ),
				$( '<button type="button" class="uplink-mbe-modal-inspector-nav uplink-mbe-modal-inspector-close" />' ).attr( 'aria-label', config.strings.close ).text( '×' )
			);
			this.$inspectorPreview = $( '<div class="uplink-mbe-modal-inspector-preview" />' );
			this.$inspectorFilename = $( '<strong class="uplink-mbe-modal-inspector-filename" />' );
			this.$inspectorTitle = $( '<input type="text" class="uplink-mbe-modal-inspector-title" />' );
			this.$inspectorAlt = $( '<textarea rows="3" class="uplink-mbe-modal-inspector-alt" />' );
			this.$inspectorDecorative = $( '<input type="checkbox" class="uplink-mbe-modal-inspector-decorative" />' );
			this.$inspectorCaption = $( '<textarea rows="3" class="uplink-mbe-modal-inspector-caption" />' );
			this.$inspectorDescription = $( '<textarea rows="4" class="uplink-mbe-modal-inspector-description" />' );
			this.$inspectorNotice = $( '<p class="uplink-mbe-modal-inspector-notice screen-reader-text" aria-live="polite" />' );
			this.$inspectorReplace = $( '<button type="button" class="button" />' ).text( config.strings.replaceAction ).attr( { title: window.uplinkMbeReplacement?.strings.title || 'Replace file', 'aria-label': window.uplinkMbeReplacement?.strings.title || 'Replace file' } ).prepend( config.actionIcons.replace );
			this.$inspectorReplace.on( 'click', async () => {
				if ( ! await this.persistInspectorChanges() ) return;
				const id = this.inspectedId;
				const media = this.media.find( ( item ) => Number( item.id ) === id );
				if ( media && await window.uplinkMbeReplaceFile( media ) ) {
					const data = await request( 'uplink_mbe_get_attachment', { attachment_id: id } );
					data.url += `${ data.url.includes( '?' ) ? '&' : '?' }mbe=${ Date.now() }`;
					Object.assign( media, data );
					this.renderMedia(); this.showInspector( id );
				}
			} );
			this.$inspectorEditImage = $( '<button type="button" class="button uplink-mbe-modal-inspector-edit-image" hidden />' ).attr( { title: config.strings.editImage, 'aria-label': config.strings.editImage } ).append(
				$( '<span class="dashicons dashicons-wordpress-alt" aria-hidden="true" />' ),
				$( '<span />' ).text( config.strings.editAction )
			);
			this.$inspectorInsert = $( '<button type="button" class="button button-primary uplink-mbe-modal-inspector-insert" />' ).text( this.actionLabel() );
			const field = ( label, control ) => $( '<label class="uplink-mbe-modal-inspector-field" />' ).append( $( '<span />' ).text( label ), control );
			this.$inspectorTabs = $( '<div class="uplink-mbe-modal-inspector-tabs" role="tablist" />' ).attr( 'aria-label', config.strings.attachmentMetadata );
			this.$inspectorDetailsTab = $( '<button type="button" id="uplink-mbe-modal-inspector-details-tab" class="uplink-mbe-modal-inspector-tab" role="tab" data-tab="details" aria-controls="uplink-mbe-modal-inspector-details-panel" />' ).text( config.strings.details );
			this.$inspectorFileTab = $( '<button type="button" id="uplink-mbe-modal-inspector-file-tab" class="uplink-mbe-modal-inspector-tab" role="tab" data-tab="file" aria-controls="uplink-mbe-modal-inspector-file-panel" />' ).text( config.strings.fileInfo );
			this.$inspectorExifTab = $( '<button type="button" id="uplink-mbe-modal-inspector-exif-tab" class="uplink-mbe-modal-inspector-tab" role="tab" data-tab="exif" aria-controls="uplink-mbe-modal-inspector-exif-panel" />' ).text( config.strings.exif );
			this.$inspectorOptimizationTab = $( '<button type="button" id="uplink-mbe-modal-inspector-optimization-tab" class="uplink-mbe-modal-inspector-tab" role="tab" data-tab="optimization" aria-controls="uplink-mbe-modal-inspector-optimization-panel" />' ).text( config.strings.optimization );
			this.$inspectorTabs.append( this.$inspectorDetailsTab, this.$inspectorFileTab, this.$inspectorExifTab, this.$inspectorOptimizationTab );
			this.$inspectorDetailsPanel = $( '<div id="uplink-mbe-modal-inspector-details-panel" class="uplink-mbe-modal-inspector-panel uplink-mbe-modal-inspector-details-panel" role="tabpanel" aria-labelledby="uplink-mbe-modal-inspector-details-tab" />' ).append(
				$( '<div class="uplink-mbe-modal-inspector-file-name" />' ).append(
					$( '<span />' ).text( config.strings.fileName ),
					this.$inspectorFilename
				),
				field( config.strings.title, this.$inspectorTitle ),
				field( config.strings.altText, this.$inspectorAlt ),
				$( '<label class="uplink-mbe-modal-inspector-check" />' ).append( this.$inspectorDecorative, $( '<span />' ).text( config.strings.decorative ) ),
				field( config.strings.caption, this.$inspectorCaption ),
				field( config.strings.description, this.$inspectorDescription )
			);
			this.$inspectorFileList = $( '<dl class="uplink-mbe-modal-inspector-data-list" />' );
			this.$inspectorFilePanel = $( '<div id="uplink-mbe-modal-inspector-file-panel" class="uplink-mbe-modal-inspector-panel uplink-mbe-modal-inspector-metadata-panel" role="tabpanel" aria-labelledby="uplink-mbe-modal-inspector-file-tab" hidden />' ).append(
				this.$inspectorFileList
			);
			this.$inspectorExifList = $( '<dl class="uplink-mbe-modal-inspector-data-list" />' );
			this.$inspectorExifPanel = $( '<div id="uplink-mbe-modal-inspector-exif-panel" class="uplink-mbe-modal-inspector-panel" role="tabpanel" aria-labelledby="uplink-mbe-modal-inspector-exif-tab" hidden />' ).append( this.$inspectorExifList );
			this.$inspectorOptimizationList = $( '<div class="uplink-mbe-modal-optimization-groups" />' );
			this.$inspectorOptimizationPanel = $( '<div id="uplink-mbe-modal-inspector-optimization-panel" class="uplink-mbe-modal-inspector-panel uplink-mbe-modal-inspector-metadata-panel" role="tabpanel" aria-labelledby="uplink-mbe-modal-inspector-optimization-tab" hidden />' ).append( this.$inspectorOptimizationList );
			this.$inspector.append(
				this.$inspectorHeader,
				this.$inspectorPreview,
				$( '<div class="uplink-mbe-modal-inspector-body" />' ).append(
					this.$inspectorTabs,
					this.$inspectorDetailsPanel,
					this.$inspectorFilePanel,
					this.$inspectorExifPanel,
					this.$inspectorOptimizationPanel,
					this.$inspectorNotice,
					$( '<div class="uplink-mbe-modal-inspector-actions" />' ).append(
						this.$inspectorEditImage,
					this.$inspectorReplace,
						$( '<button type="button" class="button uplink-mbe-modal-inspector-save" />' ).text( config.strings.saveAction ).attr( { title: config.strings.saveChanges, 'aria-label': config.strings.saveChanges } ).prepend( config.actionIcons.save ),
						this.$inspectorInsert
					)
				)
			);
			this.$content = $( '<div class="uplink-mbe-modal-content" />' ).append(
				$( '<aside class="uplink-mbe-modal-sidebar" />' ).append( this.$collections ),
				this.$results,
				this.$inspector
			);

			this.$el.append( header, this.$content );
			this.setInspectorTab( 'details' );
			const isFeaturedImage = 'featured-image' === this.controller.state()?.get( 'id' );
			let selectedAttachment = this.selection?.first();
			let selectedId = Number( selectedAttachment?.get( 'id' ) || selectedAttachment?.id || this.pendingSelectionId );
			if ( isFeaturedImage ) {
				const liveFeaturedImageId = currentFeaturedImageId();
				if ( null !== liveFeaturedImageId ) {
					selectedId = liveFeaturedImageId;
					if ( this.selection ) {
						this.selection.reset( selectedId ? [ wp.media.attachment( selectedId ) ] : [], { silent: true } );
					}
				} else if ( ! selectedId ) {
					selectedId = Number( wp.media.view.settings?.post?.featuredImageId );
					if ( selectedId && this.selection ) {
						selectedAttachment = wp.media.attachment( selectedId );
						this.selection.reset( [ selectedAttachment ], { silent: true } );
					}
				}
			}
			if ( ! selectedId ) {
				selectedId = contextualAttachmentId( this.controller );
				if ( selectedId && this.selection ) {
					selectedAttachment = wp.media.attachment( selectedId );
					this.selection.reset( [ selectedAttachment ], { silent: true } );
				}
			}
			this.pendingSelectionId = 0;
			this.updateSelected();
			if ( selectedId ) {
				this.revealExternalAttachment( selectedId, false );
			} else {
				this.fetchMedia( false );
			}
			return this;
		},

		remove() {
			window.clearTimeout( this.searchTimer );
			window.clearTimeout( this.inspectorSaveTimer );
			if ( this.inspectorDirty ) this.persistInspectorChanges();
			this.controller.$el.closest( '.media-modal' ).removeClass( 'uplink-mbe-collections-active uplink-mbe-has-selection uplink-mbe-has-inspector uplink-mbe-metadata-only' );
			return wp.media.View.prototype.remove.apply( this, arguments );
		},

		actionLabel() {
			const label = this.controller.$el
				.find( '.media-frame-toolbar .media-button-select, .media-frame-toolbar .media-button-insert' )
				.first()
				.text()
				.trim();
			const isFeaturedImage = /featured image/i.test( label );
			const isReplacingFeaturedImage = isFeaturedImage && this.selection?.length && ! this.selection.get( this.inspectedId );
			if ( isReplacingFeaturedImage ) return config.strings.replaceFeaturedImage;
			return label || config.strings.insertMedia;
		},

		changeView( event ) {
			event.preventDefault();
			const view = event.currentTarget.dataset.view;
			if ( ! [ 'list', 'grid', 'masonry' ].includes( view ) ) return;
			this.view = view;
			try {
				window.localStorage.setItem( 'uplinkMbeView', view );
			} catch ( error ) {
				// The view still changes for this session when storage is unavailable.
			}
			this.renderViewControls();
		},

		renderViewControls() {
			if ( this.$grid ) {
				this.$grid
					.toggleClass( 'is-list-view', 'list' === this.view )
					.toggleClass( 'is-masonry-view', 'masonry' === this.view );
			}
			if ( this.$viewControls ) {
				this.$viewControls.find( '.uplink-mbe-modal-view-button' ).each( ( index, button ) => {
					const active = button.dataset.view === this.view;
					$( button ).toggleClass( 'is-active', active ).attr( 'aria-pressed', active ? 'true' : 'false' );
				} );
			}
		},

		setInspectorTab( requestedTab, moveFocus = false ) {
			const exifAvailable = ! this.$inspectorExifTab.prop( 'hidden' );
			const optimizationAvailable = ! this.$inspectorOptimizationTab.prop( 'hidden' );
			const activeTab = 'file' === requestedTab
				|| ( 'exif' === requestedTab && exifAvailable )
				|| ( 'optimization' === requestedTab && optimizationAvailable )
				? requestedTab
				: 'details';
			const tabs = [
				[ 'details', this.$inspectorDetailsTab, this.$inspectorDetailsPanel ],
				[ 'file', this.$inspectorFileTab, this.$inspectorFilePanel ],
				[ 'exif', this.$inspectorExifTab, this.$inspectorExifPanel ],
				[ 'optimization', this.$inspectorOptimizationTab, this.$inspectorOptimizationPanel ],
			];

			tabs.forEach( ( [ name, $tab, $panel ] ) => {
				const selected = name === activeTab;
				$tab.attr( {
					'aria-selected': selected ? 'true' : 'false',
					tabindex: selected ? '0' : '-1',
				} );
				$panel.prop( 'hidden', ! selected );
			} );

			this.inspectorTab = activeTab;
			if ( moveFocus ) {
				const active = tabs.find( ( [ name ] ) => name === activeTab );
				active?.[ 1 ]?.trigger( 'focus' );
			}
		},

		switchInspectorTab( event ) {
			event.preventDefault();
			this.setInspectorTab( event.currentTarget.dataset.tab, true );
		},

		navigateInspectorTabs( event ) {
			if ( ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) return;
			const tabs = this.$inspectorTabs.find( '.uplink-mbe-modal-inspector-tab' ).filter( ':visible' ).toArray();
			const current = tabs.indexOf( event.currentTarget );
			if ( current < 0 ) return;

			let next = current;
			if ( 'Home' === event.key ) next = 0;
			if ( 'End' === event.key ) next = tabs.length - 1;
			if ( 'ArrowLeft' === event.key ) next = ( current - 1 + tabs.length ) % tabs.length;
			if ( 'ArrowRight' === event.key ) next = ( current + 1 ) % tabs.length;
			event.preventDefault();
			this.setInspectorTab( tabs[ next ].dataset.tab, true );
		},

		async fetchMedia( append ) {
			if ( this.loading && append ) {
				return;
			}
			this.loading = true;
			const requestId = ++this.requestId;
			if ( ! append ) {
				this.$grid.empty().append( $( '<p class="uplink-mbe-modal-message" />' ).text( config.strings.loading ) );
			}
			this.$loading.text( append ? config.strings.loading : '' );

			try {
				const data = await request( 'uplink_mbe_native_state', {
					filter: this.filter,
					collection: this.collectionId,
					page: this.page,
					search: this.search,
					type: this.mediaType,
					mime_subtype: this.mimeSubtype,
					uploaded_by: this.uploadedBy,
					attachment_status: this.attachmentStatus,
					optimization_status: this.optimizationStatus,
					uploaded_from: this.uploadedFrom,
					uploaded_to: this.uploadedTo,
					width_min: this.widthMin,
					width_max: this.widthMax,
					height_min: this.heightMin,
					height_max: this.heightMax,
					file_size_min: this.fileSizeMin,
					file_size_max: this.fileSizeMax,
					missing_alt: this.missingAlt ? '1' : '',
				} );
				if ( requestId !== this.requestId ) {
					return;
				}
				this.collections = data.collections || [];
				this.counts = data.counts || this.counts;
				this.mimeTypes = data.mimeTypes || [];
				this.uploaders = data.uploaders || [];
				this.pages = data.pagination?.pages || 1;
				this.media = append ? this.media.concat( data.media || [] ) : ( data.media || [] );
				this.renderCollections();
				this.renderFilters();
				this.renderMedia();
				if ( this.controller.uplinkMbeUploadError ) {
					window.uplinkMbeToast( this.controller.uplinkMbeUploadError, 'error' );
					this.controller.uplinkMbeUploadError = '';
				}
			} catch ( error ) {
				this.$grid.empty().append( $( '<p class="uplink-mbe-modal-message is-error" />' ).text( error.message ) );
			} finally {
				if ( requestId !== this.requestId ) {
					return;
				}
				this.loading = false;
				this.$loading.text( '' );
				window.requestAnimationFrame( () => this.maybeLoadMore() );
			}
		},

		renderFilters() {
			this.$typeFilter.val( this.mediaType );
			this.$mimeFilter.empty().append( $( '<option />' ).val( '' ).text( config.strings.all ) );
			this.mimeTypes.filter( ( item ) => ! this.mediaType || item.type === this.mediaType ).forEach( ( item ) => this.$mimeFilter.append( $( '<option />' ).val( item.value ).text( item.label ) ) );
			this.$mimeFilter.val( this.mimeSubtype );
			this.$uploaderFilter.empty().append( $( '<option />' ).val( '' ).text( config.strings.all ) );
			this.uploaders.forEach( ( item ) => this.$uploaderFilter.append( $( '<option />' ).val( String( item.value ) ).text( item.label ) ) );
			this.$uploaderFilter.val( String( this.uploadedBy ) );
			this.$attachmentStatusFilter.val( this.attachmentStatus );
			if ( this.$optimizationStatusFilter ) this.$optimizationStatusFilter.val( this.optimizationStatus );
			this.$uploadedFromFilter.val( this.uploadedFrom );
			this.$uploadedToFilter.val( this.uploadedTo );
			this.$widthMinFilter.val( this.widthMin );
			this.$widthMaxFilter.val( this.widthMax );
			this.$heightMinFilter.val( this.heightMin );
			this.$heightMaxFilter.val( this.heightMax );
			this.$fileSizeMinFilter.val( this.fileSizeMin );
			this.$fileSizeMaxFilter.val( this.fileSizeMax );
			this.$missingAltFilter.prop( 'checked', this.missingAlt );
			const filterCount = this.activeFilterCount();
			this.$clearFilters.prop( 'disabled', ! filterCount );
			this.$filterToggle.toggleClass( 'is-active', Boolean( filterCount ) );
			this.$filterToggle.find( '.uplink-mbe-modal-filter-count' ).text( filterCount || '' ).prop( 'hidden', ! filterCount );
		},

		activeFilterCount() {
			return [ this.mediaType, this.mimeSubtype, this.uploadedBy, this.attachmentStatus, this.optimizationStatus, this.uploadedFrom, this.uploadedTo, this.widthMin || this.widthMax, this.heightMin || this.heightMax, this.fileSizeMin || this.fileSizeMax, this.missingAlt ].filter( Boolean ).length;
		},

		renderCollections() {
			this.$collections.empty();
			this.$collections.append(
				$( '<h3 class="uplink-mbe-modal-collections-title" />' ).text( config.strings.collections )
			);

			const special = $( '<div class="uplink-mbe-modal-special-collections" />' ).append(
				this.collectionButton( config.strings.allMedia, 'all', 0, this.counts.all, 'format-gallery' ),
				this.collectionButton( config.strings.uncategorized, 'uncategorized', 0, this.counts.uncategorized, 'category' )
			);
			const list = $( '<ul class="uplink-mbe-modal-collection-list" />' );

			this.collections.filter( ( item ) => 0 === Number( item.parent ) ).forEach( ( item ) => {
				const children = this.collections.filter( ( child ) => Number( child.parent ) === Number( item.id ) );
				const collapsed = this.collapsedCollections.has( Number( item.id ) );
				const listItem = $( '<li class="uplink-mbe-modal-collection-item" />' ).toggleClass( 'is-collapsed', collapsed );
				const row = $( '<div class="uplink-mbe-modal-collection-row" />' ).toggleClass( 'has-children', Boolean( children.length ) );

				if ( children.length ) {
					const childListId = `uplink-mbe-modal-subcollections-${ item.id }`;
					row.append(
						$( '<button type="button" class="uplink-mbe-modal-collection-toggle" />' )
							.attr( {
								'data-collection': item.id,
								'aria-expanded': collapsed ? 'false' : 'true',
								'aria-controls': childListId,
								'aria-label': collapsed ? config.strings.expandCollection : config.strings.collapseCollection,
							} )
							.append( $( '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true" />' ) ),
						this.collectionButton( item.name, 'collection', item.id, this.collectionCount( item, true ), 'category' )
					);

					const childList = $( '<ul class="uplink-mbe-modal-subcollection-list" />' ).attr( 'id', childListId );
					if ( collapsed ) {
						childList.attr( 'hidden', 'hidden' );
					}
					children.forEach( ( child ) => {
						childList.append(
							$( '<li />' ).append(
								this.collectionButton( child.name, 'collection', child.id, this.collectionCount( child ), 'category' )
							)
						);
					} );
					listItem.append( row, childList );
				} else {
					row.append(
						this.collectionButton( item.name, 'collection', item.id, this.collectionCount( item ), 'category' )
					);
					listItem.append( row );
				}
				list.append( listItem );
			} );

			this.$collections.append( special, list );
		},

		collectionButton( label, filter, collection, count, icon ) {
			const active = this.filter === filter && this.collectionId === collection;
			const folderIcon = 'collection' === filter && null !== icon;
			const displayIcon = folderIcon ? ( active ? 'open-folder' : 'category' ) : icon;
			const button = $( '<button type="button" class="uplink-mbe-modal-collection" />' )
				.toggleClass( 'is-active', active )
				.toggleClass( 'has-icon', Boolean( displayIcon ) )
				.attr( {
					'data-filter': filter,
					'data-collection': collection,
					'aria-pressed': active ? 'true' : 'false',
				} );

			if ( displayIcon ) {
				button.append( $( `<span class="dashicons dashicons-${ displayIcon }" aria-hidden="true" />` ) );
			}

			button.append( $( '<span class="uplink-mbe-modal-collection-name" />' ).text( label ) );

			if ( null !== count && undefined !== count && '' !== String( count ) ) {
				button.append(
					$( '<span class="uplink-mbe-modal-collection-count" aria-hidden="true" />' ).text( count )
				);
			}
			return button;
		},

		collectionCount( collection, hasChildren = false ) {
			const direct = Number( collection.count ) || 0;
			const total = Number.isFinite( Number( collection.totalCount ) ) ? Number( collection.totalCount ) : direct;
			if ( ! hasChildren || 'direct' === config.parentCountDisplay ) {
				return direct;
			}
			if ( 'direct_total' === config.parentCountDisplay ) {
				return `${ direct }/${ total }`;
			}
			return total;
		},

		toggleCollection( event ) {
			event.preventDefault();
			event.stopPropagation();
			const collection = Number( event.currentTarget.dataset.collection );
			if ( this.collapsedCollections.has( collection ) ) {
				this.collapsedCollections.delete( collection );
			} else {
				this.collapsedCollections.add( collection );
			}
			this.renderCollections();
		},

		renderMedia() {
			this.$grid.empty();
			this.renderViewControls();
			if ( ! this.media.length ) {
				this.$grid.append( $( '<p class="uplink-mbe-modal-message" />' ).text( config.strings.empty ) );
				return;
			}

			this.media.forEach( ( media ) => {
				const selected = Boolean( this.selection?.get( media.id ) );
				const label = config.strings.selectMedia.replace( '%s', media.title );
				const card = $( '<article class="uplink-mbe-modal-item" />' )
					.toggleClass( 'is-selected', selected )
					.attr( {
						'data-id': media.id,
					} );
				const thumbnail = media.isImage || media.hasPreview
					? $( '<img />' ).attr( { src: media.thumbnail, alt: media.alt || '' } ).css( { 'object-fit': 'cover' } )
					: filePlaceholder( media );
				const thumb = $( '<button type="button" class="uplink-mbe-modal-thumb uplink-mbe-modal-inspect" />' ).attr( { 'data-id': media.id, 'aria-label': `${ config.strings.attachmentDetails }: ${ media.title }` } ).append( thumbnail );
				const details = $( '<span class="uplink-mbe-modal-details" />' ).append(
					$( '<span class="uplink-mbe-modal-item-title" />' ).text( media.title ),
					$( '<span class="uplink-mbe-modal-filename" />' ).text( media.filename )
				);
				const badges = $( '<span class="uplink-mbe-modal-badges" />' );
				media.collections.forEach( ( id ) => {
					const collection = this.collections.find( ( item ) => item.id === id );
					if ( collection ) {
						badges.append( $( '<span class="uplink-mbe-modal-badge" />' ).text( collection.name ) );
					}
				} );
				details.append( badges );
				const footer = $( '<span class="uplink-mbe-modal-footer" />' );
				if ( media.alt ) {
					footer.append(
						$( '<span class="uplink-mbe-modal-alt-status" role="img" />' )
							.attr( { 'aria-label': config.strings.altTextPresent, title: config.strings.altTextPresent } )
							.append( $( '<img alt="" aria-hidden="true" />' ).attr( 'src', config.altIconUrl ) )
					);
				}
				if ( Array.isArray( media.exif ) && media.exif.length ) {
					footer.append(
						$( '<span class="uplink-mbe-modal-exif-status" role="img" />' )
							.attr( { 'aria-label': config.strings.exifAvailable, title: config.strings.exifAvailable } )
							.append( $( '<img alt="" aria-hidden="true" />' ).attr( 'src', config.exifIconUrl ) )
					);
				}
				if ( media.optimization?.optimized ) {
					footer.append(
						$( '<span class="uplink-mbe-modal-optimization-status" role="img" />' )
							.attr( { 'aria-label': config.strings.optimizedLabel, title: media.optimization.summary || config.strings.optimizedLabel } )
							.append( $( '<img alt="" aria-hidden="true" />' ).attr( 'src', config.optimizationIconUrl ) )
					);
				}
				const selectButton = $( '<button type="button" class="uplink-mbe-modal-select" />' ).attr( {
					'data-id': media.id,
					'aria-label': label,
					'aria-pressed': selected ? 'true' : 'false',
				} ).append( $( '<span class="uplink-mbe-modal-item-check dashicons dashicons-yes-alt" aria-hidden="true" />' ) );
				footer.prepend( selectButton );
				card.append(
					thumb,
					details,
					footer
				);
				this.$grid.append( card );
			} );
		},

		async inspectAttachment( event ) {
			event.preventDefault();
			event.stopPropagation();
			if ( ! await this.persistInspectorChanges() ) return;
			this.showInspector( Number( event.currentTarget.dataset.id ) );
		},

		showInspector( id ) {
			const media = this.media.find( ( item ) => Number( item.id ) === Number( id ) );
			if ( ! media ) return;
			window.clearTimeout( this.inspectorSaveTimer );
			this.inspectorDirty = false;
			const opening = ! this.inspectedId;
			this.inspectedId = Number( id );
			const index = this.media.indexOf( media );
			this.$inspector.removeAttr( 'hidden' );
			this.$content.addClass( 'has-inspector' );
			this.$el.addClass( 'has-asset-inspector' );
			this.controller.$el.closest( '.media-modal' ).addClass( 'uplink-mbe-has-inspector' );
			this.$inspectorPreview.empty().append(
				media.isImage || media.hasPreview
					? $( '<img />' ).attr( { src: media.isImage ? ( media.url || media.thumbnail ) : media.thumbnail, alt: media.alt || '' } )
					: filePlaceholder( media )
			);
			this.$inspectorFilename.text( media.filename || media.title );
			this.$inspectorReplace.prop( 'hidden', ! media.canEdit || ! window.uplinkMbeReplacement );
			this.$inspectorTitle.val( media.title || '' );
			this.$inspectorAlt.val( media.alt || '' );
			this.$inspectorDecorative.prop( 'checked', Boolean( media.decorative ) );
			this.$inspectorAlt.prop( 'disabled', Boolean( media.decorative ) );
			this.$inspectorCaption.val( media.caption || '' );
			this.$inspectorDescription.val( media.description || '' );
			const editState = this.controller.states.get( 'edit-image' );
			this.$inspectorEditImage.prop( 'hidden', ! media.isImage || ( ! ( window.imageEdit && editState ) && ! ( media.imageEditUrl || media.editUrl ) ) );
			const row = ( label, value ) => $( '<div />' ).append(
				$( '<dt />' ).text( label ),
				$( '<dd />' ).text( value || '—' )
			);
			const pathRow = ( label, value ) => {
				const $row = row( label, value );
				$( '<button type="button" class="button-link" />' ).text( config.strings.copyPath ).attr( 'aria-label', `${ config.strings.copyPath } ${ label }` ).on( 'click', async () => {
					try { await navigator.clipboard.writeText( value || '' ); window.uplinkMbeToast( config.strings.pathCopied ); }
					catch ( error ) { window.uplinkMbeToast( error.message, 'error' ); }
				} ).appendTo( $row.find( 'dd' ) );
				return $row;
			};
			const collectionNames = ( media.collections || [] )
				.map( ( collectionId ) => this.collections.find( ( collection ) => Number( collection.id ) === Number( collectionId ) )?.name )
				.filter( Boolean );
			this.$inspectorFileList.empty().append(
				row( config.strings.fileName, media.filename ),
				pathRow( config.strings.filePath, media.filePath ),
				pathRow( config.strings.fullFilePath, media.fullFilePath || media.url ),
				row( config.strings.fileType, media.mime || media.fileType ),
				row( config.strings.dimensions, media.dimensions ),
				row( config.strings.fileSize, media.fileSize ),
				row( config.strings.uploadedBy, media.author ),
				row( config.strings.uploaded, media.date ),
				row( config.strings.collections, collectionNames.join( ', ' ) || config.strings.uncategorized ),
				row( config.strings.attachmentId, media.id )
			);
			const optimization = media.optimization?.details || [];
			this.$inspectorOptimizationList.empty();
			let $optimizationList = null;
			optimization.forEach( ( item ) => {
				if ( item.section || ! $optimizationList ) {
					$optimizationList = $( '<dl class="uplink-mbe-modal-inspector-data-list" />' );
					this.$inspectorOptimizationList.append(
						$( '<section class="uplink-mbe-modal-optimization-group" />' ).append(
							$( '<h4 />' ).text( item.section || config.strings.optimization ),
							$optimizationList
						)
					);
				}
				$optimizationList.append( row( item.label, item.value ) );
			} );
			this.$inspectorOptimizationTab.prop( 'hidden', ! optimization.length );
			const exif = Array.isArray( media.exif ) ? media.exif : [];
			this.$inspectorExifList.empty();
			exif.forEach( ( item ) => this.$inspectorExifList.append( row( item.label, item.value ) ) );
			this.$inspectorExifTab.prop( 'hidden', ! exif.length );
			this.$inspectorNotice.text( '' ).removeClass( 'is-error is-saving is-saved' );
			this.$inspectorInsert.text( this.actionLabel() );
			this.$inspectorHeader.find( '.uplink-mbe-modal-inspector-position' ).text( `${ index + 1 } of ${ this.media.length }` );
			this.$inspectorHeader.find( '.uplink-mbe-modal-inspector-previous' ).prop( 'disabled', index <= 0 );
			this.$inspectorHeader.find( '.uplink-mbe-modal-inspector-next' ).prop( 'disabled', index >= this.media.length - 1 );
			this.setInspectorTab( opening ? 'details' : this.inspectorTab );
			this.inspectorSavedValues = JSON.stringify( this.inspectorValues() );
			this.updateInspectorSaveState();
		},

		async closeInspector() {
			if ( ! await this.persistInspectorChanges() ) return;
			this.inspectedId = 0;
			this.$inspector.attr( 'hidden', 'hidden' );
			this.$content.removeClass( 'has-inspector' );
			this.$el.removeClass( 'has-asset-inspector' );
			this.controller.$el.closest( '.media-modal' ).removeClass( 'uplink-mbe-has-inspector' );
		},

		async previousInspectorItem() {
			const index = this.media.findIndex( ( item ) => Number( item.id ) === this.inspectedId );
			if ( ! await this.persistInspectorChanges() ) return;
			if ( index > 0 ) this.showInspector( this.media[ index - 1 ].id );
		},

		async nextInspectorItem() {
			const index = this.media.findIndex( ( item ) => Number( item.id ) === this.inspectedId );
			if ( ! await this.persistInspectorChanges() ) return;
			if ( index >= 0 && index < this.media.length - 1 ) this.showInspector( this.media[ index + 1 ].id );
		},

		toggleDecorative() {
			this.$inspectorAlt.prop( 'disabled', this.$inspectorDecorative.prop( 'checked' ) );
			if ( this.$inspectorDecorative.prop( 'checked' ) ) this.$inspectorAlt.val( '' );
			this.saveInspectorChange();
		},

		inspectorValues() {
			return {
				attachment_id: this.inspectedId,
				title: this.$inspectorTitle.val(),
				alt_text: this.$inspectorAlt.val(),
				decorative: this.$inspectorDecorative.prop( 'checked' ) ? '1' : '0',
				caption: this.$inspectorCaption.val(),
				description: this.$inspectorDescription.val(),
			};
		},

		syncInspectorAttachmentModel( values ) {
			const attachment = wp.media.attachment( Number( values.attachment_id ) );
			attachment.set( {
				title: values.title,
				alt: '1' === values.decorative ? '' : values.alt_text,
				caption: values.caption,
				description: values.description,
			} );
		},

		updateInspectorSaveState() {
			this.$( '.uplink-mbe-modal-inspector-save' ).prop( 'disabled', this.inspectorSavesPending > 0 || JSON.stringify( this.inspectorValues() ) === this.inspectorSavedValues );
		},

		scheduleInspectorSave() {
			if ( ! this.inspectedId ) return;
			this.inspectorDirty = true;
			this.updateInspectorSaveState();
			this.syncInspectorAttachmentModel( this.inspectorValues() );
			window.clearTimeout( this.inspectorSaveTimer );
			this.inspectorSaveTimer = window.setTimeout( () => this.persistInspectorChanges(), 500 );
		},

		saveInspectorChange() {
			if ( ! this.inspectedId ) return;
			this.inspectorDirty = true;
			this.updateInspectorSaveState();
			this.syncInspectorAttachmentModel( this.inspectorValues() );
			window.clearTimeout( this.inspectorSaveTimer );
			this.persistInspectorChanges();
		},

		persistInspectorChanges( force = false ) {
			if ( ! this.inspectedId || ( ! force && ! this.inspectorDirty ) ) return this.inspectorSaveChain;
			window.clearTimeout( this.inspectorSaveTimer );
			const values = this.inspectorValues();
			const attachmentId = Number( values.attachment_id );
			this.inspectorDirty = false;
			this.syncInspectorAttachmentModel( values );
			this.inspectorSavesPending += 1;
			this.updateInspectorSaveState();
			if ( this.inspectedId === attachmentId ) {
				this.$inspectorNotice.removeClass( 'is-error is-saved' ).addClass( 'is-saving' ).text( config.strings.savingChanges );
			}

			this.inspectorSaveChain = this.inspectorSaveChain
				.catch( () => false )
				.then( async () => {
					let saved = false;
					try {
						const data = await request( 'uplink_mbe_update_attachment', values );
						const index = this.media.findIndex( ( item ) => Number( item.id ) === attachmentId );
						if ( index >= 0 ) this.media[ index ] = data;
						if ( this.inspectedId === attachmentId ) this.inspectorSavedValues = JSON.stringify( values );
						saved = true;
						return true;
					} catch ( error ) {
						if ( this.inspectedId === attachmentId ) {
							this.inspectorDirty = true;
							this.$inspectorNotice.text( '' ).removeClass( 'is-saving is-error is-saved' );
							window.uplinkMbeToast( error.message, 'error' );
						}
						return false;
					} finally {
						this.inspectorSavesPending = Math.max( 0, this.inspectorSavesPending - 1 );
						this.updateInspectorSaveState();
						if ( saved && this.inspectedId === attachmentId && 0 === this.inspectorSavesPending ) {
							this.$inspectorNotice.text( '' ).removeClass( 'is-saving is-error is-saved' );
							window.uplinkMbeToast( config.strings.saved );
						}
					}
				} );
			return this.inspectorSaveChain;
		},

		async saveInspector( event ) {
			event?.preventDefault?.();
			const attachmentId = this.inspectedId;
			if ( ! await this.persistInspectorChanges( true ) || this.inspectedId !== attachmentId ) return;
			this.renderMedia();
			this.showInspector( attachmentId );
			this.$inspectorNotice.text( '' ).removeClass( 'is-saving is-error is-saved' );
			window.uplinkMbeToast( config.strings.saved );
		},

		editInspectedImage( event ) {
			event.preventDefault();
			const media = this.media.find( ( item ) => Number( item.id ) === this.inspectedId );
			if ( ! media?.isImage ) return;

			const editState = this.controller.states.get( 'edit-image' );
			if ( window.imageEdit && editState ) {
				const attachment = wp.media.attachment( this.inspectedId );
				const openEditor = () => {
					this.controller.$el.closest( '.media-modal' ).removeClass( 'uplink-mbe-has-inspector' );
					editState.set( 'image', attachment );
					this.controller.setState( 'edit-image' );
				};

				if ( attachment.get( 'url' ) ) {
					openEditor();
				} else {
					attachment.fetch().done( openEditor ).fail( () => {
						this.$inspectorNotice.text( '' ).removeClass( 'is-saving is-error is-saved' );
						window.uplinkMbeToast( config.strings.error, 'error' );
					} );
				}
				return;
			}

			if ( media.imageEditUrl || media.editUrl ) {
				window.location.href = media.imageEditUrl || media.editUrl;
			}
		},

		async insertInspected( event ) {
			event.preventDefault();
			if ( ! this.inspectedId || ! this.selection ) return;
			const attachmentId = this.inspectedId;
			const actionLabel = this.actionLabel();
			const committing = this.inspectorDirty || this.inspectorSavesPending > 0;
			this.$inspectorInsert.prop( 'disabled', true ).attr( 'aria-busy', 'true' ).addClass( 'is-busy' );
			this.$inspectorInsert.text( committing ? config.strings.savingChanges : config.strings.inserting );
			this.$inspectorNotice.removeClass( 'is-error is-saved' ).addClass( 'is-saving' ).text( committing ? config.strings.savingChanges : config.strings.inserting );
			if ( ! await this.persistInspectorChanges() ) {
				this.$inspectorInsert.prop( 'disabled', false ).removeAttr( 'aria-busy' ).removeClass( 'is-busy' ).text( actionLabel );
				return;
			}
			this.$inspectorInsert.text( config.strings.inserting );
			this.$inspectorNotice.removeClass( 'is-error is-saved' ).addClass( 'is-saving' ).text( config.strings.inserting );

			const attachment = wp.media.attachment( attachmentId );
			try {
				const hydrated = await hydrateAttachment( attachment, attachmentId );
				this.selection.reset( [ selectionAttachment( hydrated, attachmentId ) ] );
				this.updateSelected();

				const $insertButton = this.controller.$el
					.find( '.media-frame-toolbar .media-button-select, .media-frame-toolbar .media-button-insert' )
					.filter( ':visible' )
					.first();
				if ( $insertButton.length ) {
					$insertButton.trigger( 'click' );
					return;
				}

				this.controller.close();
				this.controller.state().trigger( 'select' );
				this.controller.reset();
			} catch ( error ) {
				this.$inspectorNotice.text( '' ).removeClass( 'is-saving is-error is-saved' );
				window.uplinkMbeToast( config.strings.error, 'error' );
				this.$inspectorInsert.prop( 'disabled', false ).removeAttr( 'aria-busy' ).removeClass( 'is-busy' ).text( actionLabel );
			}
		},

		selectCollection( event ) {
			this.filter = event.currentTarget.dataset.filter;
			this.collectionId = Number( event.currentTarget.dataset.collection ) || 0;
			this.controller.uplinkMbeCollectionFilter = this.filter;
			this.controller.uplinkMbeCollectionId = this.collectionId;
			this.page = 1;
			this.media = [];
			this.fetchMedia( false );
		},

		searchMedia( event ) {
			event?.preventDefault();
			window.clearTimeout( this.searchTimer );
			const search = this.$( '#uplink-mbe-modal-search-input' ).val().trim();
			if ( search === this.search ) return;
			this.search = search;
			this.page = 1;
			this.media = [];
			this.fetchMedia( false );
		},

		scheduleSearch() {
			if ( this.searchComposing ) return;
			window.clearTimeout( this.searchTimer );
			this.searchTimer = window.setTimeout( () => this.searchMedia(), 350 );
		},

		startSearchComposition() {
			this.searchComposing = true;
			window.clearTimeout( this.searchTimer );
		},

		endSearchComposition() {
			this.searchComposing = false;
			this.scheduleSearch();
		},

		changeTypeFilter( event ) {
			this.mediaType = this.requiredMediaType || event.currentTarget.value;
			if ( this.mimeSubtype && ! this.mimeSubtype.startsWith( `${ this.mediaType }/` ) ) this.mimeSubtype = '';
			this.resetAndFetch();
		},

		changeAdvancedFilter( event ) {
			const key = event.currentTarget.dataset.uplinkMbeFilter;
			this[ key ] = 'checkbox' === event.currentTarget.type ? event.currentTarget.checked : event.currentTarget.value;
			this.resetAndFetch();
		},

		toggleFilterPanel() {
			const open = 'true' !== this.$filterToggle.attr( 'aria-expanded' );
			this.$filterToggle.attr( 'aria-expanded', open ? 'true' : 'false' );
			this.$filterPanel.prop( 'hidden', ! open );
		},

		closeFilterPanel() {
			this.$filterToggle.attr( 'aria-expanded', 'false' ).trigger( 'focus' );
			this.$filterPanel.prop( 'hidden', true );
		},

		clearFilters() {
			this.mediaType = this.requiredMediaType;
			this.mimeSubtype = '';
			this.uploadedBy = '';
			this.attachmentStatus = '';
			this.optimizationStatus = '';
			this.uploadedFrom = '';
			this.uploadedTo = '';
			this.widthMin = '';
			this.widthMax = '';
			this.heightMin = '';
			this.heightMax = '';
			this.fileSizeMin = '';
			this.fileSizeMax = '';
			this.missingAlt = false;
			this.resetAndFetch();
		},

		resetAndFetch() {
			this.page = 1;
			this.media = [];
			this.fetchMedia( false );
		},

		maybeLoadMore() {
			const results = this.$results?.[ 0 ];
			if ( ! results || this.loading || this.page >= this.pages ) {
				return;
			}
			if ( results.scrollTop + results.clientHeight >= results.scrollHeight - 240 ) {
				this.page += 1;
				this.fetchMedia( true );
			}
		},

		async toggleAttachment( event ) {
			event.preventDefault();
			event.stopPropagation();
			const id = Number( event.currentTarget.dataset.id );
			const attachment = wp.media.attachment( id );
			const selected = this.selection.get( id );
			if ( selected ) {
				this.selection.remove( selected );
				this.updateSelected();
				return;
			}

			try {
				const hydrated = await hydrateAttachment( attachment, id );
				const selectedAttachment = selectionAttachment( hydrated, id );
				const multiple = this.controller.state()?.get( 'multiple' );
				if ( false === multiple ) {
					this.selection.reset( [ selectedAttachment ] );
				} else {
					this.selection.add( selectedAttachment );
				}
				this.updateSelected();
			} catch ( error ) {
				window.uplinkMbeToast( config.strings.error, 'error' );
			}
		},

		updateSelected() {
			this.controller.$el.closest( '.media-modal' ).toggleClass( 'uplink-mbe-has-selection', Boolean( this.selection?.length ) );
			if ( this.$inspectorInsert && this.inspectedId ) this.$inspectorInsert.text( this.actionLabel() );
			if ( ! this.$grid ) return;
			this.$grid.find( '.uplink-mbe-modal-item' ).each( ( index, element ) => {
				const selected = Boolean( this.selection?.get( Number( element.dataset.id ) ) );
				$( element ).toggleClass( 'is-selected', selected ).find( '.uplink-mbe-modal-select' ).attr( 'aria-pressed', selected ? 'true' : 'false' );
			} );
		},

		handleExternalSelection() {
			this.updateSelected();
			const attachment = this.selection?.first();
			const id = Number( attachment?.get( 'id' ) || attachment?.id );
			if ( ! id ) return;

			if ( 'function' === typeof this.controller.uplinkMbeHandleCreatedAttachment ) {
				this.controller.uplinkMbeHandleCreatedAttachment( id );
				return;
			}
			if ( ! this.$grid ) {
				this.pendingSelectionId = id;
				return;
			}

			this.revealExternalAttachment( id, false );
		},

		async revealExternalAttachment( id, openInspector = true ) {
			this.filter = 'all';
			this.collectionId = 0;
			this.controller.uplinkMbeCollectionFilter = 'all';
			this.controller.uplinkMbeCollectionId = 0;
			this.search = '';
			this.mediaType = this.requiredMediaType;
			this.mimeSubtype = '';
			this.uploadedBy = '';
			this.attachmentStatus = '';
			this.optimizationStatus = '';
			this.uploadedFrom = '';
			this.uploadedTo = '';
			this.widthMin = '';
			this.widthMax = '';
			this.heightMin = '';
			this.heightMax = '';
			this.fileSizeMin = '';
			this.fileSizeMax = '';
			this.missingAlt = false;
			this.page = 1;
			this.media = [];
			this.$( '#uplink-mbe-modal-search-input' ).val( '' );

			await this.fetchMedia( false );
			if ( ! this.media.some( ( item ) => Number( item.id ) === id ) ) {
				try {
					const media = await request( 'uplink_mbe_get_attachment', { attachment_id: id } );
					this.media.unshift( media );
					this.renderMedia();
				} catch ( error ) {
					window.uplinkMbeToast( error.message, 'error' );
					return;
				}
			}
			if ( openInspector ) {
				this.showInspector( id );
			}
		},
	} );

	const selectPrototype = wp.media.view.MediaFrame.Select.prototype;
	if ( ! selectPrototype.uplinkMbeCollectionsPatched ) {
		const originalBindHandlers = selectPrototype.bindHandlers;
		const originalBrowseRouter = selectPrototype.browseRouter;
		const originalBrowseContent = selectPrototype.browseContent;

		selectPrototype.bindHandlers = function () {
			originalBindHandlers.apply( this, arguments );
			patchManagerUploadTransition();
			this.on( 'select', function () {
				this.state().get( 'selection' )?.each( normalizeAttachmentIdentity );
			}, this );
			this.on( 'open', function () {
				applyFrameTheme( this );
				if ( this.acf?.get && this.acf?.set && ! this.uplinkMbeAcfSelectionPatched ) {
					const acfSelect = this.acf.get( 'select' );
					if ( 'function' === typeof acfSelect ) {
						this.acf.set( 'select', function ( attachment, index ) {
							return acfSelect.call( this, normalizeAttachmentIdentity( attachment ), index );
						} );
						this.uplinkMbeAcfSelectionPatched = true;
					}
				}
				window.requestAnimationFrame( () => {
					// ACF's edit mode hides the media router and turns the frame into a
					// details-only panel. The enhanced manager uses this frame to replace
					// media, so retain its complete upload and library navigation.
					if ( config.defaultMediaScreen && 'edit' === this.acf?.get?.( 'mode' ) ) {
						this.$el.closest( '.media-modal' ).removeClass( '-edit acf-expanded' );
					}
					renderUploadWorkspace( this );
				} );
			}, this );
			this.on( 'content:render:upload', function () {
				window.requestAnimationFrame( () => renderUploadWorkspace( this ) );
			}, this );
			this.on( 'close', function () {
				restoreCimoOptimization( this );
			}, this );
			this.listenTo( wp.Uploader.queue, 'add', function ( attachment ) {
				this.uplinkMbeTrackUpload?.( attachment );
				watchUpload( this, attachment );
			} );
			this.on( 'content:render:uplink-mbe-collections', function () {
				this.$el.removeClass( 'hide-toolbar' );
				this.content.set( new CollectionsView( { controller: this } ) );
			}, this );
		};

		selectPrototype.browseRouter = function ( routerView ) {
			originalBrowseRouter.apply( this, arguments );
			if ( ! this.uplinkMbeManagerUpload ) {
				routerView.set( {
					'uplink-mbe-collections': {
						text: config.strings.tab,
						priority: 60,
					},
				} );
			}

			if ( config.defaultMediaScreen && ! this.uplinkMbeManagerUpload ) {
				if ( 'uplink-mbe-collections' !== this.content.mode() ) {
					this.content.mode( 'uplink-mbe-collections' );
				}
			}
		};

		selectPrototype.browseContent = function ( contentRegion ) {
			if ( config.defaultMediaScreen && ! this.uplinkMbeManagerUpload ) {
				this.$el.removeClass( 'hide-toolbar' );
				contentRegion.view = new CollectionsView( { controller: this } );
				return;
			}

			originalBrowseContent.apply( this, arguments );
		};

		selectPrototype.uplinkMbeCollectionsPatched = true;
	}

	if ( config.defaultMediaScreen ) {
		document.addEventListener( 'click', ( event ) => {
			const editLink = event.target.closest?.( '.rwmb-edit-media' );
			if ( ! editLink ) return;

			const mediaItem = editLink.closest( '.rwmb-file, .rwmb-image-item' );
			const editUrl = new URL( editLink.href, window.location.href );
			const attachmentId = Number( mediaItem?.querySelector( '.rwmb-media-input' )?.value || editUrl.searchParams.get( 'post' ) );
			if ( ! attachmentId ) return;

			event.preventDefault();
			event.stopImmediatePropagation();
			openAttachmentDetails( attachmentId, mediaItem.classList.contains( 'rwmb-image-item' ) ? 'image' : '' );
		}, true );
	}

}( window.jQuery, window.wp ) );
