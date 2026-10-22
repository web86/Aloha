<?php
/** Area singles own one URL level. Other sections and future Community paths stay free. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function area_base() {
	$base = trim( (string) apply_filters( 'aloha_content_area_base', 'big-island' ), '/' );
	$parts = array_map( 'sanitize_title', explode( '/', $base ) );
	return $base && ! in_array( '', $parts, true ) ? implode( '/', $parts ) : 'big-island';
}

function area_reserved_slugs() {
	global $wp_rewrite;
	$slugs = array_merge(
		array( 'guide', guide_segment(), 'communities', 'feed', 'page', 'embed', 'attachment', 'trackback' ),
		(array) $wp_rewrite->feeds,
		array( $wp_rewrite->pagination_base, $wp_rewrite->comments_pagination_base )
	);
	foreach ( $wp_rewrite->endpoints as $endpoint ) {
		if ( $endpoint[0] & EP_PERMALINK ) {
			$slugs[] = $endpoint[1];
		}
	}
	$slugs = array_map( 'sanitize_title', (array) apply_filters( 'aloha_content_area_reserved_slugs', $slugs ) );
	$slugs = array_values( array_unique( array_filter( $slugs ) ) );
	sort( $slugs );
	return $slugs;
}

function area_slug_is_reserved( $slug ) {
	global $wp_rewrite;
	return in_array( $slug, area_reserved_slugs(), true ) || preg_match( '/^(?:[0-9]+|' . preg_quote( $wp_rewrite->comments_pagination_base, '/' ) . '-[0-9]+)$/D', $slug );
}

function area_slug_error( $data ) {
	$id = (int) ( $data['ID'] ?? 0 );
	$slug = $data['post_name'] ?? ( $id ? get_post_field( 'post_name', $id ) : '' );
	$status = $data['post_status'] ?? ( $id ? get_post_status( $id ) : 'draft' );
	if ( '' === $slug && ! in_array( $status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
		$slug = sanitize_title( $data['post_title'] ?? ( $id ? get_post_field( 'post_title', $id ) : '' ) );
	}
	if ( $slug && area_slug_is_reserved( sanitize_title( $slug ) ) ) {
		return new \WP_Error( 'aloha_reserved_area_slug', __( 'This Area URL slug is reserved for a site section or WordPress endpoint. Choose a different slug.', 'aloha-content' ), array( 'status' => 400 ) );
	}
	return null;
}

add_filter( 'wp_unique_post_slug_is_bad_flat_slug', function ( $bad, $slug, $type ) {
	return $bad || ( 'area' === $type && area_slug_is_reserved( $slug ) );
}, 10, 3 );

// Core still generates attachment, embed and numeric-page rules for a CPT even
// with feeds/pages disabled. Keep only a single-level Area rule. The negative
// lookahead leaves reserved section Pages to the existing Core Page rules.
function area_route_prefix() {
	global $wp_rewrite;
	$reserved = array_map( fn( $slug ) => preg_quote( $slug, '#' ), area_reserved_slugs() );
	$reserved[] = '[0-9]+';
	$reserved[] = preg_quote( $wp_rewrite->comments_pagination_base, '#' ) . '-[0-9]+';
	$prefix = preg_quote( $wp_rewrite->root . area_base(), '#' );
	return $prefix . '/(?!(?:' . implode( '|', $reserved ) . ')(?:/|$))';
}
add_filter( 'area_rewrite_rules', function () {
	global $wp_rewrite;
	return array( area_route_prefix() . '([^/]+)/?$' => $wp_rewrite->index . '?area=$matches[1]' );
} );

function area_routes_signature() {
	return hash( 'sha256', wp_json_encode( array( 'schema' => 4, 'base' => area_base(), 'reserved' => area_reserved_slugs(), 'guide' => guide_base(), 'rubrics' => array_keys( guide_rubrics() ), 'investor' => investor_base() ) ) );
}

function sync_area_routes( $force = false ) {
	$signature = area_routes_signature();
	if ( $force || get_option( 'aloha_content_area_routes' ) !== $signature ) {
		clear_area_runtime_rules();
		flush_rewrite_rules( false );
		update_option( 'aloha_content_area_routes', $signature, false );
	}
}

function clear_area_runtime_rules() {
	global $wp_rewrite;
	// Core merges generated permastruct rules into this in-memory array. Drop
	// only Area/Community rules before a second flush/config change in a request.
	foreach ( $wp_rewrite->extra_rules_top as $regex => $query ) {
		if ( str_starts_with( $query, $wp_rewrite->index . '?area=' ) || str_starts_with( $query, $wp_rewrite->index . '?community=' ) || str_starts_with( $query, $wp_rewrite->index . '?aloha_guide_rubric=' ) || str_starts_with( $query, $wp_rewrite->index . '?aloha_investor_article=' ) ) {
			unset( $wp_rewrite->extra_rules_top[ $regex ] );
		}
	}
}

// Stable requests only read the signature. A new plugin/configuration gets one soft flush.
add_action( 'wp_loaded', __NAMESPACE__ . '\\sync_area_routes', 20 );

function is_area_namespace_request() {
	global $wp;
	$path = trim( $wp->request ?? '', '/' );
	return str_starts_with( $path, area_base() . '/' );
}

// Unknown Area/descendant URLs must be 404, not guesses pointing at another place.
add_filter( 'do_redirect_guess_404_permalink', function ( $guess ) {
	return is_area_namespace_request() || is_investor_namespace_request() ? false : $guess;
} );
add_filter( 'old_slug_redirect_url', function ( $url ) {
	return is_area_namespace_request() && in_array( get_query_var( 'post_type' ), array( 'area', 'community' ), true ) ? false : $url;
} );
