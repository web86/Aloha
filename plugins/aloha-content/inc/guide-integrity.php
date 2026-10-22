<?php
/** Two bounded editorial families; no general primary-category rules. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function editorial_category_ancestors( $post_id, $categories = null ) {
	$categories = null === $categories ? wp_get_post_categories( $post_id ) : array_map( 'intval', (array) $categories );
	$ancestors = array();
	foreach ( $categories as $id ) {
		$ancestors = array_merge( $ancestors, array( $id ), get_ancestors( $id, 'category', 'taxonomy' ) );
	}
	return $ancestors;
}

function guide_post_rubric( $post_id, $categories = null ) {
	$map = get_option( 'aloha_content_guide_categories', array() );
	if ( ! $map ) { return false; }
	$ancestors = editorial_category_ancestors( $post_id, $categories );
	$known = array_merge( array( (int) ( $map['parent'] ?? 0 ) ), array_values( $map['rubrics'] ?? array() ) );
	if ( ! array_intersect( $known, $ancestors ) ) {
		return false; // Ordinary Posts retain their existing routes and editing.
	}
	$valid = guide_terms();
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$matches = array_intersect( $map['rubrics'], $ancestors );
	if ( 1 !== count( $matches ) ) {
		return new \WP_Error( 'aloha_guide_rubric_required', 'Choose exactly one Big Island Guide rubric: Things to Do, Food & Drink, Top Spots or Events. A draft may keep an unfinished selection.', array( 'status' => 400 ) );
	}
	return array( 'slug' => array_key_first( $matches ), 'term_id' => (int) reset( $matches ) );
}

function investor_post( $post_id, $categories = null ) {
	$id = (int) get_option( 'aloha_content_investor_category', 0 );
	if ( ! $id || ! in_array( $id, editorial_category_ancestors( $post_id, $categories ), true ) ) { return false; }
	$valid = investor_category();
	return is_wp_error( $valid ) ? $valid : true;
}

function editorial_post_route( $post_id, $categories = null ) {
	$guide = guide_post_rubric( $post_id, $categories );
	$investor = investor_post( $post_id, $categories );
	if ( false !== $guide && false !== $investor ) {
		return new \WP_Error( 'aloha_article_family_conflict', 'Choose a single article family: Big Island Guide or Investor Guides. Remove categories from the other family before publishing.', array( 'status' => 400 ) );
	}
	if ( is_wp_error( $guide ) || is_wp_error( $investor ) ) { return is_wp_error( $guide ) ? $guide : $investor; }
	return $guide ? $guide + array( 'family' => 'guide' ) : ( $investor ? array( 'family' => 'investor' ) : false );
}

function editorial_save_error( $data ) {
	$id = (int) ( $data['ID'] ?? 0 );
	$status = $data['post_status'] ?? ( $id ? get_post_status( $id ) : 'draft' );
	if ( ! in_array( $status, array( 'publish', 'future', 'private' ), true ) ) {
		return null;
	}
	$categories = $data['tax_input']['category'] ?? $data['post_category'] ?? null;
	$result = editorial_post_route( $id, $categories );
	return is_wp_error( $result ) ? $result : null;
}

function protect_editorial_publication( $id ) {
	static $running = false;
	if ( $running || 'post' !== get_post_type( $id ) ) {
		return;
	}
	$error = editorial_save_error( array( 'ID' => $id ) );
	if ( $error ) {
		$running = true;
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		$running = false;
		report_block( new \WP_Error( $error->get_error_code(), 'Saved as draft. ' . $error->get_error_message() ) );
	}
}

// Native taxonomy APIs have no pre-assignment WP_Error hook. If used directly
// against a public post, fail closed as a draft with an explicit notice.
add_action( 'set_object_terms', function ( $id, $terms, $tt_ids, $taxonomy ) {
	if ( 'category' === $taxonomy ) {
		protect_editorial_publication( $id );
	}
}, 10, 4 );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' === $new ) {
		protect_editorial_publication( $post->ID );
	}
}, 20, 3 );
