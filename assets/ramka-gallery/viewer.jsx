import * as React from 'react';
import { createRoot, flushSync } from 'react-dom';
import * as Lightbox from '@ramka/react/lightbox';

function Icon( { name } ) {
	const glyphs = {
		peek: <><path d="M2 3v18" /><rect width="12" height="18" x="6" y="3" rx="2" /><path d="M22 3v18" /></>,
		theater: <><rect width="20" height="12" x="2" y="6" rx="2" /></>,
		close: <><path d="M18 6 6 18" /><path d="m6 6 12 12" /></>,
		plus: <><circle cx="11" cy="11" r="8" /><line x1="21" x2="16.65" y1="21" y2="16.65" /><line x1="11" x2="11" y1="8" y2="14" /><line x1="8" x2="14" y1="11" y2="11" /></>,
		minus: <><circle cx="11" cy="11" r="8" /><line x1="21" x2="16.65" y1="21" y2="16.65" /><line x1="8" x2="14" y1="11" y2="11" /></>,
		aperture: <><circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="4" /><path d="M12 8H21M16 12V21M8 12V3M12 16H3" /></>,
	};
	return <svg width={ name === 'close' ? 18 : 16 } height={ name === 'close' ? 18 : 16 } viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
		{ glyphs[ name ] }
	</svg>;
}

function PhotoDetails( { rows, label } ) {
	const [ open, setOpen ] = React.useState( false );
	const id = React.useId();
	return <>
		<button type="button" className="uplink-ramka-control" aria-label={ label } data-tooltip={ label }
			aria-controls={ id } aria-expanded={ open } onClick={ () => setOpen( ! open ) }>
			<Icon name="aperture" />
		</button>
		<section id={ id } className="uplink-ramka-photo-details" aria-label={ label } hidden={ ! open }>
			<dl>{ rows.map( ( { label, value } ) => <React.Fragment key={ label }><dt>{ label }</dt><dd>{ value }</dd></React.Fragment> ) }</dl>
		</section>
	</>;
}

