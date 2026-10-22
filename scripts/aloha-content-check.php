<?php
/** Real Core integration tests, intentionally refused on the regular local site. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Disposable Aloha Content test site only.' );
}

$checks = 0;
$ids = array();
$check = function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$post = function ( $type, $status = 'draft', $meta = array() ) use ( &$ids, $check ) {
	$data = array( 'post_type' => $type, 'post_status' => $status, 'post_title' => 'Isolated test ' . $type . ' ' . wp_generate_uuid4(), 'meta_input' => $meta );
	if ( 'future' === $status ) {
		$data['post_date'] = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
		$data['post_date_gmt'] = $data['post_date'];
	}
	$id = wp_insert_post( $data, true );
	$check( ! is_wp_error( $id ) && $id > 0, 'Create fixture ' . $type );
	$ids[] = $id;
	return $id;
};
$rest = function ( $method, $path, $body = array() ) {
	$request = new WP_REST_Request( $method, '/wp/v2/' . $path );
	$request->set_body_params( $body );
	return rest_do_request( $request );
};
$fingerprint = function () use ( &$ids ) {
	return hash( 'sha256', wp_json_encode( array(
		get_option( 'permalink_structure' ), get_option( 'aloha_shell_menus' ),
		get_posts( array( 'post_type' => array( 'page', 'post', 'wp_navigation' ), 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'post__not_in' => $ids ) ),
	) ) );
};
$admin = get_user_by( 'login', 'aloha_test_admin' );
wp_set_current_user( $admin->ID );
$baseline = $fingerprint();
$rules = get_option( 'rewrite_rules' );

try {
	foreach ( array( 'area', 'community' ) as $type ) {
		$object = get_post_type_object( $type );
		$check( $object && $object->show_in_rest && false === $object->has_archive && $object->publicly_queryable && $type === $object->query_var, 'CPT exposure and archive: ' . $type );
		foreach ( array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ) as $support ) {
			$check( post_type_supports( $type, $support ), $type . ' supports ' . $support );
		}
	}
	$check( ! post_type_exists( 'rental' ), 'No Rental CPT' );
	$meta = get_registered_meta_keys( 'post', 'community' )['aloha_area_id'];
	$check( 'integer' === $meta['type'] && $meta['single'] && $meta['revisions_enabled'] && $meta['show_in_rest'], 'Typed, single, REST, revisioned relation' );
	$area_a = $post( 'area', 'publish' );
	$area_b = $post( 'area', 'publish' );
	$draft_area = $post( 'area' );
	$wrong_type = $post( 'post' );
	$draft = $post( 'community' );
	$check( 0 === (int) get_post_meta( $draft, 'aloha_area_id', true ), 'Draft without Area' );
	$result = wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ), true );
	$check( is_wp_error( $result ) && 'draft' === get_post_status( $draft ), 'Core API blocks publication without Area' );
	$result = wp_insert_post( array( 'post_type' => 'community', 'post_title' => 'Must not be inserted', 'post_status' => 'publish' ), true );
	$check( is_wp_error( $result ), 'Core API blocks first published insert' );
	foreach ( array( 999999999, $wrong_type, -$area_a, 'bad', 1.5, array( $area_a ) ) as $bad_id ) {
		$check( false === update_post_meta( $draft, 'aloha_area_id', $bad_id ), 'Invalid meta cannot be assigned' );
	}
	$check( update_post_meta( $draft, 'aloha_area_id', $draft_area ), 'Draft can select draft Area' );
	$result = wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ), true );
	$check( is_wp_error( $result ) && 'draft' === get_post_status( $draft ), 'Area must be published' );
	$check( delete_post_meta( $draft, 'aloha_area_id' ), 'Draft relationship can be removed' );
	wp_publish_post( $draft );
	$check( 'draft' === get_post_status( $draft ), 'Direct wp_publish_post/scheduler cannot leave orphan published' );
	wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish', 'meta_input' => array( 'aloha_area_id' => $area_a ) ) );
	$check( 'publish' === get_post_status( $draft ) && $area_a === (int) get_post_meta( $draft, 'aloha_area_id', true ), 'Atomic Core insert fields + meta' );
	$check( false === add_post_meta( $draft, 'aloha_area_id', $area_b ), 'No duplicate meta row' );
	$check( 1 === count( get_post_meta( $draft, 'aloha_area_id' ) ), 'Exactly one relationship' );
	$check( false === delete_post_meta( $draft, 'aloha_area_id' ), 'Published relationship cannot be deleted' );
	$check( false === update_post_meta( $draft, 'aloha_area_id', 0 ), 'Published relationship cannot be cleared' );
	$mid = get_metadata( 'post', $draft, 'aloha_area_id', false );
	global $wpdb;
	$mid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id=%d AND meta_key='aloha_area_id'", $draft ) );
	$check( false === update_metadata_by_mid( 'post', $mid, $wrong_type ), 'By-ID update validation' );
	$check( false === delete_metadata_by_mid( 'post', $mid ), 'By-ID delete validation' );
	$check( false === update_metadata_by_mid( 'post', $mid, $area_a, 'renamed_key' ), 'By-ID rename cannot remove published relation' );
	$check( false === delete_post_meta_by_key( 'aloha_area_id' ), 'Bulk meta deletion cannot orphan published Community' );
	$check( false === wp_trash_post( $area_a ) && false === wp_delete_post( $area_a, true ), 'Trash and force-delete of used Area blocked' );
	foreach ( array( 'draft', 'pending', 'private', 'future', 'trash' ) as $status ) {
		$result = wp_update_post( array( 'ID' => $area_a, 'post_status' => $status ), true );
		$check( is_wp_error( $result ) && 'publish' === get_post_status( $area_a ), 'Area cannot leave publish while referenced: ' . $status );
	}
	$result = wp_update_post( array( 'ID' => $area_a, 'post_type' => 'community', 'meta_input' => array( 'aloha_area_id' => $area_b ) ), true );
	$check( is_wp_error( $result ) && 'area' === get_post_type( $area_a ), 'Used Area cannot change type' );

	// Revisions include meta-only changes, and restore by validated update (not clear + add).
	wp_update_post( array( 'ID' => $draft, 'post_content' => '<!-- wp:paragraph --><p>Revision A</p><!-- /wp:paragraph -->', 'meta_input' => array( 'aloha_area_id' => $area_a ) ) );
	$revision_a = array_key_first( wp_get_post_revisions( $draft ) );
	$check( $area_a === (int) get_post_meta( $revision_a, 'aloha_area_id', true ), 'Revision snapshot A includes Area' );
	wp_update_post( array( 'ID' => $draft, 'meta_input' => array( 'aloha_area_id' => $area_b ) ) );
	$revision_b = array_key_first( wp_get_post_revisions( $draft ) );
	$check( $revision_b !== $revision_a && $area_b === (int) get_post_meta( $revision_b, 'aloha_area_id', true ), 'Meta-only change creates revision B' );
	wp_restore_post_revision( $revision_a );
	$check( $area_a === (int) get_post_meta( $draft, 'aloha_area_id', true ) && 'publish' === get_post_status( $draft ), 'Restore published Community relation A' );
	$check( 1 === count( get_post_meta( $draft, 'aloha_area_id' ) ), 'Restore does not create duplicate meta' );
	wp_restore_post_revision( $revision_b );
	wp_update_post( array( 'ID' => $area_a, 'post_status' => 'draft' ) );
	wp_restore_post_revision( $revision_a );
	$check( 'draft' === get_post_status( $draft ) && $area_a === (int) get_post_meta( $draft, 'aloha_area_id', true ), 'Unpublished historical Area restores as draft' );
	update_post_meta( $draft, 'aloha_area_id', $area_b );
	wp_delete_post( $area_a, true );
	wp_restore_post_revision( $revision_a );
	$check( 'draft' === get_post_status( $draft ) && 0 === (int) get_post_meta( $draft, 'aloha_area_id', true ), 'Deleted historical Area restores content without invalid relation' );
	wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish', 'meta_input' => array( 'aloha_area_id' => $area_b ) ) );
	update_metadata( 'post', $revision_a, 'aloha_area_id', (string) $area_b . '.5' );
	wp_restore_post_revision( $revision_a );
	$check( 'draft' === get_post_status( $draft ) && 0 === (int) get_post_meta( $draft, 'aloha_area_id', true ), 'Malformed historical relation is not truncated to a valid Area ID' );
	$check( wp_delete_post( $draft_area, true ) instanceof WP_Post && ! get_post( $draft_area ), 'Unused Area is deletable' );
	$draft_only_area = $post( 'area', 'publish' );
	$draft_only_community = $post( 'community', 'draft', array( 'aloha_area_id' => $draft_only_area ) );
	$check( false === wp_trash_post( $draft_only_area ) && false === wp_delete_post( $draft_only_area, true ), 'An exclusively draft reference also protects Area' );
	wp_trash_post( $draft_only_community );
	$check( false === wp_delete_post( $draft_only_area, true ), 'An exclusively trashed reference also protects Area' );
	update_post_meta( $draft_only_community, 'aloha_area_id', 0 );
	$check( wp_delete_post( $draft_only_area, true ) instanceof WP_Post, 'Clearing last trash reference releases Area' );

	// REST, including first publish with meta and denied requests with no partial mutation.
	$response = $rest( 'POST', 'community', array( 'title' => 'REST invalid publish', 'status' => 'publish' ) );
	$check( 400 === $response->get_status() && 'aloha_invalid_area' === $response->get_data()['code'], 'REST friendly missing-Area error' );
	$response = $rest( 'POST', 'community', array( 'title' => 'REST Community', 'status' => 'publish', 'meta' => array( 'aloha_area_id' => $area_b ) ) );
	$check( 201 === $response->get_status(), 'REST first publish with Area' );
	$rest_id = $response->get_data()['id']; $ids[] = $rest_id;
	$check( $area_b === $response->get_data()['meta']['aloha_area_id'], 'REST returns integer relation' );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'title' => 'Should not be saved', 'meta' => array( 'aloha_area_id' => 999999999 ) ) );
	$check( 400 === $response->get_status() && 'REST Community' === get_post_field( 'post_title', $rest_id ), 'Invalid REST relation rejected before title/status write' );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'meta' => array( 'aloha_area_id' => null ) ) );
	$check( 400 === $response->get_status() && $area_b === (int) get_post_meta( $rest_id, 'aloha_area_id', true ), 'REST cannot delete published relationship' );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'status' => 'draft', 'meta' => array( 'aloha_area_id' => 0 ) ) );
	$check( 200 === $response->get_status() && 'draft' === get_post_status( $rest_id ) && 0 === (int) get_post_meta( $rest_id, 'aloha_area_id', true ), 'REST can unpublish and clear together' );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'meta' => array( 'aloha_area_id' => $wrong_type ) ) );
	$check( 400 === $response->get_status(), 'REST rejects wrong post type' );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'meta' => array( 'aloha_area_id' => -1 ) ) );
	$check( 400 === $response->get_status(), 'REST negative ID schema validation' );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'status' => 'publish', 'meta' => array( 'aloha_area_id' => $area_b ) ) );
	$check( 200 === $response->get_status(), 'REST republish' );
	foreach ( array( false, true ) as $force ) {
		$response = $rest( 'DELETE', 'area/' . $area_b, array( 'force' => $force ) );
		$check( 409 === $response->get_status() && 'aloha_area_in_use' === $response->get_data()['code'], 'REST used-Area delete protection' );
	}
	$response = $rest( 'POST', 'area/' . $area_b, array( 'status' => 'draft' ) );
	$check( 409 === $response->get_status() && 'publish' === get_post_status( $area_b ), 'REST cannot unpublish used Area' );
	wp_set_current_user( 0 );
	$response = $rest( 'POST', 'community/' . $rest_id, array( 'meta' => array( 'aloha_area_id' => 0 ) ) );
	$check( $response->get_status() >= 400 && $area_b === (int) get_post_meta( $rest_id, 'aloha_area_id', true ), 'Anonymous REST denied' );
	$check( ! current_user_can( 'edit_post_meta', $rest_id, 'aloha_area_id' ), 'Meta capability callback denies anonymous user' );
	wp_set_current_user( $admin->ID );
	$check( current_user_can( 'edit_post_meta', $rest_id, 'aloha_area_id' ), 'Meta capability callback permits editor of post' );
	$scheduled = $post( 'community', 'future', array( 'aloha_area_id' => $area_b ) );
	$check( 'future' === get_post_status( $scheduled ), 'Scheduled fixture has a future date' );
	$check( is_wp_error( wp_update_post( array( 'ID' => $area_b, 'post_status' => 'draft' ), true ) ), 'Scheduled Community keeps Area published' );
	$trashed = $post( 'community', 'draft', array( 'aloha_area_id' => $area_b ) );
	wp_trash_post( $trashed );
	$check( false === wp_delete_post( $area_b, true ), 'References in trash also protect Area' );

	// Real plugin/theme lifecycle: all content remains in Core tables.
	$check( $rules === get_option( 'rewrite_rules' ), 'Plugin does not change rewrite rules' );
	$state = array( get_post( $rest_id )->post_content, get_post_meta( $rest_id, 'aloha_area_id', true ) );
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( 'aloha-content/aloha-content.php' );
	$check( $state === array( get_post( $rest_id )->post_content, get_post_meta( $rest_id, 'aloha_area_id', true ) ), 'Deactivation retains data' );
	$check( ! is_wp_error( activate_plugin( 'aloha-content/aloha-content.php' ) ), 'Reactivation succeeds' );
	switch_theme( 'twentytwentyfive' );
	$check( post_type_exists( 'area' ) && post_type_exists( 'community' ) && $state === array( get_post( $rest_id )->post_content, get_post_meta( $rest_id, 'aloha_area_id', true ) ), 'Theme switch retains model and data' );
	switch_theme( 'aloha-hello' );
	$check( $baseline === $fingerprint(), 'Pages, navigation and permalink structure unchanged' );
	WP_CLI::success( $checks . ' Aloha Content integration checks passed.' );
} finally {
	wp_set_current_user( $admin->ID );
	// Communities first, including trash, then Areas; revision fixtures are Core children.
	foreach ( array( 'community', 'area', 'post' ) as $type ) {
		foreach ( array_reverse( $ids ) as $id ) {
			if ( $type === get_post_type( $id ) ) {
				wp_delete_post( $id, true );
			}
		}
	}
}
