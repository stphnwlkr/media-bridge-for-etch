<?php
/** Run with wp eval-file in disposable WordPress only. */
use UplinkPress\MediaBridgeForEtch\Native_Media_Library;
use UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider;
define( 'DOING_AJAX', true );
register_taxonomy( 'etch_collection', 'attachment', array( 'hierarchical' => true ) );
wp_set_current_user( 1 );
$library = new Native_Media_Library( new Etch_Provider(), false );
class Drag_Move_Response extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new Drag_Move_Response(); } );
function drag_check( bool $ok, string $label ): void { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
function drag_request( $library, array $ids, int $source, int $target, string $mode = 'move' ): array {
 $_POST = array( 'media_ids' => wp_json_encode( $ids ), 'source_collection' => $source, 'collection' => $target, 'mode' => $mode );
 $_REQUEST['nonce'] = wp_create_nonce( 'uplink_mbe_native_media' );
 ob_start(); try { $library->ajax_assign_media(); } catch ( Drag_Move_Response $e ) {}
 return json_decode( ob_get_clean(), true );
}
function drag_terms( int $id ): array { $ids = array_map( 'intval', wp_get_object_terms( $id, 'etch_collection', array( 'fields' => 'ids' ) ) ); sort( $ids ); return $ids; }
function drag_expect( int $id, array $expected, string $label ): void { sort( $expected ); drag_check( drag_terms( $id ) === $expected, $label ); }
$terms = array(); $ids = array(); $user = 0;
try {
 $tag = wp_generate_uuid4();
 foreach ( array( 'source', 'target', 'other', 'stale' ) as $name ) $terms[ $name ] = (int) wp_insert_term( "$name $tag", 'etch_collection' )['term_id'];
 $terms['child'] = (int) wp_insert_term( "child $tag", 'etch_collection', array( 'parent' => $terms['source'] ) )['term_id'];
 $user = wp_insert_user( array( 'user_login' => 'drag-' . $tag, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
 foreach ( array( $user, 1 ) as $author ) $ids[] = wp_insert_attachment( array( 'post_title' => 'Drag move test', 'post_author' => $author, 'post_mime_type' => 'image/jpeg' ) );
 $id = $ids[0]; $source = $terms['source']; $target = $terms['target']; $other = $terms['other']; $child = $terms['child'];
 wp_set_object_terms( $id, array( $source, $other ), 'etch_collection' );
 drag_check( drag_request( $library, array( $id ), $source, $target )['success'], 'Move request succeeds' );
 drag_expect( $id, array( $target, $other ), 'Removes source and preserves unrelated assignment' );
 drag_check( drag_request( $library, array( $id ), $target, $target )['success'], 'Same collection is a no-op' );
 drag_expect( $id, array( $target, $other ), 'Same collection preserves memberships' );
 wp_set_object_terms( $id, array( $child, $other ), 'etch_collection' );
 drag_check( drag_request( $library, array( $id ), $source, $target )['success'], 'Move from parent view accepts child membership' );
 drag_expect( $id, array( $target, $other ), 'Moves out of source subtree without dropping unrelated membership' );
 wp_set_object_terms( $id, array( $source, $other ), 'etch_collection' );
 drag_request( $library, array( $id ), $source, $child );
 drag_expect( $id, array( $child, $other ), 'Drop into child keeps child as destination' );
 drag_request( $library, array( $id ), 0, $target );
 drag_expect( $id, array( $child, $target, $other ), 'All media view adds destination without guessing a source' );
 drag_check( ! drag_request( $library, array( $id ), 99999999, $target )['success'], 'Invalid source rejected' );
 drag_check( ! drag_request( $library, array( $id ), $child, 99999999 )['success'], 'Invalid destination rejected' );
 drag_check( ! drag_request( $library, array( $id ), $terms['stale'], $target )['success'], 'Stale source membership rejected' );
 drag_expect( $id, array( $child, $target, $other ), 'Rejected requests do not alter assignments' );
 drag_request( $library, array( $id ), 0, 0, 'clear' );
 drag_expect( $id, array(), 'Uncategorized drop clears memberships' );
 wp_set_object_terms( $id, array( $source ), 'etch_collection' );
 wp_set_object_terms( $ids[1], array( $source ), 'etch_collection' );
 wp_set_current_user( $user );
 drag_check( drag_request( $library, array( $id ), $source, $target )['success'], 'Author can move own editable attachment' );
 drag_check( ! drag_request( $library, array( $ids[1] ), $source, $target )['success'], 'Author cannot move another author attachment' );
 drag_expect( $ids[1], array( $source ), 'Unauthorized attachment stays in source' );
 wp_set_current_user( 1 ); wp_set_object_terms( $id, array( $source ), 'etch_collection' ); wp_set_current_user( $user );
 drag_check( ! drag_request( $library, $ids, $source, $target )['success'], 'Mixed permission selection fails before writes' );
 drag_expect( $id, array( $source ), 'Mixed selection does not partially move editable image' );
 wp_set_current_user( 1 ); drag_request( $library, $ids, $source, $target );
 foreach ( $ids as $attachment ) drag_expect( $attachment, array( $target ), 'Admin can move selected images together' );
 drag_request( $library, array( $id ), 0, $other, 'add' );
 drag_expect( $id, array( $target, $other ), 'Toolbar Add still creates multiple assignments' );
 echo "Collection drag move tests passed.\n";
} finally {
 wp_set_current_user( 1 );
 foreach ( $ids as $id ) wp_delete_attachment( $id, true );
 foreach ( array_reverse( $terms ) as $term ) wp_delete_term( $term, 'etch_collection' );
 if ( $user && ! is_wp_error( $user ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user ); }
}
