<?php
/** Area-owned editorial media. No presentation, routes or automatic content writes. */
namespace Aloha\Content;
defined( 'ABSPATH' ) || exit;
const AREA_FILM_LABEL_DEFAULT = 'Watch the area film · 2 min';

function area_loop_video_id( $value ) {
	if ( ! is_scalar( $value ) || is_bool( $value ) || false === filter_var( $value, FILTER_VALIDATE_INT ) || (int) $value < 0 ) { return 0; }
	$id = (int) $value;
	return $id && 'attachment' === get_post_type( $id ) && in_array( get_post_mime_type( $id ), array( 'video/mp4', 'video/webm' ), true ) ? $id : 0;
}

function area_youtube_id( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) { return ''; }
	$parts = wp_parse_url( trim( $url ) );
	if ( ! $parts || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'https', 'http' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) { return ''; }
	$host = strtolower( $parts['host'] ?? '' ); $path = $parts['path'] ?? '';
	if ( 'youtu.be' === $host ) {
		$id = trim( $path, '/' );
	} elseif ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com' ), true ) ) {
		parse_str( $parts['query'] ?? '', $query );
		if ( '/watch' === $path ) { $id = $query['v'] ?? ''; }
		elseif ( preg_match( '#^/(?:embed|shorts|live)/([^/]+)/?$#D', $path, $match ) ) { $id = $match[1]; }
		else { return ''; }
	} else { return ''; }
	return is_string( $id ) && preg_match( '/^[A-Za-z0-9_-]{11}$/D', $id ) ? $id : '';
}

function sanitize_area_film_url( $url ) {
	$id = area_youtube_id( $url );
	return $id ? 'https://www.youtube.com/watch?v=' . $id : '';
}

function register_area_media() {
	foreach ( array( 'aloha_area_loop_video_id' => 'integer', 'aloha_area_film_url' => 'string', 'aloha_area_film_label' => 'string' ) as $key => $type ) {
		$schema = array( 'type' => $type, 'default' => 'integer' === $type ? 0 : ( 'aloha_area_film_label' === $key ? AREA_FILM_LABEL_DEFAULT : '' ) );
		if ( 'integer' === $type ) { $schema['minimum'] = 0; }
		register_post_meta( 'area', $key, array(
			'type' => $type, 'single' => true, 'default' => $schema['default'],
			'show_in_rest' => array( 'schema' => $schema ), 'revisions_enabled' => true,
			'sanitize_callback' => 'aloha_area_film_label' === $key ? 'sanitize_text_field' : __NAMESPACE__ . ( 'integer' === $type ? '\\area_loop_video_id' : '\\sanitize_area_film_url' ),
			'auth_callback' => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
		) );
	}
}

// Return a clear error before Core writes any post fields or REST meta.
add_filter( 'rest_pre_insert_area', function ( $prepared, $request ) {
	if ( is_wp_error( $prepared ) ) { return $prepared; }
	$meta = $request->get_param( 'meta' ) ?? array();
	if ( isset( $meta['aloha_area_loop_video_id'] ) && 0 !== $meta['aloha_area_loop_video_id'] && ! area_loop_video_id( $meta['aloha_area_loop_video_id'] ) ) {
		return new \WP_Error( 'aloha_invalid_area_video', 'Choose an MP4 or WebM video from the Media Library, or remove the video.', array( 'status' => 400 ) );
	}
	if ( ! empty( $meta['aloha_area_film_url'] ) && ! area_youtube_id( $meta['aloha_area_film_url'] ) ) {
		return new \WP_Error( 'aloha_invalid_area_film', 'Enter a valid YouTube video URL, or leave the film URL empty.', array( 'status' => 400 ) );
	}
	if ( isset( $meta['aloha_area_film_label'] ) && ! is_string( $meta['aloha_area_film_label'] ) ) {
		return new \WP_Error( 'aloha_invalid_area_film_label', 'Enter the film label as plain text.', array( 'status' => 400 ) );
	}
	return $prepared;
}, 20, 2 );
