<?php
/** Community presentation only. Relations and routing belong to Aloha Content. */
defined( 'ABSPATH' ) || exit;

// Core expands an unsynced pattern only for a new document. No content migrations.
add_filter( 'register_post_type_args', function ( $args, $type ) {
	if ( 'community' === $type && empty( $args['template'] ) ) {
		$args['template'] = array( array( 'core/pattern', array( 'slug' => 'aloha-hello/community-start' ) ) );
		$args['template_lock'] = false;
	}
	return $args;
}, 10, 2 );
add_filter( 'block_editor_settings_all', function ( $settings, $context ) {
	if ( isset( $context->post ) && 'community' === $context->post->post_type && 'auto-draft' !== $context->post->post_status ) {
		$settings['template'] = array(); // A saved empty document also belongs to its editor.
	}
	return $settings;
}, 10, 2 );

add_action( 'after_setup_theme', function () { add_editor_style( 'assets/css/community.css' ); } );
add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular( 'community' ) ) {
		wp_enqueue_style( 'aloha-community', get_theme_file_uri( 'assets/css/community.css' ), array( 'aloha-foundation' ), (string) filemtime( get_theme_file_path( 'assets/css/community.css' ) ) );
	}
} );

function aloha_community_hero( $attrs, $content, $block ) {
	$post = get_post( (int) ( $block->context['postId'] ?? get_the_ID() ) );
	if ( ! $post || 'community' !== $post->post_type ) { return ''; }
	// Never infer an Area from a slug, title, parent, taxonomy or URL segment.
	$area = get_post( (int) get_post_meta( $post->ID, 'aloha_area_id', true ) );
	if ( ! $area || 'area' !== $area->post_type || 'publish' !== $area->post_status ) { $area = null; }
	$title = get_the_title( $post );
	$area_title = $area ? get_the_title( $area ) : '';
	$image = get_the_post_thumbnail( $post, 'full', array(
		'class' => 'aloha-community-hero-image', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async',
		'sizes' => '(min-width: 1280px) 608px, (min-width: 900px) 50vw, (min-width: 768px) calc(100vw - 80px), calc(100vw - 40px)',
	) );
	$crumbs = '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li><li><span>Big Island</span></li>';
	if ( $area ) { $crumbs .= '<li><a href="' . esc_url( get_permalink( $area ) ) . '">' . esc_html( $area_title ) . '</a></li>'; }
	$crumbs .= '<li><span aria-current="page">' . esc_html( $title ) . '</span></li>';
	$html = '<section ' . get_block_wrapper_attributes( array( 'class' => 'aloha-community-hero' ) ) . '><div class="aloha-container">';
	$html .= '<nav class="aloha-community-breadcrumb" aria-label="Breadcrumb"><ol>' . $crumbs . '</ol></nav><div class="aloha-community-hero-grid"><div class="aloha-community-hero-copy"><h1>' . esc_html( $title );
	if ( $area ) { $html .= ' <em>' . esc_html( sprintf( __( 'at %s', 'aloha-hello' ), $area_title ) ) . '</em>'; }
	$html .= '</h1>';
	// Explicit excerpt only: do not derive an intro from starter/editorial placeholders.
	if ( trim( $post->post_excerpt ) ) { $html .= '<p class="aloha-community-hero-intro">' . esc_html( $post->post_excerpt ) . '</p>'; }
	$html .= '<div class="aloha-community-hero-actions">';
	$html .= '<button type="button" class="aloha-button aloha-community-homes" data-aloha-info-title="' . esc_attr__( 'Community homes', 'aloha-hello' ) . '" data-aloha-info="' . esc_attr__( 'Homes for this community are not available to browse yet. No availability, home count or booking is offered at this stage.', 'aloha-hello' ) . '" aria-haspopup="dialog">' . esc_html( sprintf( __( 'See our %s homes', 'aloha-hello' ), $title ) ) . '</button>';
	$html .= '<button type="button" class="aloha-button aloha-button--secondary" data-aloha-info-title="' . esc_attr__( 'For homeowners', 'aloha-hello' ) . '" data-aloha-info="' . esc_attr__( 'The homeowner enquiry section is being prepared. The rental estimate form is not available yet; no enquiry has been sent.', 'aloha-hello' ) . '" aria-haspopup="dialog">' . esc_html__( 'I own a home here', 'aloha-hello' ) . '</button></div></div>';
	$html .= '<div class="aloha-community-hero-media' . ( $image ? ' has-image' : '' ) . '">' . $image;
	if ( ! $image ) { $html .= '<span class="aloha-community-image-placeholder">' . esc_html__( 'Community photo', 'aloha-hello' ) . '</span>'; }
	return $html . '</div></div></div></section>';
}

add_action( 'init', function () {
	register_block_pattern_category( 'aloha-community', array( 'label' => 'Aloha Community' ) );
	wp_register_script( 'aloha-community-editor', get_theme_file_uri( 'assets/js/community-editor.js' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render', 'wp-data', 'wp-components' ), (string) filemtime( get_theme_file_path( 'assets/js/community-editor.js' ) ), true );
	register_block_type( 'aloha/community-hero', array(
		'api_version' => 3, 'title' => 'Community split hero', 'category' => 'theme',
		'uses_context' => array( 'postId', 'postType' ),
		'supports' => array( 'html' => false, 'multiple' => false, 'inserter' => false ),
		'editor_script' => 'aloha-community-editor', 'render_callback' => 'aloha_community_hero',
	) );
}, 8 );
