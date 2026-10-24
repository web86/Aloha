<?php
/** Optional Community film. Reuses the established Area YouTube URL contract. */
namespace Aloha\Content;
defined( 'ABSPATH' ) || exit;
const COMMUNITY_FILM_LABEL_FORMAT = '%s in 90 seconds';

function register_community_media() {
	foreach ( array( 'aloha_community_film_url', 'aloha_community_film_label' ) as $key ) {
		register_post_meta( 'community', $key, array(
			'type' => 'string', 'single' => true, 'default' => '',
			'show_in_rest' => array( 'schema' => array( 'type' => 'string', 'default' => '' ) ), 'revisions_enabled' => true,
			'sanitize_callback' => 'aloha_community_film_url' === $key ? __NAMESPACE__ . '\\sanitize_area_film_url' : 'sanitize_text_field',
			'auth_callback' => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
		) );
	}
}

function community_media_error( $meta ) {
	if ( array_key_exists( 'aloha_community_film_url', $meta ) ) {
		$url = $meta['aloha_community_film_url'];
		if ( ! is_string( $url ) || ( '' !== trim( $url ) && ! area_youtube_id( $url ) ) ) {
			return new \WP_Error( 'aloha_invalid_community_film', 'Enter a valid YouTube video URL, or leave the film URL empty.', array( 'status' => 400 ) );
		}
	}
	if ( array_key_exists( 'aloha_community_film_label', $meta ) && ! is_string( $meta['aloha_community_film_label'] ) ) {
		return new \WP_Error( 'aloha_invalid_community_film_label', 'Enter the film label as plain text.', array( 'status' => 400 ) );
	}
	return null;
}

// Validate the whole request before Core writes title, content, relationship or meta.
add_filter( 'rest_pre_insert_community', function ( $prepared, $request ) {
	if ( is_wp_error( $prepared ) ) { return $prepared; }
	$meta = $request->get_param( 'meta' ) ?? array();
	$error = community_media_error( $meta );
	if ( $error ) { return $error; }
	$id = (int) ( $prepared->ID ?? 0 );
	foreach ( array( 'aloha_community_film_url', 'aloha_community_film_label' ) as $key ) {
		if ( $id && array_key_exists( $key, $meta ) && ! current_user_can( 'edit_post_meta', $id, $key ) ) {
			return new \WP_Error( 'rest_cannot_update', 'You cannot edit this Community’s media.', array( 'status' => 403 ) );
		}
	}
	return $prepared;
}, 20, 2 );

// The trusted Core post API must also refuse an invalid meta_input before any write.
add_filter( 'wp_insert_post_empty_content', function ( $empty, $data ) {
	if ( 'community' === ( $data['post_type'] ?? get_post_type( $data['ID'] ?? 0 ) ) ) {
		$error = community_media_error( $data['meta_input'] ?? array() );
		if ( $error ) { report_block( $error ); return true; }
	}
	return $empty;
}, 9, 2 );
