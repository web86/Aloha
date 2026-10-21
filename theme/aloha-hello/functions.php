<?php
/** Theme presentation only; integrations belong to plugins. */
defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/foundation.css', 'assets/css/editor.css' ) );
} );

add_action( 'wp_enqueue_scripts', function () {
	$file = get_theme_file_path( 'assets/css/foundation.css' );
	wp_enqueue_style( 'aloha-foundation', get_theme_file_uri( 'assets/css/foundation.css' ), array(), (string) filemtime( $file ) );
} );

add_action( 'init', function () {
	register_block_style( 'core/button', array( 'name' => 'secondary', 'label' => __( 'Secondary', 'aloha-hello' ) ) );
	register_block_style( 'core/button', array( 'name' => 'text', 'label' => __( 'Text link', 'aloha-hello' ) ) );
	register_block_style( 'core/group', array( 'name' => 'card', 'label' => __( 'Card', 'aloha-hello' ) ) );
} );

// Optional tooling is mounted separately and cannot load outside local development.
if ( 'local' === wp_get_environment_type() && defined( 'ALOHA_DEV_SHOWCASE_BOOTSTRAP' ) && is_readable( ALOHA_DEV_SHOWCASE_BOOTSTRAP ) ) {
	require_once ALOHA_DEV_SHOWCASE_BOOTSTRAP;
}
