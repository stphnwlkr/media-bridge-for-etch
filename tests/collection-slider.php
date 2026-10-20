<?php
/** Disposable WordPress only: wp eval-file tests/collection-slider.php */
if ( ! defined( 'UPLINK_MBE_VERSION' ) ) require_once dirname( __DIR__ ) . '/media-bridge-for-etch.php';
register_taxonomy( 'etch_collection', 'attachment', array( 'hierarchical' => true ) );
$library = new UplinkPress\MediaBridgeForEtch\Native_Media_Library( new UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider(), false );
$gallery = new UplinkPress\MediaBridgeForEtch\Collection_Gallery( new UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider() );
$gallery->register();
$term = wp_insert_term( 'Slider test ' . wp_generate_uuid4(), 'etch_collection' )['term_id'];
$ids = array();
function slider_check( bool $passed, string $label ): void {
	if ( ! $passed ) throw new RuntimeException( $label );
	echo "PASS: {$label}\n";
}
try {
	foreach ( array( 'First', 'Second', 'Third' ) as $title ) {
		$file = wp_upload_bits( 'slider-' . wp_generate_uuid4() . '.png', null, file_get_contents( ABSPATH . 'wp-includes/images/w-logo-blue.png' ) );
		$id = wp_insert_attachment( array( 'post_title' => $title, 'post_excerpt' => $title . ' caption', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file['file'] );
		$ids[] = $id;
		wp_set_object_terms( $id, array( $term ), 'etch_collection' );
	}
	wp_update_attachment_metadata( $ids[0], array( 'width' => 640, 'height' => 480, 'image_meta' => array( 'camera' => 'Test Camera', 'iso' => 200, 'lens' => 'Test Lens' ) ) );
	$exif_args = array( 'collectionId' => $term, 'lightboxExifFields' => array( 'camera_model', 'iso', 'unknown' ) );
	$exif_html = $gallery->render_block( $exif_args );
	slider_check( str_contains( $exif_html, 'Test Camera' ) && ! str_contains( $exif_html, 'Test Lens' ), 'Only selected available EXIF is serialized' );
	slider_check( ! str_contains( $gallery->render_block( array_merge( $exif_args, array( 'lightboxShowExif' => false ) ) ), 'Test Camera' ), 'Block EXIF toggle removes data from output' );
	slider_check( ! str_contains( $gallery->render_block( array_merge( $exif_args, array( 'lightboxExifFields' => array() ) ) ), 'Test Camera' ), 'Empty field selection removes EXIF' );
	$saved_settings = get_option( 'uplink_mbe_settings', array() );
	try {
		update_option( 'uplink_mbe_settings', array_merge( $saved_settings, array( 'exif_dynamic_data' => 0 ) ) );
		slider_check( ! str_contains( $gallery->render_block( $exif_args ), 'Test Camera' ), 'Global EXIF setting prevents serialization' );
	} finally { update_option( 'uplink_mbe_settings', $saved_settings ); }
	update_term_meta( $term, '_uplink_mbe_media_order', array_reverse( $ids ) );
	$args = array( 'collection' => $term, 'layout' => 'slider', 'sort' => 'custom', 'show_title' => 'true', 'captions' => 'true', 'aspect_ratio' => '16/9' );
	$html = $gallery->render_shortcode( $args );
	slider_check( strpos( $html, 'wp-image-' . $ids[2] ) < strpos( $html, 'wp-image-' . $ids[0] ), 'Custom collection order drives slides' );
	slider_check( str_contains( $html, 'Third caption' ), 'Captions render from attachment metadata' );
	slider_check( str_contains( $html, 'is-layout-slider' ), 'Slider layout survives shortcode sanitization' );
	slider_check( str_contains( $html, 'data-slider-next' ) && str_contains( $html, 'aria-roledescription="carousel"' ), 'Slider navigation and accessible region render' );
	slider_check( wp_script_is( 'uplink-mbe-gallery-slider', 'enqueued' ), 'Slider script loads when used' );
	slider_check( str_contains( $html, '(max-width: 599px) 100vw, (max-width: 959px) 100vw, 100vw' ), 'Slider uses full-width responsive image sizes' );
	wp_update_post( array( 'ID' => $ids[0], 'post_title' => 'Updated title' ) );
	slider_check( str_contains( $gallery->render_shortcode( $args ), 'Updated title' ), 'Attachment changes appear without resaving the slider' );
	wp_remove_object_terms( $ids[2], $term, 'etch_collection' );
	slider_check( ! str_contains( $gallery->render_shortcode( $args ), 'wp-image-' . $ids[2] . '"' ), 'Collection removals update live output' );
	$single = $gallery->render_shortcode( array_merge( $args, array( 'limit' => 1 ) ) );
	slider_check( ! str_contains( $single, 'data-slider-next' ), 'Single-image collection needs no navigation' );
	$carousel = $gallery->render_shortcode( array_merge( $args, array( 'layout' => 'slider', 'slides' => 4, 'carousel_tablet' => 2, 'carousel_mobile' => 1, 'nav_position' => 'top', 'nav_alignment' => 'end', 'nav_shape' => 'pill', 'nav_content' => 'icon' ) ) );
	slider_check( str_contains( $carousel, 'is-layout-slider' ) && str_contains( $carousel, '--uplink-mbe-carousel-desktop:4' ), 'Carousel preserves its chosen image count' );
	slider_check( str_contains( $carousel, '--uplink-mbe-carousel-tablet:2' ) && str_contains( $carousel, '--uplink-mbe-carousel-mobile:1' ), 'Carousel preserves responsive counts' );
	slider_check( str_contains( $carousel, 'has-navposition-top has-navalignment-end has-navshape-pill has-navcontent-icon' ), 'Navigation styling survives server rendering' );
	slider_check( str_contains( $carousel, 'aria-label="Previous"' ) && str_contains( $carousel, 'aria-hidden="true"' ), 'Icon buttons keep accessible labels' );
	slider_check( str_contains( $carousel, 'data-range-format=' ), 'Carousel reports visible image range' );
	$invalid = $gallery->render_shortcode( array_merge( $args, array( 'layout' => 'slider', 'slides' => 99, 'carousel_mobile' => 99, 'nav_position' => 'unsafe', 'nav_content' => '<script>' ) ) );
	slider_check( str_contains( $invalid, '--uplink-mbe-carousel-desktop:8' ) && str_contains( $invalid, '--uplink-mbe-carousel-mobile:8' ), 'Responsive image counts are bounded' );
	slider_check( str_contains( $invalid, 'has-navposition-bottom' ) && str_contains( $invalid, 'has-navcontent-text' ), 'Invalid navigation options fall back safely' );
	slider_check( str_contains( $html, 'data-slider-autoplay="false"' ) && str_contains( $html, 'data-slider-play' ), 'Existing sliders start paused and expose Play' );
	$autoplay = $gallery->render_shortcode( array_merge( $args, array( 'autoplay' => 'true', 'interval' => 3000 ) ) );
	slider_check( str_contains( $autoplay, 'data-slider-autoplay="true"' ) && str_contains( $autoplay, 'data-slider-interval="3000"' ), 'Autoplay and timing survive rendering' );
	$fast = $gallery->render_shortcode( array_merge( $args, array( 'interval' => 1 ) ) );
	slider_check( str_contains( $fast, 'data-slider-interval="2000"' ), 'Playback interval cannot be faster than two seconds' );
	$hidden_status = $gallery->render_shortcode( array_merge( $args, array( 'show_status' => 'false', 'status_position' => 'below' ) ) );
	slider_check( str_contains( $hidden_status, 'has-hidden-slider-status' ) && str_contains( $hidden_status, 'has-slider-status-below' ), 'Status visibility and position survive rendering' );
	$invalid_status = $gallery->render_shortcode( array_merge( $args, array( 'status_position' => 'invalid' ) ) );
	slider_check( str_contains( $invalid_status, 'has-slider-status-auto' ) && ! str_contains( $invalid_status, 'has-hidden-slider-status' ), 'Existing sliders keep visible automatic status' );
	foreach ( array( '#ef4444', 'var(--danger)', 'var(--wp--preset--color--accent)' ) as $color ) {
		$colored = $gallery->render_shortcode( array_merge( $args, array( 'progress_color' => $color ) ) );
		slider_check( str_contains( $colored, '--uplink-mbe-slider-progress-color:' . $color . ';' ), 'Progress color survives rendering: ' . $color );
	}
	$unsafe_color = $gallery->render_shortcode( array_merge( $args, array( 'progress_color' => 'var(--danger);background:url(https://example.com)' ) ) );
	slider_check( ! str_contains( $unsafe_color, '--uplink-mbe-slider-progress-color:' ), 'Progress color rejects extra CSS declarations' );
	$lightbox_slideshow = $gallery->render_shortcode( array_merge( $args, array( 'interval' => 3000, 'lightbox_autoplay' => 'true', 'lightbox_interval' => 9000, 'lightbox_loop' => 'false' ) ) );
	slider_check( str_contains( $lightbox_slideshow, 'data-slider-interval="3000"' ) && str_contains( $lightbox_slideshow, 'data-uplink-mbe-lightbox-interval="9000"' ), 'Lightbox timing is independent of in-page playback' );
	slider_check( str_contains( $lightbox_slideshow, 'data-uplink-mbe-lightbox-loop="false"' ) && str_contains( $lightbox_slideshow, 'data-uplink-mbe-lightbox-autoplay="true"' ), 'Lightbox autoplay and loop choices survive rendering' );
	$lightbox_bounds = $gallery->render_block( array( 'collectionId' => $term, 'lightboxInterval' => 1, 'lightboxSlideshow' => false ) );
	slider_check( str_contains( $lightbox_bounds, 'data-uplink-mbe-lightbox-interval="2000"' ) && str_contains( $lightbox_bounds, 'data-uplink-mbe-lightbox-slideshow="false"' ), 'Lightbox timing is bounded and slideshow can be disabled' );
	slider_check( ! str_contains( $html, 'data-slider-slideshow' ), 'Fullscreen launch button is opt-in' );
	$launch_args = array_merge( $args, array( 'show_slideshow' => 'true' ) );
	slider_check( str_contains( $gallery->render_shortcode( $launch_args ), 'data-slider-slideshow' ), 'Fullscreen launch button renders when enabled' );
	foreach ( array( array( 'lightbox' => 'native' ), array( 'lightbox_slideshow' => 'false' ) ) as $disabled ) slider_check( ! str_contains( $gallery->render_shortcode( array_merge( $launch_args, $disabled ) ), 'data-slider-slideshow' ), 'Launch button requires custom slideshow' );
	slider_check( str_contains( $gallery->render_shortcode( array_merge( $launch_args, array( 'limit' => 1 ) ) ), 'data-slider-slideshow' ), 'Single image can launch fullscreen view' );
	if ( getenv( 'LAUNCH_FIXTURE' ) ) file_put_contents( getenv( 'LAUNCH_FIXTURE' ), $gallery->render_shortcode( array_merge( $launch_args, array( 'lightbox_interval' => 30000, 'slides' => 1 ) ) ) . $gallery->render_shortcode( array_merge( $launch_args, array( 'slides' => 4 ) ) ) );
	$controls_examples = '';
	foreach ( array( 'left', 'right' ) as $side ) {
		$split = $gallery->render_shortcode( array_merge( $args, array( 'status_position' => $side, 'nav_text_size' => 18, 'nav_icon_size' => 28, 'nav_content' => 'both', 'show_progress' => 'false' ) ) );
		slider_check( str_contains( $split, 'has-slider-status-' . $side ) && str_contains( $split, 'uplink-mbe-slider-button-group' ), 'Split controls render on ' . $side );
		slider_check( str_contains( $split, 'has-hidden-slider-progress' ) && str_contains( $split, '--uplink-mbe-slider-text-size:18px;' ) && str_contains( $split, '--uplink-mbe-slider-icon-size:28px;' ), 'Progress visibility and control sizing survive rendering' );
		$controls_examples .= '<h2>Status ' . $side . '</h2>' . $split;
	}
	$bounded_controls = $gallery->render_shortcode( array_merge( $args, array( 'nav_text_size' => 99, 'nav_icon_size' => 1 ) ) );
	slider_check( str_contains( $bounded_controls, '--uplink-mbe-slider-text-size:40px;' ) && str_contains( $bounded_controls, '--uplink-mbe-slider-icon-size:12px;' ), 'Control sizes are bounded' );
	slider_check( ! str_contains( $html, 'has-hidden-slider-progress' ) && ! str_contains( $html, '--uplink-mbe-slider-text-size:' ), 'Existing sliders preserve progress and inherited text size' );
	if ( getenv( 'CONTROLS_FIXTURE' ) ) file_put_contents( getenv( 'CONTROLS_FIXTURE' ), $controls_examples );
	$progress_examples = '';
	foreach ( array( 'bar', 'segments', 'dots', 'line', 'ring' ) as $progress_style ) {
		$styled = $gallery->render_shortcode( array_merge( $args, array( 'progress_style' => $progress_style, 'progress_color' => '#7454ed', 'nav_content' => 'icon', 'status_position' => 'left', 'interval' => 20000 ) ) );
		slider_check( str_contains( $styled, 'has-progress-' . $progress_style ), 'Progress style survives rendering: ' . $progress_style );
		$progress_examples .= '<h2>' . $progress_style . '</h2>' . $styled;
	}
	slider_check( str_contains( $gallery->render_shortcode( array_merge( $args, array( 'progress_style' => 'invalid' ) ) ), 'has-progress-bar' ), 'Invalid progress style falls back to bar' );
	if ( getenv( 'PROGRESS_FIXTURE' ) ) file_put_contents( getenv( 'PROGRESS_FIXTURE' ), $progress_examples );
	slider_check( str_contains( $gallery->render_shortcode( array_merge( $args, array( 'show_play' => 'false' ) ) ), 'has-hidden-slider-play' ), 'Play/Pause visibility survives rendering' );
	slider_check( ! str_contains( $html, 'has-hidden-slider-play' ), 'Existing sliders retain Play/Pause' );
	slider_check( str_contains( $gallery->render_shortcode( array_merge( $args, array( 'progress_placement' => 'inline', 'progress_style' => 'dots', 'show_play' => 'false' ) ) ), 'has-progress-placement-inline' ), 'Inline indicator layout survives rendering with Play hidden' );
	slider_check( str_contains( $gallery->render_shortcode( array_merge( $args, array( 'progress_placement' => 'invalid' ) ) ), 'has-progress-placement-below' ), 'Invalid indicator placement falls back below controls' );
	$opposite_examples = '';
	foreach ( array( 'opposite-left', 'opposite-right' ) as $placement ) {
		foreach ( array( 'left', 'center', 'right' ) as $count_align ) {
			$output = $gallery->render_shortcode( array_merge( $args, array( 'progress_style' => 'dots', 'progress_placement' => $placement, 'count_alignment' => $count_align, 'nav_content' => 'icon' ) ) );
			slider_check( str_contains( $output, 'has-progress-placement-' . $placement ) && str_contains( $output, 'has-count-alignment-' . $count_align ) && str_contains( $output, 'uplink-mbe-slider-button-group' ), 'Opposite controls with count ' . $count_align . ': ' . $placement );
			$opposite_examples .= '<h2>' . $placement . ' / count ' . $count_align . '</h2>' . $output;
		}
	}
	if ( getenv( 'OPPOSITE_FIXTURE' ) ) file_put_contents( getenv( 'OPPOSITE_FIXTURE' ), $opposite_examples );
	$grid = $gallery->render_shortcode( array_merge( $args, array( 'layout' => 'grid' ) ) );
	slider_check( str_contains( $grid, 'is-layout-grid' ) && ! str_contains( $grid, 'data-slider-next' ), 'Grid output keeps existing behavior' );
	if ( getenv( 'SLIDESHOW_FIXTURE' ) ) file_put_contents( getenv( 'SLIDESHOW_FIXTURE' ), $gallery->render_shortcode( array_merge( $args, array( 'lightbox_autoplay' => 'true', 'lightbox_interval' => 2000, 'lightbox_loop' => 'false', 'interval' => 9000 ) ) ) );
	if ( getenv( 'STATUS_FIXTURE' ) ) {
		$examples = '';
		foreach ( array( 'before', 'between', 'after', 'above', 'below', 'hidden' ) as $position ) {
			$examples .= '<h2>' . $position . '</h2>' . $gallery->render_block( array( 'collectionId' => $term, 'layout' => 'slider', 'sliderShowStatus' => 'hidden' !== $position, 'sliderStatusPosition' => $position, 'navAlignment' => 'center', 'navContent' => 'icon' ) );
		}
		file_put_contents( getenv( 'STATUS_FIXTURE' ), $examples );
	}
	if ( getenv( 'SLIDER_FIXTURE' ) ) file_put_contents( getenv( 'SLIDER_FIXTURE' ), $exif_html );
} finally {
	foreach ( $ids as $id ) wp_delete_attachment( $id, true );
	wp_delete_term( $term, 'etch_collection' );
}
