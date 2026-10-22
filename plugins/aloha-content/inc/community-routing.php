<?php
/** Community singles: one bounded route, resolved by the native main query. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

add_filter( 'community_rewrite_rules', function () {
	global $wp_rewrite;
	return array( area_route_prefix() . '([^/]+)/([^/]+)/?$' => $wp_rewrite->index . '?community=$matches[2]&aloha_area_route=$matches[1]' );
} );

add_filter( 'post_type_link', function ( $link, $post ) {
	if ( 'community' !== $post->post_type || ! str_contains( $link, '%aloha_area_route%' ) ) {
		return $link; // Keep native plain draft/preview links intact.
	}
	$area_id = (int) get_post_meta( $post->ID, 'aloha_area_id', true );
	$area = $area_id > 0 ? get_post( $area_id ) : null;
	if ( $area && 'area' === $area->post_type && 'publish' === $area->post_status && $area->post_name && ! area_slug_is_reserved( $area->post_name ) ) {
		return str_replace( '%aloha_area_route%', $area->post_name, $link );
	}
	// An unassigned draft has no nested public address; Core preview uses its ID.
	return add_query_arg( array( 'post_type' => 'community', 'p' => $post->ID ), home_url( '/' ) );
}, 10, 2 );

function community_route_vars() {
	global $wp;
	parse_str( $wp->matched_query ?? '', $vars );
	return isset( $vars['aloha_area_route'], $vars['community'] ) ? $vars : array();
}

add_filter( 'request', function ( $vars ) {
	$route = community_route_vars();
	if ( ! $route ) {
		return $vars; // Admin, REST, plain preview and other routes remain native.
	}
	$area = get_page_by_path( $route['aloha_area_route'], OBJECT, array( 'area' ) );
	$valid_area = $area && 'publish' === $area->post_status && ! area_slug_is_reserved( $area->post_name );
	$query = array(
		'post_type'   => 'community',
		'name'        => $route['community'],
		'post_status' => 'publish',
		'meta_key'    => 'aloha_area_id',
		'meta_value'  => $valid_area ? (string) $area->ID : '0',
	);
	if ( ! $valid_area ) {
		$query['post__in'] = array( 0 );
	}
	// Do not let public query-string overrides replace the path's identity.
	// Published previews still retain Core's nonce/permission handling.
	return $query + array_intersect_key( $vars, array_flip( array( 'preview', 'preview_id', 'preview_nonce' ) ) );
} );

add_filter( 'redirect_canonical', function ( $redirect ) {
	// In particular, an existing Community under the wrong Area must stay 404.
	return is_404() && community_route_vars() ? false : $redirect;
} );
