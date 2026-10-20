<?php
/** Run only on a disposable WordPress install: wp eval-file tests/collection-media-order.php */
use UplinkPress\MediaBridgeForEtch\Native_Media_Library;
use UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider;
use UplinkPress\MediaBridgeForEtch\Collection_Gallery;

if ( ! defined( 'UPLINK_MBE_VERSION' ) ) require_once dirname( __DIR__ ) . '/media-bridge-for-etch.php';
define( 'DOING_AJAX', true );
register_taxonomy( 'etch_collection', 'attachment', array( 'hierarchical' => true ) );
wp_set_current_user( 1 );
$library = new Native_Media_Library( new Etch_Provider(), false );
$gallery = new Collection_Gallery( new Etch_Provider() );
$gallery->register();
class Order_Test_Response extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new Order_Test_Response(); } );
function check_order( $actual, $expected, string $label ): void {
	if ( $actual !== $expected ) {
		throw new RuntimeException( $label . ': expected ' . json_encode( $expected ) . ', got ' . json_encode( $actual ) );
	}
	echo "PASS: {$label}\n";
}
function query_order( int $collection, array $extra = array() ): array {
	$args = array_merge( array(
		'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1,
		'orderby' => 'collection_order', 'order' => 'ASC', 'fields' => 'ids',
		'tax_query' => array( array( 'taxonomy' => 'etch_collection', 'field' => 'term_id', 'terms' => array( $collection ), 'include_children' => true ) ),
	), $extra );
	return array_map( 'intval', ( new WP_Query( $args ) )->posts );
}
function move_items( Native_Media_Library $library, int $collection, array $ids, int $target, string $placement = 'before' ): array {
	$_POST = array( 'collection' => $collection, 'media_ids' => wp_json_encode( $ids ), 'target' => $target, 'placement' => $placement );
	$_REQUEST['nonce'] = wp_create_nonce( 'uplink_mbe_native_media' );
	ob_start();
	try { $library->ajax_reorder_media(); } catch ( Order_Test_Response $e ) {}
	return json_decode( ob_get_clean(), true );
}
$tag = wp_generate_password( 8, false );
$a = wp_insert_term( 'Order A ' . $tag, 'etch_collection' )['term_id'];
$b = wp_insert_term( 'Order B ' . $tag, 'etch_collection' )['term_id'];
$child = wp_insert_term( 'Order child ' . $tag, 'etch_collection', array( 'parent' => $a ) )['term_id'];
$ids = array();
for ( $i = 0; $i < 45; $i++ ) {
	$id = wp_insert_attachment( array( 'post_title' => sprintf( 'Photo %02d', $i ), 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'menu_order' => 100 + $i, 'guid' => 'http://localhost/photo-' . $i . '.jpg' ) );
	update_post_meta( $id, '_wp_attached_file', 'photo-' . $i . '.jpg' );
	update_post_meta( $id, '_wp_attachment_metadata', array( 'file' => 'photo-' . $i . '.jpg', 'width' => 800, 'height' => 600, 'sizes' => array() ) );
	wp_set_object_terms( $id, array( $a, $b ), 'etch_collection' );
	$ids[] = $id;
}
check_order( query_order( $a ), $ids, 'Unordered collection has stable ID order' );
$response = move_items( $library, $a, array( $ids[44] ), $ids[0] );
$expected = array_merge( array( $ids[44] ), array_slice( $ids, 0, 44 ) );
check_order( $response['success'], true, 'Relative move saves successfully' );
check_order( query_order( $a ), $expected, 'Move across a page boundary retains all 45 items' );
check_order( query_order( $b ), $ids, 'Shared attachments keep independent order in collection B' );
check_order( array_map( static fn( $id ) => (int) get_post( $id )->menu_order, $ids ), range( 100, 144 ), 'Attachment menu_order is unchanged' );
check_order( query_order( $a, array( 'posts_per_page' => 5, 'paged' => 2 ) ), array_slice( $expected, 5, 5 ), 'SQL order applies before pagination' );
check_order( query_order( $a, array( 'post__in' => array( $ids[0], $ids[44] ) ) ), array( $ids[44], $ids[0] ), 'Filtered query retains custom order' );
check_order( query_order( $a, array( 'order' => 'DESC' ) ), array_reverse( $expected ), 'Descending custom order' );
$slug_query = array( array( 'taxonomy' => 'etch_collection', 'field' => 'slug', 'terms' => array( get_term( $a )->slug ) ) );
check_order( query_order( $a, array( 'tax_query' => $slug_query ) ), $expected, 'Etch-style slug taxonomy query' );
move_items( $library, $a, array( $ids[3], $ids[1] ), $ids[0], 'after' );
$expected = array_values( array_diff( $expected, array( $ids[1], $ids[3] ) ) );
array_splice( $expected, 2, 0, array( $ids[1], $ids[3] ) );
check_order( query_order( $a ), $expected, 'Multi-selection preserves current order, not click order' );
move_items( $library, $b, array( $ids[2] ), $ids[0] );
check_order( query_order( $a ), $expected, 'Reordering B does not change A' );
check_order( move_items( $library, $a, array( 99999999 ), $ids[0] )['success'], false, 'Reject unrelated or deleted attachment' );
check_order( move_items( $library, $a, array( array( $ids[1] ) ), $ids[0] )['success'], false, 'Reject malformed IDs' );
check_order( move_items( $library, $a, array( $ids[0] ), $ids[0] )['success'], false, 'Reject self-target' );
$deny = static function ( $caps, $cap, $user, $args ) use ( $ids ) {
	return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $ids[40] ? array( 'do_not_allow' ) : $caps;
};
add_filter( 'map_meta_cap', $deny, 10, 4 );
check_order( move_items( $library, $a, array( $ids[0] ), $ids[1] )['success'], false, 'Reject reorder when an unseen attachment is not editable' );
remove_filter( 'map_meta_cap', $deny );
check_order( query_order( $a ), $expected, 'Denied save leaves order unchanged' );
// Taxonomy membership, not the stored list, decides which images are returned.
wp_remove_object_terms( $ids[44], $a, 'etch_collection' );
$expected = array_values( array_diff( $expected, array( $ids[44] ) ) );
check_order( query_order( $a ), $expected, 'Removed membership ignores stale stored ID' );
wp_set_object_terms( $ids[44], array( $child ), 'etch_collection', true );
check_order( query_order( $a )[0], $ids[44], 'Parent order includes child membership' );
check_order( query_order( $child ), array( $ids[44] ), 'Child has its own order' );
// Observe the actual rendered block query while preserving the normal renderer.
$captured = array();
$capture = static function ( $query ) use ( &$captured ) {
	if ( 'attachment' === $query->get( 'post_type' ) ) { $captured = $query->query_vars; }
};
add_action( 'pre_get_posts', $capture );
$gallery->render_block( array( 'collectionId' => $a, 'sortOrder' => 'custom', 'limit' => 3, 'lightboxMode' => 'none' ) );
check_order( $captured['orderby'], 'collection_order', 'Block Custom order uses collection order' );
check_order( $captured['order'], 'ASC', 'Block Custom order is ascending' );
$gallery->render_block( array( 'collectionId' => $a, 'randomOrder' => true, 'limit' => 1 ) );
check_order( $captured['orderby'], 'rand', 'Existing random blocks stay random' );
$gallery->render_block( array( 'collectionId' => $a, 'limit' => 1 ) );
check_order( $captured['orderby'], 'date', 'Existing default blocks keep date order' );
$gallery->render_shortcode( array( 'collection' => $a, 'sort' => 'custom', 'limit' => 2 ) );
check_order( $captured['orderby'], 'collection_order', 'Shortcode custom order' );
remove_action( 'pre_get_posts', $capture );
foreach ( $ids as $id ) { wp_delete_attachment( $id, true ); }
foreach ( array( $child, $a, $b ) as $term ) { wp_delete_term( $term, 'etch_collection' ); }
echo "Collection order integration tests passed.\n";
