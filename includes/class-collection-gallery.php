<?php
namespace UplinkPress\MediaBridgeForEtch;

use WP_Block;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

final class Collection_Gallery {
	private const SHORTCODE = 'etch_collection_gallery';
	private Provider_Interface $etch;

	public function __construct( Provider_Interface $etch ) {
		$this->etch = $etch;
		add_action( 'init', array( $this, 'register' ), 110 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'etch_builder_assets' ), 120 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'wp_content_img_tag', array( $this, 'preserve_masonry_image_sizes' ), 20 );
	}

	public function preserve_masonry_image_sizes( string $image ): string {
		if ( ! str_contains( $image, 'uplink-mbe-gallery-image' ) ) {
			return $image;
		}

		return (string) preg_replace( '/(\ssizes=["\'])auto,\s*/', '$1', $image, 1 );
	}

	public function register(): void {
		wp_register_script(
			'uplink-mbe-gallery-block',
			UPLINK_MBE_URL . 'assets/gallery-block.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			UPLINK_MBE_ASSET_VERSION,
			true
		);
		wp_register_style(
			'uplink-mbe-collection-gallery',
			UPLINK_MBE_URL . 'assets/gallery.css',
			array(),
			UPLINK_MBE_ASSET_VERSION
		);
		wp_register_script(
			'uplink-mbe-gallery-lightbox',
			UPLINK_MBE_URL . 'assets/gallery-lightbox.js',
			array(),
			UPLINK_MBE_ASSET_VERSION,
			true
		);
		wp_localize_script(
			'uplink-mbe-gallery-lightbox',
			'uplinkMbeGalleryLightbox',
			array(
				'dialogLabel'     => __( 'Image gallery lightbox', 'media-bridge-for-etch' ),
				'closeLabel'      => __( 'Close gallery lightbox', 'media-bridge-for-etch' ),
				'previousLabel'   => __( 'Previous image', 'media-bridge-for-etch' ),
				'nextLabel'       => __( 'Next image', 'media-bridge-for-etch' ),
				'fullscreenEnter' => __( 'Enter fullscreen', 'media-bridge-for-etch' ),
				'fullscreenExit'  => __( 'Exit fullscreen', 'media-bridge-for-etch' ),
				'zoomIn'          => __( 'Zoom in', 'media-bridge-for-etch' ),
				'zoomOut'         => __( 'Zoom out', 'media-bridge-for-etch' ),
				'zoomReset'       => __( 'Reset zoom', 'media-bridge-for-etch' ),
				'thumbnailsLabel' => __( 'Gallery thumbnails', 'media-bridge-for-etch' ),
				/* translators: 1: Current image number. 2: Total number of images. */
				'showImage'       => __( 'Show image %1$d of %2$d', 'media-bridge-for-etch' ),
				/* translators: 1: Current image number. 2: Total number of images. 3: Optional image title or caption status. */
				'imageStatus'     => __( 'Image %1$d of %2$d%3$s', 'media-bridge-for-etch' ),
			)
		);
		register_block_type(
			UPLINK_MBE_PATH . 'blocks/collection-gallery',
			array( 'render_callback' => array( $this, 'render_block' ) )
		);
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	public function editor_data(): void {
		wp_localize_script( 'uplink-mbe-gallery-block', 'uplinkMbeGalleryBlock', $this->editor_config() );
	}

	/**
	 * Load the Collection Gallery passthrough adapter only inside the Etch builder.
	 */
	public function etch_builder_assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request context check.
		if ( ! isset( $_GET['etch'] ) || 'magic' !== sanitize_key( wp_unslash( $_GET['etch'] ) ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		wp_enqueue_style( 'uplink-mbe-collection-gallery' );
		wp_enqueue_style(
			'uplink-mbe-gallery-etch-passthrough',
			UPLINK_MBE_URL . 'assets/gallery-etch-passthrough.css',
			array(),
			UPLINK_MBE_ASSET_VERSION
		);
		wp_enqueue_script(
			'uplink-mbe-gallery-etch-passthrough',
			UPLINK_MBE_URL . 'assets/gallery-etch-passthrough.js',
			array(),
			UPLINK_MBE_ASSET_VERSION,
			true
		);

		$config = $this->editor_config();
		$config['blockName'] = 'uplinkpress/collection-gallery';
		$config['previewUrl'] = rest_url( 'uplink-mbe/v1/gallery-preview' );
		$config['nonce']      = wp_create_nonce( 'wp_rest' );
		$config['strings']    = array(
			'edit'             => __( 'Edit gallery', 'media-bridge-for-etch' ),
			'editing'          => __( 'Collection Gallery settings', 'media-bridge-for-etch' ),
			'apply'            => __( 'Apply and save', 'media-bridge-for-etch' ),
			'cancel'           => __( 'Cancel', 'media-bridge-for-etch' ),
			'loading'          => __( 'Loading gallery preview…', 'media-bridge-for-etch' ),
			'previewError'     => __( 'The gallery preview could not be loaded.', 'media-bridge-for-etch' ),
			'emptyPreview'     => __( 'Choose a populated collection to preview the gallery.', 'media-bridge-for-etch' ),
			'saving'           => __( 'Saving gallery…', 'media-bridge-for-etch' ),
			'saved'            => __( 'Gallery saved.', 'media-bridge-for-etch' ),
			'saveError'        => __( 'The gallery changes could not be saved.', 'media-bridge-for-etch' ),
			'advancedNotice'   => __( 'Additional lightbox and typography settings remain available in the WordPress block editor.', 'media-bridge-for-etch' ),
		);
		wp_localize_script( 'uplink-mbe-gallery-etch-passthrough', 'uplinkMbeEtchGallery', $config );
	}

	/**
	 * Register the authenticated server-rendered preview used by the Etch adapter.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'uplink-mbe/v1',
			'/gallery-preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_gallery_preview' ),
				'permission_callback' => static fn(): bool => current_user_can( 'upload_files' ),
				'args'                => array(
					'attributes' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);
	}

	public function rest_gallery_preview( WP_REST_Request $request ): WP_REST_Response {
		$attributes = $request->get_param( 'attributes' );
		$html       = $this->render( is_array( $attributes ) ? $attributes : array(), true );

		return new WP_REST_Response(
			array(
				'success' => true,
				'html'    => $html,
			)
		);
	}

	private function editor_config(): array {
		$collections = array();
		$terms       = $this->etch->get_terms();
		if ( ! is_wp_error( $terms ) ) {
			$terms = $this->etch->order_terms_hierarchically( $terms );
			$collections = array_map(
				static fn( WP_Term $term ): array => array(
					'id'     => (int) $term->term_id,
					'name'   => $term->name,
					'parent' => (int) $term->parent,
				),
				$terms
			);
		}

		$sizes = array_map(
			static fn( string $size ): array => array(
				'value' => $size,
				'label' => ucwords( str_replace( array( '-', '_' ), ' ', $size ) ),
			),
			array_values( array_unique( array_merge( get_intermediate_image_sizes(), array( 'full' ) ) ) )
		);

		$settings      = Plugin::settings();
		$manager_label = trim( (string) ( $settings['manager_label'] ?? '' ) );

		return array(
			'collections' => $collections,
			'imageSizes'  => $sizes,
			'managerLabel' => '' !== $manager_label ? $manager_label : __( 'Etch Collections', 'media-bridge-for-etch' ),
		);
	}

	public function frontend_assets(): void {
		global $post;
		if ( $post instanceof \WP_Post && ( has_block( 'uplinkpress/collection-gallery', $post ) || has_shortcode( $post->post_content, self::SHORTCODE ) ) ) {
			wp_enqueue_style( 'uplink-mbe-collection-gallery' );
		}
	}

	public function render_block( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		return $this->render( $attributes, true );
	}

	public function render_shortcode( array|string $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'collection'       => 0,
				'include_children' => 'false',
				'layout'           => 'grid',
				'columns'          => 3,
				'size'             => 'large',
				'crop'             => 'true',
				'aspect_ratio'     => '1/1',
				'random'           => 'false',
				'show_title'       => 'false',
				'captions'         => 'false',
				'lightbox'         => 'custom',
				'lightbox_title'   => 'true',
				'lightbox_caption' => 'true',
				'lightbox_thumbnails' => 'true',
				'lightbox_fullscreen' => 'true',
				'lightbox_zoom'    => 'true',
				'lightbox_size'    => 'full',
				'lightbox_thumbnail_position' => 'horizontal',
				'lightbox_info_position' => 'bottom',
				'lightbox_background' => '#111315',
				'lightbox_panel'   => '#1d2125',
				'lightbox_title_color' => '#ffffff',
				'lightbox_caption_color' => '#d9dde1',
				'lightbox_font'      => 'inherit',
				'lightbox_title_size' => 20,
				'lightbox_caption_size' => 15,
				'lightbox_title_weight' => 700,
				'lightbox_caption_weight' => 400,
				'title_color'      => '#ffffff',
				'caption_color'    => '#ffffff',
				'text_background'  => '#000000',
				'background_opacity' => 72,
				'title_size'       => 16,
				'caption_size'     => 14,
				'text_align'       => 'center',
				'text_position'    => 'bottom',
				'limit'            => 24,
				'gap'              => 8,
			),
			is_array( $attributes ) ? $attributes : array(),
			self::SHORTCODE
		);

		$term = $this->resolve_collection( $attributes['collection'] );
		if ( ! $term ) {
			return '';
		}
		$lightbox_mode = strtolower( trim( (string) $attributes['lightbox'] ) );
		if ( in_array( $lightbox_mode, array( '1', 'true', 'yes', 'on' ), true ) ) {
			$lightbox_mode = 'custom';
		} elseif ( in_array( $lightbox_mode, array( '0', 'false', 'no', 'off' ), true ) ) {
			$lightbox_mode = 'none';
		}

		return $this->render(
			array(
				'collectionId'   => (int) $term->term_id,
				'includeChildren' => $this->to_bool( $attributes['include_children'] ),
				'layout'          => (string) $attributes['layout'],
				'columns'         => absint( $attributes['columns'] ),
				'sizeSlug'        => (string) $attributes['size'],
				'imageCrop'       => $this->to_bool( $attributes['crop'] ),
				'aspectRatio'     => (string) $attributes['aspect_ratio'],
				'randomOrder'     => $this->to_bool( $attributes['random'] ),
				'showTitle'       => $this->to_bool( $attributes['show_title'] ),
				'showCaptions'    => $this->to_bool( $attributes['captions'] ),
				'lightboxMode'    => $lightbox_mode,
				'lightboxShowTitle' => $this->to_bool( $attributes['lightbox_title'] ),
				'lightboxShowCaption' => $this->to_bool( $attributes['lightbox_caption'] ),
				'lightboxThumbnails' => $this->to_bool( $attributes['lightbox_thumbnails'] ),
				'lightboxFullscreen' => $this->to_bool( $attributes['lightbox_fullscreen'] ),
				'lightboxZoom'    => $this->to_bool( $attributes['lightbox_zoom'] ),
				'lightboxSizeSlug' => (string) $attributes['lightbox_size'],
				'lightboxThumbnailPosition' => (string) $attributes['lightbox_thumbnail_position'],
				'lightboxInfoPosition' => (string) $attributes['lightbox_info_position'],
				'lightboxBackgroundColor' => (string) $attributes['lightbox_background'],
				'lightboxPanelColor' => (string) $attributes['lightbox_panel'],
				'lightboxTitleColor' => (string) $attributes['lightbox_title_color'],
				'lightboxCaptionColor' => (string) $attributes['lightbox_caption_color'],
				'lightboxFontFamily' => (string) $attributes['lightbox_font'],
				'lightboxTitleFontSize' => absint( $attributes['lightbox_title_size'] ),
				'lightboxCaptionFontSize' => absint( $attributes['lightbox_caption_size'] ),
				'lightboxTitleFontWeight' => absint( $attributes['lightbox_title_weight'] ),
				'lightboxCaptionFontWeight' => absint( $attributes['lightbox_caption_weight'] ),
				'titleColor'      => (string) $attributes['title_color'],
				'captionColor'    => (string) $attributes['caption_color'],
				'textBackgroundColor' => (string) $attributes['text_background'],
				'textBackgroundOpacity' => absint( $attributes['background_opacity'] ),
				'titleFontSize'   => absint( $attributes['title_size'] ),
				'captionFontSize' => absint( $attributes['caption_size'] ),
				'textAlign'       => (string) $attributes['text_align'],
				'textPosition'    => (string) $attributes['text_position'],
				'limit'           => absint( $attributes['limit'] ),
				'gap'             => absint( $attributes['gap'] ),
			),
			false
		);
	}

	private function render( array $attributes, bool $is_block ): string {
		$attributes = $this->sanitize_attributes( $attributes );
		$term       = $this->etch->get_term( $attributes['collectionId'] );
		if ( ! $term ) {
			return $this->empty_message( __( 'Choose a collection to display its images.', 'media-bridge-for-etch' ), $is_block );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $attributes['limit'],
				'orderby'        => $attributes['randomOrder'] ? 'rand' : 'date',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The selected Etch collection is the gallery's dynamic data source.
				'tax_query'      => array(
					array(
						'taxonomy'         => $this->etch->taxonomy(),
						'field'            => 'term_id',
						'terms'            => array( $attributes['collectionId'] ),
						'include_children' => $attributes['includeChildren'],
					),
				),
			)
		);
		if ( ! $query->posts ) {
			return $this->empty_message( __( 'No images were found in this collection.', 'media-bridge-for-etch' ), $is_block );
		}

		wp_enqueue_style( 'uplink-mbe-collection-gallery' );
		if ( 'custom' === $attributes['lightboxMode'] && ! is_admin() ) {
			wp_enqueue_script( 'uplink-mbe-gallery-lightbox' );
		}
		$classes = array(
			'wp-block-uplinkpress-collection-gallery',
			'is-layout-' . $attributes['layout'],
			'columns-' . $attributes['columns'],
		);
		if ( 'auto' === $attributes['aspectRatio'] ) {
			$classes[] = 'has-original-aspect-ratio';
		}
		if ( $attributes['imageCrop'] ) {
			$classes[] = 'is-cropped';
		}
		$background_rgb = $this->hex_to_rgb( $attributes['textBackgroundColor'] );
		$lightbox_background_rgb = $this->hex_to_rgb( $attributes['lightboxBackgroundColor'] );
		$lightbox_panel_rgb = $this->hex_to_rgb( $attributes['lightboxPanelColor'] );
		$lightbox_font_stacks = array(
			'inherit' => 'inherit',
			'system'  => 'system-ui, sans-serif',
			'serif'   => 'Georgia, "Times New Roman", serif',
			'mono'    => 'ui-monospace, SFMono-Regular, Menlo, monospace',
		);
		$lightbox_font_stack = $lightbox_font_stacks[ $attributes['lightboxFontFamily'] ] ?? 'inherit';
		$position       = array( 'top' => 'start', 'center' => 'center', 'bottom' => 'end' )[ $attributes['textPosition'] ];
		$style = sprintf(
			'--uplink-mbe-gallery-columns:%1$d;--uplink-mbe-gallery-gap:%2$dpx;--uplink-mbe-gallery-ratio:%3$s;--uplink-mbe-gallery-title-color:%4$s;--uplink-mbe-gallery-caption-color:%5$s;--uplink-mbe-gallery-title-size:%6$dpx;--uplink-mbe-gallery-caption-size:%7$dpx;--uplink-mbe-gallery-text-align:%8$s;--uplink-mbe-gallery-text-background-rgb:%9$s;--uplink-mbe-gallery-text-background-opacity:%10$s;--uplink-mbe-gallery-text-position:%11$s;--uplink-mbe-lightbox-background-rgb:%12$s;--uplink-mbe-lightbox-panel-rgb:%13$s;--uplink-mbe-lightbox-title-color:%14$s;--uplink-mbe-lightbox-caption-color:%15$s;--uplink-mbe-lightbox-title-size:%16$dpx;--uplink-mbe-lightbox-caption-size:%17$dpx;--uplink-mbe-lightbox-font-family:%18$s;--uplink-mbe-lightbox-title-weight:%19$d;--uplink-mbe-lightbox-caption-weight:%20$d;',
			$attributes['columns'],
			$attributes['gap'],
			str_replace( '/', ' / ', $attributes['aspectRatio'] ),
			$attributes['titleColor'],
			$attributes['captionColor'],
			$attributes['titleFontSize'],
			$attributes['captionFontSize'],
			$attributes['textAlign'],
			implode( ' ', $background_rgb ),
			rtrim( rtrim( number_format( $attributes['textBackgroundOpacity'] / 100, 2, '.', '' ), '0' ), '.' ),
			$position,
			implode( ' ', $lightbox_background_rgb ),
			implode( ' ', $lightbox_panel_rgb ),
			$attributes['lightboxTitleColor'],
			$attributes['lightboxCaptionColor'],
			$attributes['lightboxTitleFontSize'],
			$attributes['lightboxCaptionFontSize'],
			$lightbox_font_stack,
			$attributes['lightboxTitleFontWeight'],
			$attributes['lightboxCaptionFontWeight']
		);
		$data_attributes = array();
		if ( 'custom' === $attributes['lightboxMode'] ) {
			$data_attributes = array(
				'data-uplink-mbe-lightbox'            => 'custom',
				'data-uplink-mbe-lightbox-thumbnails' => $attributes['lightboxThumbnails'] ? 'true' : 'false',
				'data-uplink-mbe-lightbox-strip'      => $attributes['lightboxThumbnailPosition'],
				'data-uplink-mbe-lightbox-info'       => $attributes['lightboxInfoPosition'],
				'data-uplink-mbe-lightbox-title'      => $attributes['lightboxShowTitle'] ? 'true' : 'false',
				'data-uplink-mbe-lightbox-caption'    => $attributes['lightboxShowCaption'] ? 'true' : 'false',
				'data-uplink-mbe-lightbox-fullscreen' => $attributes['lightboxFullscreen'] ? 'true' : 'false',
				'data-uplink-mbe-lightbox-zoom'       => $attributes['lightboxZoom'] ? 'true' : 'false',
			);
		}
		$wrapper = $is_block
			? get_block_wrapper_attributes( array_merge( array( 'class' => implode( ' ', $classes ), 'style' => $style ), $data_attributes ) )
			: $this->html_attributes( array_merge( array( 'class' => implode( ' ', $classes ), 'style' => $style ), $data_attributes ) );

		$image_blocks = array();
		$column_width = max( 1, (int) ceil( 100 / max( 1, (int) $attributes['columns'] ) ) );
		$image_sizes  = sprintf( '(max-width: 599px) 100vw, %dvw', $column_width );
		foreach ( $query->posts as $attachment ) {
			$attachment_id = (int) $attachment->ID;
			$image          = wp_get_attachment_image(
				$attachment_id,
				$attributes['sizeSlug'],
				false,
				array(
					'class'    => 'uplink-mbe-gallery-image wp-image-' . $attachment_id,
					'decoding' => 'async',
					'loading'  => 'lazy',
					'sizes'    => $image_sizes,
				)
			);
			if ( ! $image ) {
				continue;
			}

			$attachment_title   = (string) get_post_field( 'post_title', $attachment_id, 'raw' );
			$attachment_caption = (string) wp_get_attachment_caption( $attachment_id );
			$title              = $attributes['showTitle'] ? $attachment_title : '';
			$caption            = $attributes['showCaptions'] ? $attachment_caption : '';
			$figure             = sprintf( '<figure class="wp-block-image size-%1$s">', esc_attr( $attributes['sizeSlug'] ) );
			if ( 'custom' === $attributes['lightboxMode'] ) {
				$lightbox_image = wp_get_attachment_image_src( $attachment_id, $attributes['lightboxSizeSlug'] );
				$figure .= sprintf(
					'<button type="button" class="uplink-mbe-custom-lightbox-trigger" data-uplink-mbe-src="%1$s" data-uplink-mbe-title="%2$s" data-uplink-mbe-caption="%3$s" aria-label="%4$s">%5$s</button>',
						esc_url( $lightbox_image ? $lightbox_image[0] : wp_get_attachment_url( $attachment_id ) ),
						esc_attr( $attachment_title ),
						esc_attr( wp_strip_all_tags( $attachment_caption ) ),
						/* translators: %s: Attachment title. */
						esc_attr( sprintf( __( 'Open %s in gallery lightbox', 'media-bridge-for-etch' ), $attachment_title ) ),
					$image
				);
			} else {
				$figure .= $image;
			}
			if ( $title || $caption ) {
				$figure .= '<figcaption class="wp-element-caption">';
				if ( $title ) {
					$figure .= '<span class="uplink-mbe-gallery-title">' . esc_html( $title ) . '</span>';
				}
				if ( $caption ) {
					$figure .= '<span class="uplink-mbe-gallery-caption">' . wp_kses_post( $caption ) . '</span>';
				}
				$figure .= '</figcaption>';
			}
			$figure .= '</figure>';

			$image_attributes = array(
				'id'              => $attachment_id,
				'data-id'         => (string) $attachment_id,
				'sizeSlug'        => $attributes['sizeSlug'],
				'linkDestination' => 'none',
			);
			if ( 'native' === $attributes['lightboxMode'] ) {
				$image_attributes['lightbox'] = array( 'enabled' => true );
			}
			if ( 'auto' !== $attributes['aspectRatio'] ) {
				$image_attributes['aspectRatio'] = $attributes['aspectRatio'];
				$image_attributes['scale']       = 'cover';
			}
			$image_blocks[] = array(
				'blockName'    => 'core/image',
				'attrs'        => $image_attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => $figure,
				'innerContent' => array( $figure ),
			);
		}

		if ( ! $image_blocks ) {
			return '';
		}

		$gallery_class = 'wp-block-gallery has-nested-images columns-' . $attributes['columns'];
		if ( $attributes['imageCrop'] ) {
			$gallery_class .= ' is-cropped';
		}
		$gallery_open = '<figure class="' . esc_attr( $gallery_class ) . '">';
		$gallery      = array(
			'blockName'    => 'core/gallery',
			'attrs'        => array(
				'columns'              => $attributes['columns'],
				'imageCrop'            => $attributes['imageCrop'],
				'fixedHeight'          => $attributes['imageCrop'],
				'aspectRatio'          => $attributes['aspectRatio'],
				'sizeSlug'             => $attributes['sizeSlug'],
				'linkTo'               => 'native' === $attributes['lightboxMode'] ? 'lightbox' : 'none',
				'navigationButtonType' => 'icon',
				'style'                => array(
					'spacing' => array( 'blockGap' => $attributes['gap'] . 'px' ),
				),
			),
			'innerBlocks'  => $image_blocks,
			'innerHTML'    => $gallery_open . '</figure>',
			'innerContent' => array_merge( array( $gallery_open ), array_fill( 0, count( $image_blocks ), null ), array( '</figure>' ) ),
		);

		$rendered_gallery = render_block( $gallery );
		// WordPress adds "auto" to lazy-image sizes during block rendering. Chrome
		// then reserves a 3000x1500 intrinsic box, which breaks CSS-column masonry.
		// Keep lazy loading but restore the explicit responsive sizes value.
		$rendered_gallery = preg_replace( '/(\ssizes=["\'])auto,\s*/', '$1', $rendered_gallery );

		return sprintf( '<div %1$s>%2$s</div>', $wrapper, $rendered_gallery );
	}

	private function sanitize_attributes( array $attributes ): array {
		$layouts = array( 'grid', 'tiled', 'circles', 'square', 'columns' );
		$layout  = sanitize_key( (string) ( $attributes['layout'] ?? 'grid' ) );
		$size    = sanitize_key( (string) ( $attributes['sizeSlug'] ?? 'large' ) );
		$sizes   = array_merge( get_intermediate_image_sizes(), array( 'full' ) );
		$ratio         = (string) ( $attributes['aspectRatio'] ?? '1/1' );
		$ratios        = array( 'auto', '1/1', '4/3', '3/2', '16/9', '3/4' );
		$align         = sanitize_key( (string) ( $attributes['textAlign'] ?? 'center' ) );
		$title_color   = sanitize_hex_color( (string) ( $attributes['titleColor'] ?? '#ffffff' ) );
		$caption_color = sanitize_hex_color( (string) ( $attributes['captionColor'] ?? '#ffffff' ) );
		$background_color = sanitize_hex_color( (string) ( $attributes['textBackgroundColor'] ?? '#000000' ) );
		$text_position = sanitize_key( (string) ( $attributes['textPosition'] ?? 'bottom' ) );
		$lightbox_mode = sanitize_key( (string) ( $attributes['lightboxMode'] ?? 'custom' ) );
		$lightbox_strip = sanitize_key( (string) ( $attributes['lightboxThumbnailPosition'] ?? 'horizontal' ) );
		$lightbox_info = sanitize_key( (string) ( $attributes['lightboxInfoPosition'] ?? 'bottom' ) );
		$lightbox_background = sanitize_hex_color( (string) ( $attributes['lightboxBackgroundColor'] ?? '#111315' ) );
		$lightbox_panel = sanitize_hex_color( (string) ( $attributes['lightboxPanelColor'] ?? '#1d2125' ) );
		$lightbox_title_color = sanitize_hex_color( (string) ( $attributes['lightboxTitleColor'] ?? '#ffffff' ) );
		$lightbox_caption_color = sanitize_hex_color( (string) ( $attributes['lightboxCaptionColor'] ?? '#d9dde1' ) );
		$lightbox_font = sanitize_key( (string) ( $attributes['lightboxFontFamily'] ?? 'inherit' ) );
		$lightbox_size = sanitize_key( (string) ( $attributes['lightboxSizeSlug'] ?? 'full' ) );
		$font_stacks = array(
			'inherit' => 'inherit',
			'system'  => 'system-ui, sans-serif',
			'serif'   => 'Georgia, "Times New Roman", serif',
			'mono'    => 'ui-monospace, SFMono-Regular, Menlo, monospace',
		);
		$font_weights = array( 300, 400, 500, 600, 700, 800, 900 );

		return array(
			'collectionId'   => absint( $attributes['collectionId'] ?? 0 ),
			'includeChildren' => ! empty( $attributes['includeChildren'] ),
			'layout'          => in_array( $layout, $layouts, true ) ? $layout : 'grid',
			'columns'         => max( 1, min( 8, absint( $attributes['columns'] ?? 3 ) ) ),
			'sizeSlug'        => in_array( $size, $sizes, true ) ? $size : 'large',
			'imageCrop'       => ! empty( $attributes['imageCrop'] ),
			'aspectRatio'     => in_array( $ratio, $ratios, true ) ? $ratio : '1/1',
			'randomOrder'     => ! empty( $attributes['randomOrder'] ),
			'showTitle'       => ! empty( $attributes['showTitle'] ),
			'showCaptions'    => ! empty( $attributes['showCaptions'] ),
			'useLightbox'     => ! array_key_exists( 'useLightbox', $attributes ) || ! empty( $attributes['useLightbox'] ),
			'lightboxMode'    => in_array( $lightbox_mode, array( 'custom', 'native', 'none' ), true ) ? $lightbox_mode : 'custom',
			'lightboxShowTitle' => ! array_key_exists( 'lightboxShowTitle', $attributes ) || ! empty( $attributes['lightboxShowTitle'] ),
			'lightboxShowCaption' => ! array_key_exists( 'lightboxShowCaption', $attributes ) || ! empty( $attributes['lightboxShowCaption'] ),
			'lightboxThumbnails' => ! array_key_exists( 'lightboxThumbnails', $attributes ) || ! empty( $attributes['lightboxThumbnails'] ),
			'lightboxFullscreen' => ! array_key_exists( 'lightboxFullscreen', $attributes ) || ! empty( $attributes['lightboxFullscreen'] ),
			'lightboxZoom'    => ! array_key_exists( 'lightboxZoom', $attributes ) || ! empty( $attributes['lightboxZoom'] ),
			'lightboxSizeSlug' => in_array( $lightbox_size, $sizes, true ) ? $lightbox_size : 'full',
			'lightboxThumbnailPosition' => in_array( $lightbox_strip, array( 'horizontal', 'vertical' ), true ) ? $lightbox_strip : 'horizontal',
			'lightboxInfoPosition' => in_array( $lightbox_info, array( 'top', 'bottom' ), true ) ? $lightbox_info : 'bottom',
			'lightboxBackgroundColor' => $lightbox_background ?: '#111315',
			'lightboxPanelColor' => $lightbox_panel ?: '#1d2125',
			'lightboxTitleColor' => $lightbox_title_color ?: '#ffffff',
			'lightboxCaptionColor' => $lightbox_caption_color ?: '#d9dde1',
			'lightboxFontFamily' => isset( $font_stacks[ $lightbox_font ] ) ? $lightbox_font : 'inherit',
			'lightboxTitleFontSize' => max( 12, min( 48, absint( $attributes['lightboxTitleFontSize'] ?? 20 ) ) ),
			'lightboxCaptionFontSize' => max( 10, min( 36, absint( $attributes['lightboxCaptionFontSize'] ?? 15 ) ) ),
			'lightboxTitleFontWeight' => in_array( absint( $attributes['lightboxTitleFontWeight'] ?? 700 ), $font_weights, true ) ? absint( $attributes['lightboxTitleFontWeight'] ?? 700 ) : 700,
			'lightboxCaptionFontWeight' => in_array( absint( $attributes['lightboxCaptionFontWeight'] ?? 400 ), $font_weights, true ) ? absint( $attributes['lightboxCaptionFontWeight'] ?? 400 ) : 400,
			'titleColor'      => $title_color ?: '#ffffff',
			'captionColor'    => $caption_color ?: '#ffffff',
			'textBackgroundColor' => $background_color ?: '#000000',
			'textBackgroundOpacity' => max( 0, min( 100, absint( $attributes['textBackgroundOpacity'] ?? 72 ) ) ),
			'titleFontSize'   => max( 12, min( 40, absint( $attributes['titleFontSize'] ?? 16 ) ) ),
			'captionFontSize' => max( 10, min( 32, absint( $attributes['captionFontSize'] ?? 14 ) ) ),
			'textAlign'       => in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center',
			'textPosition'    => in_array( $text_position, array( 'top', 'center', 'bottom' ), true ) ? $text_position : 'bottom',
			'limit'           => max( 1, min( 100, absint( $attributes['limit'] ?? 24 ) ) ),
			'gap'             => max( 0, min( 40, absint( $attributes['gap'] ?? 8 ) ) ),
		);
	}

	private function html_attributes( array $attributes ): string {
		$html = array();
		foreach ( $attributes as $name => $value ) {
			$html[] = sprintf( '%s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}
		return implode( ' ', $html );
	}

	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	private function resolve_collection( mixed $collection ): ?WP_Term {
		if ( is_numeric( $collection ) ) {
			return $this->etch->get_term( absint( $collection ) );
		}
		$term = get_term_by( 'slug', sanitize_title( (string) $collection ), $this->etch->taxonomy() );
		return $term instanceof WP_Term ? $term : null;
	}

	private function to_bool( mixed $value ): bool {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	private function empty_message( string $message, bool $is_block ): string {
		if ( ! $is_block || ! is_admin() ) {
			return '';
		}
		return '<p class="uplink-mbe-gallery-empty">' . esc_html( $message ) . '</p>';
	}
}
