<?php
/** Explicit local/test seed helpers. Never loaded by the theme or a web request. */
namespace Aloha\AreaReference;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) { exit( 'Local WP-CLI only.' ); }

function import_media( $parent ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$files = array(
		'hero' => array( '6c330ccef195df702ef6d2bc01cf292a.jpg', 'Palm-lined shoreline at sunset' ),
		'bay' => array( 'c804f2a8d11de7a68cfd784391470685.jpg', 'Bay with palms and a catamaran' ),
		'golf' => array( 'bf6a202faf164ed8cbc957692f61bb3e.jpg', 'Golfers on a coastal fairway' ),
		'whale' => array( '3a3363e7e4b10fd76c2d3ce73581d525.jpg', 'Whale above the ocean at sunset' ),
		'manta' => array( 'f50d3eed09b990a66fd9c0dac8f1efc6.jpg', 'Manta ray underwater' ),
		'luau' => array( 'c395525affc5b2c3b33d5d2fb150a932.jpg', 'An outdoor performance at sunset' ),
		'food' => array( '6c1f0acb1af55c964bc974cb56e2ea57.jpg', 'A bowl of food' ),
		'lava' => array( 'bffda349bb987af855fd76ae762df08a.jpg', 'Volcanic landscape' ),
	);
	$ids = array();
	foreach ( $files as $key => list( $name, $description ) ) {
		$source = '/var/www/aloha-area-media/' . $name;
		if ( ! is_readable( $source ) ) { throw new \RuntimeException( 'Missing local design-reference media: ' . $name ); }
		$tmp = wp_tempnam( $name );
		if ( ! copy( $source, $tmp ) ) { throw new \RuntimeException( 'Could not prepare reference image.' ); }
		$id = media_handle_sideload( array( 'name' => 'area-reference-' . $key . '.jpg', 'tmp_name' => $tmp ), $parent, 'Design reference — ' . $description );
		if ( is_wp_error( $id ) ) { @unlink( $tmp ); throw new \RuntimeException( $id->get_error_message() ); }
		$ids[ $key ] = $id;
		update_post_meta( $id, '_wp_attachment_image_alt', $description . ' — design reference photograph, location not verified' );
		update_post_meta( $id, '_aloha_reference_source', 'Design v2 — Area Mauna Lani-html.zip/assets/' . $name );
		wp_update_post( array( 'ID' => $id, 'post_excerpt' => 'Design reference photograph — location and usage rights require editorial review.', 'post_content' => 'Local editorial placeholder from the supplied Area design export. Replace or approve before production.' ) );
	}
	return $ids;
}
function image_block( $id ) {
	$url = wp_get_attachment_image_url( $id, 'large' );
	$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
	return '<!-- wp:image ' . wp_json_encode( array( 'id' => $id, 'sizeSlug' => 'large', 'linkDestination' => 'none' ) ) . ' --><figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . (int) $id . '"/></figure><!-- /wp:image -->';
}
function content( $media ) {
	$html = aloha_area_pattern_content( 'area-start' );
	$html = str_replace( array( 'What this area <em>is like</em>', 'The area <em>in pictures</em>', 'Beaches <em>nearby</em>', 'Things to see <em>and do</em>', 'Staying here: <em>what to know</em>' ), array( 'What Mauna Lani <em>is like</em>', 'Mauna Lani <em>in pictures</em>', 'Beaches near <em>Mauna Lani</em>', 'Things to see and do <em>in Mauna Lani</em>', 'Staying in Mauna Lani: <em>what to know</em>' ), $html );
	$html = str_replace( 'Editorial placeholder: describe the landscape, atmosphere and everyday rhythm in your own words.', 'Mauna Lani, at an island pace. This local editorial draft follows the approved design composition; the copy and reference photography still need review.', $html );
	$html = str_replace( 'Explain who this area suits, and what visitors should consider before choosing a home.', 'A slower morning, time by the shoreline, and an evening back at your own place. Use this introduction to tell guests what a stay here feels like.', $html );
	$html = str_replace( 'Confirm local access, seasonal advice and any factual claims before publishing.', 'Editorial placeholder: confirm beach access, seasonal advice and property-specific amenities. No home counts, prices or travel times are asserted here.', $html );
	$beaches = array( 'Pauoa Bay · Beach Club', 'Makaiwa Bay', 'Beach 69 (Waialea Bay)', '49 Black Sand Beach' );
	$activities = array( 'Kalahuipua‘a fishponds &amp; historic trail', 'Puakō petroglyphs', 'Golf on lava-lined fairways', 'Night snorkel with manta rays', 'Whale watching', 'Sunset dinner &amp; lū‘au', 'Fresh poke &amp; local food', 'Volcanoes National Park' );
	foreach ( $beaches as $title ) { $html = preg_replace( '/Beach to explore/', $title, $html, 1 ); }
	foreach ( $activities as $title ) { $html = preg_replace( '/An island experience/', $title, $html, 1 ); }
	$blocks = parse_blocks( $html ); $photo = 0;
	$walk = function ( &$blocks ) use ( &$walk, $media, &$photo ) {
		foreach ( $blocks as &$block ) {
			if ( 'core/gallery' === $block['blockName'] ) {
				$images = '';
				foreach ( array( 'bay', 'golf', 'hero', 'manta', 'whale' ) as $key ) { $images .= image_block( $media[ $key ] ); }
				$gallery = '<!-- wp:gallery {"columns":3,"linkTo":"none","className":"aloha-area-gallery"} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped aloha-area-gallery">' . $images . '</figure><!-- /wp:gallery -->';
				$block = parse_blocks( $gallery )[0];
				continue;
			}
			if ( str_contains( $block['attrs']['className'] ?? '', 'aloha-area-photo' ) ) {
				$map = array( 0 => 'hero', 6 => 'golf', 7 => 'manta', 8 => 'whale', 9 => 'luau', 10 => 'food', 11 => 'lava' );
				if ( isset( $map[ $photo ] ) ) {
					$block['innerBlocks'][0] = parse_blocks( image_block( $media[ $map[ $photo ] ] ) )[0];
				}
				++$photo;
			}
			$walk( $block['innerBlocks'] );
		}
	};
	$walk( $blocks );
	return serialize_blocks( $blocks );
}
