<?php
/** Run only with local WP-CLI eval-file; restores all modified values in finally. */
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Local WP-CLI only.' );
}
$menu = get_post( aloha_shell_menu_id( 'primary' ) );
$settings = get_option( 'aloha_shell_mega_menu' );
$version = get_option( 'aloha_shell_mega_version' );
$check = function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } };
try {
	$bad = aloha_mega_sanitize( array( 'rentals' => array( 'heading' => '<script>alert(1)</script>Stay', 'description' => array(), 'cta_label' => '<b>Explore</b>', 'image_id' => $menu->ID ) ) );
	$check( 'Stay' === $bad['rentals']['heading'] && '' === $bad['rentals']['description'] && 'Explore' === $bad['rentals']['cta_label'] && 0 === $bad['rentals']['image_id'], 'Sanitization/type validation' );
	$check( 0 === aloha_mega_sanitize( 'invalid' )['rentals']['image_id'], 'Missing/malformed input' );
	$blocks = parse_blocks( $menu->post_content );
	$rentals = array_values( array_filter( $blocks, fn( $b ) => 'rentals' === aloha_mega_key( $b['attrs'] ?? array() ) ) )[0];
	update_option( 'aloha_shell_mega_menu', array( 'rentals' => array( 'heading' => '', 'description' => '', 'cta_label' => '', 'image_id' => PHP_INT_MAX ) ) );
	$html = render_block( $rentals );
	$check( ! str_contains( $html, 'aloha-mega-editorial' ) && ! str_contains( $html, '<img' ), 'Empty content/deleted image has no empty editorial or broken image' );
	update_option( 'aloha_shell_mega_menu', array( 'rentals' => array( 'heading' => '<unsafe> & "quoted"', 'description' => 'A & B', 'cta_label' => 'Visit "us"' ) ) );
	$html = render_block( $rentals );
	$check( str_contains( $html, '&lt;unsafe&gt; &amp; &quot;quoted&quot;' ), 'Escaped editorial output' );
	$rentals['attrs']['label'] = 'Changed & "quoted"'; $rentals['attrs']['url'] = '/qa-changed/';
	$content = serialize_blocks( array_merge( array_reverse( array_filter( $blocks, fn( $b ) => 'rentals' !== aloha_mega_key( $b['attrs'] ?? array() ) ) ), array( $rentals ) ) );
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => $content ) ) );
	$prior_settings = get_option( 'aloha_shell_mega_menu' );
	aloha_shell_install(); aloha_shell_install();
	$check( $content === get_post( $menu->ID )->post_content && $prior_settings === get_option( 'aloha_shell_mega_menu' ), 'Repeated install preserves edited menus and settings' );
	// Exercise the once-only M1.2 upgrade, including Unicode escaping and duplicate detection.
	$seed = parse_blocks( aloha_shell_menu_content( 'primary' ) );
	foreach ( $seed as &$block ) { unset( $block['attrs']['className'] ); }
	unset( $block );
	$seed[0]['innerBlocks'] = array_values( array_filter( $seed[0]['innerBlocks'], fn( $b ) => ( $b['attrs']['url'] ?? '' ) !== aloha_destinations()['destinations']['step-free'][1] ) );
	$seed[0]['innerContent'] = array_fill( 0, count( $seed[0]['innerBlocks'] ), null );
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => serialize_blocks( $seed ) ) ) );
	delete_option( 'aloha_shell_mega_version' ); aloha_mega_install();
	$upgraded = get_post( $menu->ID )->post_content;
	$parsed = parse_blocks( $upgraded );
	$check( 'rentals' === aloha_mega_key( $parsed[0]['attrs'] ) && 5 === count( $parsed[0]['innerBlocks'] ), 'Targeted migration adds one missing link and stable key' );
	$check( 'Design & Renovation' === $parsed[2]['innerBlocks'][2]['attrs']['label'], 'Migration preserves ampersands' );
	delete_option( 'aloha_shell_mega_version' ); aloha_mega_install();
	$check( $upgraded === get_post( $menu->ID )->post_content, 'No duplicate link on migration repeat' );
	echo "PASS: sanitization, missing input, image type/deletion, optional content, escaping, repeat install, targeted migration, special characters, deduplication.\n";
} finally {
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => $menu->post_content ) ) );
	update_option( 'aloha_shell_mega_menu', $settings );
	update_option( 'aloha_shell_mega_version', $version );
}
