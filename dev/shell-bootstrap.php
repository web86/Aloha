<?php
/** Independent local preview; M1.1 style showcase remains unchanged. */
defined( 'ABSPATH' ) || exit;
function aloha_is_shell_preview() {
	return 'local' === wp_get_environment_type() && isset( $_GET['aloha-shell-preview'] ) && '1' === $_GET['aloha-shell-preview'];
}
add_filter( 'template_include', function ( $template ) {
	if ( ! aloha_is_shell_preview() ) {
		return $template;
	}
	status_header( 200 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	return __DIR__ . '/shell-preview.php';
}, 20 );
add_filter( 'pre_get_document_title', function ( $title ) { return aloha_is_shell_preview() ? 'Aloha Hello · Shell preview' : $title; }, 20 );
add_filter( 'show_admin_bar', function ( $show ) { return aloha_is_shell_preview() ? false : $show; }, 20 );
add_filter( 'wp_robots', function ( $robots ) {
	if ( aloha_is_shell_preview() ) { $robots['noindex'] = true; $robots['nofollow'] = true; }
	return $robots;
} );
// Planned destinations must not be prefetched before the preview click handler runs.
add_filter( 'wp_speculation_rules_configuration', function ( $config ) {
	return aloha_is_shell_preview() ? null : $config;
} );
