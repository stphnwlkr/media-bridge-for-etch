( function () {
	'use strict';
	const instances = new WeakMap();
	const strings = window.uplinkMbeExifMapStrings || {};
	function valid( location ) {
		return location && typeof location.latitude === 'number' && Number.isFinite( location.latitude ) && Math.abs( location.latitude ) <= 90
			&& typeof location.longitude === 'number' && Number.isFinite( location.longitude ) && Math.abs( location.longitude ) <= 180;
	}
	function dispose( panel ) {
		const state = panel && instances.get( panel );
		if ( ! state ) return;
		state.observer?.disconnect();
		state.resize?.disconnect();
		state.map?.remove();
		state.section.remove();
		instances.delete( panel );
	}
	function refresh( panel ) {
		const state = panel && instances.get( panel );
		if ( ! state || ! panel.isConnected || panel.closest( '[hidden]' ) || ! state.canvas.getClientRects().length || ! window.L ) return;
		if ( state.map ) { state.map.invalidateSize( { pan: false } ); return; }
		const point = [ state.location.latitude, state.location.longitude ];
		const satellite = L.tileLayer( 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
			maxZoom: 19, referrerPolicy: 'strict-origin-when-cross-origin', attribution: 'Source: <a href="https://www.esri.com/">Esri</a>, Vantor, Earthstar Geographics, and the GIS User Community',
		} );
		const streets = L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19, referrerPolicy: 'strict-origin-when-cross-origin', attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
		} );
		for ( const layer of [ satellite, streets ] ) {
			layer.on( 'tileerror', () => { state.status.hidden = false; } );
			layer.on( 'load', () => { if ( state.canvas.querySelector( '.leaflet-tile-loaded' ) ) state.status.hidden = true; } );
		}
		state.map = L.map( state.canvas, { center: point, zoom: 15, layers: [ satellite ], scrollWheelZoom: false } );
		L.circleMarker( point, { radius: 7, weight: 3, color: '#ffffff', fillColor: '#1475e1', fillOpacity: 1 } ).addTo( state.map ).bindTooltip( strings.location || 'Photo location' );
		L.control.layers( { [ strings.satellite || 'Satellite' ]: satellite, [ strings.streets || 'OpenStreetMap' ]: streets }, null, { collapsed: false } ).addTo( state.map );
	}
	function render( panel, location ) {
		dispose( panel );
		if ( ! panel || ! valid( location ) ) return;
		const section = document.createElement( 'section' );
		section.className = 'uplink-mbe-exif-map';
		const heading = document.createElement( 'h3' );
		heading.textContent = strings.location || 'Photo location';
		const canvas = document.createElement( 'div' );
		canvas.className = 'uplink-mbe-exif-map-canvas';
		canvas.setAttribute( 'role', 'region' );
		canvas.setAttribute( 'aria-label', strings.location || 'Photo location' );
		const link = document.createElement( 'a' );
		link.textContent = strings.open || 'Open in OpenStreetMap';
		const latitude = Number( location.latitude.toFixed( 6 ) );
		const longitude = Number( location.longitude.toFixed( 6 ) );
		link.href = `https://www.openstreetmap.org/?mlat=${ latitude }&mlon=${ longitude }#map=16/${ latitude }/${ longitude }`;
		link.target = '_blank'; link.rel = 'noopener noreferrer';
		const status = document.createElement( 'p' );
		status.className = 'uplink-mbe-exif-map-status';
		status.setAttribute( 'role', 'status' );
		status.hidden = true; status.textContent = strings.error || 'Map imagery could not be loaded.';
		section.append( heading, canvas, status, link ); panel.append( section );
		const state = { section, canvas, status, location, map: null };
		instances.set( panel, state );
		if ( window.IntersectionObserver ) {
			state.observer = new IntersectionObserver( entries => { if ( entries.some( entry => entry.isIntersecting ) ) refresh( panel ); } );
			state.observer.observe( canvas );
		}
		if ( window.ResizeObserver ) { state.resize = new ResizeObserver( () => refresh( panel ) ); state.resize.observe( canvas ); }
		refresh( panel );
	}
	window.uplinkMbeExifMap = { render, refresh, dispose };
}() );
