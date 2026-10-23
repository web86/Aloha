<?php
/** Explicit, conservative local CLI conversion. Never loaded by a web/save hook. */
function aloha_area_beaches_migration_plan( $body ) {
 $old = array(); $new = array();
 $walk = function ( $blocks, $inside = false ) use ( &$walk, &$old, &$new ) {
  foreach ( $blocks as $block ) {
   $inside_section = $inside || in_array( 'aloha-area-beaches', explode( ' ', $block['attrs']['className'] ?? '' ), true );
   if ( $inside_section && 'aloha/beaches' === $block['blockName'] ) { $new[] = $block; }
   if ( $inside_section && in_array( 'aloha-area-beach-grid', explode( ' ', $block['attrs']['className'] ?? '' ), true ) ) { $old[] = $block; }
   $walk( $block['innerBlocks'], $inside_section );
  }
 };
 $walk( parse_blocks( $body ) );
 if ( ! $old && 1 === count( $new ) ) { return array( 'changed' => false, 'content' => $body, 'cards' => $new[0]['attrs']['cards'] ?? array() ); }
 if ( 1 !== count( $old ) || $new || 'core/group' !== $old[0]['blockName'] ) { throw new RuntimeException( 'Expected one legacy Beaches grid. Replace the section manually in Gutenberg.' ); }
 if ( trim( wp_strip_all_tags( $old[0]['innerHTML'] ) ) ) { throw new RuntimeException( 'Extra grid content needs manual review.' ); }
 $cards = array();
 foreach ( $old[0]['innerBlocks'] as $card ) {
  $children = $card['innerBlocks'];
  if ( 'core/group' !== $card['blockName'] || 3 !== count( $children ) || 'core/group' !== $children[0]['blockName'] || 'core/heading' !== $children[1]['blockName'] || 'core/paragraph' !== $children[2]['blockName'] ) { throw new RuntimeException( 'Unsupported beach card structure; manual review required.' ); }
  if ( trim( wp_strip_all_tags( $card['innerHTML'] ) ) || trim( wp_strip_all_tags( $children[0]['innerHTML'] ) ) ) { throw new RuntimeException( 'Extra card content needs manual review.' ); }
  $photos = $children[0]['innerBlocks'];
  if ( count( $photos ) < 1 || count( $photos ) > 2 || 'core/image' !== $photos[0]['blockName'] || ( isset( $photos[1] ) && ( 'core/paragraph' !== $photos[1]['blockName'] || 'Photo to be added' !== trim( wp_strip_all_tags( $photos[1]['innerHTML'] ) ) ) ) ) { throw new RuntimeException( 'Unsupported photo content; manual review required.' ); }
  $image = $photos[0]; $id = $image['attrs']['id'] ?? 0;
  $p = new WP_HTML_Tag_Processor( $image['innerHTML'] );
  if ( ! $p->next_tag( 'img' ) ) { throw new RuntimeException( 'Image markup missing.' ); }
  if ( $id ) {
   if ( ! aloha_beaches_image_id( $id ) || html_entity_decode( (string) $p->get_attribute( 'alt' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) !== (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) { throw new RuntimeException( 'Missing image or inline alt differs from Media Library. Resolve manually.' ); }
  } elseif ( $p->get_attribute( 'src' ) ) { throw new RuntimeException( 'An image URL without attachment ID requires manual replacement.' ); }
  if ( preg_match( '~<figcaption\b~i', $image['innerHTML'] ) || $p->get_attribute( 'style' ) || isset( $image['attrs']['style'] ) ) { throw new RuntimeException( 'Custom image caption/crop needs manual review.' ); }
  if ( ! preg_match( '~<h3\b[^>]*>(.*?)</h3>~s', $children[1]['innerHTML'], $heading ) || ! preg_match( '~<p\b[^>]*>(.*?)</p>~s', $children[2]['innerHTML'], $description ) ) { throw new RuntimeException( 'Unsupported title/description markup.' ); }
  // An image and title may share a destination. Conflicting destinations are never discarded.
  $links = array();
  foreach ( array( $image['innerHTML'], $heading[1] ) as $markup ) {
   $tags = new WP_HTML_Tag_Processor( $markup );
   while ( $tags->next_tag( 'a' ) ) {
    $raw = html_entity_decode( (string) $tags->get_attribute( 'href' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    if ( ! $raw || aloha_beaches_url( $raw ) !== $raw ) { throw new RuntimeException( 'Unverified or unsupported card destination.' ); }
    $links[] = $raw;
   }
  }
  if ( count( array_unique( $links ) ) > 1 || preg_match( '~<(?!/?a(?:\s|>))[^>]+>~i', $heading[1] ) || wp_kses_post( $description[1] ) !== $description[1] ) { throw new RuntimeException( 'Card formatting or conflicting destinations need manual review.' ); }
  $cards[] = array( 'title' => html_entity_decode( wp_strip_all_tags( $heading[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'description' => $description[1], 'imageId' => $id, 'url' => $links[0] ?? '', 'focalPoint' => array( 'x' => .5, 'y' => .5 ) );
 }
 if ( ! $cards ) { throw new RuntimeException( 'Empty legacy grid requires manual replacement.' ); }
 // Match balanced Gutenberg comments, then compare the complete parsed grid. Surrounding bytes stay untouched.
 preg_match_all( '~<!--\s*(/?)wp:([a-z0-9_/-]+)\s+(.*?)-->~s', $body, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
 $stack = array(); $matches = array();
 foreach ( $tokens as $token ) {
  $name = $token[2][0]; $offset = $token[0][1];
  if ( '/' === $token[1][0] ) {
   $open = array_pop( $stack );
   if ( ! $open || $open['name'] !== $name ) { throw new RuntimeException( 'Unbalanced block comments.' ); }
   if ( 'group' === $name ) {
    $raw = substr( $body, $open['offset'], $offset + strlen( $token[0][0] ) - $open['offset'] );
    if ( ( parse_blocks( $raw )[0] ?? null ) === $old[0] ) { $matches[] = array( $raw, $open['offset'] ); }
   }
  } elseif ( ! str_ends_with( rtrim( $token[3][0] ), '/' ) ) { $stack[] = array( 'name' => $name, 'offset' => $offset ); }
 }
 if ( $stack || 1 !== count( $matches ) ) { throw new RuntimeException( 'Ambiguous Beaches byte range; manual replacement required.' ); }
 list( $raw, $offset ) = $matches[0];
 $replacement = serialize_block( array( 'blockName' => 'aloha/beaches', 'attrs' => array( 'cards' => $cards ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
 return array( 'changed' => true, 'cards' => $cards, 'offset' => $offset, 'length' => strlen( $raw ), 'replacement' => $replacement, 'content' => substr_replace( $body, $replacement, $offset, strlen( $raw ) ) );
}
function aloha_area_beaches_migrate( $id, $backup ) {
 $body = get_post_field( 'post_content', $id ); $plan = aloha_area_beaches_migration_plan( $body );
 if ( ! $plan['changed'] ) { return array( 'changed' => false, 'cards' => $plan['cards'] ); }
 if ( $body !== $backup ) { throw new RuntimeException( 'Stale backup. Refusing to overwrite current content.' ); }
 $force = function ( $check, $last, $post ) use ( $id ) { return (int) $post->ID === (int) $id ? false : $check; };
 add_filter( 'wp_save_post_revision_check_for_changes', $force, 10, 3 );
 $revision = wp_save_post_revision( $id ); remove_filter( 'wp_save_post_revision_check_for_changes', $force );
 if ( ! $revision || is_wp_error( $revision ) || get_post_field( 'post_content', $revision ) !== $body ) { throw new RuntimeException( 'Verified original revision could not be created.' ); }
 $saved = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $plan['content'] ) ), true );
 if ( is_wp_error( $saved ) || get_post_field( 'post_content', $id ) !== $plan['content'] ) { throw new RuntimeException( 'Save failed. Restore the verified revision.' ); }
 return array( 'changed' => true, 'cards' => $plan['cards'], 'revision' => $revision, 'before' => hash( 'sha256', $body ), 'after' => hash( 'sha256', $plan['content'] ) );
}
