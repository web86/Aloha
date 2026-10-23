<?php
/** Explicit local CLI migration helper. Never loaded by the theme or a save hook. */
function aloha_area_gallery_migration_plan( $body ) {
	$old = array(); $new = array();
	$walk = function ( $blocks, $in_section = false ) use ( &$walk, &$old, &$new ) {
		foreach ( $blocks as $block ) {
			$section = $in_section || in_array( 'aloha-area-gallery-section', explode( ' ', $block['attrs']['className'] ?? '' ), true );
			if ( $section && 'core/gallery' === $block['blockName'] && in_array( 'aloha-area-gallery', explode( ' ', $block['attrs']['className'] ?? '' ), true ) ) { $old[] = $block; }
			if ( $section && 'aloha/media-gallery' === $block['blockName'] ) { $new[] = $block; }
			$walk( $block['innerBlocks'], $section );
		}
	};
	$walk( parse_blocks( $body ) );
	if ( ! $old && 1 === count( $new ) ) { return array( 'changed' => false, 'content' => $body, 'ids' => $new[0]['attrs']['ids'] ?? array() ); }
	if ( 1 !== count( $old ) || $new ) { throw new RuntimeException( 'Expected exactly one legacy gallery in the Area gallery section. Replace it manually in Gutenberg.' ); }
	$ids = array();
	foreach ( $old[0]['innerBlocks'] as $image ) {
		$id = $image['attrs']['id'] ?? 0;
		if ( 'core/image' !== $image['blockName'] || ! is_int( $id ) || ! aloha_media_gallery_photos( array( $id ) ) ) { throw new RuntimeException( 'Unsupported or missing gallery image; manual review required.' ); }
		$p = new WP_HTML_Tag_Processor( $image['innerHTML'] );
		if ( ! $p->next_tag( 'img' ) ) { throw new RuntimeException( 'Image markup missing.' ); }
		$alt = html_entity_decode( (string) $p->get_attribute( 'alt' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $alt !== (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) { throw new RuntimeException( 'Inline alt differs from Media Library. Resolve it manually before migration.' ); }
		if ( preg_match( '~<figcaption\b[^>]*>(.*?)</figcaption>~s', $image['innerHTML'], $m ) && trim( wp_strip_all_tags( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) !== trim( wp_strip_all_tags( wp_get_attachment_caption( $id ) ?: '' ) ) ) { throw new RuntimeException( 'Inline caption differs from Media Library. Resolve it manually before migration.' ); }
		$ids[] = $id;
	}
	if ( ! $ids || count( $ids ) !== count( array_unique( $ids ) ) ) { throw new RuntimeException( 'Empty or duplicate-image legacy gallery needs manual review.' ); }
	// Replace the exact original byte range; do not reserialize any surrounding blocks.
	preg_match_all( '~<!-- wp:gallery\b.*?<!-- /wp:gallery -->~s', $body, $matches, PREG_OFFSET_CAPTURE );
	$candidates = array();
	foreach ( $matches[0] as $match ) { $parsed = parse_blocks( $match[0] ); if ( ( $parsed[0] ?? null ) === $old[0] ) { $candidates[] = $match; } }
	if ( 1 !== count( $candidates ) ) { throw new RuntimeException( 'Ambiguous original gallery byte range; manual replacement required.' ); }
	list( $raw, $offset ) = $candidates[0];
	$replacement = serialize_block( array( 'blockName' => 'aloha/media-gallery', 'attrs' => array( 'ids' => $ids ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	return array( 'changed' => true, 'ids' => $ids, 'offset' => $offset, 'length' => strlen( $raw ), 'replacement' => $replacement, 'content' => substr_replace( $body, $replacement, $offset, strlen( $raw ) ) );
}
function aloha_area_gallery_migrate( $id, $expected_body ) {
	$body = get_post_field( 'post_content', $id );
	$plan = aloha_area_gallery_migration_plan( $body );
	if ( ! $plan['changed'] ) { return array( 'changed' => false, 'ids' => $plan['ids'] ); }
	if ( $body !== $expected_body ) { throw new RuntimeException( 'Backup does not match the current body. Refusing a stale migration.' ); }
	$force = function ( $check, $last, $post ) use ( $id ) { return (int) $post->ID === (int) $id ? false : $check; };
	add_filter( 'wp_save_post_revision_check_for_changes', $force, 10, 3 );
	$revision = wp_save_post_revision( $id );
	remove_filter( 'wp_save_post_revision_check_for_changes', $force );
	if ( ! $revision || is_wp_error( $revision ) || get_post_field( 'post_content', $revision ) !== $body ) { throw new RuntimeException( 'Verified pre-migration revision could not be created.' ); }
	$result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $plan['content'] ) ), true );
	if ( is_wp_error( $result ) || get_post_field( 'post_content', $id ) !== $plan['content'] ) { throw new RuntimeException( 'Migration save failed; restore the verified revision.' ); }
	return array( 'changed' => true, 'ids' => $plan['ids'], 'revision' => $revision, 'before' => hash( 'sha256', $body ), 'after' => hash( 'sha256', $plan['content'] ) );
}
