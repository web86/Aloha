<?php
/** One Investor article route; shared Post filters/guards live in guide-routing. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function investor_base() {
	$base = trim( (string) apply_filters( 'aloha_content_investor_base', 'homeowners/invest/guides' ), '/' );
	$parts = array_map( 'sanitize_title', explode( '/', $base ) );
	return $base && ! in_array( '', $parts, true ) ? implode( '/', $parts ) : 'homeowners/invest/guides';
}

function register_investor_routes() {
	add_rewrite_tag( '%aloha_investor_article%', '([^/]+)' );
	add_permastruct( 'aloha_investor', investor_base() . '/%aloha_investor_article%', array( 'with_front' => false, 'ep_mask' => EP_NONE, 'walk_dirs' => false, 'feeds' => false, 'pages' => false ) );
}

add_filter( 'aloha_investor_rewrite_rules', function () {
	global $wp_rewrite;
	return array( preg_quote( $wp_rewrite->root . investor_base(), '#' ) . '/([^/]+)/?$' => $wp_rewrite->index . '?aloha_investor_article=$matches[1]' );
} );

function investor_route_vars() {
	global $wp;
	parse_str( $wp->matched_query ?? '', $vars );
	return isset( $vars['aloha_investor_article'] ) ? $vars : array();
}

function is_investor_namespace_request() {
	global $wp;
	$path = trim( $wp->request ?? '', '/' );
	return $path === investor_base() || str_starts_with( $path, investor_base() . '/' );
}

// There is no approved Investor category archive/hub. Leave native term links
// as diagnostic URLs, return 404 for them, and omit them from the Core sitemap.
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	if ( 'category' === $taxonomy ) {
		$id = (int) get_option( 'aloha_content_investor_category', 0 );
		if ( $id ) {
			$children = get_term_children( $id, 'category' );
			$args['exclude'] = array_unique( array_merge( wp_parse_id_list( $args['exclude'] ?? array() ), array( $id ), is_wp_error( $children ) ? array() : $children ) );
		}
	}
	return $args;
}, 10, 2 );
