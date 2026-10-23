<?php
/** Editorial cards in block attributes, not a shared content model. */
defined( 'ABSPATH' ) || exit;
add_action( 'init', function () {
	wp_register_style( 'aloha-beaches', get_theme_file_uri( 'assets/css/beaches.css' ), array( 'aloha-foundation' ), (string) filemtime( get_theme_file_path( 'assets/css/beaches.css' ) ) );
	wp_register_script( 'aloha-beaches', get_theme_file_uri( 'assets/js/beaches.js' ), array(), (string) filemtime( get_theme_file_path( 'assets/js/beaches.js' ) ), true );
	wp_register_script( 'aloha-beaches-editor', get_theme_file_uri( 'blocks/beaches/editor.js' ), array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data' ), (string) filemtime( get_theme_file_path( 'blocks/beaches/editor.js' ) ), true );
	register_block_type( get_theme_file_path( 'blocks/beaches' ) );
} );
add_action( 'after_setup_theme', function () { add_editor_style( 'assets/css/beaches.css' ); } );
function aloha_beaches_url( $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) || str_starts_with( trim( $value ), '#' ) ) { return ''; }
	return esc_url_raw( $value, array( 'http', 'https' ) );
}
function aloha_beaches_focal( $value ) {
	$point = array();
	foreach ( array( 'x', 'y' ) as $axis ) {
		$number = is_array( $value ) ? ( $value[$axis] ?? .5 ) : .5;
		$point[$axis] = is_numeric( $number ) && is_finite( (float) $number ) ? max( 0, min( 1, (float) $number ) ) : .5;
	}
	return $point;
}
function aloha_beaches_image_id( $value ) {
	if ( ! is_int( $value ) || $value < 1 || ! wp_attachment_is_image( $value ) || 'trash' === get_post_status( $value ) ) { return 0; }
	$file = get_attached_file( $value );
	if ( $file && ! wp_is_stream( $file ) && ! file_exists( $file ) ) { return 0; }
	return $value;
}
function aloha_beaches_cards( $values ) {
	$cards = array();
	foreach ( is_array( $values ) ? $values : array() as $value ) {
		if ( ! is_array( $value ) ) { continue; }
		$title = sanitize_text_field( is_string( $value['title'] ?? '' ) ? ( $value['title'] ?? '' ) : '' );
		$description = wp_kses_post( is_string( $value['description'] ?? '' ) ? ( $value['description'] ?? '' ) : '' );
		$image = aloha_beaches_image_id( $value['imageId'] ?? 0 );
		if ( '' === $title && '' === trim( wp_strip_all_tags( $description ) ) && ! $image ) { continue; }
		$cards[] = array( 'title' => $title, 'description' => $description, 'imageId' => $image, 'url' => aloha_beaches_url( $value['url'] ?? '' ), 'focalPoint' => aloha_beaches_focal( $value['focalPoint'] ?? array() ) );
	}
	return $cards;
}
function aloha_beaches_render( $attrs ) {
	$cards = aloha_beaches_cards( $attrs['cards'] ?? array() );
	if ( ! $cards ) { return ''; }
	$count = count( $cards ); $carousel = $count > 4; $id = wp_unique_id( 'aloha-beaches-' );
	$wrapper = get_block_wrapper_attributes( array( 'class' => 'aloha-beaches' . ( $carousel ? ' is-carousel' : '' ), 'data-aloha-beaches' => '' ) );
	$html = '<div ' . $wrapper . '><div class="aloha-beaches-track" id="' . esc_attr( $id ) . '"' . ( $carousel ? ' tabindex="0" role="region" aria-roledescription="carousel" aria-label="Beaches, ' . $count . ' cards"' : '' ) . '>';
	foreach ( $cards as $card ) {
		$html .= '<article class="aloha-beaches-card' . ( $card['imageId'] ? '' : ' is-text-only' ) . '">';
		if ( $card['imageId'] ) {
			$position = number_format( $card['focalPoint']['x'] * 100, 2, '.', '' ) . '% ' . number_format( $card['focalPoint']['y'] * 100, 2, '.', '' ) . '%';
			$image = wp_get_attachment_image( $card['imageId'], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 1280px) 282px, (min-width: 1024px) 24vw, (min-width: 600px) 46vw, 90vw', 'style' => 'object-position:' . $position ) );
			$html .= '<div class="aloha-beaches-image">' . ( $card['url'] ? '<a href="' . esc_url( $card['url'] ) . '" aria-label="' . esc_attr( $card['title'] ?: 'Read beach details' ) . '">' . $image . '</a>' : $image ) . '</div>';
		}
		if ( $card['title'] ) { $html .= '<h3>' . ( $card['url'] ? '<a href="' . esc_url( $card['url'] ) . '">' . esc_html( $card['title'] ) . '</a>' : esc_html( $card['title'] ) ) . '</h3>'; }
		if ( $card['description'] ) { $html .= '<div class="aloha-beaches-description">' . wp_kses_post( $card['description'] ) . '</div>'; }
		if ( $card['url'] && ! $card['title'] ) { $html .= '<a class="aloha-beaches-details" href="' . esc_url( $card['url'] ) . '">Read beach details</a>'; }
		$html .= '</article>';
	}
	$html .= '</div>';
	if ( $carousel ) {
		$html .= '<div class="aloha-beaches-controls" hidden><button type="button" data-beaches-direction="-1" aria-label="Previous beaches" aria-controls="' . esc_attr( $id ) . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button><span class="aloha-beaches-status" role="status" aria-live="polite"></span><button type="button" data-beaches-direction="1" aria-label="Next beaches" aria-controls="' . esc_attr( $id ) . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg></button></div>';
	}
	return $html . '</div>';
}
