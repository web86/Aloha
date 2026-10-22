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
	$check( $settings === aloha_mega_sanitize( 'invalid' ), 'Missing/malformed input' );
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
	$check( aloha_mega_is_section( parse_blocks( '<!-- wp:navigation-submenu {"label":"Home group","url":"/"} --><!-- wp:home-link /--><!-- /wp:navigation-submenu -->' )[0] ), 'Core Home Link counts without a saved URL' );
	// Dynamic discovery, server-side ID allocation/deduplication and orphan retention.
	$dynamic = parse_blocks( $menu->post_content );
	$new = parse_blocks( '<!-- wp:navigation-submenu {"label":"Experiences QA","url":"/qa-experiences/","kind":"custom","className":"qa-existing-class"} --><!-- wp:navigation-link {"label":"Guide","url":"/big-island/guide/","kind":"custom"} /--><!-- /wp:navigation-submenu -->' )[0];
	$dynamic[] = $new;
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => serialize_blocks( $dynamic ) ) ) );
	$dynamic = parse_blocks( get_post( $menu->ID )->post_content ); $new = end( $dynamic );
	$key = aloha_mega_key( $new['attrs'] );
	$check( str_starts_with( $key, 'section-' ) && str_contains( $new['attrs']['className'], 'qa-existing-class' ), 'New ID allocated on save; unrelated classes preserved' );
	$check( 'Experiences QA' === aloha_mega_sections()[ $key ], 'Dynamic group discovered' );
	$dynamic[] = $new;
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => serialize_blocks( $dynamic ) ) ) );
	$dynamic = parse_blocks( get_post( $menu->ID )->post_content ); $copy = end( $dynamic );
	$check( $key !== aloha_mega_key( $copy['attrs'] ), 'Copied IDs cannot share settings' );
	$option = aloha_mega_sanitize( array( $key => array( 'heading' => '<b>Explore</b>', 'description' => 'A & B', 'image_left' => '1' ) ) );
	update_option( 'aloha_shell_mega_menu', $option );
	$check( true === $option[ $key ]['image_left'] && 'Explore' === $option[ $key ]['heading'], 'Dynamic fields sanitized' );
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => $menu->post_content ) ) );
	$check( ! isset( aloha_mega_sections()[ $key ] ), 'Removed group hidden from settings' );
	$check( $option[ $key ] === aloha_mega_sanitize( array( 'rentals' => array( 'heading' => 'Stay' ) ) )[ $key ], 'Saving active groups preserves orphan content' );
	$new['attrs']['label'] = 'Renamed & restored'; $new['attrs']['url'] = '/qa-renamed/';
	$dynamic = array_merge( array( $new ), parse_blocks( $menu->post_content ) );
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => serialize_blocks( $dynamic ) ) ) );
	$check( $key === array_key_first( aloha_mega_sections() ) && 'Renamed & restored' === aloha_mega_sections()[ $key ] && 'Explore' === get_option( 'aloha_shell_mega_menu' )[ $key ]['heading'], 'Restored ID recovers content after rename/URL/order changes' );
	$check( ! isset( aloha_mega_sanitize( array( 'unknown' => array( 'heading' => 'Unsafe' ) ) )['unknown'] ), 'Inactive arbitrary submitted keys ignored' );
	// Exercise the once-only M1.2 upgrade, including Unicode escaping and duplicate detection.
	$seed = parse_blocks( aloha_shell_menu_content( 'primary' ) );
	foreach ( $seed as &$block ) { unset( $block['attrs']['className'] ); }
	unset( $block );
	$seed[0]['innerBlocks'] = array_values( array_filter( $seed[0]['innerBlocks'], fn( $b ) => ( $b['attrs']['url'] ?? '' ) !== aloha_destinations()['destinations']['step-free'][1] ) );
	$seed[0]['innerContent'] = array_fill( 0, count( $seed[0]['innerBlocks'] ), null );
	delete_option( 'aloha_shell_mega_version' );
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => serialize_blocks( $seed ) ) ) ); aloha_mega_install();
	$upgraded = get_post( $menu->ID )->post_content;
	$parsed = parse_blocks( $upgraded );
	$check( 'rentals' === aloha_mega_key( $parsed[0]['attrs'] ) && 5 === count( $parsed[0]['innerBlocks'] ), 'Targeted migration adds one missing link and stable key' );
	$check( 'Design & Renovation' === $parsed[2]['innerBlocks'][2]['attrs']['label'], 'Migration preserves ampersands' );
	delete_option( 'aloha_shell_mega_version' ); aloha_mega_install();
	$check( $upgraded === get_post( $menu->ID )->post_content, 'No duplicate link on migration repeat' );
	echo "PASS: sanitization, missing input, image type/deletion, optional content, escaping, repeat install, targeted migration, special characters, deduplication, dynamic groups, copied IDs, orphan retention, image position.\n";
} finally {
	wp_update_post( wp_slash( array( 'ID' => $menu->ID, 'post_content' => $menu->post_content ) ) );
	update_option( 'aloha_shell_mega_menu', $settings );
	update_option( 'aloha_shell_mega_version', $version );
}
