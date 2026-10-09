<?php
namespace UplinkPress\MediaBridgeForEtch;

use WP_Query;

/** Create collection image loops in Etch's existing loop library. */
final class Loop_Generator {
	private Provider_Interface $etch;

	public function __construct( Provider_Interface $etch ) {
		$this->etch = $etch;
		add_action( 'wp_ajax_uplink_mbe_preview_loop', array( $this, 'preview' ) );
		add_action( 'wp_ajax_uplink_mbe_save_loop', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'uplink_mbe_loop_dialog', array( $this, 'dialog' ) );
	}

	public function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'media_page_media-bridge-for-etch', 'media_page_etch-collections' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'uplink-mbe-loop-generator', UPLINK_MBE_URL . 'assets/loop-generator.css', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-loop-generator', UPLINK_MBE_URL . 'assets/loop-generator.js', array(), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbe-loop-generator', 'uplinkMbeLoopGenerator', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'uplink_mbe_loop_generator' ),
			'strings' => array(
				'copy' => __( 'Copy query', 'media-bridge-for-etch' ),
				'copied' => __( 'Query copied.', 'media-bridge-for-etch' ),
				'copyFailed' => __( 'Select and copy the query manually.', 'media-bridge-for-etch' ),
				'choose' => __( 'Choose a collection to preview its loop.', 'media-bridge-for-etch' ),
				'loading' => __( 'Checking images…', 'media-bridge-for-etch' ),
				'saving' => __( 'Adding to Etch…', 'media-bridge-for-etch' ),
				'save' => __( 'Add to Etch loop library', 'media-bridge-for-etch' ),
				'error' => __( 'Could not complete the request. Please try again.', 'media-bridge-for-etch' ),
				/* translators: %s is the collection name. */
				'name' => __( '%s images', 'media-bridge-for-etch' ),
			),
		) );
	}

	public function dialog(): void {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_posts' ) ) return;
		?>
		<dialog id="uplink-mbe-loop-dialog" aria-label="<?php esc_attr_e( 'Etch image loop generator', 'media-bridge-for-etch' ); ?>">
			<button type="button" class="button uplink-mbe-loop-close" aria-label="<?php esc_attr_e( 'Close loop generator', 'media-bridge-for-etch' ); ?>">&times;</button>
			<?php $this->render(); ?>
		</dialog>
		<?php
	}

	public function render(): void {
		$terms = $this->etch->get_terms();
		if ( is_wp_error( $terms ) ) {
			echo '<p>' . esc_html( $terms->get_error_message() ) . '</p>';
			return;
		}
		$terms = $this->etch->order_terms_hierarchically( $terms );
		$by_id = array();
		foreach ( $terms as $term ) {
			$by_id[ $term->term_id ] = $term;
		}
		?>
		<section class="uplink-mbe-card">
			<h2><?php esc_html_e( 'Etch image loop generator', 'media-bridge-for-etch' ); ?></h2>
			<p><?php esc_html_e( 'Choose a collection by name and add a reusable image loop to Etch. The loop stays connected to the collection as images are added, removed, or reordered.', 'media-bridge-for-etch' ); ?></p>
			<form id="uplink-mbe-loop-generator" class="uplink-mbe-loop-generator">
				<div class="uplink-mbe-loop-controls">
				<label for="uplink-mbe-loop-collection"><?php esc_html_e( 'Collection', 'media-bridge-for-etch' ); ?></label>
				<select id="uplink-mbe-loop-collection" name="collection" required>
					<option value=""><?php esc_html_e( 'Choose a collection…', 'media-bridge-for-etch' ); ?></option>
					<?php foreach ( $terms as $term ) :
						$path = array( $term->name );
						$parent = (int) $term->parent;
						$seen = array( $term->term_id => true );
						while ( $parent && isset( $by_id[ $parent ] ) && ! isset( $seen[ $parent ] ) ) {
							$seen[ $parent ] = true;
							array_unshift( $path, $by_id[ $parent ]->name );
							$parent = (int) $by_id[ $parent ]->parent;
						}
						?>
						<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( implode( ' / ', $path ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! $terms ) : ?><p><?php esc_html_e( 'Create an Etch collection first, then return here to generate its loop.', 'media-bridge-for-etch' ); ?></p><?php endif; ?>
				<label for="uplink-mbe-loop-name"><?php esc_html_e( 'Loop name', 'media-bridge-for-etch' ); ?></label>
				<input id="uplink-mbe-loop-name" name="name" type="text" required pattern=".*\S.*" maxlength="120" autocomplete="off" aria-describedby="uplink-mbe-loop-name-help">
				<small id="uplink-mbe-loop-name-help"><?php esc_html_e( 'Required when adding to Etch. No name is needed to copy the query.', 'media-bridge-for-etch' ); ?></small>
				<label for="uplink-mbe-loop-sort"><?php esc_html_e( 'Image order', 'media-bridge-for-etch' ); ?></label>
				<select id="uplink-mbe-loop-sort" name="sort">
					<option value="custom"><?php esc_html_e( 'Custom collection order', 'media-bridge-for-etch' ); ?></option>
					<option value="newest"><?php esc_html_e( 'Newest first', 'media-bridge-for-etch' ); ?></option>
					<option value="oldest"><?php esc_html_e( 'Oldest first', 'media-bridge-for-etch' ); ?></option>
					<option value="title"><?php esc_html_e( 'Image title (A–Z)', 'media-bridge-for-etch' ); ?></option>
					<option value="random"><?php esc_html_e( 'Random', 'media-bridge-for-etch' ); ?></option>
				</select>
				<label><input type="checkbox" name="include_children" value="1"> <?php esc_html_e( 'Include images from child collections', 'media-bridge-for-etch' ); ?></label>
				<label><input type="checkbox" name="all_images" value="1" checked> <?php esc_html_e( 'Include all images', 'media-bridge-for-etch' ); ?></label>
				<label for="uplink-mbe-loop-limit"><?php esc_html_e( 'Maximum images', 'media-bridge-for-etch' ); ?></label>
				<input id="uplink-mbe-loop-limit" name="limit" type="number" min="1" max="1000" value="24" disabled>
				<p data-loop-summary role="status" aria-live="polite"><?php esc_html_e( 'Choose a collection to preview its loop.', 'media-bridge-for-etch' ); ?></p>
				<p><button type="submit" class="button button-primary" <?php disabled( ! $terms ); ?>><?php esc_html_e( 'Add to Etch loop library', 'media-bridge-for-etch' ); ?></button></p>
				<p data-loop-result role="status" aria-live="polite"></p>
				</div>
				<section class="uplink-mbe-loop-code" aria-labelledby="uplink-mbe-loop-code-title">
					<div class="uplink-mbe-loop-code-heading">
						<h3 id="uplink-mbe-loop-code-title"><?php esc_html_e( 'Generated query', 'media-bridge-for-etch' ); ?></h3>
						<button type="button" class="button" data-loop-copy disabled><?php esc_html_e( 'Copy query', 'media-bridge-for-etch' ); ?></button>
					</div>
					<p><?php esc_html_e( 'Copy these arguments into an Etch WordPress query loop to add it manually.', 'media-bridge-for-etch' ); ?></p>
					<pre tabindex="0" aria-label="<?php esc_attr_e( 'Generated query code', 'media-bridge-for-etch' ); ?>"><code data-loop-query><?php esc_html_e( 'Choose a collection to preview its loop.', 'media-bridge-for-etch' ); ?></code></pre>
					<p data-loop-copy-status role="status" aria-live="polite"></p>
				</section>
			</form>
			<p><?php esc_html_e( 'After adding the loop, open or reload the Etch builder and select it by name in the loop library. Existing loops are never overwritten.', 'media-bridge-for-etch' ); ?></p>
		</section>
		<?php
	}

	private function request_args(): array {
		check_ajax_referer( 'uplink_mbe_loop_generator', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to create Etch loops.', 'media-bridge-for-etch' ) ), 403 );
		}
		if ( ! $this->etch->is_available() || ! class_exists( '\Etch\Blocks\Global\Data\EtchDataGlobalLoop' ) ) {
			wp_send_json_error( array( 'message' => __( 'An active, compatible Etch installation is required.', 'media-bridge-for-etch' ) ), 409 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		$term = $this->etch->get_term( absint( $_POST['collection'] ?? 0 ) );
		$sort = sanitize_key( wp_unslash( $_POST['sort'] ?? 'custom' ) );
		$limit = ! empty( $_POST['all_images'] ) ? -1 : absint( $_POST['limit'] ?? 24 );
		$children = ! empty( $_POST['include_children'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$sorts = array( 'custom' => array( 'collection_order', 'ASC' ), 'newest' => array( 'date', 'DESC' ), 'oldest' => array( 'date', 'ASC' ), 'title' => array( 'title', 'ASC' ), 'random' => array( 'rand', 'ASC' ) );
		if ( ! $term || ! isset( $sorts[ $sort ] ) || ( -1 !== $limit && ( $limit < 1 || $limit > 1000 ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid collection, image order, and image limit.', 'media-bridge-for-etch' ) ), 400 );
		}
		return array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image',
			'posts_per_page' => $limit, 'orderby' => $sorts[ $sort ][0], 'order' => $sorts[ $sort ][1],
			'tax_query' => array( array( 'taxonomy' => $this->etch->taxonomy(), 'field' => 'term_id', 'terms' => array( (int) $term->term_id ), 'include_children' => $children ) ),
		);
	}

	public function preview(): void {
		$args = $this->request_args();
		$query = new WP_Query( array_merge( $args, array( 'posts_per_page' => 1, 'fields' => 'ids' ) ) );
		$count = (int) $query->found_posts;
		$shown = -1 === $args['posts_per_page'] ? $count : min( $count, $args['posts_per_page'] );
		wp_send_json_success( array(
			'args' => $args,
			/* translators: 1: images returned, 2: matching images in the collection. */
			'summary' => sprintf( __( 'This loop will return %1$d of %2$d matching images.', 'media-bridge-for-etch' ), $shown, $count ),
		) );
	}

	public function save(): void {
		$args = $this->request_args();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in request_args().
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( '' === trim( $name ) || strlen( $name ) > 480 ) {
			wp_send_json_error( array( 'message' => __( 'Enter a loop name.', 'media-bridge-for-etch' ) ), 400 );
		}
		$loops = get_option( 'etch_loops', array() );
		if ( ! is_array( $loops ) ) {
			wp_send_json_error( array( 'message' => __( 'The Etch loop library could not be read. No loops were changed.', 'media-bridge-for-etch' ) ), 409 );
		}
		$config = array( 'type' => 'wp-query', 'args' => $args );
		foreach ( $loops as $loop_id => $existing ) {
			if ( is_array( $existing ) && 0 === strcasecmp( (string) ( $existing['name'] ?? '' ), $name ) ) {
				if ( ( $existing['config'] ?? null ) === $config ) {
					wp_send_json_success( array( 'id' => $loop_id, 'message' => __( 'This loop is already in the Etch loop library. Open or reload Etch to select it.', 'media-bridge-for-etch' ) ) );
				}
				wp_send_json_error( array( 'message' => __( 'A different loop already uses this name. Choose another name to keep the existing loop unchanged.', 'media-bridge-for-etch' ) ), 409 );
			}
		}
		$id = 'mbe-' . wp_generate_uuid4();
		$loop = array( 'name' => $name, 'key' => $id, 'global' => true, 'config' => $config );
		if ( ! \Etch\Blocks\Global\Data\EtchDataGlobalLoop::from_array( $loop ) ) {
			wp_send_json_error( array( 'message' => __( 'Etch did not accept this loop format.', 'media-bridge-for-etch' ) ), 409 );
		}
		$loops[ $id ] = $loop;
		if ( ! update_option( 'etch_loops', $loops ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the loop. Please try again.', 'media-bridge-for-etch' ) ), 500 );
		}
		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Added to the Etch loop library. Open or reload Etch and select your loop by name.', 'media-bridge-for-etch' ) ) );
	}
}
