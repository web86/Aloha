<?php
/** Editorial presentation of the existing Core Navigation; no parallel link store. */
defined( 'ABSPATH' ) || exit;

function aloha_mega_sections() {
	return array( 'rentals' => 'Vacation Rentals', 'island' => 'Big Island', 'homeowners' => 'Homeowners', 'about' => 'About' );
}

function aloha_mega_defaults() {
	return array(
		'rentals' => array( 'heading' => 'Find your island stay', 'description' => 'Explore vacation homes along the Kohala Coast. Make room for a slower kind of getaway.', 'cta_label' => 'Explore vacation rentals', 'image_id' => 0 ),
		'island' => array( 'heading' => 'A little closer to paradise', 'description' => 'Discover the places, communities and experiences that make the Big Island special.', 'cta_label' => 'Discover the Big Island', 'image_id' => 0 ),
		'homeowners' => array( 'heading' => 'Your home. Our care.', 'description' => 'Thoughtful property management, local expertise and a personal approach to your island home.', 'cta_label' => 'For homeowners', 'image_id' => 0 ),
		'about' => array( 'heading' => 'A warm island welcome', 'description' => 'Meet the people behind Aloha Hello and find helpful information for your stay.', 'cta_label' => 'Get to know us', 'image_id' => 0 ),
	);
}

function aloha_mega_key( $attrs ) {
	foreach ( aloha_mega_sections() as $key => $label ) {
		if ( in_array( 'aloha-mega-' . $key, preg_split( '/\s+/', $attrs['className'] ?? '' ), true ) ) { return $key; }
	}
	return '';
}

/** Explicit, once-only upgrade. Match unmodified seed items, never guess positions. */
function aloha_mega_install() {
	add_option( 'aloha_shell_mega_menu', aloha_mega_defaults(), '', false );
	if ( get_option( 'aloha_shell_mega_version' ) ) { return; }
	$post = get_post( aloha_shell_menu_id( 'primary' ) );
	if ( ! $post ) { return; }
	$blocks = parse_blocks( $post->post_content );
	$registry = aloha_destinations();
	foreach ( aloha_mega_sections() as $key => $label ) {
		$found = array();
		foreach ( $blocks as $index => $block ) {
			if ( 'core/navigation-submenu' !== $block['blockName'] ) { continue; }
			$a = $block['attrs'];
			if ( $key === aloha_mega_key( $a ) || ( ! aloha_mega_key( $a ) && $label === ( $a['label'] ?? '' ) && in_array( $a['url'] ?? '', array( $registry['destinations'][ $key ][1], home_url( $registry['destinations'][ $key ][1] ) ), true ) ) ) { $found[] = $index; }
		}
		if ( 1 !== count( $found ) ) { continue; } // Edited/ambiguous items require the documented editor class.
		$i = $found[0];
		if ( ! aloha_mega_key( $blocks[ $i ]['attrs'] ) ) {
			$blocks[ $i ]['attrs']['className'] = trim( ( $blocks[ $i ]['attrs']['className'] ?? '' ) . ' aloha-mega-' . $key );
		}
		if ( 'rentals' !== $key ) { continue; }
		$step = $registry['destinations']['step-free'];
		$duplicate = false;
		$scan = function ( $items ) use ( &$scan, &$duplicate, $step ) {
			foreach ( $items as $item ) {
				$a = $item['attrs'];
				if ( in_array( $a['url'] ?? '', array( $step[1], home_url( $step[1] ) ), true ) || $step[0] === ( $a['label'] ?? '' ) ) { $duplicate = true; }
				$scan( $item['innerBlocks'] );
			}
		};
		$scan( $blocks[ $i ]['innerBlocks'] );
		if ( ! $duplicate ) {
			$blocks[ $i ]['innerBlocks'][] = parse_blocks( '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $step[0], 'url' => $step[1], 'kind' => 'custom' ) ) . ' /-->' )[0];
			$blocks[ $i ]['innerContent'][] = null;
		}
	}
	$result = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => serialize_blocks( $blocks ) ) ), true );
	if ( ! is_wp_error( $result ) ) { update_option( 'aloha_shell_mega_version', 1 ); }
}

