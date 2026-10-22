<?php
/** Editorial presentation of the existing Core Navigation; no parallel link store. */
defined( 'ABSPATH' ) || exit;

/** Discover active groups in the current Primary post, preserving its labels and order. */
function aloha_mega_sections() {
	$post = get_post( aloha_shell_menu_id( 'primary' ) );
	$sections = array();
	foreach ( $post ? parse_blocks( $post->post_content ) : array() as $block ) {
		if ( ! aloha_mega_is_section( $block ) ) { continue; }
		$key = aloha_mega_key( $block['attrs'] ?? array() );
		if ( $key && ! isset( $sections[ $key ] ) ) {
			$sections[ $key ] = wp_strip_all_tags( $block['attrs']['label'] ?? '' );
		}
	}
	return $sections;
}

function aloha_mega_is_section( $block ) {
	if ( 'core/navigation-submenu' !== ( $block['blockName'] ?? '' ) ) { return false; }
	$has_links = function ( $children ) use ( &$has_links ) {
		foreach ( $children as $child ) {
			if ( 'core/home-link' === $child['blockName'] ) { return true; }
			if ( in_array( $child['blockName'], array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) && ! empty( $child['attrs']['url'] ) ) { return true; }
			if ( $has_links( $child['innerBlocks'] ?? array() ) ) { return true; }
		}
		return false;
	};
	return $has_links( $block['innerBlocks'] ?? array() );
}

function aloha_mega_key( $attrs ) {
	foreach ( preg_split( '/\s+/', $attrs['className'] ?? '' ) as $class ) {
		if ( preg_match( '/^aloha-mega-(rentals|island|homeowners|about|section-[a-f0-9-]{36})$/D', $class, $match ) ) { return $match[1]; }
	}
	return '';
}

/** Assign IDs only when saving Primary or explicitly preparing the settings screen/install. */
function aloha_mega_identify_blocks( $blocks ) {
	$seen = array();
	$changed = false;
	foreach ( $blocks as &$block ) {
		if ( ! aloha_mega_is_section( $block ) ) { continue; }
		$key = aloha_mega_key( $block['attrs'] ?? array() );
		if ( $key && ! isset( $seen[ $key ] ) ) { $seen[ $key ] = true; continue; }
		$classes = preg_split( '/\s+/', $block['attrs']['className'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		$classes = array_filter( $classes, fn( $class ) => ! aloha_mega_key( array( 'className' => $class ) ) );
		do { $key = 'section-' . wp_generate_uuid4(); } while ( isset( $seen[ $key ] ) );
		$classes[] = 'aloha-mega-' . $key;
		$block['attrs']['className'] = implode( ' ', $classes );
		$seen[ $key ] = true; $changed = true;
	}
	unset( $block );
	return $changed ? $blocks : null;
}

add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( 'wp_navigation' !== $data['post_type'] || absint( $postarr['ID'] ?? 0 ) !== aloha_shell_menu_id( 'primary' ) ) { return $data; }
	// The v1 installer must attach the legacy keys before generic IDs are assigned.
	if ( ! get_option( 'aloha_shell_mega_version' ) ) { return $data; }
	$blocks = aloha_mega_identify_blocks( parse_blocks( wp_unslash( $data['post_content'] ) ) );
	if ( null !== $blocks ) { $data['post_content'] = wp_slash( serialize_blocks( $blocks ) ); }
	return $data;
}, 10, 2 );

function aloha_mega_prepare_primary() {
	$post = get_post( aloha_shell_menu_id( 'primary' ) );
	if ( ! $post ) { return; }
	$blocks = aloha_mega_identify_blocks( parse_blocks( $post->post_content ) );
	if ( null !== $blocks ) {
		wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => serialize_blocks( $blocks ) ) ) );
	}
}

add_action( 'admin_init', function () {
	if ( 'aloha-shell' === ( $_GET['page'] ?? '' ) && current_user_can( 'manage_options' ) ) { aloha_mega_install(); }
}, 5 );

function aloha_mega_defaults() {
	return array(
		'rentals' => array( 'heading' => 'Find your island stay', 'description' => 'Explore vacation homes along the Kohala Coast. Make room for a slower kind of getaway.', 'cta_label' => 'Explore vacation rentals', 'image_id' => 0 ),
		'island' => array( 'heading' => 'A little closer to paradise', 'description' => 'Discover the places, communities and experiences that make the Big Island special.', 'cta_label' => 'Discover the Big Island', 'image_id' => 0 ),
		'homeowners' => array( 'heading' => 'Your home. Our care.', 'description' => 'Thoughtful property management, local expertise and a personal approach to your island home.', 'cta_label' => 'For homeowners', 'image_id' => 0 ),
		'about' => array( 'heading' => 'A warm island welcome', 'description' => 'Meet the people behind Aloha Hello and find helpful information for your stay.', 'cta_label' => 'Get to know us', 'image_id' => 0 ),
	);
}