function Viewer( { items, visible, options, dir } ) {
	const [ peek, setPeek ] = React.useState( options.openingView !== 'theater' );
	const [ activeIndex, setActiveIndex ] = React.useState( 0 );
	const strings = options.strings;
	const strip = items.length > 1;
	const extra = items.length - visible;
	const switchView = () => {
		const flip = () => setPeek( ( previous ) => ! previous );
		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! document.startViewTransition ) {
			flip();
		} else {
			document.startViewTransition( () => flushSync( flip ) );
		}
	};
	const edges = peek ? {
		'--lightbox-snug-ratio-start': items[ 0 ].ratio,
		'--lightbox-snug-ratio-end': items[ items.length - 1 ].ratio,
	} : undefined;
	const layoutLabel = peek ? strings.theater : strings.peek;

	return <Lightbox.Root dir={ dir } morphTo="closest" awaitImageDecode={ false } onValueChange={ setActiveIndex } scrollTriggerIntoView={ [
		{ type: 'onChange', behavior: 'instant' },
		{ type: 'onOpenComplete', behavior: 'instant' },
	] }>
		<div className="uplink-ramka-grid" data-demo-wall={ visible === 12 ? '' : undefined }>
			{ items.slice( 0, visible ).map( ( photo, index ) => {
				const overflow = index === visible - 1 && extra > 0;
				return <Lightbox.Trigger key={ index } index={ index } className="uplink-ramka-tile"
					crossfade={ overflow ? 'both' : undefined } aria-label={ overflow ? options.moreLabel : photo.alt || photo.caption || undefined }>
					{ ( { imageRef } ) => <span className="uplink-ramka-tile-frame" style={ { '--uplink-ramka-photo-ratio': photo.ratio } }>
						<img ref={ imageRef } src={ photo.thumb } srcSet={ photo.srcSet } sizes={ photo.sizes }
							alt={ photo.alt } width={ photo.thumbWidth } height={ photo.thumbHeight } loading="lazy" decoding="async" draggable={ false } />
						{ overflow && <span className="uplink-ramka-more" aria-hidden="true">+{ options.moreCount }</span> }
					</span> }
				</Lightbox.Trigger>;
			} ) }
		</div>
		<Lightbox.Portal>
			<Lightbox.Backdrop className="lightbox-demo-backdrop uplink-ramka-backdrop" />
			<Lightbox.Content aria-label={ options.label } pullToClose
				className={ 'lightbox-demo-content uplink-ramka-content' + ( peek ? ' lightbox-demo-content-snug' : '' ) + ( strip ? '' : ' lightbox-demo-content-solo' ) }>
				<Lightbox.Slides key={ peek ? 'peek' : 'theater' } aria-label={ strings.images }
					aria-roledescription={ strings.carousel } preload={ 3 } style={ edges }
					className={ 'lightbox-demo-slides uplink-ramka-slides' + ( peek ? ' lightbox-demo-slides-snug' : '' ) }>
					{ items.map( ( photo, index ) => <Lightbox.Slide key={ index }
						className={ 'lightbox-demo-slide-pad uplink-ramka-slide' + ( peek ? ' lightbox-demo-slide-snug' : '' ) }
						style={ peek ? { '--lightbox-snug-ratio': photo.ratio } : undefined }>
						<Lightbox.Item index={ index } caption={ photo.caption } aria-roledescription={ strings.slide }
							aria-label={ strip ? undefined : photo.alt || options.label }>
							<Lightbox.Zoom maxZoom={ 6 }>
								<Lightbox.Media width={ photo.width } height={ photo.height } className="uplink-ramka-media">
									<img src={ photo.src } alt={ photo.alt } width={ photo.width } height={ photo.height } draggable={ false } />
								</Lightbox.Media>
							</Lightbox.Zoom>
						</Lightbox.Item>
					</Lightbox.Slide> ) }
				</Lightbox.Slides>
				<div className="lightbox-demo-chrome-top lightbox-demo-chrome-hide uplink-ramka-top">
					{ strip && <button type="button" className="uplink-ramka-control uplink-ramka-desktop lightbox-demo-chrome-hide lightbox-demo-zoom-hide"
						aria-pressed={ peek } aria-label={ layoutLabel } data-tooltip={ peek ? strings.theaterName : strings.peekName } onClick={ switchView }>
						<Icon name={ peek ? 'peek' : 'theater' } />
					</button> }
					<div className="uplink-ramka-zoom-controls uplink-ramka-desktop">
						<Lightbox.ZoomOut className="uplink-ramka-segment" aria-label={ strings.zoomOut } data-tooltip={ strings.zoomOut }><Icon name="minus" /></Lightbox.ZoomOut>
						<Lightbox.ZoomIn className="uplink-ramka-segment" aria-label={ strings.zoomIn } data-tooltip={ strings.zoomIn }><Icon name="plus" /></Lightbox.ZoomIn>
					</div>
					{ items[ activeIndex ]?.exif.length > 0 && <PhotoDetails key={ activeIndex } rows={ items[ activeIndex ].exif } label={ strings.exif } /> }
					<Lightbox.Close className="uplink-ramka-control" aria-label={ strings.close } data-tooltip={ strings.close }><Icon name="close" /></Lightbox.Close>
				</div>
				<div className={ 'lightbox-demo-chrome-bottom lightbox-demo-chrome-hide uplink-ramka-bottom' + ( strip ? '' : ' lightbox-demo-chrome-bottom-solo' ) }>
					<div className="uplink-ramka-info">
						<Lightbox.Caption className="uplink-ramka-caption" />
						{ strip && <Lightbox.Counter className="uplink-ramka-counter">
							{ ( { current, total } ) => strings.counter.replace( '%1$d', current ).replace( '%2$d', total ) }
						</Lightbox.Counter> }
					</div>
					{ strip && <Lightbox.ThumbnailStrip className="lightbox-demo-thumbnail-strip uplink-ramka-desktop">
						<Lightbox.ThumbnailStripTrack aria-label={ strings.thumbnails }>
							{ items.map( ( photo, index ) => <Lightbox.Thumbnail key={ index } index={ index }>
								<img src={ photo.thumb } srcSet={ photo.srcSet } sizes="144px" alt={ photo.alt || photo.caption } draggable={ false } />
							</Lightbox.Thumbnail> ) }
						</Lightbox.ThumbnailStripTrack>
						<span className="lightbox-demo-thumbnail-strip-indicator" aria-hidden="true" />
					</Lightbox.ThumbnailStrip> }
				</div>
			</Lightbox.Content>
		</Lightbox.Portal>
	</Lightbox.Root>;
}

function readPhoto( link ) {
	const image = link.querySelector( 'img' );
	const width = Number( link.dataset.width );
	const height = Number( link.dataset.height );
	return {
		src: link.getAttribute( 'href' ),
		thumb: image.getAttribute( 'src' ),
		thumbWidth: Number( image.getAttribute( 'width' ) ) || undefined,
		thumbHeight: Number( image.getAttribute( 'height' ) ) || undefined,
		srcSet: image.getAttribute( 'srcset' ) || undefined,
		sizes: image.getAttribute( 'sizes' ) || undefined,
		alt: image.alt,
		caption: link.dataset.caption,
		exif: JSON.parse( link.dataset.exif || '[]' ),
		width,
		height,
		ratio: width / height,
	};
}

for ( const gallery of document.querySelectorAll( '[data-uplink-ramka-gallery]' ) ) {
	if ( gallery.hasAttribute( 'data-uplink-ramka-mounted' ) ) continue;
	const grid = gallery.querySelector( '.uplink-ramka-grid' );
	const visible = Array.from( grid.querySelectorAll( 'a' ), readPhoto );
	const hidden = gallery.querySelector( 'template' );
	const items = [ ...visible, ...( hidden ? Array.from( hidden.content.querySelectorAll( 'a' ), readPhoto ) : [] ) ];
	if ( ! items.length ) continue;
	const options = JSON.parse( gallery.dataset.uplinkRamkaGallery );
	gallery.setAttribute( 'data-uplink-ramka-mounted', '' );
	createRoot( gallery ).render( <Viewer items={ items } visible={ visible.length } options={ options } dir={ getComputedStyle( gallery ).direction } /> );
}