function aloha_mega_image_valid( $id ) {
	return $id && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) && in_array( get_post_mime_type( $id ), array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif' ), true );
}

function aloha_mega_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$result = array();
	foreach ( aloha_mega_sections() as $key => $label ) {
		$value = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
		foreach ( array( 'heading', 'description', 'cta_label' ) as $field ) {
			$text = is_string( $value[ $field ] ?? null ) ? $value[ $field ] : '';
			$result[ $key ][ $field ] = 'description' === $field ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
		}
		$id = is_scalar( $value['image_id'] ?? null ) ? absint( $value['image_id'] ) : 0;
		$result[ $key ]['image_id'] = aloha_mega_image_valid( $id ) ? $id : 0;
	}
	return $result;
}

// Keep Core's own links, labels, targets, rel and child rendering. Add only editorial content.
add_filter( 'render_block_core/navigation-submenu', function ( $html, $block ) {
	$key = aloha_mega_key( $block['attrs'] );
	if ( ! $key || ! str_contains( $html, '<ul ' ) ) { return $html; }
	$settings = get_option( 'aloha_shell_mega_menu', array() );
	$s = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
	$editorial = '';
	if ( aloha_mega_image_valid( absint( $s['image_id'] ?? 0 ) ) ) {
		$image = wp_get_attachment_image( absint( $s['image_id'] ), 'medium_large', false, array( 'class' => 'aloha-mega-image', 'sizes' => '(min-width: 1280px) 360px, 1px', 'loading' => 'lazy', 'decoding' => 'async', 'fetchpriority' => 'low' ) );
		// A template defers even lazy image discovery until this desktop panel first opens.
		if ( $image ) { $editorial .= '<template class="aloha-mega-media">' . $image . '</template>'; }
	}
	if ( ! empty( $s['heading'] ) ) { $editorial .= '<h2 class="aloha-mega-heading">' . esc_html( $s['heading'] ) . '</h2>'; }
	if ( ! empty( $s['description'] ) ) { $editorial .= '<p class="aloha-mega-description">' . esc_html( $s['description'] ) . '</p>'; }
	if ( ! empty( $s['cta_label'] ) && ! empty( $block['attrs']['url'] ) ) {
		// Copy the current Core parent anchor (including the local preview marker).
		if ( preg_match( '/<a\b[^>]*>/', $html, $anchor ) ) {
			$p = new WP_HTML_Tag_Processor( $anchor[0] ); $p->next_tag();
			$p->set_attribute( 'class', 'aloha-mega-cta' ); $p->remove_attribute( 'aria-current' );
			$editorial .= $p->get_updated_html() . esc_html( $s['cta_label'] ) . '<span aria-hidden="true"> ↗</span></a>';
		}
	}
	if ( ! $editorial ) { return $html; }
	return preg_replace_callback( '/<ul\b[^>]*>/', function ( $match ) use ( $editorial ) { return $match[0] . '<li class="aloha-mega-editorial">' . $editorial . '</li>'; }, $html, 1 );
}, 15, 2 );

// One owner for submenu state. Core still owns its responsive dialog, trap and scroll lock.
add_filter( 'render_block_core/navigation', function ( $html, $block ) {
	if ( ! str_contains( $block['attrs']['className'] ?? '', 'aloha-primary-nav' ) || is_admin() ) { return $html; }
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag() ) {
		if ( ! $p->has_class( 'has-child' ) && ! $p->has_class( 'wp-block-navigation-submenu__toggle' ) && ! $p->has_class( 'wp-block-navigation__submenu-container' ) ) { continue; }
		foreach ( $p->get_attribute_names_with_prefix( 'data-wp-' ) ?? array() as $attribute ) { $p->remove_attribute( $attribute ); }
		$p->remove_attribute( 'tabindex' );
	}
	return $p->get_updated_html();
}, 30, 2 );
