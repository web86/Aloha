<?php
/** Guide acceptance against disposable WordPress only; no main-site fixtures. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_content_guide_test_fixtures';
$state = get_option( $key, array() );
$snapshot = fn() => hash( 'sha256', wp_json_encode( array( get_option( 'permalink_structure' ), get_option( 'aloha_shell_menus' ), get_posts( array( 'post_type' => array( 'page', 'post', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false ) ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
	foreach ( array_reverse( $state['posts'] ) as $id ) {
		if ( get_post( $id ) && ! wp_delete_post( $id, true ) ) {
			WP_CLI::error( 'Guide post fixture cleanup failed.' );
		}
	}
	foreach ( array_reverse( $state['terms'] ) as $fixture ) {
		if ( term_exists( $fixture[0], $fixture[1] ) && ! wp_delete_term( $fixture[0], $fixture[1] ) ) {
			WP_CLI::error( 'Guide category/tag fixture cleanup failed.' );
		}
	}
	delete_option( 'aloha_content_guide_categories' );
	update_option( 'posts_per_page', $state['posts_per_page'] );
	if ( $snapshot() !== $state['baseline'] ) {
		WP_CLI::error( 'Guide tests changed existing Posts/Pages/Navigation/categories/tags/permalinks.' );
	}
	delete_option( $key );
	WP_CLI::success( 'Guide fixtures removed; existing content, terms, Navigation and settings preserved.' );
	return;
}
if ( $state || get_option( 'aloha_content_guide_categories' ) ) {
	WP_CLI::error( 'Guide setup requires a clean isolated site.' );
}
$state = array( 'baseline' => $snapshot(), 'posts_per_page' => get_option( 'posts_per_page' ), 'posts' => array(), 'terms' => array() );
$save_state = function () use ( &$state, $key ) { update_option( $key, $state, false ); };
$save_state();
$count = 0;
$check = function ( $ok, $message ) use ( &$count ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$count;
};
$term = function ( $name, $taxonomy, $data = array() ) use ( &$state, $save_state ) {
	$result = wp_insert_term( $name, $taxonomy, $data );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
	$id = (int) $result['term_id']; $state['terms'][] = array( $id, $taxonomy ); $save_state();
	return $id;
};
$post = function ( $slug, $categories, $status = 'publish' ) use ( &$state, $save_state ) {
	$data = array( 'post_type' => 'post', 'post_title' => 'Isolated Guide fixture ' . $slug, 'post_name' => $slug, 'post_status' => $status, 'post_category' => $categories, 'post_content' => '<!-- wp:paragraph --><p>Isolated Guide content.</p><!-- /wp:paragraph -->' );
	if ( 'future' === $status ) { $data['post_date'] = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ); $data['post_date_gmt'] = $data['post_date']; }
	$id = wp_insert_post( $data, true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$state['posts'][] = $id; $save_state(); return $id;
};
$rest = function ( $method, $path, $body ) {
	$request = new WP_REST_Request( $method, '/wp/v2/' . $path ); $request->set_body_params( $body ); return rest_do_request( $request );
};
$conflict = $term( 'Unrelated Events', 'category', array( 'slug' => 'events' ) );
$before = $snapshot();
$check( is_wp_error( \Aloha\Content\install_guide_categories() ) && $before === $snapshot() && ! get_option( 'aloha_content_guide_categories' ), 'Unmanaged identifier conflict causes no category writes/adoption' );
wp_delete_term( $conflict, 'category' );
$map = \Aloha\Content\install_guide_categories();
$check( ! is_wp_error( $map ) && 4 === count( $map['rubrics'] ), 'Install native Guide category family' );
foreach ( array_merge( array( $map['parent'] ), array_values( $map['rubrics'] ) ) as $id ) { $state['terms'][] = array( $id, 'category' ); }
$save_state();
$before = $snapshot();
$check( $map === \Aloha\Content\install_guide_categories() && $before === $snapshot(), 'Repeated install preserves IDs and all term fields' );
wp_update_term( $map['rubrics']['food-and-drink'], 'category', array( 'description' => 'Manager text' ) );
\Aloha\Content\install_guide_categories();
$check( 'Manager text' === get_term( $map['rubrics']['food-and-drink'] )->description, 'Install preserves user category content' );
wp_update_term( $map['rubrics']['events'], 'category', array( 'parent' => 0 ) );
$check( is_wp_error( \Aloha\Content\install_guide_categories() ) && 0 === get_term( $map['rubrics']['events'] )->parent, 'Conflicting bound hierarchy is not overwritten' );
wp_update_term( $map['rubrics']['events'], 'category', array( 'parent' => $map['parent'] ) );
$check( $map === \Aloha\Content\guide_terms(), 'Restored native category hierarchy passes check' );
$rules = get_option( 'rewrite_rules' );
$manifest = array( 'base' => home_url( '/' ), 'map' => $map, 'articles' => array() );
$manifest['hub'] = get_page_by_path( 'big-island/guide', OBJECT, array( 'page' ) )->ID;
foreach ( $map['rubrics'] as $slug => $id ) {
	$article = $post( 'fixture-guide-' . $slug, array( $id ) );
	$manifest['articles'][ $slug ] = $article;
	$check( home_url( '/big-island/guide/' . $slug . '/fixture-guide-' . $slug . '/' ) === get_permalink( $article ), 'Guide post permalink ' . $slug );
	$check( home_url( '/big-island/guide/' . $slug . '/' ) === get_category_link( $id ) && get_category_link( $id ) === get_term_link( $id, 'category' ), 'Both native category link APIs use canonical URL' );
}
$check( home_url( '/big-island/guide/' ) === get_category_link( $map['parent'] ), 'Parent internal slug links to Page hub' );
$things = $map['rubrics']['things-to-do']; $events = $map['rubrics']['events'];
$tag = $term( 'Isolated Guide Tag', 'post_tag', array( 'slug' => 'fixture-guide-tag' ) );
$tagged = $manifest['articles']['things-to-do']; $link = get_permalink( $tagged );
wp_set_post_tags( $tagged, array( $tag ) );
$check( $link === get_permalink( $tagged ), 'Tags never change routing rubric/permalink' );
$ordinary_category = $term( 'Isolated General', 'category', array( 'slug' => 'fixture-general' ) );
$manifest['ordinary_category'] = $ordinary_category;
$manifest['ordinary'] = $post( 'fixture-ordinary-post', array( $ordinary_category ) );
$check( home_url( '/fixture-ordinary-post/' ) === get_permalink( $manifest['ordinary'] ) && str_contains( get_category_link( $ordinary_category ), '/category/fixture-general/' ), 'Ordinary Post/category URL unchanged' );
$manifest['draft'] = $post( 'fixture-guide-draft', array( $things ), 'draft' );
$manifest['private'] = $post( 'fixture-guide-private', array( $things ), 'private' );
$manifest['future'] = $post( 'fixture-guide-future', array( $things ), 'future' );
require_once ABSPATH . 'wp-admin/includes/post.php';
$sample = get_sample_permalink( $manifest['draft'] );
$check( home_url( '/big-island/guide/things-to-do/%postname%/' ) === $sample[0], 'Gutenberg sample permalink retains native token' );
$check( str_contains( get_preview_post_link( $manifest['draft'] ), 'preview=true' ) && str_contains( get_preview_post_link( $manifest['draft'] ), '?p=' ), 'Draft keeps native plain ID preview' );
$ambiguous = $post( 'fixture-guide-ambiguous', array( $things, $events ), 'draft' );
$manifest['ambiguous'] = $ambiguous;
$check( is_wp_error( wp_update_post( array( 'ID' => $ambiguous, 'post_status' => 'publish' ), true ) ) && 'draft' === get_post_status( $ambiguous ), 'PHP publication with two rubrics blocked' );
$check( is_wp_error( wp_insert_post( array( 'post_title' => 'Root-only invalid', 'post_status' => 'publish', 'post_category' => array( $map['parent'] ) ), true ) ), 'Guide root-only publication blocked' );
$response = $rest( 'POST', 'posts', array( 'title' => 'Invalid REST first publish', 'status' => 'publish', 'categories' => array( $things, $events ) ) );
$check( 400 === $response->get_status() && 'aloha_guide_rubric_required' === $response->get_data()['code'], 'REST publication returns understandable category error' );
$old_title = get_the_title( $tagged );
$response = $rest( 'POST', 'posts/' . $tagged, array( 'title' => 'Must not change', 'categories' => array( $things, $events ) ) );
$check( 400 === $response->get_status() && $old_title === get_the_title( $tagged ) && array( $things ) === wp_get_post_categories( $tagged ), 'Invalid REST update changes neither title nor taxonomy' );
$response = $rest( 'POST', 'posts/' . $ambiguous, array( 'status' => 'publish', 'categories' => array( $things ) ) );
$check( 200 === $response->get_status() && 'publish' === get_post_status( $ambiguous ), 'REST atomically repairs categories and publishes' );
wp_set_post_categories( $ambiguous, array( $things, $events ) );
$check( 'draft' === get_post_status( $ambiguous ), 'Direct taxonomy API ambiguity explicitly demotes to draft' );
wp_publish_post( $ambiguous );
$check( 'draft' === get_post_status( $ambiguous ), 'Direct publisher/scheduler cannot leave ambiguous post public' );
$descendant = $term( 'Isolated Guide Detail', 'category', array( 'slug' => 'fixture-guide-detail', 'parent' => $things ) );
$manifest['descendant'] = $post( 'fixture-guide-descendant', array( $descendant ) );
$check( 'things-to-do' === \Aloha\Content\guide_post_rubric( $manifest['descendant'] )['slug'], 'Single approved ancestor is an unambiguous route' );
$check( get_term_link( $descendant, 'category' ) === get_category_link( $things ), 'Descendant category links cannot expose the internal parent slug' );
$manifest['extra'] = $post( 'fixture-guide-extra', array( $things ) );
wp_set_current_user( 0 );
$response = $rest( 'POST', 'posts/' . $tagged, array( 'categories' => array( $events ) ) );
$check( $response->get_status() >= 400 && array( $things ) === wp_get_post_categories( $tagged ), 'Core term assignment permissions remain enforced' );
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
update_option( 'posts_per_page', 2 );
$check( $rules === get_option( 'rewrite_rules' ), 'Category installation/editing/assignment never flushes rewrite rules' );
$check( 3 === count( array_filter( $rules, fn( $q ) => str_starts_with( $q, 'index.php?aloha_guide_rubric=' ) ) ), 'Exactly three bounded Guide rules: archive/article/pagination' );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'aloha-content/aloha-content.php' );
$check( ! array_filter( get_option( 'rewrite_rules' ), fn( $q ) => str_starts_with( $q, 'index.php?aloha_guide_rubric=' ) ), 'Deactivation clears Guide rules and runtime cache' );
activate_plugin( 'aloha-content/aloha-content.php' );
$check( 3 === count( array_filter( get_option( 'rewrite_rules' ), fn( $q ) => str_starts_with( $q, 'index.php?aloha_guide_rubric=' ) ) ) && $map === \Aloha\Content\guide_terms(), 'Activation restores rules and preserves categories' );
$manifest['checks'] = $count;
echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
