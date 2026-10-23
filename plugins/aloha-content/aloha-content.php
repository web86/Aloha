<?php
/**
 * Plugin Name: Aloha Content
 * Description: Areas, Communities, Big Island Guide and Investor Guides with validated public routes.
 * Version: 0.7.2
 * Requires at least: 6.6
 * Requires PHP: 8.3
 * Text Domain: aloha-content
 * License: GPL-2.0-or-later
 */

namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/integrity.php';
require_once __DIR__ . '/inc/area-media.php';
require_once __DIR__ . '/inc/area-routing.php';
require_once __DIR__ . '/inc/community-routing.php';
require_once __DIR__ . '/inc/guide-categories.php';
require_once __DIR__ . '/inc/guide-integrity.php';
require_once __DIR__ . '/inc/guide-routing.php';
require_once __DIR__ . '/inc/investor-routing.php';
require_once __DIR__ . '/inc/routing-overview.php';

function register_content_types() {
	register_guide_routes();
	register_investor_routes();
	add_rewrite_tag( '%aloha_area_route%', '([^/]+)' );
	foreach ( array( 'area' => array( 'Area', 'Areas' ), 'community' => array( 'Community', 'Communities' ) ) as $type => $names ) {
		register_post_type( $type, array(
			'labels' => array(
				'name'          => $names[1],
				'singular_name' => $names[0],
				'add_new_item'  => 'Add New ' . $names[0],
				'edit_item'     => 'Edit ' . $names[0],
			),
			'public'             => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'menu_icon'          => 'area' === $type ? 'dashicons-location-alt' : 'dashicons-admin-multisite',
			'publicly_queryable' => true,
			'exclude_from_search'=> true,
			'show_in_nav_menus'  => true,
			'has_archive'        => false,
			'rewrite'            => array( 'slug' => area_base() . ( 'community' === $type ? '/%aloha_area_route%' : '' ), 'with_front' => false, 'feeds' => false, 'pages' => false, 'ep_mask' => EP_NONE ),
			'query_var'          => $type,
		) );
	}
	register_area_media();
	register_post_meta( 'community', 'aloha_area_id', array(
		'type'              => 'integer',
		'single'            => true,
		'default'           => 0,
		'description'       => 'The related Area. Zero means no Area selected in a draft.',
		'show_in_rest'      => array( 'schema' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ) ),
		'revisions_enabled' => true,
		'sanitize_callback' => __NAMESPACE__ . '\\sanitize_area_id',
		'auth_callback'     => function ( $allowed, $key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		},
	) );
}
add_action( 'init', __NAMESPACE__ . '\\register_content_types' );

add_action( 'enqueue_block_editor_assets', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'community' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script( 'aloha-content-editor', plugins_url( 'assets/editor.js', __FILE__ ), array(
		'wp-api-fetch', 'wp-components', 'wp-data', 'wp-element', 'wp-editor', 'wp-html-entities', 'wp-plugins',
	), (string) filemtime( __DIR__ . '/assets/editor.js' ), true );
} );

register_activation_hook( __FILE__, function () {
	register_content_types();
	sync_area_routes( true );
} );
register_deactivation_hook( __FILE__, function () {
	unregister_post_type( 'area' );
	unregister_post_type( 'community' );
	remove_rewrite_tag( '%aloha_area_route%' );
	remove_permastruct( 'aloha_guide' );
	remove_rewrite_tag( '%aloha_guide_rubric%' );
	remove_rewrite_tag( '%aloha_guide_article%' );
	remove_permastruct( 'aloha_investor' );
	remove_rewrite_tag( '%aloha_investor_article%' );
	clear_area_runtime_rules();
	flush_rewrite_rules( false );
	delete_option( 'aloha_content_area_routes' );
} );

// No automatic content seed, uninstall deletion or theme dependency.
