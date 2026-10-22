<?php
/** B2 server checks/fixtures; only the disposable integration site may run this. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$option = 'aloha_content_community_test_fixtures';
$state = get_option( $option, array() );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_option( 'permalink_structure' ), get_option( 'aloha_shell_menus' ), get_posts( array( 'post_type' => array( 'page', 'post', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) ) ) );
$phase = $args[0] ?? 'setup';
if ( 'cleanup' === $phase ) {
	foreach ( array_reverse( $state['ids'] ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) {
			WP_CLI::error( 'Cannot remove Community routing fixture ' . $id );
		}
	}
	if ( $state['baseline'] !== $fingerprint() ) {
		WP_CLI::error( 'Community tests changed existing Pages/posts/Navigation/permalink settings.' );
	}
	delete_option( $option );
	WP_CLI::success( 'Community routing fixtures removed; existing content/navigation/settings preserved.' );
	return;
}
$checks = 0;
$check = function ( $ok, $message ) use ( &$checks ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
if ( in_array( $phase, array( 'move', 'restore' ), true ) ) {
	$manifest = $state['manifest'];
	$slug = 'move' === $phase ? 'waikoloa-beach-resort' : 'mauna-lani';
	$rules = get_option( 'rewrite_rules' );
	$signature = get_option( 'aloha_content_area_routes' );
	$generations = 0;
	add_action( 'generate_rewrite_rules', function () use ( &$generations ) { ++$generations; } );
	$check( update_post_meta( $manifest['published'], 'aloha_area_id', $manifest['areas'][ $slug ] ), 'Move relationship through native meta API' );
	$check( home_url( '/' . \Aloha\Content\area_base() . '/' . $slug . '/ka-milo/' ) === get_permalink( $manifest['published'] ), 'Permalink follows current relationship' );
	$check( $rules === get_option( 'rewrite_rules' ) && $signature === get_option( 'aloha_content_area_routes' ) && 0 === $generations, 'Moving Community never flushes or changes rules/signature' );
	WP_CLI::success( $checks . ' Community move/restore checks (' . $phase . ').' );
	return;
}
if ( $state ) {
	WP_CLI::error( 'Community fixtures already exist.' );
}
$state = array( 'baseline' => $fingerprint(), 'ids' => array() );
update_option( $option, $state, false );
$create = function ( $type, $slug, $status = 'publish', $area_id = 0, $parent = 0 ) use ( &$state, $option ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_name' => $slug, 'post_title' => 'Isolated B2 fixture: ' . $slug, 'post_status' => $status, 'post_parent' => $parent, 'post_content' => '<!-- wp:paragraph --><p>Isolated Community routing content.</p><!-- /wp:paragraph -->', 'meta_input' => 'community' === $type ? array( 'aloha_area_id' => $area_id ) : array() ), true );
	if ( ! $id || is_wp_error( $id ) ) {
		throw new RuntimeException( 'Cannot create B2 fixture ' . $slug );
	}
	$state['ids'][] = $id;
	update_option( $option, $state, false );
	return $id;
};
$manifest = array( 'base' => home_url( '/' ), 'areas' => array() );
foreach ( array( 'mauna-lani', 'waikoloa-beach-resort' ) as $slug ) {
	$manifest['areas'][ $slug ] = get_page_by_path( $slug, OBJECT, array( 'area' ) )->ID;
}
$area_id = $manifest['areas']['mauna-lani'];
$manifest['published'] = $create( 'community', 'ka-milo', 'publish', $area_id );
add_post_meta( $manifest['published'], '_wp_old_slug', 'fixture-old-community' );
$manifest['draft'] = $create( 'community', 'fixture-draft-community', 'draft', $area_id );
$manifest['private'] = $create( 'community', 'fixture-private-community', 'private', $area_id );
$manifest['unassigned'] = $create( 'community', 'fixture-unassigned-community', 'draft' );
$check( home_url( '/big-island/mauna-lani/ka-milo/' ) === get_permalink( $manifest['published'] ), 'Native nested Community permalink' );
$check( home_url( '/big-island/mauna-lani/%community%/' ) === get_post_permalink( $manifest['published'], true ), 'Native Gutenberg permalink template' );
$check( str_contains( get_preview_post_link( $manifest['draft'] ), 'preview=true' ) && ! str_contains( get_preview_post_link( $manifest['draft'] ), '%aloha_area_route%' ), 'Native assigned draft preview' );
$check( str_contains( get_preview_post_link( $manifest['unassigned'] ), 'post_type=community' ) && str_contains( get_preview_post_link( $manifest['unassigned'] ), 'preview=true' ), 'Unassigned draft has a native ID preview' );
$original_post = $GLOBALS['post'] ?? null;
$GLOBALS['post'] = get_post( $area_id );
$check( str_contains( get_post_permalink( $manifest['unassigned'], true, true ), 'post_type=community' ), 'Unassigned sample permalink never borrows the global Area post' );
$GLOBALS['post'] = $original_post;

// Intentionally damaged legacy records bypass the API ONLY in this isolated
// fixture. Confirm that URL validation still fails closed; never alter guards.
global $wpdb;
$manifest['invalid'] = array();
foreach ( array( 'missing', 'draft', 'private', 'wrong-type' ) as $kind ) {
	if ( in_array( $kind, array( 'draft', 'private' ), true ) ) {
		$bad_area = $create( 'area', 'fixture-' . $kind . '-parent', $kind );
	} elseif ( 'wrong-type' === $kind ) {
		$bad_area = get_page_by_path( 'big-island/guide', OBJECT, array( 'page' ) )->ID;
	} else {
		$bad_area = 999999999;
	}
	$id = $create( 'community', 'fixture-invalid-' . $kind, 'publish', $area_id );
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $bad_area ), array( 'post_id' => $id, 'meta_key' => 'aloha_area_id' ) );
	clean_post_cache( $id );
	$manifest['invalid'][ $kind ] = $id;
}
$reserved_area = $create( 'area', 'fixture-reserved-area' );
$reserved_community = $create( 'community', 'fixture-child', 'publish', $reserved_area );
$wpdb->update( $wpdb->posts, array( 'post_name' => 'guide' ), array( 'ID' => $reserved_area ) );
clean_post_cache( $reserved_area );
$manifest['guide_rubric'] = get_page_by_path( 'big-island/guide/fixture-child', OBJECT, array( 'page' ) )->ID;
$manifest['guide_article'] = $create( 'page', 'fixture-article', 'publish', 0, $manifest['guide_rubric'] );

$rules = get_option( 'rewrite_rules' );
$community_rules = array_filter( $rules, fn( $query ) => str_starts_with( $query, 'index.php?community=' ) );
$check( 1 === count( $community_rules ), 'Exactly one bounded Community rule' );
$first_rule = function ( $path ) use ( $rules ) {
	foreach ( $rules as $regex => $query ) {
		if ( preg_match( '#^' . $regex . '#', $path ) ) {
			return $query;
		}
	}
	return '';
};
$check( str_starts_with( $first_rule( 'big-island/mauna-lani/ka-milo/' ), 'index.php?community=' ), 'Community wins only its two-segment URL' );
foreach ( array( 'guide', 'communities', 'guide/fixture-child', 'guide/fixture-child/fixture-article', 'communities/fixture-child', 'mauna-lani', 'mauna-lani/ka-milo/extra', 'feed/example', 'page/example', '123/example', 'embed/example' ) as $path ) {
	$check( ! str_starts_with( $first_rule( 'big-island/' . $path . '/' ), 'index.php?community=' ), 'Community does not capture ' . $path );
}
$check( ! get_post_type_object( 'community' )->has_archive, 'Community has no archive' );
$check( ! array_filter( array_keys( get_post_meta( $manifest['published'] ) ), fn( $key ) => preg_match( '/area.*(?:slug|url|name)/', $key ) ), 'No duplicated Area slug/name/URL meta' );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'aloha-content/aloha-content.php' );
$check( ! array_filter( get_option( 'rewrite_rules' ), fn( $query ) => str_starts_with( $query, 'index.php?community=' ) ), 'Deactivation removes Community rules, including runtime cache' );
activate_plugin( 'aloha-content/aloha-content.php' );
$check( 1 === count( array_filter( get_option( 'rewrite_rules' ), fn( $query ) => str_starts_with( $query, 'index.php?community=' ) ) ), 'Reactivation restores one Community rule' );
$manifest['checks'] = $checks;
$state['manifest'] = $manifest;
update_option( $option, $state, false );
echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
