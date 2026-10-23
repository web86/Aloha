<?php
/** Reusable attachment-ID gallery. No content model or stored media URL copies. */
defined( 'ABSPATH' ) || exit;
add_action( 'init', function () {
	foreach ( array( 'media-gallery', 'media-dialog' ) as $name ) {
		wp_register_style( 'aloha-' . $name, get_theme_file_uri( 'assets/css/' . $name . '.css' ), array( 'aloha-foundation' ), (string) filemtime( get_theme_file_path( 'assets/css/' . $name . '.css' ) ) );
	}
	wp_register_script( 'aloha-media-dialog', get_theme_file_uri( 'assets/js/media-dialog.js' ), array(), (string) filemtime( get_theme_file_path( 'assets/js/media-dialog.js' ) ), true );
	wp_register_script( 'aloha-media-gallery-editor', get_theme_file_uri( 'blocks/media-gallery/editor.js' ), array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data', 'wp-api-fetch', 'wp-html-entities' ), (string) filemtime( get_theme_file_path( 'blocks/media-gallery/editor.js' ) ), true );
	register_block_type( get_theme_file_path( 'blocks/media-gallery' ) );
} );
// Editor canvas uses the same mosaic geometry, including when the block is inserted in a Page.
add_action( 'after_setup_theme', function () { add_editor_style( 'assets/css/media-gallery.css' ); } );
function aloha_media_gallery_ids( $values ) {
	return array_values( array_unique( array_filter( (array) $values, fn( $id ) => is_int( $id ) && $id > 0 ) ) );
}
function aloha_media_gallery_photos( $ids ) {
	$photos = array();
	foreach ( aloha_media_gallery_ids( $ids ) as $id ) {
		if ( ! wp_attachment_is_image( $id ) || 'trash' === get_post_status( $id ) ) { continue; }
		$file = get_attached_file( $id );
		// Local attachments with a removed file should not produce a broken tile.
		if ( $file && ! wp_is_stream( $file ) && ! file_exists( $file ) ) { continue; }
		$full = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $full || $full[1] < 1 || $full[2] < 1 ) { continue; }
		$caption = wp_strip_all_tags( wp_get_attachment_caption( $id ) ?: '' );
		// Keep the seed's editorial provenance in the Library, as in Hero polish.
		if ( get_post_meta( $id, '_aloha_reference_source', true ) && 'Design reference photograph — location and usage rights require editorial review.' === $caption ) { $caption = ''; }
		$photos[] = array( 'id' => $id, 'url' => $full[0], 'width' => $full[1], 'height' => $full[2],
			'srcset' => wp_get_attachment_image_srcset( $id, 'full' ) ?: '',
			'alt' => wp_strip_all_tags( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			'caption' => $caption );
	}
	return $photos;
}
function aloha_media_gallery_render( $attrs, $block ) {
	$photos = aloha_media_gallery_photos( $attrs['ids'] ?? array() );
	$count = count( $photos );
	if ( ! $count ) { return ''; }
	$id = wp_unique_id( 'aloha-gallery-' );
	$post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
	$title = get_the_title( $post_id ) ?: 'Photo gallery';
	$film = array( 'url' => '', 'label' => '' );
	if ( 'area' === get_post_type( $post_id ) ) {
		$film = array( 'url' => get_post_meta( $post_id, 'aloha_area_film_url', true ), 'label' => get_post_meta( $post_id, 'aloha_area_film_label', true ) );
	}
	// Future integrations may supply a film; there is intentionally no second Area URL field.
	$film = apply_filters( 'aloha_media_gallery_film', $film, $post_id, $block );
	$film_id = function_exists( '\Aloha\Content\area_youtube_id' ) ? \Aloha\Content\area_youtube_id( $film['url'] ?? '' ) : '';
	$film_label = sanitize_text_field( $film['label'] ?? '' ) ?: 'Watch the area film · 2 min';
	$wrapper = get_block_wrapper_attributes( array( 'class' => 'aloha-media-gallery', 'data-aloha-gallery' => $id, 'data-aloha-media-title' => $title . ' — photos' ) );
	$html = '<div ' . $wrapper . '><div class="aloha-gallery-mosaic" data-count="' . min( 5, $count ) . '">';
	foreach ( array_slice( $photos, 0, 5 ) as $index => $photo ) {
		$html .= '<figure class="aloha-gallery-tile"><a class="aloha-gallery-photo" href="' . esc_url( $photo['url'] ) . '" data-aloha-photo="' . $index . '" aria-label="' . esc_attr( 'Open photo ' . ( $index + 1 ) . ' of ' . $count . ( $photo['alt'] ? ': ' . $photo['alt'] : '' ) ) . '" aria-haspopup="dialog">';
		$html .= wp_get_attachment_image( $photo['id'], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => 0 === $index ? '(min-width: 1280px) 600px, (min-width: 768px) 50vw, 100vw' : '(min-width: 1280px) 300px, (min-width: 768px) 25vw, 50vw' ) ) . '</a>';
		if ( 0 === $index && $film_id ) {
			$html .= '<button class="aloha-gallery-film" type="button" data-aloha-youtube="' . esc_attr( $film_id ) . '" data-aloha-media-title="' . esc_attr( $title . ' — area film' ) . '" aria-haspopup="dialog"><span aria-hidden="true">▶</span>' . esc_html( $film_label ) . '</button>';
		}
		if ( 4 === $index ) {
			$label = $count > 5 ? '+' . ( $count - 5 ) . ' photos' : 'View all photos (' . $count . ')';
			$html .= '<a class="aloha-gallery-more" href="#' . esc_attr( $id . '-photos' ) . '" data-aloha-photo="0" aria-label="' . esc_attr( 'View all ' . $count . ' photos' ) . '" aria-haspopup="dialog">' . esc_html( $label ) . '</a>';
		}
		$html .= '</figure>';
	}
	$html .= '</div>';
	$html .= '<script type="application/json" class="aloha-gallery-data">' . wp_json_encode( $photos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	$html .= '<noscript><div id="' . esc_attr( $id . '-photos' ) . '"><p>All photos</p><ol>';
	foreach ( $photos as $index => $photo ) { $html .= '<li><a href="' . esc_url( $photo['url'] ) . '">' . esc_html( $photo['alt'] ?: 'Photo ' . ( $index + 1 ) ) . '</a></li>'; }
	return $html . '</ol></div></noscript></div>';
}
