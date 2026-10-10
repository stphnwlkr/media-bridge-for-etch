( function () {
	'use strict';
	const initialized = new Map();
	const selector = '.wp-block-uplinkpress-collection-gallery.is-layout-slider';

	function initialize( root = document ) {
		const galleries = Array.from( root.querySelectorAll( selector ) );
		if ( root.matches?.( selector ) ) galleries.unshift( root );
		galleries.forEach( ( gallery ) => {
			const track = gallery.querySelector( ':scope > .wp-block-gallery' );
			const controls = gallery.querySelector( ':scope > .uplink-mbe-slider-controls' );
			if ( ! track || initialized.has( track ) ) return;
			const slides = Array.from( track.children ).filter( ( child ) => child.classList.contains( 'wp-block-image' ) );
			track.style.setProperty( '--uplink-mbe-carousel-total', slides.length );
			if ( ! controls || slides.length < 2 ) return;
			const view = gallery.ownerDocument.defaultView;
			const events = new view.AbortController();
			track.classList.add( 'is-slider-ready' );
			const previous = controls.querySelector( '[data-slider-prev]' );
			const next = controls.querySelector( '[data-slider-next]' );
			const status = controls.querySelector( '[data-slider-status]' );
			const play = controls.querySelector( '[data-slider-play]' );
			const motion = view.matchMedia( '(prefers-reduced-motion: reduce)' );
			const interval = Math.max( 2000, Math.min( 20000, Number( gallery.dataset.sliderInterval ) || 5000 ) );
			let playing = gallery.dataset.sliderAutoplay === 'true' && ! motion.matches;
			let hovered = false;
			let onscreen = false;
			let playbackTimer;
			let progressAnimation;
			const progress = controls.querySelector( '.uplink-mbe-slider-progress > span' );

			let current = 0;
			let visible = 1;
			let timer;
			let lastWidth = 0;
			track.setAttribute( 'aria-label', gallery.getAttribute( 'aria-label' ) || '' );

			function syncPlayback() {
				view.clearTimeout( playbackTimer );
				progressAnimation?.cancel();
				if ( play ) {
					play.setAttribute( 'aria-pressed', String( playing ) );
					play.setAttribute( 'aria-label', playing ? play.dataset.pauseLabel : play.dataset.playLabel );
					const label = play.querySelector( '.uplink-mbe-slider-label' );
					const text = playing ? play.dataset.pauseText : play.dataset.playText;
					if ( label.textContent !== text ) label.textContent = text;
				}
				status.setAttribute( 'aria-live', playing ? 'off' : 'polite' );
				if ( ! playing || hovered || ! onscreen || gallery.ownerDocument.hidden || slides.length <= visible ) return;
				progressAnimation = progress?.animate( [ { transform: 'scaleX(0)' }, { transform: 'scaleX(1)' } ], { duration: interval, fill: 'forwards', easing: 'linear' } );
				playbackTimer = view.setTimeout( () => {
					const wrap = current >= slides.length - visible;
					move( wrap ? 0 : current + 1, wrap );
					syncPlayback();
				}, interval );
			}
			function stopPlayback() { playing = false; syncPlayback(); }
			function measure() {
				if ( ! track.clientWidth ) return;
				const gap = parseFloat( view.getComputedStyle( track ).columnGap ) || 0;
				visible = Math.max( 1, Math.min( slides.length, Math.round( ( track.clientWidth + gap ) / ( slides[0].getBoundingClientRect().width + gap ) ) ) );
			}
			function update() {
				if ( ! track.clientWidth ) return;
				measure();
				const rtl = view.getComputedStyle( track ).direction === 'rtl';
				const trackRect = track.getBoundingClientRect();
				const edge = rtl ? trackRect.right : trackRect.left;
				let distance = Infinity;
				slides.forEach( ( slide, index ) => {
					const rect = slide.getBoundingClientRect();
					const delta = Math.abs( ( rtl ? rect.right : rect.left ) - edge );
					if ( delta < distance ) { distance = delta; current = index; }
				} );
				current = Math.min( current, slides.length - visible );
				slides.forEach( ( slide, index ) => {
					const offscreen = index < current || index >= current + visible;
					if ( offscreen && slide.contains( gallery.ownerDocument.activeElement ) ) track.focus( { preventScroll: true } );
					slide.inert = offscreen;
				} );
				const canScroll = slides.length > visible;
				// Keep focus reachable when a wider viewport makes the controls unnecessary.
				if ( ! canScroll && controls.contains( gallery.ownerDocument.activeElement ) ) {
					track.tabIndex = 0;
					track.focus( { preventScroll: true } );
				}
				controls.hidden = ! canScroll;
				track.tabIndex = canScroll ? 0 : -1;
				previous.setAttribute( 'aria-disabled', String( current === 0 ) );
				next.setAttribute( 'aria-disabled', String( current >= slides.length - visible ) );
				const message = visible > 1
					? status.dataset.rangeFormat.replace( '%1$d', current + 1 ).replace( '%2$d', current + visible ).replace( '%3$d', slides.length )
					: status.dataset.format.replace( '%1$d', current + 1 ).replace( '%2$d', slides.length );
				if ( status.textContent !== message ) status.textContent = message;
				syncPlayback();
			}
			function move( index, instant = false ) {
				measure();
				current = Math.max( 0, Math.min( slides.length - visible, index ) );
				const trackRect = track.getBoundingClientRect();
				const slideRect = slides[ current ].getBoundingClientRect();
				const rtl = view.getComputedStyle( track ).direction === 'rtl';
				track.scrollBy( {
					left: rtl ? slideRect.right - trackRect.right : slideRect.left - trackRect.left,
					behavior: instant || view.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'instant' : 'smooth',
				} );
				if ( instant ) update();
			}
			previous.addEventListener( 'click', () => { stopPlayback(); move( current - 1 ); }, { signal: events.signal } );
			next.addEventListener( 'click', () => { stopPlayback(); move( current + 1 ); }, { signal: events.signal } );
			gallery.addEventListener( 'keydown', ( event ) => {
				if ( event.altKey || event.ctrlKey || event.metaKey || event.target.closest( 'input, textarea, select, [contenteditable="true"]' ) ) return;
				const rtl = view.getComputedStyle( track ).direction === 'rtl';
				const destinations = { ArrowLeft: current + ( rtl ? 1 : -1 ), ArrowRight: current + ( rtl ? -1 : 1 ), Home: 0, End: slides.length - visible };
				if ( ! Object.hasOwn( destinations, event.key ) ) return;
				event.preventDefault();
				stopPlayback();
				move( destinations[ event.key ] );
			}, { signal: events.signal } );
			track.addEventListener( 'scroll', () => {
				view.clearTimeout( timer );
				timer = view.setTimeout( update, 120 );
			}, { passive: true, signal: events.signal } );
			play?.addEventListener( 'click', () => {
				playing = ! playing;
				hovered = false; // An explicit Play request can start beneath the pointer.
				syncPlayback();
			}, { signal: events.signal } );
			gallery.addEventListener( 'mouseenter', () => { hovered = true; syncPlayback(); }, { signal: events.signal } );
			gallery.addEventListener( 'mouseleave', () => { hovered = false; syncPlayback(); }, { signal: events.signal } );
			gallery.addEventListener( 'focusin', ( event ) => {
				if ( event.target !== play ) stopPlayback();
			}, { signal: events.signal } );
			track.addEventListener( 'pointerdown', stopPlayback, { signal: events.signal } );
			gallery.ownerDocument.addEventListener( 'visibilitychange', syncPlayback, { signal: events.signal } );
			motion.addEventListener( 'change', () => { if ( motion.matches ) stopPlayback(); }, { signal: events.signal } );
			const visibility = new IntersectionObserver( ( entries ) => {
				onscreen = entries[0].isIntersecting && entries[0].intersectionRatio >= 0.2;
				syncPlayback();
			}, { threshold: [ 0, 0.2 ] } );
			visibility.observe( track );
			const resize = new ResizeObserver( () => {
				if ( track.clientWidth && track.clientWidth !== lastWidth ) {
					lastWidth = track.clientWidth;
					move( current, true );
				}
			} );
			resize.observe( track );
			initialized.set( track, () => { progressAnimation?.cancel(); resize.disconnect(); visibility.disconnect(); events.abort(); view.clearTimeout( timer ); view.clearTimeout( playbackTimer ); } );
			update();
		} );
	}
	window.uplinkMbeInitSliders = initialize;
	function start() {
		initialize();
		new MutationObserver( ( records ) => {
			for ( const [ track, cleanup ] of initialized ) {
				if ( ! track.isConnected ) { cleanup(); initialized.delete( track ); }
			}
			records.forEach( ( record ) => record.addedNodes.forEach( ( node ) => {
				if ( node.nodeType === 1 ) initialize( node );
			} ) );
		} ).observe( document.body, { childList: true, subtree: true } );
	}
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )();
