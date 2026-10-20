<?php
/** Disposable WordPress only. ETCH_PLUGIN_DIR=/path/to/etch wp eval-file tests/loop-generator.php */
if ( ! defined( 'UPLINK_MBE_VERSION' ) ) require_once dirname( __DIR__ ) . '/media-bridge-for-etch.php';
if ( ! class_exists( '\Etch\Blocks\Global\Data\EtchDataGlobalLoop' ) ) {
	$etch_path = rtrim( (string) getenv( 'ETCH_PLUGIN_DIR' ), '/' );
	require_once $etch_path . '/classes/Blocks/Utilities/EtchTypeAsserter.php';
	require_once $etch_path . '/classes/Blocks/Global/Data/EtchDataGlobalLoop.php';
}
define( 'DOING_AJAX', true );
register_taxonomy( 'etch_collection', 'attachment', array( 'hierarchical' => true ) );
wp_set_current_user( 1 );
$generator = new UplinkPress\MediaBridgeForEtch\Loop_Generator( new UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider() );
class Loop_Test_Response extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', static fn() => static function() { throw new Loop_Test_Response(); } );
function loop_check( bool $passed, string $label ): void {
	if ( ! $passed ) throw new RuntimeException( $label );
	echo "PASS: {$label}\n";
}
function call_generator( $generator, string $method, array $data ): array {
	$_POST = $data;
	$_REQUEST['nonce'] = wp_create_nonce( 'uplink_mbe_loop_generator' );
	ob_start();
	try { $generator->$method(); } catch ( Loop_Test_Response $e ) {}
	return json_decode( ob_get_clean(), true );
}
$original = get_option( 'etch_loops', array() );
$term = wp_insert_term( 'Loop generator test ' . wp_generate_uuid4(), 'etch_collection' )['term_id'];
$id = wp_insert_attachment( array( 'post_title' => 'Test image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
wp_set_object_terms( $id, array( $term ), 'etch_collection' );
$data = array( 'collection' => $term, 'name' => 'Generated images', 'sort' => 'custom', 'all_images' => 1 );
try {
	$sentinel = array( 'name' => 'Existing loop', 'key' => 'existing', 'global' => true, 'config' => array( 'type' => 'wp-query', 'args' => array( 'post_type' => 'post' ) ) );
	update_option( 'etch_loops', array( 'existing' => $sentinel ) );
	$preview = call_generator( $generator, 'preview', $data );
	loop_check( $preview['success'] && $preview['data']['args']['orderby'] === 'collection_order', 'Custom sort generates collection_order query' );
	loop_check( str_contains( $preview['data']['summary'], '1 of 1' ), 'Preview counts matching images' );
	loop_check( count( get_option( 'etch_loops' ) ) === 1, 'Preview never changes the loop library' );
	$saved = call_generator( $generator, 'save', $data );
	loop_check( $saved['success'], 'Save creates a reusable loop' );
	$loops = get_option( 'etch_loops' );
	loop_check( $loops['existing'] === $sentinel, 'Existing loop remains byte-for-byte unchanged' );
	$created = $loops[ $saved['data']['id'] ];
	loop_check( null !== Etch\Blocks\Global\Data\EtchDataGlobalLoop::from_array( $created ), 'Etch validates the saved loop format' );
	loop_check( $created['global'] && $created['name'] === $data['name'], 'Loop is globally available by its chosen name' );
	$again = call_generator( $generator, 'save', $data );
	loop_check( $again['success'] && count( get_option( 'etch_loops' ) ) === 2, 'Repeat save does not duplicate the loop' );
	$conflict = call_generator( $generator, 'save', array_merge( $data, array( 'sort' => 'title' ) ) );
	loop_check( ! $conflict['success'] && get_option( 'etch_loops' ) === $loops, 'Same name with different settings cannot overwrite a loop' );
	foreach ( array( 'newest' => 'date', 'oldest' => 'date', 'title' => 'title', 'random' => 'rand' ) as $sort => $orderby ) {
		$response = call_generator( $generator, 'preview', array_merge( $data, array( 'sort' => $sort, 'all_images' => 0, 'limit' => 8, 'include_children' => 1 ) ) );
		$args = $response['data']['args'];
		loop_check( $args['orderby'] === $orderby && $args['posts_per_page'] === 8 && $args['tax_query'][0]['include_children'], "{$sort} sort, limit, and child collections" );
	}
	loop_check( ! call_generator( $generator, 'save', array_merge( $data, array( 'collection' => 99999999 ) ) )['success'], 'Reject missing collection' );
	loop_check( ! call_generator( $generator, 'save', array_merge( $data, array( 'sort' => 'invalid' ) ) )['success'], 'Reject unsupported ordering' );
	loop_check( ! call_generator( $generator, 'save', array_merge( $data, array( 'all_images' => 0, 'limit' => 0 ) ) )['success'], 'Reject invalid limit' );
	wp_set_current_user( 0 );
	loop_check( ! call_generator( $generator, 'save', $data )['success'], 'Reject unauthorized library changes' );
	wp_set_current_user( 1 );
	loop_check( get_option( 'etch_loops' ) === $loops, 'Invalid requests leave the library unchanged' );
} finally {
	update_option( 'etch_loops', $original );
	wp_delete_attachment( $id, true );
	wp_delete_term( $term, 'etch_collection' );
}
echo "Loop generator integration tests passed.\n";
