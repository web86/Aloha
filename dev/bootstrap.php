<?php
/** Optional local tooling. This directory is outside the distributable theme. */
defined( 'ABSPATH' ) || exit;
if ( 'local' !== wp_get_environment_type() ) {
	return;
}

function aloha_is_style_showcase() {
	return isset( $_GET['aloha-style-showcase'] ) && '1' === $_GET['aloha-style-showcase'];
}

add_filter( 'template_include', function ( $template ) {
	if ( ! aloha_is_style_showcase() ) {
		return $template;
	}
	status_header( 200 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	return __DIR__ . '/style-showcase.php';
} );
add_filter( 'pre_get_document_title', function ( $title ) {
	return aloha_is_style_showcase() ? 'Aloha Hello · Local design system' : $title;
} );
add_filter( 'wp_robots', function ( $robots ) {
	if ( aloha_is_style_showcase() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );
add_filter( 'show_admin_bar', function ( $show ) {
	return aloha_is_style_showcase() ? false : $show;
} );
add_action( 'wp_enqueue_scripts', function () {
	if ( aloha_is_style_showcase() ) {
		wp_add_inline_style( 'aloha-foundation', file_get_contents( __DIR__ . '/style-showcase.css' ) );
	}
}, 20 );
