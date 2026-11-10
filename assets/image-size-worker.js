// Single-thread local encoding works without cross-origin isolation headers.
let encoder;
self.onmessage = async ( { data } ) => {
	let image;
	let canvas;
	try {
		const { blob, width, height, size } = data;
		const bytes = await blob.arrayBuffer();
		if ( new TextDecoder( 'latin1' ).decode( bytes.slice( 0, 64 ) ).includes( 'avis' ) ) throw new Error( 'Animated AVIF images are left unchanged.' );
		image = await createImageBitmap( blob, { imageOrientation: 'none' } );
		if ( image.width !== width || image.height !== height ) throw new Error( 'Decoded dimensions do not match the original.' );
		canvas = new OffscreenCanvas( size.width, size.height );
		const context = canvas.getContext( '2d' );
		context.drawImage( image, ...size.crop, 0, 0, size.width, size.height );
		const pixels = context.getImageData( 0, 0, size.width, size.height );
		if ( ! encoder ) {
			const [ { default: factory }, { defaultOptions } ] = await Promise.all( [ import( './vendor/jsquash/avif/codec/enc/avif_enc.js' ), import( './vendor/jsquash/avif/meta.js' ) ] );
			encoder = { module: await factory( { noInitialRun: true } ), options: defaultOptions };
		}
		let result;
		for ( const quality of [ 50, 40, 30, 20 ] ) {
			const output = encoder.module.encode( new Uint8Array( pixels.data.buffer ), size.width, size.height, { ...encoder.options, quality } );
			if ( ! output ) throw new Error( 'The AVIF encoder did not produce a file.' );
			result = new Blob( [ output.slice() ], { type: 'image/avif' } );
			if ( result.size <= blob.size ) break;
		}
		if ( result.size > blob.size ) throw new Error( 'This size could not be made smaller than the original. No file was saved for this size.' );
		self.postMessage( { blob: result } );
	} catch ( error ) {
		self.postMessage( { error: String( error.message || error ) } );
	} finally {
		image?.close();
		if ( canvas ) { canvas.width = 0; canvas.height = 0; }
	}
};