/** Explicit, once-only upgrade. Match unmodified seed items, never guess positions. */
function aloha_mega_install() {
	add_option( 'aloha_shell_mega_menu', aloha_mega_defaults(), '', false );
	if ( get_option( 'aloha_shell_mega_version' ) ) { aloha_mega_prepare_primary(); return; }
	$post = get_post( aloha_shell_menu_id( 'primary' ) );
	if ( ! $post ) { return; }
	$blocks = parse_blocks( $post->post_content );
	$registry = aloha_destinations();
	foreach ( $registry['primary'] as $entry ) {
		if ( empty( $entry[2] ) ) { continue; }
		list( $key, $label ) = $entry;
		$found = array();
		foreach ( $blocks as $index => $block ) {
			if ( 'core/navigation-submenu' !== $block['blockName'] ) { continue; }
			$a = $block['attrs'];
			if ( $key === aloha_mega_key( $a ) || ( ! aloha_mega_key( $a ) && $label === ( $a['label'] ?? '' ) && in_array( $a['url'] ?? '', array( $registry['destinations'][ $key ][1], home_url( $registry['destinations'][ $key ][1] ) ), true ) ) ) { $found[] = $index; }
		}
		if ( 1 !== count( $found ) ) { continue; } // Do not guess legacy ownership; unmatched sections receive independent UUIDs below.
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
	if ( ! is_wp_error( $result ) ) { update_option( 'aloha_shell_mega_version', 1 ); aloha_mega_prepare_primary(); }
}

function aloha_mega_image_valid( $id ) {
	return $id && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) && in_array( get_post_mime_type( $id ), array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif' ), true );
}

function aloha_mega_sanitize( $input ) {
	$stored = get_option( 'aloha_shell_mega_menu', array() );
	$result = is_array( $stored ) ? $stored : array();
	if ( ! is_array( $input ) ) { return $result; }
	foreach ( aloha_mega_sections() as $key => $label ) {
		if ( ! isset( $input[ $key ] ) || ! is_array( $input[ $key ] ) ) { continue; }
		$value = $input[ $key ];
		foreach ( array( 'heading', 'description', 'cta_label' ) as $field ) {
			$text = is_string( $value[ $field ] ?? null ) ? $value[ $field ] : '';
			$result[ $key ][ $field ] = 'description' === $field ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
		}
		$id = is_scalar( $value['image_id'] ?? null ) ? absint( $value['image_id'] ) : 0;
		$result[ $key ]['image_id'] = aloha_mega_image_valid( $id ) ? $id : 0;
		$result[ $key ]['image_left'] = ! empty( $value['image_left'] );
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
	$media = '';
	if ( aloha_mega_image_valid( absint( $s['image_id'] ?? 0 ) ) ) {
		$image = wp_get_attachment_image( absint( $s['image_id'] ), 'medium_large', false, array( 'class' => 'aloha-mega-image', 'sizes' => '(min-width: 1280px) 360px, 1px', 'loading' => 'lazy', 'decoding' => 'async', 'fetchpriority' => 'low' ) );
		// A template defers even lazy image discovery until this desktop panel first opens.
		if ( $image ) { $media = '<div class="aloha-mega-photo"><template class="aloha-mega-media">' . $image . '</template></div>'; }
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
	if ( ! $editorial && ! $media ) { return $html; }
	$text = $editorial ? '<div class="aloha-mega-copy">' . $editorial . '</div>' : '';
	$class = 'aloha-mega-editorial' . ( $media && $text && ! empty( $s['image_left'] ) ? ' has-image-left' : '' );
	return preg_replace_callback( '/<ul\b[^>]*>/', function ( $match ) use ( $media, $text, $class ) { return $match[0] . '<li class="' . esc_attr( $class ) . '"><div class="aloha-mega-feature' . ( str_contains( $class, 'has-image-left' ) ? ' has-image-left' : '' ) . '">' . $media . $text . '</div></li>'; }, $html, 1 );
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


// A true no-JS fallback, never applied during a normal (possibly slow) JS startup.
add_action( 'wp_head', function () {
	?><noscript><style>
	nav.aloha-primary-nav .wp-block-navigation__responsive-container { display:block!important; position:static!important; overflow:visible!important; padding:0!important; }
	nav.aloha-primary-nav .wp-block-navigation__responsive-container-content { padding:0!important; }
	nav.aloha-primary-nav .wp-block-navigation__container { flex-direction:column!important; align-items:stretch!important; }
	nav.aloha-primary-nav .has-child { display:block; }
	nav.aloha-primary-nav:not(.aloha-nav-ready) .has-child > ul { display:flex!important; flex-direction:column; position:static!important; visibility:visible!important; opacity:1!important; height:auto!important; width:100%!important; min-width:0!important; overflow:visible!important; transform:none!important; }
	nav.aloha-primary-nav button, nav.aloha-primary-nav .aloha-mega-editorial { display:none!important; }
	.wp-block-template-part:has(> .aloha-site-header) { position:static; }
	</style></noscript><?php
}, 30 );

add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script( 'aloha-mega-editor', get_theme_file_uri( 'assets/js/mega-menu-editor.js' ), array( 'wp-hooks', 'wp-data', 'wp-element' ), (string) filemtime( get_theme_file_path( 'assets/js/mega-menu-editor.js' ) ), true );
	wp_localize_script( 'aloha-mega-editor', 'alohaMegaEditor', array( 'primaryId' => aloha_shell_menu_id( 'primary' ) ) );
} );
