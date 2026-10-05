( function ( $, wp ) {
	'use strict';

	const config = window.uplinkMbeNativeCollections;
	if ( ! config || ! wp?.media ) {
		return;
	}

	let uploadCollection = 0;
	const uploaders = new Set();
	const childrenByParent = new Map();
	const collapsedCollections = new Set();
	let collectionOrderSaving = false;
	let collectionPointerDrag = null;
	let suppressCollectionClick = false;

	function setCollectionData( collections ) {
		config.collections = Array.isArray( collections ) ? collections : [];
		childrenByParent.clear();
		config.collections.forEach( ( collection ) => {
			const parent = Number( collection.parent ) || 0;
			if ( ! childrenByParent.has( parent ) ) {
				childrenByParent.set( parent, [] );
			}
			childrenByParent.get( parent ).push( collection );
		} );
	}

	setCollectionData( config.collections );

	async function request( action, data = {} ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.entries( data ).forEach( ( [ key, value ] ) => body.append( key, value ) );
		const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
		const payload = await response.json();
		if ( ! response.ok || ! payload.success ) {
			throw new Error( payload.data?.message || config.strings.error );
		}
		return payload.data || {};
	}

	function folderIcon( iconName = 'category' ) {
		const icon = document.createElement( 'span' );
		icon.className = `dashicons dashicons-${ iconName }`;
		icon.setAttribute( 'aria-hidden', 'true' );
		return icon;
	}

	function countBadge( value ) {
		const count = document.createElement( 'span' );
		count.className = 'uplink-mbe-native-collection-count';
		count.textContent = null === value || undefined === value || '' === String( value ) ? '0' : String( value );
		return count;
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

	function makeFilterButton( label, count, filter, collectionId = 0 ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'uplink-mbe-native-collection-filter';
		button.dataset.filter = filter;
		button.dataset.collection = String( collectionId );
		const iconName = 'all' === filter ? 'format-gallery' : 'category';
		const name = document.createElement( 'span' );
		name.className = 'uplink-mbe-native-collection-name';
		name.textContent = label;
		button.append( folderIcon( iconName ), name, countBadge( count ) );
		return button;
	}

	function siblingCollections( parent ) {
		return childrenByParent.get( Number( parent ) || 0 ) || [];
	}

	function clearCollectionDropIndicators( panel ) {
		panel.querySelectorAll( '.is-reorder-before, .is-reorder-after, .is-reordering' ).forEach( ( item ) => {
			item.classList.remove( 'is-reorder-before', 'is-reorder-after', 'is-reordering' );
		} );
	}

	async function saveCollectionOrder( view, panel, parent, orderedIds, focusId ) {
		if ( collectionOrderSaving ) {
			return;
		}
		collectionOrderSaving = true;
		try {
			await request( 'uplink_mbe_reorder_collections', {
				parent,
				ordered_ids: JSON.stringify( orderedIds ),
			} );
			await refreshCollectionState( view, panel );
			showPanelStatus( panel, config.strings.orderSaved );
			window.setTimeout( () => panel.querySelector( `[data-reorder-id="${ Number( focusId ) }"]` )?.focus(), 0 );
		} catch ( error ) {
			showPanelStatus( panel, error.message, 'error' );
		} finally {
			collectionOrderSaving = false;
			clearCollectionDropIndicators( panel );
		}
	}

	function moveCollectionByKeyboard( view, panel, collection, direction ) {
		const ids = siblingCollections( collection.parent ).map( ( sibling ) => Number( sibling.id ) );
		const current = ids.indexOf( Number( collection.id ) );
		const target = current + direction;
		if ( current < 0 || target < 0 || target >= ids.length ) {
			return;
		}
		[ ids[ current ], ids[ target ] ] = [ ids[ target ], ids[ current ] ];
		saveCollectionOrder( view, panel, collection.parent, ids, collection.id );
	}

	function enableCollectionPointerReorder( view, panel, row, filter, collection ) {
		filter.addEventListener( 'pointerdown', ( event ) => {
			if ( 0 !== event.button || collectionOrderSaving ) {
				return;
			}
			collectionPointerDrag = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startY: event.clientY,
				sourceRow: row,
				target: null,
				placeAfter: false,
				moved: false,
			};
			filter.setPointerCapture?.( event.pointerId );
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
			clearCollectionDropIndicators( panel );
			drag.sourceRow.classList.add( 'is-reordering' );
			const targetRow = document.elementFromPoint( event.clientX, event.clientY )?.closest( '.uplink-mbe-native-collection-row' );
			if ( ! targetRow || Number( targetRow.dataset.parent ) !== Number( collection.parent ) || Number( targetRow.dataset.collectionId ) === Number( collection.id ) ) {
				drag.target = null;
				return;
			}
			drag.target = targetRow;
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
			if ( filter.hasPointerCapture?.( event.pointerId ) ) {
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
				let targetIndex = ids.indexOf( Number( drag.target.dataset.collectionId ) );
				if ( drag.placeAfter ) {
					targetIndex += 1;
				}
				ids.splice( Math.max( 0, targetIndex ), 0, Number( collection.id ) );
				saveCollectionOrder( view, panel, collection.parent, ids, collection.id );
			} else {
				clearCollectionDropIndicators( panel );
			}
		};
		filter.addEventListener( 'pointerup', finishPointerReorder );
		filter.addEventListener( 'pointercancel', finishPointerReorder );
	}

	function collectionActionButton( action, label, iconName, collection ) {
		const button = iconButton( 'uplink-mbe-native-collection-action', `${ label }: ${ collection.name }`, iconName );
		button.dataset.collectionAction = action;
		button.dataset.collection = String( collection.id );
		return button;
	}

	function collectionBranch( view, panel, parent = 0 ) {
		const list = document.createElement( 'ul' );
		list.className = 'uplink-mbe-native-collection-tree';
		( childrenByParent.get( parent ) || [] ).forEach( ( collection ) => {
			const item = document.createElement( 'li' );
			const row = document.createElement( 'div' );
			row.className = 'uplink-mbe-native-collection-row';
			row.dataset.collectionId = String( collection.id );
			row.dataset.parent = String( collection.parent );
			const children = childrenByParent.get( Number( collection.id ) ) || [];
			if ( children.length ) {
				row.classList.add( 'has-children' );
				const toggle = document.createElement( 'button' );
				toggle.type = 'button';
				toggle.className = 'uplink-mbe-native-branch-toggle';
				toggle.setAttribute( 'aria-expanded', 'true' );
				toggle.setAttribute( 'aria-label', `${ config.strings.collapse }: ${ collection.name }` );
				toggle.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
				row.append( toggle );
			}
			const filter = makeFilterButton( collection.name, collectionCount( collection, Boolean( children.length ) ), 'collection', Number( collection.id ) );
			if ( config.canManage ) {
				row.classList.add( 'can-reorder' );
				filter.dataset.reorderId = String( collection.id );
				filter.setAttribute( 'aria-keyshortcuts', 'Alt+ArrowUp Alt+ArrowDown' );
				filter.setAttribute( 'aria-description', config.strings.reorderInstructions );
				filter.addEventListener( 'keydown', ( event ) => {
					if ( ! event.altKey || ( 'ArrowUp' !== event.key && 'ArrowDown' !== event.key ) ) {
						return;
					}
					event.preventDefault();
					moveCollectionByKeyboard( view, panel, collection, 'ArrowUp' === event.key ? -1 : 1 );
				} );
				enableCollectionPointerReorder( view, panel, row, filter, collection );
			}
			row.append( filter );
			if ( config.canManage ) {
				const actions = document.createElement( 'div' );
				actions.className = 'uplink-mbe-native-collection-actions';
				if ( Number( collection.depth ) < Number( config.maxCollectionDepth || 2 ) - 1 ) {
					actions.append( collectionActionButton( 'add-child', config.strings.addChild, 'plus-alt2', collection ) );
				}
				actions.append(
					collectionActionButton( 'edit', config.strings.editCollection, 'edit', collection ),
					collectionActionButton( 'delete', config.strings.deleteCollection, 'trash', collection )
				);
				row.append( actions );
			}
			item.append( row );
			if ( children.length ) {
				const childList = collectionBranch( view, panel, Number( collection.id ) );
				toggleBranch( item, row.querySelector( '.uplink-mbe-native-branch-toggle' ), childList, ! collapsedCollections.has( Number( collection.id ) ) );
				item.append( childList );
			}
			list.append( item );
		} );
		return list;
	}

	function toggleBranch( item, toggle, childList, expanded ) {
		item.classList.toggle( 'is-expanded', expanded );
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		toggle.setAttribute( 'aria-label', `${ expanded ? config.strings.collapse : config.strings.expand }: ${ item.querySelector( '.uplink-mbe-native-collection-filter' )?.textContent || '' }` );
		childList.hidden = ! expanded;
	}

	function refreshActiveFilters( panel, filter, collectionId ) {
		panel.querySelectorAll( '.uplink-mbe-native-collection-filter' ).forEach( ( button ) => {
			const active = button.dataset.filter === filter && Number( button.dataset.collection ) === collectionId;
			button.classList.toggle( 'is-active', active );
			if ( active ) {
				button.setAttribute( 'aria-current', 'true' );
			} else {
				button.removeAttribute( 'aria-current' );
			}
			if ( 'collection' === button.dataset.filter ) {
				const icon = button.querySelector( '.dashicons' );
				if ( icon ) {
					icon.className = `dashicons dashicons-${ active ? 'open-folder' : 'category' }`;
				}
			}
		} );
	}

	function applyFilter( view, filter, collectionId ) {
		if ( view.uplinkMbeNativeListMode ) {
			const url = new URL( window.location.href );
			url.searchParams.delete( 'uplink_mbe_collection' );
			url.searchParams.delete( 'uplink_mbe_uncategorized' );
			url.searchParams.delete( 'paged' );
			if ( 'collection' === filter ) {
				url.searchParams.set( 'uplink_mbe_collection', String( collectionId ) );
			} else if ( 'uncategorized' === filter ) {
				url.searchParams.set( 'uplink_mbe_uncategorized', '1' );
			}
			window.location.assign( url.toString() );
			return;
		}
		const props = view?.collection?.props;
		if ( ! props ) {
			return;
		}
		props.set( {
			uplink_mbe_collection: 'collection' === filter ? collectionId : null,
			uplink_mbe_uncategorized: 'uncategorized' === filter ? 1 : null,
		} );
		view.uplinkMbeNativeCollectionsFilter = { filter, collectionId };
		const panel = view.uplinkMbeNativeCollectionsPanel || view.el;
		refreshActiveFilters( panel, filter, collectionId );
	}

	function iconButton( className, label, iconName ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = `uplink-mbe-native-panel-action ${ className }`;
		button.setAttribute( 'aria-label', label );
		button.title = label;
		button.innerHTML = `<span class="dashicons dashicons-${ iconName }" aria-hidden="true"></span>`;
		return button;
	}

	function collectionDescendantIds( collectionId ) {
		const descendants = new Set();
		const visit = ( parent ) => {
			( childrenByParent.get( Number( parent ) ) || [] ).forEach( ( child ) => {
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
		return 1 + ( childrenByParent.get( normalizedId ) || [] ).reduce( ( height, child ) => Math.max( height, collectionSubtreeHeight( child.id, nextVisited ) ), 0 );
	}

	function fillCollectionSelect( select, includeUncategorized = false, parentMode = false, editedCollection = null ) {
		const selected = select.value;
		const excluded = editedCollection ? collectionDescendantIds( editedCollection.id ) : new Set();
		if ( editedCollection ) {
			excluded.add( Number( editedCollection.id ) );
		}
		const subtreeHeight = editedCollection ? collectionSubtreeHeight( editedCollection.id ) : 1;
		select.replaceChildren();
		const first = document.createElement( 'option' );
		first.value = '0';
		first.textContent = includeUncategorized ? config.strings.uncategorized : config.strings.topLevel;
		select.append( first );
		config.collections.forEach( ( collection ) => {
			if ( parentMode && ( Number( collection.depth ) + 1 + subtreeHeight > Number( config.maxCollectionDepth || 2 ) || excluded.has( Number( collection.id ) ) ) ) {
				return;
			}
			const option = document.createElement( 'option' );
			option.value = String( collection.id );
			option.textContent = `${ '\u00a0\u00a0'.repeat( Number( collection.depth ) || 0 ) }${ collection.name }`;
			select.append( option );
		} );
		select.value = Array.from( select.options ).some( ( option ) => option.value === selected ) ? selected : '0';
	}

	function draggedAttachmentIds( view, attachment ) {
		if ( view.uplinkMbeNativeListMode ) {
			const selected = Array.from( view.el.querySelectorAll( '#the-list tr[id^="post-"]' ) ).filter( ( row ) => row.querySelector( 'input[type="checkbox"]:checked' ) );
			const sourceSelected = Boolean( attachment.querySelector( 'input[type="checkbox"]:checked' ) );
			const sources = sourceSelected && selected.length ? selected : [ attachment ];
			return sources.map( ( item ) => Number( item.dataset.id ) ).filter( Boolean );
		}
		const selected = Array.from( view.el.querySelectorAll( '.attachment.selected[data-id]' ) );
		const sources = attachment.classList.contains( 'selected' ) && selected.length ? selected : [ attachment ];
		return sources.map( ( item ) => Number( item.dataset.id ) ).filter( Boolean );
	}

	function attachmentFromTarget( view, target ) {
		return target.closest( view.uplinkMbeNativeListMode ? '.uplink-mbe-native-list-attachment[data-id]' : '.attachment[data-id]' );
	}

	function dragAssignmentHelper( view, attachment ) {
		const count = draggedAttachmentIds( view, attachment ).length;
		const helper = document.createElement( 'div' );
		helper.className = 'uplink-mbe-native-drag-helper';
		const title = document.createElement( 'strong' );
		title.textContent = 1 === count
			? config.strings.dragAssignOne
			: config.strings.dragAssignMany.replace( '%d', String( count ) );
		const instructions = document.createElement( 'span' );
		instructions.textContent = config.strings.dragAssignHelp;
		helper.append( title, instructions );
		return helper;
	}

	async function assignDroppedMedia( view, panel, target, ids ) {
		if ( view.uplinkMbeNativeAssignmentBusy ) {
			return;
		}
		view.uplinkMbeNativeAssignmentBusy = true;
		const clear = 'uncategorized' === target.dataset.filter;
		try {
			await request( 'uplink_mbe_assign_media', {
				media_ids: JSON.stringify( ids ),
				collection: clear ? 0 : Number( target.dataset.collection ),
				mode: clear ? 'clear' : 'add',
			} );
			await refreshCollectionState( view, panel );
			if ( view.uplinkMbeNativeListMode ) {
				if ( clear && 'all' !== view.uplinkMbeNativeCollectionsFilter?.filter ) {
					window.location.reload();
					return;
				}
			} else {
				view.collection.props.set( 'uplink_mbe_refresh', Date.now() );
			}
			showPanelStatus( panel, ( clear ? config.strings.cleared : config.strings.assigned ).replace( '%d', String( ids.length ) ) );
		} catch ( error ) {
			showPanelStatus( panel, error.message, 'error' );
		} finally {
			view.uplinkMbeNativeAssignmentBusy = false;
		}
	}

	function enableJqueryDropTargets( view, panel ) {
		if ( ! view.uplinkMbeNativeDragAssignment || ! $.fn.droppable ) {
			return;
		}
		panel.querySelectorAll( '.uplink-mbe-native-collection-filter:not([data-filter="all"])' ).forEach( ( target ) => {
			$( target ).droppable( {
				accept: '.attachment[data-id], .uplink-mbe-native-list-attachment[data-id]',
				scope: 'uplink-mbe-native-collections',
				tolerance: 'pointer',
				over: () => target.classList.add( 'is-drop-target' ),
				out: () => target.classList.remove( 'is-drop-target' ),
				drop: ( event, ui ) => {
					target.classList.remove( 'is-drop-target' );
					const attachment = ui.draggable?.[ 0 ];
					const ids = attachment ? draggedAttachmentIds( view, attachment ) : [];
					if ( ids.length ) {
						assignDroppedMedia( view, panel, target, ids );
					}
				},
			} );
		} );
	}

	function renderPanelBody( panel, view ) {
		const body = panel.querySelector( '.uplink-mbe-native-collections-body' );
		const contents = [
			makeFilterButton( config.strings.allMedia, config.counts.all, 'all' ),
			makeFilterButton( config.strings.uncategorized, config.counts.uncategorized, 'uncategorized' ),
			collectionBranch( view, panel )
		];
		if ( view.uplinkMbeNativeDragAssignment ) {
			const help = document.createElement( 'p' );
			help.className = 'uplink-mbe-native-assignment-help';
			help.textContent = config.strings.assignHelp;
			contents.push( help );
		}
		body.replaceChildren( ...contents );
		enableJqueryDropTargets( view, panel );
		const active = view.uplinkMbeNativeCollectionsFilter || { filter: 'all', collectionId: 0 };
		refreshActiveFilters( panel, active.filter, active.collectionId );
	}

	function showPanelStatus( panel, message, type = 'success' ) {
		const status = panel.querySelector( '.uplink-mbe-native-panel-status' );
		status.textContent = message;
		status.classList.toggle( 'is-error', 'error' === type );
		status.hidden = false;
		window.clearTimeout( status.uplinkMbeHideTimeout );
		status.uplinkMbeHideTimeout = window.setTimeout( () => {
			status.hidden = true;
		}, 4000 );
	}

	async function refreshCollectionState( view, panel ) {
		const state = await request( 'uplink_mbe_native_collections_state' );
		setCollectionData( state.collections );
		config.counts = state.counts || config.counts;
		renderPanelBody( panel, view );
		panel.querySelectorAll( '.uplink-mbe-native-collection-parent' ).forEach( ( select ) => fillCollectionSelect( select, false, true ) );
		document.querySelectorAll( '.uplink-mbe-native-upload-select' ).forEach( ( select ) => fillCollectionSelect( select, true ) );
	}

	function enableDragAssignment( view, panel ) {
		if ( ! config.canAssign ) {
			return;
		}
		const targetAtPoint = ( x, y ) => document.elementFromPoint( x, y )?.closest( '.uplink-mbe-native-collection-filter:not([data-filter="all"])' );
		const markAttachments = () => {
			if ( view.uplinkMbeNativeListMode ) {
				view.el.querySelectorAll( '#the-list tr[id^="post-"]' ).forEach( ( row ) => {
					row.classList.add( 'uplink-mbe-native-list-attachment' );
					row.dataset.id = row.id.replace( /^post-/, '' );
				} );
			}
			view.el.querySelectorAll( '.attachment[data-id], .uplink-mbe-native-list-attachment[data-id]' ).forEach( ( attachment ) => {
				if ( attachment.dataset.uplinkMbeDragReady ) {
					return;
				}
				attachment.dataset.uplinkMbeDragReady = '1';
				attachment.draggable = true;
				if ( ! view.uplinkMbeNativeListMode && $.fn.draggable ) {
					$( attachment ).draggable( {
						appendTo: document.body,
						helper: () => dragAssignmentHelper( view, attachment ),
						revert: 'invalid',
						scope: 'uplink-mbe-native-collections',
						zIndex: 100000,
						start: () => attachment.classList.add( 'uplink-mbe-native-is-dragging' ),
						stop: ( event ) => {
							attachment.classList.remove( 'uplink-mbe-native-is-dragging' );
							const target = targetAtPoint( event.clientX, event.clientY );
							const ids = draggedAttachmentIds( view, attachment );
							if ( target && ids.length ) {
								assignDroppedMedia( view, panel, target, ids );
							}
						},
					} );
				}
			} );
		};
		markAttachments();
		const observer = new MutationObserver( markAttachments );
		observer.observe( view.el, { childList: true, subtree: true } );

		let pointerDrag = null;
		const clearPointerDrag = () => {
			pointerDrag?.attachment.classList.remove( 'uplink-mbe-native-is-dragging' );
			panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
			pointerDrag = null;
		};
		view.el.addEventListener( 'pointerdown', ( event ) => {
			const attachment = attachmentFromTarget( view, event.target );
			if ( ! attachment || 0 !== event.button ) {
				return;
			}
			pointerDrag = {
				attachment,
				ids: draggedAttachmentIds( view, attachment ),
				startX: event.clientX,
				startY: event.clientY,
				active: false,
			};
		} );
		document.addEventListener( 'pointermove', ( event ) => {
			if ( ! pointerDrag ) {
				return;
			}
			if ( ! pointerDrag.active && Math.hypot( event.clientX - pointerDrag.startX, event.clientY - pointerDrag.startY ) > 6 ) {
				pointerDrag.active = true;
				pointerDrag.attachment.classList.add( 'uplink-mbe-native-is-dragging' );
			}
			if ( pointerDrag.active ) {
				const target = targetAtPoint( event.clientX, event.clientY );
				panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.toggle( 'is-drop-target', item === target ) );
			}
		}, true );
		document.addEventListener( 'pointerup', ( event ) => {
			if ( ! pointerDrag ) {
				return;
			}
			const drag = pointerDrag;
			const target = drag.active ? targetAtPoint( event.clientX, event.clientY ) : null;
			clearPointerDrag();
			if ( target && drag.ids.length ) {
				event.preventDefault();
				event.stopPropagation();
				assignDroppedMedia( view, panel, target, drag.ids );
			}
		}, true );
		document.addEventListener( 'pointercancel', clearPointerDrag, true );

		view.el.addEventListener( 'dragstart', ( event ) => {
			const attachment = attachmentFromTarget( view, event.target );
			if ( ! attachment || ! event.dataTransfer ) {
				return;
			}
			const ids = draggedAttachmentIds( view, attachment );
			event.dataTransfer.effectAllowed = 'copy';
			event.dataTransfer.setData( 'text/plain', JSON.stringify( ids ) );
			attachment.classList.add( 'uplink-mbe-native-is-dragging' );
		} );
		view.el.addEventListener( 'dragend', () => {
			view.el.querySelectorAll( '.uplink-mbe-native-is-dragging' ).forEach( ( item ) => item.classList.remove( 'uplink-mbe-native-is-dragging' ) );
			panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
		} );
		panel.addEventListener( 'dragover', ( event ) => {
			const target = event.target.closest( '.uplink-mbe-native-collection-filter:not([data-filter="all"])' );
			if ( target ) {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'copy';
				panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.toggle( 'is-drop-target', item === target ) );
			}
		} );
		panel.addEventListener( 'dragleave', ( event ) => {
			if ( ! panel.contains( event.relatedTarget ) ) {
				panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
			}
		} );
		panel.addEventListener( 'drop', async ( event ) => {
			const target = event.target.closest( '.uplink-mbe-native-collection-filter:not([data-filter="all"])' );
			if ( ! target ) {
				return;
			}
			event.preventDefault();
			panel.querySelectorAll( '.is-drop-target' ).forEach( ( item ) => item.classList.remove( 'is-drop-target' ) );
			let ids = [];
			try {
				ids = JSON.parse( event.dataTransfer.getData( 'text/plain' ) );
			} catch ( error ) {
				return;
			}
			ids = Array.isArray( ids ) ? ids.map( Number ).filter( Boolean ) : [];
			if ( ! ids.length ) {
				return;
			}
			await assignDroppedMedia( view, panel, target, ids );
		} );
	}

	function createPanel( view ) {
		if ( ! view?.el || view.uplinkMbeNativeCollectionsPanel?.isConnected ) {
			return;
		}
		if ( ! view.el.isConnected ) {
			if ( ! view.uplinkMbeNativeCollectionsPending ) {
				view.uplinkMbeNativeCollectionsPending = true;
				window.requestAnimationFrame( () => {
					view.uplinkMbeNativeCollectionsPending = false;
					if ( view.el?.isConnected ) {
						createPanel( view );
					}
				} );
			}
			return;
		}

		const modal = view.el.closest( '.media-modal' );
		if ( modal ) {
			const frameContent = modal.querySelector( '.media-frame-content' );
			if ( frameContent && ! frameContent.contains( view.el ) ) {
				return;
			}
			modal.querySelectorAll( '.uplink-mbe-native-collections-panel' ).forEach( ( existingPanel ) => {
				if ( ! view.el.contains( existingPanel ) ) {
					existingPanel.uplinkMbeResizeObserver?.disconnect();
					existingPanel.parentElement?.classList.remove( 'uplink-mbe-native-collections-browser', 'uplink-mbe-native-collections-collapsed', 'uplink-mbe-native-collections-compact' );
					existingPanel.remove();
				}
			} );
		}

		const libraryRoot = view.el.closest( '#wp-media-grid' );
		const isListMode = Boolean( view.uplinkMbeNativeListMode );
		const isStandaloneLibrary = isListMode || Boolean( libraryRoot && ! view.el.closest( '.media-modal' ) );
		const layoutHost = isStandaloneLibrary ? ( libraryRoot?.closest( '#wpbody-content' ) || document.querySelector( '#wpbody-content' ) ) : view.el;
		if ( isStandaloneLibrary ) {
			document.body.classList.add( 'uplink-mbe-native-collections-active' );
		}
		const oldPanel = layoutHost.querySelector( ':scope > .uplink-mbe-native-collections-panel' );
		if ( oldPanel ) {
			oldPanel.uplinkMbeResizeObserver?.disconnect();
			oldPanel.remove();
		}

		if ( isStandaloneLibrary ) {
			layoutHost.classList.add( 'uplink-mbe-native-collections-page' );
		} else {
			layoutHost.classList.add( 'uplink-mbe-native-collections-browser' );
		}

		const panel = document.createElement( 'aside' );
		panel.className = 'uplink-mbe-native-collections-panel is-open';
		panel.setAttribute( 'aria-label', config.strings.collections );

		const heading = document.createElement( 'div' );
		heading.className = 'uplink-mbe-native-collections-heading';
		const title = document.createElement( 'strong' );
		title.textContent = config.strings.collections;
		const collapse = document.createElement( 'button' );
		collapse.type = 'button';
		collapse.className = 'uplink-mbe-native-panel-toggle';
		collapse.setAttribute( 'aria-expanded', 'true' );
		collapse.setAttribute( 'aria-label', config.strings.collapse );
		collapse.innerHTML = '<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>';
		const headingActions = document.createElement( 'div' );
		headingActions.className = 'uplink-mbe-native-panel-actions';
		let newCollection = null;
		if ( config.canManage ) {
			newCollection = iconButton( 'uplink-mbe-native-new-collection', config.strings.newCollection, 'category' );
			newCollection.setAttribute( 'aria-expanded', 'false' );
			headingActions.append( newCollection );
		}
		headingActions.append( collapse );
		heading.append( title, headingActions );

		const creationForm = document.createElement( 'form' );
		creationForm.className = 'uplink-mbe-native-collection-form';
		creationForm.hidden = true;
		const nameLabel = document.createElement( 'label' );
		nameLabel.textContent = config.strings.collectionName;
		const nameInput = document.createElement( 'input' );
		nameInput.type = 'text';
		nameInput.required = true;
		nameInput.maxLength = 200;
		nameLabel.append( nameInput );
		const parentLabel = document.createElement( 'label' );
		parentLabel.textContent = config.strings.parentCollection;
		const parentSelect = document.createElement( 'select' );
		parentSelect.className = 'uplink-mbe-native-collection-parent';
		fillCollectionSelect( parentSelect, false, true );
		parentLabel.append( parentSelect );
		const formActions = document.createElement( 'div' );
		formActions.className = 'uplink-mbe-native-collection-form-actions';
		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = config.strings.cancel;
		const submit = document.createElement( 'button' );
		submit.type = 'submit';
		submit.className = 'button button-primary';
		submit.textContent = config.strings.createCollection;
		formActions.append( cancel, submit );
		creationForm.append( nameLabel, parentLabel, formActions );

		const status = document.createElement( 'div' );
		status.className = 'uplink-mbe-native-panel-status';
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		status.hidden = true;

		const body = document.createElement( 'div' );
		body.className = 'uplink-mbe-native-collections-body';
		panel.append( heading, creationForm, status, body );
		layoutHost.prepend( panel );
		view.uplinkMbeNativeCollectionsPanel = panel;
		view.uplinkMbeNativeDragAssignment = isStandaloneLibrary && config.canAssign;
		if ( isListMode ) {
			const params = new URLSearchParams( window.location.search );
			const collectionId = Number( params.get( 'uplink_mbe_collection' ) ) || 0;
			const filter = collectionId ? 'collection' : ( params.has( 'uplink_mbe_uncategorized' ) ? 'uncategorized' : 'all' );
			view.uplinkMbeNativeCollectionsFilter = { filter, collectionId };
			[ 'uplink_mbe_collection', 'uplink_mbe_uncategorized' ].forEach( ( name ) => {
				view.el.querySelectorAll( `input[name="${ name }"]` ).forEach( ( input ) => input.remove() );
			} );
			if ( 'all' !== filter ) {
				const input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = 'collection' === filter ? 'uplink_mbe_collection' : 'uplink_mbe_uncategorized';
				input.value = 'collection' === filter ? String( collectionId ) : '1';
				view.el.append( input );
			}
		} else {
			view.uplinkMbeNativeCollectionsFilter = { filter: 'all', collectionId: 0 };
		}
		renderPanelBody( panel, view );
		if ( isStandaloneLibrary ) {
			enableDragAssignment( view, panel );
		}

		if ( newCollection ) {
			const openCollectionForm = ( collection = null, parent = 0 ) => {
				creationForm.hidden = false;
				creationForm.dataset.termId = String( Number( collection?.id ) || 0 );
				nameInput.value = collection?.name || '';
				fillCollectionSelect( parentSelect, false, true, collection );
				parentSelect.value = String( Number( collection?.parent ) || Number( parent ) || 0 );
				newCollection.setAttribute( 'aria-expanded', 'true' );
				nameInput.focus();
			};
			const closeCreationForm = () => {
				creationForm.hidden = true;
				creationForm.dataset.termId = '0';
				creationForm.reset();
				newCollection.setAttribute( 'aria-expanded', 'false' );
			};
			newCollection.addEventListener( 'click', () => {
				if ( creationForm.hidden ) {
					openCollectionForm();
				} else {
					closeCreationForm();
				}
			} );
			cancel.addEventListener( 'click', closeCreationForm );
			creationForm.addEventListener( 'submit', async ( event ) => {
				event.preventDefault();
				const name = nameInput.value.trim();
				if ( ! name ) {
					nameInput.focus();
					return;
				}
				submit.disabled = true;
				try {
					const termId = Number( creationForm.dataset.termId ) || 0;
					await request( 'uplink_mbe_save_collection', { term_id: termId, name, parent: Number( parentSelect.value ) || 0 } );
					await refreshCollectionState( view, panel );
					closeCreationForm();
					showPanelStatus( panel, termId ? config.strings.collectionUpdated : config.strings.collectionCreated );
				} catch ( error ) {
					showPanelStatus( panel, error.message, 'error' );
				} finally {
					submit.disabled = false;
				}
			} );
			panel.addEventListener( 'click', async ( event ) => {
				const actionButton = event.target.closest( '[data-collection-action]' );
				if ( ! actionButton ) {
					return;
				}
				const collection = config.collections.find( ( item ) => Number( item.id ) === Number( actionButton.dataset.collection ) );
				if ( ! collection ) {
					return;
				}
				if ( 'add-child' === actionButton.dataset.collectionAction ) {
					openCollectionForm( null, collection.id );
					return;
				}
				if ( 'edit' === actionButton.dataset.collectionAction ) {
					openCollectionForm( collection );
					return;
				}
				if ( 'delete' !== actionButton.dataset.collectionAction ) {
					return;
				}
				const children = childrenByParent.get( Number( collection.id ) ) || [];
				const confirmation = ( children.length ? config.strings.confirmDeleteWithChildren : config.strings.confirmDelete ).replace( '%s', collection.name );
				if ( ! window.confirm( confirmation ) ) {
					return;
				}
				actionButton.disabled = true;
				try {
					await request( 'uplink_mbe_delete_collection', {
						term_id: collection.id,
						child_action: children.length ? 'promote' : '',
					} );
					if ( 'collection' === view.uplinkMbeNativeCollectionsFilter?.filter && Number( view.uplinkMbeNativeCollectionsFilter.collectionId ) === Number( collection.id ) ) {
						applyFilter( view, 'all', 0 );
					}
					await refreshCollectionState( view, panel );
					showPanelStatus( panel, config.strings.collectionDeleted );
				} catch ( error ) {
					actionButton.disabled = false;
					showPanelStatus( panel, error.message, 'error' );
				}
			} );
		}
		let panelUserToggled = false;
		const setCompactLayout = ( compact ) => {
			layoutHost.classList.toggle( 'uplink-mbe-native-collections-compact', compact );
			if ( compact && ! panelUserToggled ) {
				panel.classList.remove( 'is-open' );
				layoutHost.classList.add( 'uplink-mbe-native-collections-collapsed' );
				collapse.setAttribute( 'aria-expanded', 'false' );
				collapse.setAttribute( 'aria-label', config.strings.expand );
				collapse.querySelector( '.dashicons' ).className = 'dashicons dashicons-arrow-right-alt2';
			} else if ( ! compact && ! panelUserToggled ) {
				panel.classList.add( 'is-open' );
				layoutHost.classList.remove( 'uplink-mbe-native-collections-collapsed' );
				collapse.setAttribute( 'aria-expanded', 'true' );
				collapse.setAttribute( 'aria-label', config.strings.collapse );
				collapse.querySelector( '.dashicons' ).className = 'dashicons dashicons-arrow-left-alt2';
			}
		};
		setCompactLayout( layoutHost.getBoundingClientRect().width < 820 );
		if ( window.ResizeObserver ) {
			const observer = new ResizeObserver( () => {
				setCompactLayout( layoutHost.getBoundingClientRect().width < 820 );
			} );
			observer.observe( layoutHost );
			panel.uplinkMbeResizeObserver = observer;
		}

		collapse.addEventListener( 'click', () => {
			panelUserToggled = true;
			const open = ! panel.classList.contains( 'is-open' );
			panel.classList.toggle( 'is-open', open );
			layoutHost.classList.toggle( 'uplink-mbe-native-collections-collapsed', ! open );
			collapse.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			collapse.setAttribute( 'aria-label', open ? config.strings.collapse : config.strings.expand );
			collapse.querySelector( '.dashicons' ).className = `dashicons ${ open ? 'dashicons-arrow-left-alt2' : 'dashicons-arrow-right-alt2' }`;
			view.controller?.uploader?.refresh?.();
		} );
		panel.addEventListener( 'click', ( event ) => {
			const branchToggle = event.target.closest( '.uplink-mbe-native-branch-toggle' );
			if ( branchToggle ) {
				const item = branchToggle.closest( 'li' );
				const childList = item.querySelector( ':scope > .uplink-mbe-native-collection-tree' );
				const collectionId = Number( item.querySelector( '.uplink-mbe-native-collection-filter' )?.dataset.collection ) || 0;
				const expanded = 'false' === branchToggle.getAttribute( 'aria-expanded' );
				if ( expanded ) {
					collapsedCollections.delete( collectionId );
				} else {
					collapsedCollections.add( collectionId );
				}
				toggleBranch( item, branchToggle, childList, expanded );
				return;
			}
			const filterButton = event.target.closest( '.uplink-mbe-native-collection-filter' );
			if ( filterButton ) {
				if ( suppressCollectionClick ) {
					event.preventDefault();
					return;
				}
				applyFilter( view, filterButton.dataset.filter, Number( filterButton.dataset.collection ) || 0 );
			}
		} );
	}

	function updateUploadParams() {
		if ( window.wpUploaderInit?.multipart_params ) {
			window.wpUploaderInit.multipart_params.uplink_mbe_collection = uploadCollection;
		}
		if ( window.uploader?.settings?.multipart_params ) {
			window.uploader.settings.multipart_params.uplink_mbe_collection = uploadCollection;
		}
		uploaders.forEach( ( uploader ) => uploader.param?.( 'uplink_mbe_collection', uploadCollection ) );
	}

	function createDestinationSelect() {
		const select = document.createElement( 'select' );
		select.className = 'uplink-mbe-native-upload-select';
		select.setAttribute( 'aria-label', config.strings.destination );
		fillCollectionSelect( select, true );
		select.value = String( uploadCollection );
		select.addEventListener( 'change', () => {
			uploadCollection = Number( select.value ) || 0;
			document.querySelectorAll( '.uplink-mbe-native-upload-select' ).forEach( ( other ) => {
				if ( other !== select ) {
					other.value = String( uploadCollection );
				}
			} );
			updateUploadParams();
		} );
		return select;
	}

	function addUploadDestination( root ) {
		if ( ! config.canAssign || ! root || root.querySelector( '.uplink-mbe-native-upload-destination' ) ) {
			return;
		}
		const field = document.createElement( 'div' );
		field.className = 'uplink-mbe-native-upload-destination';
		const label = document.createElement( 'label' );
		label.textContent = config.strings.destination;
		label.append( createDestinationSelect() );
		const help = document.createElement( 'p' );
		help.textContent = config.strings.uploadHelp;
		field.append( label, help );
		root.prepend( field );
	}

	function defaultInserterToLibrary( modal ) {
		if ( ! modal || modal.dataset.uplinkMbeDefaultLibraryApplied || ! ( modal.offsetWidth || modal.offsetHeight || modal.getClientRects().length ) ) {
			return;
		}
		const libraryTab = modal.querySelector( '#menu-item-browse, #menu-item-library' );
		const uploadTab = modal.querySelector( '#menu-item-upload' );
		if ( ! libraryTab || ! uploadTab ) {
			return;
		}
		modal.dataset.uplinkMbeDefaultLibraryApplied = '1';
		if ( uploadTab.classList.contains( 'active' ) || 'true' === uploadTab.getAttribute( 'aria-selected' ) ) {
			libraryTab.click();
		}
	}

	function defaultVisibleInsertersToLibrary() {
		document.querySelectorAll( '.media-modal' ).forEach( defaultInserterToLibrary );
	}
	let defaultLibraryPending = false;
	function scheduleDefaultInserter() {
		if ( defaultLibraryPending ) {
			return;
		}
		defaultLibraryPending = true;
		window.requestAnimationFrame( () => {
			defaultLibraryPending = false;
			defaultVisibleInsertersToLibrary();
		} );
	}

	document.addEventListener( 'click', ( event ) => {
		const closingModal = event.target instanceof Element ? event.target.closest( '.media-modal-close' )?.closest( '.media-modal' ) : null;
		if ( closingModal ) {
			delete closingModal.dataset.uplinkMbeDefaultLibraryApplied;
		}
		scheduleDefaultInserter();
	}, true );

	if ( window.MutationObserver ) {
		const modalObserver = new MutationObserver( ( mutations ) => {
			const modalChanged = mutations.some( ( mutation ) => {
				if ( mutation.target instanceof Element && mutation.target.closest( '.media-modal' ) ) {
					return true;
				}
				return Array.from( mutation.addedNodes ).some( ( node ) => node instanceof Element && ( node.matches( '.media-modal' ) || node.querySelector( '.media-modal' ) ) );
			} );
			if ( modalChanged ) {
				scheduleDefaultInserter();
			}
		} );
		modalObserver.observe( document.body, { childList: true, subtree: true } );
	}

	const uploaderPrototype = wp.Uploader?.prototype;
	if ( uploaderPrototype?.init && ! uploaderPrototype.uplinkMbeNativeCollectionsPatched ) {
		uploaderPrototype.uplinkMbeNativeCollectionsPatched = true;
		const originalUploaderInit = uploaderPrototype.init;
		uploaderPrototype.init = function () {
			uploaders.add( this );
			this.param?.( 'uplink_mbe_collection', uploadCollection );
			return originalUploaderInit.apply( this, arguments );
		};
	}

	const browserPrototype = wp.media.view?.AttachmentsBrowser?.prototype;
	if ( browserPrototype?.render && ! browserPrototype.uplinkMbeNativeCollectionsPatched ) {
		browserPrototype.uplinkMbeNativeCollectionsPatched = true;
		const originalBrowserRender = browserPrototype.render;
		browserPrototype.render = function () {
			const result = originalBrowserRender.apply( this, arguments );
			createPanel( this );
			return result;
		};
	}

	const uploaderInlinePrototype = wp.media.view?.UploaderInline?.prototype;
	if ( uploaderInlinePrototype?.render && ! uploaderInlinePrototype.uplinkMbeNativeCollectionsPatched ) {
		uploaderInlinePrototype.uplinkMbeNativeCollectionsPatched = true;
		const originalUploaderRender = uploaderInlinePrototype.render;
		uploaderInlinePrototype.render = function () {
			const result = originalUploaderRender.apply( this, arguments );
			addUploadDestination( this.el.querySelector( '.uploader-inline-content' ) || this.el );
			return result;
		};
	}

	$( () => {
		defaultVisibleInsertersToLibrary();
		const listForm = document.querySelector( 'body.upload-php #posts-filter' );
		if ( listForm && ! document.querySelector( '#wp-media-grid' ) ) {
			createPanel( { el: listForm, uplinkMbeNativeListMode: true } );
		}
		const nativeUpload = document.querySelector( '#plupload-upload-ui' );
		if ( nativeUpload ) {
			addUploadDestination( nativeUpload );
		}
		updateUploadParams();
	} );
}( window.jQuery, window.wp ) );
