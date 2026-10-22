<?php
/** Isolated fixtures + Core rewrite/slug checks. Invoked by test-aloha-content.sh. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$fixture_option = 'aloha_content_area_test_fixtures';
$state = get_option( $fixture_option, array() );
$fingerprint = function () {
	return hash( 'sha256', wp_json_encode( array( get_option( 'permalink_structure' ), get_option( 'aloha_shell_menus' ), get_posts( array( 'post_type' => array( 'page', 'post', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) ) ) );
};
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
	foreach ( array_reverse( $state['ids'] ?? array() ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) {
			WP_CLI::error( 'Could not remove routing fixture ' . $id );
		}
	}
	if ( $state['baseline'] !== $fingerprint() ) {
		WP_CLI::error( 'Routing tests changed existing Pages, posts, Navigation or permalink settings.' );
	}
	delete_option( $fixture_option );
	WP_CLI::success( 'Routing fixtures removed; existing content/navigation/permalink settings preserved.' );
	return;
}
if ( $state ) {
	WP_CLI::error( 'Routing fixtures already exist.' );
}
$state = array( 'baseline' => $fingerprint(), 'ids' => array() );
update_option( $fixture_option, $state, false );
$count = 0;
$check = function ( $value, $message ) use ( &$count ) {
	if ( ! $value ) {
		throw new RuntimeException( $message );
	}
	++$count;
};
$create = function ( $type, $slug, $status = 'publish', $parent = 0, $meta = array() ) use ( &$state, $fixture_option ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => 'Isolated route fixture: ' . $slug, 'post_name' => $slug, 'post_status' => $status, 'post_parent' => $parent, 'post_content' => '<!-- wp:paragraph --><p>Isolated routing content.</p><!-- /wp:paragraph -->', 'meta_input' => $meta ), true );
	if ( is_wp_error( $id ) || ! $id ) {
		throw new RuntimeException( 'Could not create routing fixture ' . $slug );
	}
	$state['ids'][] = $id;
	update_option( $fixture_option, $state, false );
	return $id;
};
$manifest = array( 'base' => home_url( '/' ), 'areas' => array(), 'pages' => array(), 'checks' => 0 );
foreach ( array( 'mauna-lani', 'waikoloa-beach-resort', 'waikoloa-village' ) as $slug ) {
	$id = $create( 'area', $slug );
	$check( home_url( '/big-island/' . $slug . '/' ) === get_permalink( $id ), 'Native Area permalink' );
	$check( home_url( '/big-island/%area%/' ) === get_post_permalink( $id, true ), 'Native editor permalink template' );
	$manifest['areas'][ $slug ] = $id;
}
$parent = $create( 'page', 'big-island' );
foreach ( array( 'guide', 'communities' ) as $slug ) {
	$id = $create( 'page', $slug, 'publish', $parent );
	$manifest['pages'][ 'big-island/' . $slug ] = $id;
	if ( 'guide' === $slug ) {
		$manifest['pages']['big-island/guide/fixture-child'] = $create( 'page', 'fixture-child', 'publish', $id );
	}
}
$manifest['pages']['big-island'] = $parent;
$manifest['pages']['fixture-ordinary-page'] = $create( 'page', 'fixture-ordinary-page' );
$manifest['draft'] = $create( 'area', 'fixture-draft-area', 'draft' );
$manifest['private'] = $create( 'area', 'fixture-private-area', 'private' );
$manifest['preview_url'] = get_preview_post_link( $manifest['draft'] );
$manifest['community'] = $create( 'community', 'fixture-community', 'publish', 0, array( 'aloha_area_id' => $manifest['areas']['mauna-lani'] ) );

foreach ( array_merge( \Aloha\Content\area_reserved_slugs(), array( '123', 'comment-page-2' ) ) as $slug ) {
	foreach ( array( 'draft', 'publish' ) as $status ) {
		$result = wp_insert_post( array( 'post_type' => 'area', 'post_title' => 'Invalid Area', 'post_name' => $slug, 'post_status' => $status ), true );
		$check( is_wp_error( $result ), 'Reserved slug blocked through Core: ' . $slug );
	}
	$request = new WP_REST_Request( 'POST', '/wp/v2/area' );
	$request->set_body_params( array( 'title' => 'Invalid Area', 'slug' => $slug, 'status' => 'publish' ) );
	$response = rest_do_request( $request );
	$check( 400 === $response->get_status() && 'aloha_reserved_area_slug' === $response->get_data()['code'], 'REST reserved-slug explanation' );
}
$reserved_title = wp_insert_post( array( 'post_type' => 'area', 'post_title' => 'Guide', 'post_status' => 'publish' ), true );
$check( is_wp_error( $reserved_title ), 'Auto-generated reserved slug blocked' );

$rules = get_option( 'rewrite_rules' );
$area_rules = array_filter( $rules, fn( $query ) => str_contains( $query, '?area=' ) );
$check( 1 === count( $area_rules ), 'Only one single-level Area rule' );
$first_rule = function ( $path ) use ( $rules ) {
	foreach ( $rules as $regex => $query ) {
		if ( preg_match( '#^' . $regex . '#', $path ) ) {
			return $query;
		}
	}
	return '';
};
foreach ( array( 'guide', 'communities', 'feed', 'page', 'embed', 'rss2', 'atom', 'attachment', 'trackback', '123', 'comment-page-2' ) as $slug ) {
	$check( ! str_contains( $first_rule( 'big-island/' . $slug . '/' ), '?area=' ), 'Reserved paths never select Area query: ' . $slug );
}
foreach ( array_keys( $manifest['areas'] ) as $slug ) {
	$check( str_contains( $first_rule( 'big-island/' . $slug . '/' ), '?area=' ), 'Published Area matcher' );
}
foreach ( array( 'mauna-lani/fixture-community', 'mauna-lani/123', 'mauna-lani/embed', 'guide/fixture-rubric/article', 'mauna-lani/attachment/photo' ) as $suffix ) {
	foreach ( $area_rules as $regex => $query ) {
		$check( ! preg_match( '#^' . $regex . '#', 'big-island/' . $suffix . '/' ), 'Area does not consume descendant paths: ' . $suffix );
	}
}
$generations = 0;
add_action( 'generate_rewrite_rules', function () use ( &$generations ) { ++$generations; } );
\Aloha\Content\sync_area_routes();
\Aloha\Content\sync_area_routes();
$check( 0 === $generations, 'Stable requests do not regenerate rules' );
delete_option( 'aloha_content_area_routes' );
\Aloha\Content\sync_area_routes();
\Aloha\Content\sync_area_routes();
$check( 1 === $generations, 'A configuration/version upgrade generates rules once' );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'aloha-content/aloha-content.php' );
$check( ! array_filter( get_option( 'rewrite_rules' ), fn( $query ) => str_contains( $query, '?area=' ) ), 'Deactivation removes cached Area rules even after an earlier flush in this request' );
activate_plugin( 'aloha-content/aloha-content.php' );
$check( 1 === count( array_filter( get_option( 'rewrite_rules' ), fn( $query ) => str_contains( $query, '?area=' ) ) ), 'Reactivation restores only the canonical Area rule' );
$check( false === get_post_type_object( 'area' )->has_archive, 'No Area archive' );
$check( false === get_post_type_object( 'community' )->has_archive && get_post_type_object( 'community' )->publicly_queryable, 'Community is public without an archive (B2)' );
$manifest['checks'] = $count;
echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
