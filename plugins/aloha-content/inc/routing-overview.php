<?php
/** Read-only view of existing registration, persisted rules and editorial bindings. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'Aloha Content — Routing Overview', 'Aloha Content: Routing', 'manage_options', 'aloha-content-routing', __NAMESPACE__ . '\\render_routing_overview' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'tools_page_aloha-content-routing' === $hook ) {
		// Core's mobile flex layout assumes a checkbox before column-primary.
		// This read-only table has none: give its first two cells full rows.
		wp_add_inline_style( 'list-tables', '@media screen and (max-width:782px){.aloha-routing-routes thead{display:none}.aloha-routing-routes td.column-primary{flex-basis:100%;padding-right:8px}.aloha-routing-routes tr td:nth-child(2){flex-basis:100%}}' );
	}
} );

// Inspect saved rules, never WP_Rewrite::wp_rewrite_rules(), which can flush.
function overview_route_registration( $struct, $filter, $rules ) {
	global $wp_rewrite;
	if ( empty( $wp_rewrite->extra_permastructs[ $struct ] ) ) {
		return 'Not registered — configuration requires attention';
	}
	$expected = apply_filters( $filter, array() );
	if ( ! $expected || array_diff_assoc( $expected, $rules ) ) {
		return 'Configuration requires attention — saved rules missing or different';
	}
	return 'Registered — permastruct and saved rules verified';
}

function overview_hub( $path ) {
	$page = get_page_by_path( $path, OBJECT, 'page' );
	return array(
		'state' => ! $page ? 'Content not created — not a rewrite error' : ( 'publish' === $page->post_status ? 'Published Page' : 'Page exists, not published' ),
		'url' => $page && 'publish' === $page->post_status ? get_permalink( $page ) : '',
	);
}

// Only show a real published object with a valid current classification/relation.
function overview_example( $type, $family = '' ) {
	$args = array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 20, 'orderby' => 'ID', 'order' => 'ASC' );
	if ( $family ) {
		$binding = 'guide' === $family ? guide_terms() : investor_category();
		if ( is_wp_error( $binding ) ) { return ''; }
		$ids = array( (int) ( 'guide' === $family ? $binding['parent'] : $binding ) );
		// Native include_children can lazily write category_children to options.
		// Read the small editorial term tree directly, without populating that cache.
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'hierarchical' => false ) );
		if ( is_wp_error( $terms ) ) { return ''; }
		do {
			$count = count( $ids );
			foreach ( $terms as $term ) {
				if ( in_array( (int) $term->parent, $ids, true ) && ! in_array( (int) $term->term_id, $ids, true ) ) { $ids[] = (int) $term->term_id; }
			}
		} while ( count( $ids ) !== $count );
		$args['tax_query'] = array( array( 'taxonomy' => 'category', 'terms' => $ids, 'include_children' => false ) );
	}
	for ( $page = 1; ; ++$page ) {
		$posts = get_posts( $args + array( 'paged' => $page ) );
		foreach ( $posts as $post ) {
			if ( 'area' === $type && area_slug_is_reserved( $post->post_name ) ) { continue; }
			if ( 'community' === $type ) {
				$area = get_post( (int) get_post_meta( $post->ID, 'aloha_area_id', true ) );
				if ( ! $area || 'area' !== $area->post_type || 'publish' !== $area->post_status || area_slug_is_reserved( $area->post_name ) ) { continue; }
			}
			if ( $family ) {
				$route = editorial_post_route( $post->ID );
				if ( ! $route || is_wp_error( $route ) || $family !== $route['family'] ) { continue; }
			}
			return get_permalink( $post );
		}
		if ( count( $posts ) < 20 ) { return ''; }
	}
}

function routing_overview_data() {
	$rules = (array) get_option( 'rewrite_rules', array() );
	$guide = guide_terms();
	$investor = investor_category();
	$hub = overview_hub( guide_base() );
	$communities = overview_hub( area_base() . '/communities' );
	$page_rules = post_type_exists( 'page' ) && (bool) array_filter( $rules, fn( $query ) => str_contains( $query, 'pagename=' ) || str_contains( $query, 'page_id=' ) );
	$area_status = overview_route_registration( 'area', 'area_rewrite_rules', $rules );
	$community_status = overview_route_registration( 'community', 'community_rewrite_rules', $rules );
	$guide_status = overview_route_registration( 'aloha_guide', 'aloha_guide_rewrite_rules', $rules );
	$investor_status = overview_route_registration( 'aloha_investor', 'aloha_investor_rewrite_rules', $rules );
	$routes = array(
		'Area' => array( 'path' => '/' . area_base() . '/{area}/', 'source' => 'CPT area', 'registration' => $area_status, 'url' => overview_example( 'area' ) ),
		'Community' => array( 'path' => '/' . area_base() . '/{area}/{community}/', 'source' => 'CPT community', 'registration' => $community_status, 'url' => overview_example( 'community' ) ),
		'Guide Hub' => array( 'path' => '/' . guide_base() . '/', 'source' => 'Page (Core)', 'registration' => $page_rules ? 'Registered — Core Page type and saved Page rules verified' : 'Configuration requires attention — Core Page rules not found', 'url' => $hub['url'] ),
		'Guide Rubric' => array( 'path' => '/' . guide_base() . '/{rubric}/', 'source' => 'Category / native WP_Query', 'registration' => $guide_status, 'url' => is_wp_error( $guide ) ? '' : get_category_link( reset( $guide['rubrics'] ) ) ),
		'Guide Article' => array( 'path' => '/' . guide_base() . '/{rubric}/{article}/', 'source' => 'Post + Guide Category', 'registration' => $guide_status, 'url' => overview_example( 'post', 'guide' ) ),
		'Investor Article' => array( 'path' => '/' . investor_base() . '/{article}/', 'source' => 'Post + Investor Category', 'registration' => $investor_status, 'url' => overview_example( 'post', 'investor' ) ),
	);
	$bindings = array();
	foreach ( guide_rubrics() as $slug => $label ) {
		$id = (int) ( get_option( 'aloha_content_guide_categories', array() )['rubrics'][ $slug ] ?? 0 );
		$term = $id ? get_term( $id, 'category' ) : null;
		$bindings[] = array( $label . ' (' . $slug . ')', is_wp_error( $guide ) ? 'Configuration requires attention — ' . $guide->get_error_message() : $term->name . ' — ID ' . $id );
	}
	$term = is_wp_error( $investor ) ? null : get_term( $investor, 'category' );
	$bindings[] = array( 'Investor Guides', is_wp_error( $investor ) ? 'Configuration requires attention — ' . $investor->get_error_message() : $term->name . ' — ID ' . $investor );
	return array(
		'routes' => $routes,
		'content' => array_merge( array(
			array( 'Published Area', (string) wp_count_posts( 'area' )->publish ),
			array( 'Published Community', (string) wp_count_posts( 'community' )->publish ),
			array( 'Guide Hub Page', $hub['state'] ),
			array( 'Communities Hub Page', $communities['state'] ),
		), $bindings, array( array( 'Investor category archive', 'Intentionally disabled — no public category archive or automatic hub' ) ) ),
		'diagnostics' => array(
			array( 'Area base', area_base() ), array( 'Guide segment', guide_segment() ), array( 'Guide base', guide_base() ), array( 'Investor base', investor_base() ),
			array( 'Reserved Area slugs', implode( ', ', area_reserved_slugs() ) . '; numeric slugs and numbered comment pages are also reserved' ),
			array( 'Guide category bindings', is_wp_error( $guide ) ? 'Configuration requires attention — ' . $guide->get_error_message() : 'Verified — parent and all four rubric IDs, slugs and parents' ),
			array( 'Investor category binding', is_wp_error( $investor ) ? 'Configuration requires attention — ' . $investor->get_error_message() : 'Verified — category ID, slug and independent root parent' ),
			array( 'Routing signature', get_option( 'aloha_content_area_routes' ) === area_routes_signature() ? 'Matches expected configuration' : 'Configuration requires attention — saved signature does not match' ),
		),
	);
}

function render_routing_overview() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to view this page.', 'aloha-content' ), '', array( 'response' => 403 ) );
	}
	$data = routing_overview_data();
	echo '<div class="wrap"><h1>' . esc_html( 'Aloha Content — Routing Overview' ) . '</h1><p>' . esc_html( 'Read-only inspection of registration, saved rewrite rules and content. No HTTP checks are performed; registration does not guarantee a rendered frontend page.' ) . '</p>';
	echo '<h2>' . esc_html( 'Route families' ) . '</h2><table class="aloha-routing-routes wp-list-table widefat fixed striped"><thead><tr>';
	foreach ( array( 'Family', 'URL structure', 'Source', 'Registration', 'Existing published example' ) as $heading ) { echo '<th scope="col">' . esc_html( $heading ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $data['routes'] as $name => $row ) {
		// Core's responsive list-table styles stack the labelled cells on mobile.
		// Keep every row expanded: diagnostics need no disclosure/editing controls.
		echo '<tr class="is-expanded"><td class="column-primary" role="rowheader"><strong>' . esc_html( $name ) . '</strong></td><td data-colname="' . esc_attr( 'URL structure' ) . '"><code>' . esc_html( $row['path'] ) . '</code></td><td data-colname="' . esc_attr( 'Source' ) . '">' . esc_html( $row['source'] ) . '</td><td data-colname="' . esc_attr( 'Registration' ) . '">' . esc_html( $row['registration'] ) . '</td><td data-colname="' . esc_attr( 'Existing published example' ) . '">';
		if ( $row['url'] && ! is_wp_error( $row['url'] ) ) {
			echo '<a href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['url'] ) . '</a>';
		} else {
			echo esc_html( 'No published example — ' . $row['path'] );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	foreach ( array( 'content' => 'Content status', 'diagnostics' => 'Configuration diagnostics' ) as $key => $title ) {
		echo '<h2>' . esc_html( $title ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( $data[ $key ] as $row ) { echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . esc_html( $row[1] ) . '</td></tr>'; }
		echo '</tbody></table>';
	}
	echo '<h2>' . esc_html( 'Not implemented' ) . '</h2><p>' . esc_html( post_type_exists( 'rental' ) ? 'Vacation Rentals — rental CPT detected; GuestyBridge routing is not inspected by Aloha Content.' : 'Vacation Rentals — will be managed by GuestyBridge (not installed). No active rental route.' ) . '</p></div>';
}
