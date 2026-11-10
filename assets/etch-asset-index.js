( function () {
	'use strict';

	const config = window.uplinkMbeEtchAssetManager || {};
	const listeners = new Set();
	let generation = 0;
	let cached;
	let pending;
	let expires = 0;
	let retryAfter = 0;

	function invalidate() {
		generation += 1;
		cached = undefined;
		expires = 0;
		retryAfter = 0;
		listeners.forEach( function ( listener ) { listener(); } );
	}

	function normalize( assets, collections = null ) {
		if ( ! Array.isArray( assets ) ) {
			throw new Error( 'Invalid asset index.' );
		}
		const normalized = assets.map( function ( asset ) {
			return {
				id: Number( asset.id ),
				fileName: asset.fileName || '',
				mimeType: asset.mimeType || '',
				fontUrl: asset.fontUrl || asset.url || '',
				searchName: asset.searchName ?? asset.fileName ?? '',
				altText: asset.altText ?? asset.alt ?? '',
				collectionIds: Array.isArray( asset.collectionIds ) ? asset.collectionIds.map( Number ) : null,
			};
		} ).filter( function ( asset ) { return Number.isSafeInteger( asset.id ) && asset.id > 0; } );
		const byId = new Map();
		const byUrl = new Map();
		const byPath = new Map();
		function addUnique( map, key, asset ) {
			map.set( key, map.has( key ) ? undefined : asset );
		}
		normalized.forEach( function ( asset ) {
			byId.set( asset.id, asset );
			if ( asset.fontUrl ) {
				try {
					const url = new URL( asset.fontUrl, window.location.href );
					addUnique( byUrl, url.href, asset );
					addUnique( byPath, url.pathname, asset );
				} catch ( error ) { /* An invalid URL cannot identify a tile. */ }
			}
		} );
		return { assets: normalized, byId, byUrl, byPath, collections };
	}

	async function readIndex() {
		const etch = window.etch;
		const capabilities = etch?.environment?.capabilities;
		if ( etch?.assets && typeof etch.assets.listAsync === 'function' &&
			( capabilities?.assets === true || capabilities?.assetsRead === true ) ) {
			try {
				// Etch refreshes its own store too. Do not assume its oldest-first
				// result order matches the grid, and never use its compression API.
				const canReadCollections = typeof etch.assets.listCollectionsAsync === 'function' &&
					( capabilities.assets === true || capabilities.assetsCollections === true );
				const [ assets, collections ] = await Promise.all( [
					etch.assets.listAsync(),
					canReadCollections ? Promise.resolve().then( function () { return etch.assets.listCollectionsAsync(); } ).catch( function () { return null; } ) : null,
				] );
				return normalize( assets, collections );
			} catch ( error ) {
				// A failed request is not evidence that a capability is absent.
				if ( error?.code !== 'NOT_AVAILABLE' ) {
					throw error;
				}
			}
		}
		if ( ! config.ajaxUrl || ! config.nonce ) {
			throw new Error( 'Asset index is unavailable.' );
		}
		const response = await window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams( { action: 'uplink_mbe_etch_asset_manifest', nonce: config.nonce } ).toString(),
		} );
		if ( ! response.ok ) {
			throw new Error( 'Asset index request failed.' );
		}
		const result = await response.json();
		if ( ! result?.success ) {
			throw new Error( 'Asset index is unavailable.' );
		}
		return normalize( result.data?.assets, result.data?.collections );
	}

	function getIndex() {
		if ( cached && Date.now() < expires ) {
			return Promise.resolve( cached );
		}
		if ( pending ) {
			return pending;
		}
		if ( Date.now() < retryAfter ) {
			return Promise.resolve( null );
		}
		const revision = generation;
		pending = readIndex().then( function ( index ) {
			if ( revision !== generation ) {
				return null;
			}
			cached = index;
			expires = Date.now() + 30000;
			return index;
		} ).catch( function () {
			if ( revision === generation ) {
				retryAfter = Date.now() + 3000;
			}
			return null;
		} ).finally( function () {
			pending = undefined;
			if ( revision !== generation ) {
				listeners.forEach( function ( listener ) { listener(); } );
			}
		} );
		return pending;
	}

	function isMediaWrite( input, options ) {
		const method = String( options?.method || input?.method || 'GET' ).toUpperCase();
		if ( [ 'GET', 'HEAD', 'OPTIONS' ].includes( method ) ) {
			return false;
		}
		try {
			const url = new URL( typeof input === 'string' || input instanceof URL ? input : input.url, window.location.href );
			if ( url.origin !== window.location.origin ) {
				return false;
			}
			const route = url.searchParams.get( 'rest_route' ) || url.pathname;
			return /\/(?:wp\/v2\/(?:media|etch-collections)|etch-api\/replace)(?:\/\d+)?(?:\/(?:sideload|finalize))?\/?$/.test( route );
		} catch ( error ) {
			return false;
		}
	}

	// Etch's UI and Connector both use fetch for these REST writes. Observe the
	// result without reading request bodies, cloning responses or retrying writes.
	const originalFetch = window.fetch;
	window.fetch = function ( input, options ) {
		const write = isMediaWrite( input, options );
		return originalFetch.apply( this, arguments ).then( function ( response ) {
			if ( write && response.ok ) {
				invalidate();
			}
			return response;
		} );
	};
	window.addEventListener( 'focus', invalidate );
	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'visible' ) {
			invalidate();
		}
	} );
	document.addEventListener( 'uplink-mbe:assets-changed', invalidate );
	window.uplinkMbeEtchAssetIndex = { getIndex, invalidate, subscribe: function ( listener ) {
		listeners.add( listener );
		return function () { listeners.delete( listener ); };
	} };
}() );
