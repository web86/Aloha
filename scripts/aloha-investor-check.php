<?php
/** Investor acceptance fixtures, exclusively in disposable WordPress. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_content_investor_test_fixtures';
$state = get_option( $key, array() );
$snapshot = fn() => hash( 'sha256', wp_json_encode( array( get_option( 'permalink_structure' ), get_option( 'aloha_shell_menus' ), get_option( 'aloha_content_guide_categories' ), get_posts( array( 'post_type' => array( 'post', 'page', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false ) ) ) ) );
$phase = $args[0] ?? 'setup';
if ( 'cleanup' === $phase ) {
	foreach ( array_reverse( $state['posts'] ) as $id ) { if ( get_post( $id ) && ! wp_delete_post( $id, true ) ) { WP_CLI::error( 'Investor fixture cleanup failed.' ); } }
	foreach ( array_reverse( $state['terms'] ) as $id ) { if ( term_exists( $id, 'category' ) && ! wp_delete_term( $id, 'category' ) ) { WP_CLI::error( 'Investor term cleanup failed.' ); } }
	delete_option( 'aloha_content_investor_category' );
	if ( $snapshot() !== $state['baseline'] ) { WP_CLI::error( 'Investor tests changed existing content/terms/Guide/Navigation/settings.' ); }
	delete_option( $key );
	WP_CLI::success( 'Investor fixtures removed; all original content/terms/settings preserved.' ); return;
}
$count = 0;
$check = function ( $ok, $message ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$count; };
if ( in_array( $phase, array( 'remove', 'restore' ), true ) ) {
	$before = get_option( 'rewrite_rules' ); $signature = get_option( 'aloha_content_area_routes' ); $n = 0;
	add_action( 'generate_rewrite_rules', function () use ( &$n ) { ++$n; } );
	wp_set_post_categories( $state['manifest']['published'], 'remove' === $phase ? array( 1 ) : array( $state['manifest']['category'] ) );
	$expected = 'remove' === $phase ? home_url( '/choosing-a-vacation-rental/' ) : home_url( '/' . \Aloha\Content\investor_base() . '/choosing-a-vacation-rental/' );
	$check( $expected === get_permalink( $state['manifest']['published'] ), 'Membership immediately determines permalink' );
	$check( 0 === $n && $before === get_option( 'rewrite_rules' ) && $signature === get_option( 'aloha_content_area_routes' ), 'Changing family never regenerates rules' );
	WP_CLI::success( $count . ' Investor membership checks (' . $phase . ').' ); return;
}
if ( $state || get_option( 'aloha_content_investor_category' ) ) { WP_CLI::error( 'Investor fixtures require a clean disposable family.' ); }
$state = array( 'baseline' => $snapshot(), 'posts' => array(), 'terms' => array() );
$save = function () use ( &$state, $key ) { update_option( $key, $state, false ); }; $save();
$category = function ( $name, $args = array() ) use ( &$state, $save ) {
	$r = wp_insert_term( $name, 'category', $args ); if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
	$id = (int) $r['term_id']; $state['terms'][] = $id; $save(); return $id;
};
$create = function ( $type, $slug, $status = 'publish', $cats = array(), $parent = 0 ) use ( &$state, $save ) {
	$d = array( 'post_type' => $type, 'post_title' => 'Isolated Investor ' . $slug, 'post_name' => $slug, 'post_status' => $status, 'post_parent' => $parent, 'post_category' => $cats, 'post_content' => '<!-- wp:paragraph --><p>Isolated Investor content.</p><!-- /wp:paragraph -->' );
	if ( 'future' === $status ) { $d['post_date'] = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ); $d['post_date_gmt'] = $d['post_date']; }
	$id = wp_insert_post( $d, true ); if ( ! $id || is_wp_error( $id ) ) { throw new RuntimeException( 'Investor fixture insert failed: ' . $slug ); }
	$state['posts'][] = $id; $save(); return $id;
};
$rest = function ( $method, $path, $body ) { $r = new WP_REST_Request( $method, '/wp/v2/' . $path ); $r->set_body_params( $body ); return rest_do_request( $r ); };
$conflict = $category( 'Unrelated Research', array( 'slug' => 'investor-guides' ) ); $before = $snapshot();
$check( is_wp_error( \Aloha\Content\investor_category( true ) ) && $before === $snapshot() && ! get_option( 'aloha_content_investor_category' ), 'Unmanaged category is not adopted or changed' );
wp_delete_term( $conflict, 'category' );
$id = \Aloha\Content\investor_category( true ); $state['terms'][] = $id; $save();
$check( is_int( $id ) && $id > 0 && 0 === get_term( $id )->parent, 'Investor category is a separate native root category' );
$before = $snapshot();
$check( $id === \Aloha\Content\investor_category( true ) && $before === $snapshot(), 'Repeated installation is idempotent' );
wp_update_term( $id, 'category', array( 'name' => 'Manager Research', 'description' => 'Manager description' ) ); \Aloha\Content\investor_category( true );
$check( 'Manager Research' === get_term( $id )->name && 'Manager description' === get_term( $id )->description, 'User category labels/descriptions preserved' );
wp_update_term( $id, 'category', array( 'name' => 'Investor Guides' ) );
$guide = \Aloha\Content\guide_terms(); $things = $guide['rubrics']['things-to-do'];
wp_update_term( $id, 'category', array( 'parent' => $guide['parent'] ) );
$check( is_wp_error( \Aloha\Content\investor_category( true ) ) && $guide['parent'] === get_term( $id )->parent, 'Conflicting parent binding is not overwritten' );
wp_update_term( $id, 'category', array( 'parent' => 0 ) );
$manifest = array( 'base' => home_url( '/' ), 'category' => $id, 'pages' => array() );
$manifest['published'] = $create( 'post', 'choosing-a-vacation-rental', 'publish', array( $id ) );
add_post_meta( $manifest['published'], '_wp_old_slug', 'fixture-old-investor' );
foreach ( array( 'draft', 'private', 'future', 'pending' ) as $status ) { $manifest[ $status ] = $create( 'post', 'fixture-investor-' . $status, $status, array( $id ) ); }
$manifest['ordinary'] = $create( 'post', 'fixture-investor-ordinary', 'publish', array( 1 ) );
$manifest['guide'] = $create( 'post', 'fixture-investor-guide-control', 'publish', array( $things ) );
$check( home_url( '/homeowners/invest/guides/choosing-a-vacation-rental/' ) === get_permalink( $manifest['published'] ), 'Investor get_permalink uses required structure' );
$check( home_url( '/fixture-investor-ordinary/' ) === get_permalink( $manifest['ordinary'] ), 'Ordinary Post URL preserved' );
$check( home_url( '/big-island/guide/things-to-do/fixture-investor-guide-control/' ) === get_permalink( $manifest['guide'] ), 'Guide Post URL preserved with Investor installed' );
require_once ABSPATH . 'wp-admin/includes/post.php';
$sample = get_sample_permalink( $manifest['draft'] );
$check( home_url( '/homeowners/invest/guides/%postname%/' ) === $sample[0], 'Native sample permalink for Gutenberg' );
$check( str_contains( get_preview_post_link( $manifest['draft'] ), '?p=' ) && str_contains( get_preview_post_link( $manifest['draft'] ), 'preview=true' ), 'Native draft ID preview preserved' );
foreach ( array( 'publish', 'future', 'private' ) as $status ) {
	$result = wp_insert_post( array( 'post_title' => 'Invalid mixed family', 'post_status' => $status, 'post_category' => array( $id, $things ) ), true );
	$check( is_wp_error( $result ), 'PHP blocks mixed family ' . $status );
	$response = $rest( 'POST', 'posts', array( 'title' => 'Invalid mixed REST', 'status' => $status, 'categories' => array( $id, $things ) ) );
	$check( 400 === $response->get_status() && 'aloha_article_family_conflict' === $response->get_data()['code'], 'REST blocks mixed family ' . $status );
}
$manifest['mixed'] = $create( 'post', 'fixture-investor-mixed', 'draft', array( $id, $things ) );
$check( 'draft' === get_post_status( $manifest['mixed'] ), 'Draft may keep unfinished classification' );
wp_publish_post( $manifest['mixed'] );
$check( 'draft' === get_post_status( $manifest['mixed'] ), 'Direct scheduler cannot publish mixed families' );
$old_title = get_the_title( $manifest['published'] );
$response = $rest( 'POST', 'posts/' . $manifest['published'], array( 'title' => 'Must not change', 'categories' => array( $id, $things ) ) );
$check( 400 === $response->get_status() && $old_title === get_the_title( $manifest['published'] ) && array( $id ) === wp_get_post_categories( $manifest['published'] ), 'REST conflict prevents partial title/taxonomy mutation' );
$response = $rest( 'POST', 'posts/' . $manifest['mixed'], array( 'status' => 'publish', 'categories' => array( $id ) ) );
$check( 200 === $response->get_status() && 'publish' === get_post_status( $manifest['mixed'] ), 'Atomic family repair and publication' );
wp_set_post_categories( $manifest['mixed'], array( $id, $things ) );
$check( 'draft' === get_post_status( $manifest['mixed'] ), 'Direct taxonomy mutation fails closed with draft status' );
// Malformed legacy data bypassing APIs exists only in this isolated fixture.
global $wpdb; $wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $manifest['mixed'] ) ); clean_post_cache( $manifest['mixed'] );
$home = $create( 'page', 'homeowners' ); $invest = $create( 'page', 'invest', 'publish', array(), $home );
$manifest['pages']['homeowners'] = $home; $manifest['pages']['homeowners/invest'] = $invest;
foreach ( array( 'calculator', 'fixture-other-page' ) as $slug ) { $manifest['pages'][ 'homeowners/invest/' . $slug ] = $create( 'page', $slug, 'publish', array(), $invest ); }
$args = apply_filters( 'wp_sitemaps_taxonomies_query_args', array(), 'category' );
$check( in_array( $id, $args['exclude'], true ) && ! in_array( $things, $args['exclude'], true ), 'Core sitemap excludes only Investor category archive' );
$rules = get_option( 'rewrite_rules' );
$check( 1 === count( array_filter( $rules, fn( $q ) => str_starts_with( $q, 'index.php?aloha_investor_article=' ) ) ), 'Exactly one Investor rule' );
$first = function ( $path ) use ( $rules ) { foreach ( $rules as $regex => $q ) { if ( preg_match( '#^' . $regex . '#', $path ) ) { return $q; } } return ''; };
foreach ( array( 'homeowners/', 'homeowners/invest/', 'homeowners/invest/calculator/', 'homeowners/invest/fixture-other-page/', 'homeowners/invest/guides/', 'homeowners/invest/guides/a/extra/', 'big-island/mauna-lani/', 'big-island/guide/events/' ) as $path ) {
	$check( ! str_starts_with( $first( $path ), 'index.php?aloha_investor_article=' ), 'Bounded Investor matcher leaves ' . $path );
}
$n = 0; add_action( 'generate_rewrite_rules', function () use ( &$n ) { ++$n; } );
\Aloha\Content\sync_area_routes(); \Aloha\Content\sync_area_routes();
$check( 0 === $n, 'Stable requests generate no rules' );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'aloha-content/aloha-content.php' );
$check( ! array_filter( get_option( 'rewrite_rules' ), fn( $q ) => str_starts_with( $q, 'index.php?aloha_investor_article=' ) ), 'Deactivation removes runtime Investor rules' );
activate_plugin( 'aloha-content/aloha-content.php' );
$check( 1 === count( array_filter( get_option( 'rewrite_rules' ), fn( $q ) => str_starts_with( $q, 'index.php?aloha_investor_article=' ) ) ) && $id === \Aloha\Content\investor_category(), 'Activation restores one rule and stable category ID' );
$manifest['checks'] = $count; $state['manifest'] = $manifest; $save();
echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
