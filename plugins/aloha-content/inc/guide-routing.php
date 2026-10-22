<?php
/** Shared native article filters; Guide category routes remain scoped. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function guide_segment() {
	return sanitize_title( (string) apply_filters( 'aloha_content_guide_slug', 'guide' ) ) ?: 'guide';
}

function guide_base() {
	return area_base() . '/' . guide_segment();
}

function register_guide_routes() {
	add_rewrite_tag( '%aloha_guide_rubric%', '(' . implode( '|', array_keys( guide_rubrics() ) ) . ')' );
	add_rewrite_tag( '%aloha_guide_article%', '([^/]+)' );
	add_permastruct( 'aloha_guide', guide_base() . '/%aloha_guide_rubric%', array( 'with_front' => false, 'ep_mask' => EP_NONE, 'walk_dirs' => false, 'feeds' => false, 'pages' => false ) );
}

add_filter( 'aloha_guide_rewrite_rules', function () {
	global $wp_rewrite;
	$prefix = preg_quote( $wp_rewrite->root . guide_base(), '#' ) . '/(' . implode( '|', array_keys( guide_rubrics() ) ) . ')';
	return array(
		$prefix . '/' . preg_quote( $wp_rewrite->pagination_base, '#' ) . '/([1-9][0-9]*)/?$' => $wp_rewrite->index . '?aloha_guide_rubric=$matches[1]&paged=$matches[2]',
		$prefix . '/([^/]+)/?$' => $wp_rewrite->index . '?aloha_guide_rubric=$matches[1]&aloha_guide_article=$matches[2]',
		$prefix . '/?$' => $wp_rewrite->index . '?aloha_guide_rubric=$matches[1]',
	);
} );

function guide_route_vars() {
	global $wp;
	parse_str( $wp->matched_query ?? '', $vars );
	return isset( $vars['aloha_guide_rubric'] ) ? $vars : array();
}

function guide_category_url( $slug = '', $page = 1 ) {
	global $wp_rewrite;
	$path = guide_base() . ( $slug ? '/' . $slug : '' );
	if ( $page > 1 ) {
		$path .= '/' . $wp_rewrite->pagination_base . '/' . (int) $page;
	}
	return home_url( user_trailingslashit( $path, 'category' ) );
}

add_filter( 'post_link', function ( $link, $post, $leavename ) {
	if ( 'post' !== $post->post_type || wp_force_plain_post_permalink( $post ) ) {
		return $link;
	}
	$route = editorial_post_route( $post->ID );
	if ( ! $route || is_wp_error( $route ) ) { return $link; }
	$base = 'investor' === $route['family'] ? investor_base() : guide_base() . '/' . $route['slug'];
	return home_url( user_trailingslashit( $base . '/' . ( $leavename ? '%postname%' : $post->post_name ), 'single' ) );
}, 10, 3 );

add_filter( 'term_link', function ( $link, $term, $taxonomy ) {
	if ( 'category' !== $taxonomy ) {
		return $link;
	}
	$term_id = $term->term_id;
	$map = guide_terms();
	if ( is_wp_error( $map ) ) {
		return $link;
	}
	if ( (int) $term_id === (int) $map['parent'] ) {
		return guide_category_url();
	}
	$rubric = guide_post_rubric( 0, array( (int) $term_id ) );
	return false === $rubric ? $link : guide_category_url( is_wp_error( $rubric ) ? '' : $rubric['slug'] );
}, 10, 3 );

function editorial_article_query( $slug, $family, $vars, $rubric_slug = '' ) {
	$post = get_page_by_path( $slug, OBJECT, array( 'post' ) );
	$route = $post ? editorial_post_route( $post->ID ) : false;
	$valid = $post && 'publish' === $post->post_status && $route && ! is_wp_error( $route ) && $family === $route['family'] && ( 'investor' === $family || $rubric_slug === $route['slug'] );
	return array( 'post_type' => 'post', 'name' => $slug, 'post_status' => 'publish', 'post__in' => array( $valid ? $post->ID : 0 ) ) + array_intersect_key( $vars, array_flip( array( 'preview', 'preview_id', 'preview_nonce' ) ) );
}

add_filter( 'request', function ( $vars ) {
	$investor = investor_route_vars();
	if ( $investor ) {
		return editorial_article_query( $investor['aloha_investor_article'], 'investor', $vars );
	}
	$route = guide_route_vars();
	if ( ! $route ) {
		return $vars;
	}
	$map = guide_terms();
	$slug = $route['aloha_guide_rubric'];
	$term_id = ! is_wp_error( $map ) ? (int) ( $map['rubrics'][ $slug ] ?? 0 ) : 0;
	if ( isset( $route['aloha_guide_article'] ) ) {
		return editorial_article_query( $route['aloha_guide_article'], 'guide', $vars, $slug );
	}
	return $term_id ? array( 'cat' => $term_id, 'paged' => (int) ( $route['paged'] ?? 0 ) ) : array( 'category_name' => '__aloha_unavailable_guide__', 'post__in' => array( 0 ) );
} );

add_filter( 'redirect_canonical', function ( $redirect ) {
	return is_404() && ( guide_route_vars() || investor_route_vars() || is_investor_namespace_request() || get_query_var( 'aloha_editorial_invalid' ) ) ? false : $redirect;
} );
add_filter( 'old_slug_redirect_url', function ( $url ) {
	global $wp;
	$target = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) . '/';
	$base_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	foreach ( array( guide_base(), investor_base() ) as $base ) {
		$prefix = $base . '/';
		if ( str_starts_with( $wp->request ?? '', $prefix ) || str_starts_with( $target, ( $base_path ? $base_path . '/' : '' ) . $prefix ) ) { return false; }
	}
	return $url;
} );

function guide_current_archive_url() {
	$map = guide_terms();
	if ( is_wp_error( $map ) ) {
		return false;
	}
	$id = get_queried_object_id();
	if ( (int) $id === (int) $map['parent'] ) {
		return guide_category_url();
	}
	$rubric = guide_post_rubric( 0, array( (int) $id ) );
	return false === $rubric ? false : ( is_wp_error( $rubric ) ? guide_category_url() : guide_category_url( $rubric['slug'], max( 1, (int) get_query_var( 'paged' ) ) ) );
}

function guide_not_found() {
	global $wp_query;
	$wp_query->set_404();
	$wp_query->posts = array();
	$wp_query->post = null;
	$wp_query->post_count = 0;
	$wp_query->set( 'aloha_editorial_invalid', true );
	status_header( 404 );
	nocache_headers();
}

// Only aliases of current editorial objects; this is not a history map.
add_action( 'template_redirect', function () {
	// An ID query must not turn a malformed Investor path into a current alias.
	// Genuine Core Pages in this namespace retain their native behavior.
	if ( ! is_404() && is_singular( 'post' ) && is_investor_namespace_request() && ! investor_route_vars() ) {
		guide_not_found();
		return;
	}
	if ( is_404() || is_preview() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	$target = false;
	if ( is_singular( 'post' ) ) {
		$rubric = editorial_post_route( get_queried_object_id() );
		if ( is_wp_error( $rubric ) ) {
			guide_not_found();
			return;
		}
		if ( $rubric && 'publish' === get_post_status( get_queried_object_id() ) && ! guide_route_vars() && ! investor_route_vars() ) {
			$target = get_permalink( get_queried_object_id() );
		}
	} elseif ( is_category() && ! guide_route_vars() ) {
		if ( false !== investor_post( 0, array( get_queried_object_id() ) ) ) {
			guide_not_found();
			return;
		}
		if ( is_wp_error( guide_post_rubric( 0, array( get_queried_object_id() ) ) ) && is_wp_error( guide_terms() ) ) {
			guide_not_found();
			return;
		}
		$target = guide_current_archive_url();
	}
	if ( $target ) {
		wp_safe_redirect( $target, 301, 'Aloha Content' );
		exit;
	}
}, 2 );

add_action( 'wp_head', function () {
	if ( is_category() && ! is_404() ) {
		$url = guide_current_archive_url();
		if ( $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		}
	}
} );
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_preview() && is_singular( 'post' ) && false !== editorial_post_route( get_queried_object_id() ) ) {
		$robots['noindex'] = true;
		unset( $robots['index'] );
	}
	return $robots;
} );
