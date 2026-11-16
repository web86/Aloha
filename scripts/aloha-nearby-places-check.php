<?php
/** Integration tests on the disposable Aloha Content site. */

use const Aloha\Content\NEARBY_PLACE_DETAILS;
use const Aloha\Content\NEARBY_PLACE_AREA;
use function Aloha\Content\get_nearby_place_coordinates;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE ) {
	exit( 1 );
}

$checks = 0;
$check = static function ( $ok, $message ) use ( &$checks ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$admin = get_user_by( 'login', 'aloha_test_admin' );
wp_set_current_user( $admin->ID );
$editor = wp_insert_user( array( 'user_login' => 'nearby-fixture-editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$check( is_int( $editor ), 'Could not create fixture editor.' );
$ids = array();
$add = static function ( $type, $title, $status = 'publish', $args = array() ) use ( &$ids, $check ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => $status ) + $args, true );
	$check( is_int( $id ) && $id > 0, 'Could not create fixture ' . $title );
	$ids[] = $id;
	return $id;
};
$rest = static function ( $method, $path, $params = array() ) {
	$request = new WP_REST_Request( $method, $path );
	$request->set_query_params( $params );
	return rest_do_request( $request );
};
$meta = static fn ( $lat, $lng, $category = '' ) => array( NEARBY_PLACE_DETAILS => array(
	'latitude' => $lat, 'longitude' => $lng, 'category' => $category,
) );
$route = '/aloha-content/v1/nearby-places';
try {
	$type = get_post_type_object( 'nearby_place' );
	$check( $type && $type->show_ui && $type->show_in_rest && ! $type->public && ! $type->publicly_queryable
		&& ! $type->has_archive && ! $type->rewrite && ! $type->query_var && $type->exclude_from_search,
		'Catalog is missing or publicly queryable.' );
	$check( false === get_post_type_archive_link( 'nearby_place' ), 'Catalog has an archive link.' );
	$area_a = $add( 'area', 'Nearby fixture Area A' );
	$area_b = $add( 'area', 'Nearby fixture Area B' );
	$area_draft = $add( 'area', 'Nearby fixture Draft Area', 'draft' );
	$community = $add( 'community', 'Nearby fixture Community A', 'publish', array( 'meta_input' => array( 'aloha_area_id' => $area_a ) ) );

	$draft = $add( 'nearby_place', 'Fixture Cove draft', 'draft', array( 'meta_input' => $meta( '', '' ) ) );
	$check( array( 'latitude' => null, 'longitude' => null ) === get_nearby_place_coordinates( $draft ), 'Draft can omit both coordinates.' );
	$check( is_wp_error( wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ), true ) )
		&& 'draft' === get_post_status( $draft ), 'Missing coordinates allowed on publish.' );
	wp_publish_post( $draft );
	$check( 'draft' === get_post_status( $draft ), 'Direct/scheduled publication left an empty place published.' );
	$check( $draft === wp_update_post( array( 'ID' => $draft, 'meta_input' => $meta( '19.82', '' ) ), true ), 'Partial draft should save.' );
	$check( array( 'latitude' => 19.82, 'longitude' => null ) === get_nearby_place_coordinates( $draft ), 'Partial draft coordinate was lost.' );
	foreach ( array( '90.1', '-90.1', '180', '1e1', '1,5', 'text', array( 1 ), true, INF, NAN ) as $bad ) {
		$check( is_wp_error( wp_update_post( array( 'ID' => $draft, 'meta_input' => $meta( $bad, '-155' ) ), true ) ),
			'Invalid coordinate allowed even in a draft.' );
	}
	$check( is_wp_error( wp_update_post( array( 'ID' => $draft, 'meta_input' => $meta( 0, 181 ) ), true ) ), 'Invalid longitude accepted.' );
	foreach ( array( $community, $area_draft, -1, (string) $area_a . '.5', true, 999999999 ) as $bad ) {
		$check( is_wp_error( wp_update_post( array( 'ID' => $draft, 'meta_input' => array( NEARBY_PLACE_AREA => $bad ) ), true ) ), 'Forged Area was accepted.' );
	}

	$cove_a = $add( 'nearby_place', 'Fixture Cove A', 'publish', array( 'meta_input' => $meta( 0, -180, '<b>Beach</b>' ) + array( NEARBY_PLACE_AREA => $area_a ) ) );
	$cove_b = $add( 'nearby_place', 'Fixture Cove B', 'publish', array( 'meta_input' => $meta( -90, 180 ) + array( NEARBY_PLACE_AREA => $area_b ) ) );
	$trail = $add( 'nearby_place', 'Fixture Trail', 'publish', array( 'meta_input' => $meta( 90, 0, 'Hike' ), 'post_content' => 'Cove' ) );
	$check( array( 'latitude' => 0.0, 'longitude' => -180.0 ) === get_nearby_place_coordinates( $cove_a ), 'Zero and valid boundary rejected.' );
	$check( $cove_a === wp_update_post( array( 'ID' => $cove_a, 'post_title' => 'Fixture Cove Updated', 'meta_input' => $meta( 1, 2, 'Beach' ) ), true ),
		'Published edit failed.' );
	$check( array( 'latitude' => 1.0, 'longitude' => 2.0 ) === get_nearby_place_coordinates( $cove_a ), 'Published edit did not update coordinates.' );
	$check( ! update_post_meta( $cove_a, NEARBY_PLACE_DETAILS, array( 'latitude' => 1 ) )
		&& ! add_post_meta( $cove_a, NEARBY_PLACE_DETAILS, $meta( 0, 0 )[ NEARBY_PLACE_DETAILS ] )
		&& ! delete_post_meta( $cove_a, NEARBY_PLACE_DETAILS )
		&& ! delete_post_meta_by_key( NEARBY_PLACE_DETAILS ), 'Invalid or duplicate direct metadata mutation was accepted.' );
	global $wpdb;
	$mid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $cove_a, NEARBY_PLACE_DETAILS ) );
	$check( $mid && ! update_metadata_by_mid( 'post', $mid, array() ) && ! delete_metadata_by_mid( 'post', $mid ), 'By-ID mutation broke coordinate integrity.' );

	$result = $rest( 'GET', $route, array( 'search' => 'Cove' ) );
	$check( 200 === $result->get_status() && array_column( $result->get_data(), 'id' ) === array( $cove_b, $cove_a ), 'Title-only search included draft or content.' );
	$check( array( 'id' => $cove_a, 'name' => 'Fixture Cove Updated', 'category' => 'Beach', 'areaId' => $area_a, 'status' => 'publish' ) === $result->get_data()[1], 'Unexpected selector fields or types.' );
	$result = $rest( 'GET', $route, array( 'search' => 'Cove', 'areaId' => $area_b, 'per_page' => 1 ) );
	$check( 200 === $result->get_status() && array( $cove_b ) === array_column( $result->get_data(), 'id' )
		&& 'private, no-store' === $result->get_headers()['Cache-Control'], 'Area filtering or private response failed.' );
	$result = $rest( 'GET', $route, array( 'search' => 'Fixture', 'per_page' => 1, 'page' => 2 ) );
	$check( 200 === $result->get_status() && array( $cove_a ) === array_column( $result->get_data(), 'id' )
		&& 3 === (int) $result->get_headers()['X-WP-TotalPages'], 'Selector pagination failed.' );
	$check( 400 === $rest( 'GET', $route, array( 'areaId' => $area_draft ) )->get_status()
		&& 400 === $rest( 'GET', $route, array( 'per_page' => 101 ) )->get_status(), 'Selector accepted invalid filters.' );
	$check( 404 === $rest( 'GET', '/wp/v2/nearby_place' )->get_status()
		&& 404 === $rest( 'POST', $route )->get_status(), 'Generic REST read or catalog REST write was registered.' );
	$check( 400 === $rest( 'GET', '/wp/v2/search', array( 'subtype' => 'nearby_place' ) )->get_status(), 'Generic Core search exposed the catalog type.' );

	foreach ( array( 0, $editor ) as $user ) {
		wp_set_current_user( $user );
		$check( ( $user ? 403 : 401 ) === $rest( 'GET', $route )->get_status()
			&& is_wp_error( get_nearby_place_coordinates( $draft ) )
			&& ! current_user_can( 'edit_post', $cove_a ) && ! current_user_can( 'delete_post', $cove_a ),
			'Unauthorized user could read or edit places.' );
	}
	$check( is_wp_error( wp_update_post( array( 'ID' => $cove_a, 'post_title' => 'Unauthorized' ), true ) )
		&& ! wp_trash_post( $cove_a ) && ! wp_delete_post( $cove_a, true ), 'An editor bypassed the catalog capabilities.' );
	wp_set_current_user( $admin->ID );
	$check( $draft === wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish', 'meta_input' => $meta( 1, 2 ) ), true )
		&& 'publish' === get_post_status( $draft ), 'First publication with a valid pair failed.' );

	$check( wp_trash_post( $cove_b ) instanceof WP_Post
		&& array( $cove_a ) === array_column( $rest( 'GET', $route, array( 'search' => 'Updated' ) )->get_data(), 'id' ),
		'Trash unexpectedly changed another place.' );
	$check( wp_delete_post( $cove_b, true ) instanceof WP_Post && ! get_post( $cove_b )
		&& ! get_post_meta( $cove_b, NEARBY_PLACE_DETAILS, true ), 'Deleting a catalog record did not clean up its own metadata.' );
	$check( 'publish' === get_post_status( $area_b ), 'Catalog deletion affected Area.' );
	wp_update_post( array( 'ID' => $cove_a, 'meta_input' => array( NEARBY_PLACE_AREA => $area_b ) ) );
	$check( $area_b === wp_update_post( array( 'ID' => $area_b, 'post_status' => 'draft' ), true ), 'Catalog blocked an Area lifecycle action.' );
	$result = $rest( 'GET', $route, array( 'search' => 'Updated' ) )->get_data();
	$check( count( $result ) === 1 && null === $result[0]['areaId'], 'Unpublished Area leaked through the catalog relation.' );
	$check( wp_delete_post( $area_b, true ) instanceof WP_Post && 'publish' === get_post_status( $cove_a )
		&& (int) get_post_meta( $community, 'aloha_area_id', true ) === $area_a, 'Area deletion cascaded or affected Community ownership.' );

	WP_CLI::success( $checks . ' Nearby Places integration checks passed.' );
} finally {
	wp_set_current_user( $admin->ID );
	foreach ( array_reverse( $ids ) as $id ) {
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $editor );
}
