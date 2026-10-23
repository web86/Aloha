<?php
/** Area presentation. CPTs, relationships and routing remain in Aloha Content. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/area-patterns.php';

// Native CPT template is consumed only by a new, empty Gutenberg document.
// Saved post_content is never seeded or rewritten on save, theme switch or update.
add_filter( 'register_post_type_args', function ( $args, $type ) {
	if ( 'area' === $type && empty( $args['template'] ) ) {
		// Core's Pattern Placeholder expands into ordinary, unsynced blocks.
		$args['template'] = array( array( 'core/pattern', array( 'slug' => 'aloha-hello/area-start' ) ) );
		$args['template_lock'] = false;
	}
	return $args;
}, 10, 2 );
add_filter( 'block_editor_settings_all', function ( $settings, $context ) {
	if ( isset( $context->post ) && 'area' === $context->post->post_type && 'auto-draft' !== $context->post->post_status ) {
		// An intentionally empty saved document is also manager-owned content.
		$settings['template'] = array();
	}
	return $settings;
}, 10, 2 );

add_action( 'after_setup_theme', function () { add_editor_style( 'assets/css/area.css' ); } );
add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular( 'area' ) ) {
		wp_enqueue_style( 'aloha-area', get_theme_file_uri( 'assets/css/area.css' ), array( 'aloha-foundation' ), (string) filemtime( get_theme_file_path( 'assets/css/area.css' ) ) );
		foreach ( array( 'media-dialog', 'area-hero' ) as $script ) {
			wp_enqueue_script( 'aloha-' . $script, get_theme_file_uri( 'assets/js/' . $script . '.js' ), array(), (string) filemtime( get_theme_file_path( 'assets/js/' . $script . '.js' ) ), true );
		}
	}
} );

function aloha_area_block_post( $block ) {
	$id = (int) ( $block->context['postId'] ?? get_the_ID() );
	$post = $id ? get_post( $id ) : null;
	return $post && 'area' === $post->post_type ? $post : null;
}
function aloha_area_hero( $attrs, $content, $block ) {
	$post = aloha_area_block_post( $block );
	if ( ! $post ) { return ''; }
	$image = get_the_post_thumbnail( $post, 'full', array( 'class' => 'aloha-area-hero-image', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '100vw' ) );
	$video_id = function_exists( '\\Aloha\\Content\\area_loop_video_id' ) ? \Aloha\Content\area_loop_video_id( get_post_meta( $post->ID, 'aloha_area_loop_video_id', true ) ) : 0;
	$video_url = $video_id ? wp_get_attachment_url( $video_id ) : '';
	$film_id = function_exists( '\\Aloha\\Content\\area_youtube_id' ) ? \Aloha\Content\area_youtube_id( get_post_meta( $post->ID, 'aloha_area_film_url', true ) ) : '';
	$html = '<section class="aloha-area-hero' . ( $image || $video_url ? ' has-image' : '' ) . '">' . $image;
	if ( $video_url ) {
		// No src until the client has checked motion, data-saving and visibility.
		// The responsive fallback stays underneath. JS reuses its loaded currentSrc
		// as the video poster, avoiding another full-resolution image download.
		$html .= '<video class="aloha-area-hero-video" muted autoplay loop playsinline preload="none" aria-hidden="true" tabindex="-1" data-aloha-loop-src="' . esc_url( $video_url ) . '"></video>';
		$html .= '<button class="aloha-area-video-toggle" type="button" hidden>Pause background video</button>';
	}
	$caption = get_the_post_thumbnail_caption( $post );
	if ( $caption ) { $html .= '<p class="aloha-area-image-note">' . esc_html( $caption ) . '</p>'; }
	$html .= '<div class="aloha-container aloha-area-hero-content"><nav class="aloha-area-breadcrumb" aria-label="Breadcrumb"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a><span aria-hidden="true"> › </span><span>Big Island</span><span aria-hidden="true"> › </span><span aria-current="page">' . esc_html( get_the_title( $post ) ) . '</span></nav>';
	$html .= '<h1>' . esc_html( get_the_title( $post ) ) . ' <em>vacation rentals</em></h1><div class="aloha-area-hero-bottom">';
	if ( $post->post_excerpt ) { $html .= '<p>' . esc_html( $post->post_excerpt ) . '</p>'; }
	$html .= '<div class="aloha-area-hero-actions">';
	if ( $film_id ) {
		$html .= '<button class="aloha-area-film" type="button" data-aloha-youtube="' . esc_attr( $film_id ) . '" data-aloha-media-title="' . esc_attr( get_the_title( $post ) . ' — area film' ) . '" aria-haspopup="dialog"><span aria-hidden="true">▶</span>Watch the 2-min area film</button>';
	}
	// GuestyBridge will supply a verified collection/count/target; none exists yet.
	$html .= '<a class="aloha-button" href="#area-content">Explore the area</a></div></div></div></section>';
	return $html;
}
function aloha_area_communities( $attrs, $content, $block ) {
	$post = aloha_area_block_post( $block );
	if ( ! $post || ! post_type_exists( 'community' ) ) { return ''; }
	$query = new WP_Query( array( 'post_type' => 'community', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_key' => 'aloha_area_id', 'meta_value' => $post->ID, 'no_found_rows' => true ) );
	$html = '<section class="aloha-area-section aloha-area-communities"><div class="aloha-container"><h2>Communities <em>in ' . esc_html( get_the_title( $post ) ) . '</em></h2>';
	if ( ! $query->posts ) {
		return $html . '<p class="aloha-area-empty">Community details will appear here as they are published.</p></div></section>';
	}
	$html .= '<div class="aloha-area-community-grid">';
	foreach ( $query->posts as $community ) {
		$html .= '<article class="aloha-area-community"><a href="' . esc_url( get_permalink( $community ) ) . '">';
		$html .= get_the_post_thumbnail( $community, 'large', array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 1024px) 384px, (min-width: 600px) 45vw, 100vw' ) );
		$html .= '<h3>' . esc_html( get_the_title( $community ) ) . '</h3></a>';
		$excerpt = get_the_excerpt( $community );
		if ( $excerpt ) { $html .= '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), 32 ) ) . '</p>'; }
		$html .= '</article>';
	}
	return $html . '</div></div></section>';
}
add_action( 'init', function () { register_block_pattern_category( 'aloha-area', array( 'label' => 'Aloha Area' ) ); }, 8 );
add_action( 'init', function () {
	wp_register_script( 'aloha-area-editor', get_theme_file_uri( 'assets/js/area-editor.js' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render', 'wp-components', 'wp-data', 'wp-core-data', 'wp-editor', 'wp-plugins', 'wp-html-entities' ), (string) filemtime( get_theme_file_path( 'assets/js/area-editor.js' ) ), true );
	foreach ( array( 'hero' => 'Area hero', 'communities' => 'Area communities', 'rentals' => 'Area rentals — future integration' ) as $kind => $label ) {
		register_block_type( 'aloha/area-' . $kind, array(
			'api_version' => 3, 'title' => $label, 'category' => 'theme', 'uses_context' => array( 'postId', 'postType' ),
			'supports' => array( 'html' => false, 'multiple' => false, 'inserter' => 'hero' !== $kind ),
			'editor_script' => 'aloha-area-editor',
			'render_callback' => 'rentals' === $kind ? fn() => '' : 'aloha_area_' . $kind,
		) );
	}
} );

// Reuse the local shell's planned-destination dialog for the native CTA buttons.
add_filter( 'render_block_core/button', function ( $html, $block ) {
	if ( str_contains( $block['attrs']['className'] ?? '', 'aloha-area-planned-link' ) ) {
		$p = new WP_HTML_Tag_Processor( $html );
		if ( $p->next_tag( 'a' ) ) { return aloha_mark_planned_link( $html, array( 'attrs' => array( 'url' => $p->get_attribute( 'href' ) ) ) ); }
	}
	return $html;
}, 10, 2 );
add_filter( 'render_block_core/image', function ( $html, $block ) {
	if ( is_singular( 'area' ) ) {
		if ( ! empty( $block['attrs']['id'] ) && ! wp_attachment_is_image( $block['attrs']['id'] ) ) { return ''; }
		$p = new WP_HTML_Tag_Processor( $html );
		if ( $p->next_tag( 'img' ) ) {
			if ( ! $p->get_attribute( 'src' ) ) { return ''; } // Native editor upload placeholder, not a broken frontend image.
			$p->set_attribute( 'loading', 'lazy' ); $p->set_attribute( 'decoding', 'async' ); $p->remove_attribute( 'fetchpriority' );
		}
		return $p->get_updated_html();
	}
	return $html;
}, 10, 2 );
