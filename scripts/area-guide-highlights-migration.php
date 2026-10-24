<?php
/** Explicit local CLI planner. No title guessing and no web/save hooks. */
function aloha_guide_migration_range( $body, $block ) {
 preg_match_all( '~<!--\s*(/?)wp:([a-z0-9_/-]+)\s+(.*?)-->~s', $body, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
 $stack = array(); $matches = array();
 foreach ( $tokens as $token ) {
  $name = $token[2][0]; $offset = $token[0][1];
  if ( '/' === $token[1][0] ) {
   $open = array_pop( $stack ); if ( ! $open || $open['name'] !== $name ) { throw new RuntimeException( 'Unbalanced block comments.' ); }
   if ( 'group' === $name ) {
    $raw = substr( $body, $open['offset'], $offset + strlen( $token[0][0] ) - $open['offset'] );
    if ( ( parse_blocks( $raw )[0] ?? null ) === $block ) { $matches[] = array( $open['offset'], strlen( $raw ) ); }
   }
  } elseif ( ! str_ends_with( rtrim( $token[3][0] ), '/' ) ) { $stack[] = array( 'name' => $name, 'offset' => $offset ); }
 }
 if ( $stack || 1 !== count( $matches ) ) { throw new RuntimeException( 'Ambiguous legacy group; replace manually.' ); }
 return $matches[0];
}
function aloha_guide_migration_plan( $body, $mapping ) {
 $old = array(); $new = array();
 $walk = function ( $blocks, $inside = false ) use ( &$walk, &$old, &$new ) {
  foreach ( $blocks as $block ) {
   $classes = explode( ' ', $block['attrs']['className'] ?? '' ); $here = $inside || in_array( 'aloha-area-activities', $classes, true );
   if ( $here && 'aloha/guide-highlights' === $block['blockName'] ) { $new[] = $block; }
   if ( $here ) { foreach ( array( 'featured' => 'aloha-area-activity-featured', 'additional' => 'aloha-area-activity-grid' ) as $key => $class ) { if ( in_array( $class, $classes, true ) ) { $old[$key][] = $block; } } }
   $walk( $block['innerBlocks'], $here );
  }
 };
 $walk( parse_blocks( $body ) );
 if ( ! $old && 1 === count( $new ) ) { return array( 'changed' => false, 'content' => $body ); }
 if ( $new || 1 !== count( $old['featured'] ?? array() ) || 1 !== count( $old['additional'] ?? array() ) ) { throw new RuntimeException( 'Expected one legacy featured grid and one additional grid. Manual review required.' ); }
 $ids = aloha_guide_highlights_ids( $mapping );
 if ( $ids !== $mapping ) { throw new RuntimeException( 'Mapping must contain only unique featuredIds (max 2) and additionalIds (max 6).' ); }
 $normalize = fn( $text ) => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
 $ranges = array();
 foreach ( array( 'featured' => 'featuredIds', 'additional' => 'additionalIds' ) as $group => $key ) {
  $grid = $old[$group][0]; if ( trim( wp_strip_all_tags( $grid['innerHTML'] ) ) || count( $grid['innerBlocks'] ) !== count( $ids[$key] ) ) { throw new RuntimeException( 'Explicit mapping must cover every existing card, without losing extra content.' ); }
  foreach ( $grid['innerBlocks'] as $index => $card ) {
   $children = $card['innerBlocks'];
   if ( 'core/group' !== $card['blockName'] || 2 !== count( $children ) || 'core/group' !== $children[0]['blockName'] || 'core/group' !== $children[1]['blockName'] || trim( wp_strip_all_tags( $card['innerHTML'] ) ) ) { throw new RuntimeException( 'Custom card structure needs manual review.' ); }
   $photo = $children[0]; $caption = $children[1]['innerBlocks']; $images = $photo['innerBlocks'];
   if ( count( $images ) < 1 || count( $images ) > 2 || 'core/image' !== $images[0]['blockName'] || count( $caption ) !== 2 || 'core/heading' !== $caption[0]['blockName'] || 'core/paragraph' !== $caption[1]['blockName'] || trim( wp_strip_all_tags( $photo['innerHTML'] ) ) || trim( wp_strip_all_tags( $children[1]['innerHTML'] ) ) ) { throw new RuntimeException( 'Custom photo or caption needs manual review.' ); }
   if ( isset( $images[1] ) && ( 'core/paragraph' !== $images[1]['blockName'] || ! in_array( $normalize( $images[1]['innerHTML'] ), array( 'Reference photo to be added', 'Photo to be added' ), true ) ) ) { throw new RuntimeException( 'Custom image annotation must be preserved manually.' ); }
   $item = aloha_guide_highlight( $ids[$key][$index] ); $image = $images[0]; $image_id = (int) ( $image['attrs']['id'] ?? 0 );
   if ( ! $item || $normalize( $caption[0]['innerHTML'] ) !== $normalize( $item['title'] ) || $normalize( $caption[1]['innerHTML'] ) !== $normalize( $item['excerpt'] ) || $image_id !== $item['imageId'] ) { throw new RuntimeException( 'Guide post must already preserve the legacy title, excerpt and photo. No editorial data was discarded.' ); }
   if ( ! preg_match( '~^<h3 class="wp-block-heading">[^<]*</h3>$~s', trim( $caption[0]['innerHTML'] ) ) || ! preg_match( '~^<p>[^<]*</p>$~s', trim( $caption[1]['innerHTML'] ) ) ) { throw new RuntimeException( 'Rich title or description needs manual transfer.' ); }
   // Rich inline copy, destinations, crop, captions and inline alt overrides are never silently flattened.
   foreach ( array( $caption[0]['innerHTML'], $caption[1]['innerHTML'], $image['innerHTML'] ) as $markup ) { if ( preg_match( '~<(a|em|strong|figcaption)\b~i', $markup ) ) { throw new RuntimeException( 'Rich formatting/destination/caption needs manual transfer.' ); } }
   if ( isset( $image['attrs']['style'] ) ) { throw new RuntimeException( 'Custom image crop needs manual review.' ); }
   $tag = new WP_HTML_Tag_Processor( $image['innerHTML'] );
   if ( ! $tag->next_tag( 'img' ) || $tag->get_attribute( 'style' ) || ( $image_id && html_entity_decode( (string) $tag->get_attribute( 'alt' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) !== get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) || ( ! $image_id && $tag->get_attribute( 'src' ) ) ) { throw new RuntimeException( 'Inline image data differs from Media Library; review manually.' ); }
  }
  $ranges[$group] = aloha_guide_migration_range( $body, $grid );
 }
 list( $offset, $length ) = $ranges['featured']; list( $second, $second_length ) = $ranges['additional'];
 if ( $second < $offset + $length || trim( substr( $body, $offset + $length, $second - $offset - $length ) ) ) { throw new RuntimeException( 'Content between groups cannot be removed automatically.' ); }
 $replacement = serialize_block( array( 'blockName' => 'aloha/guide-highlights', 'attrs' => $ids, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
 $length = $second + $second_length - $offset;
 return array( 'changed' => true, 'content' => substr_replace( $body, $replacement, $offset, $length ), 'offset' => $offset, 'length' => $length, 'replacement' => $replacement );
}
function aloha_guide_migrate( $id, $mapping, $backup ) {
 $body = get_post_field( 'post_content', $id ); $plan = aloha_guide_migration_plan( $body, $mapping ); if ( ! $plan['changed'] ) { return array( 'changed' => false ); }
 if ( $body !== $backup ) { throw new RuntimeException( 'Backup is stale; refusing writes.' ); }
 $lock = explode( ':', (string) get_post_meta( $id, '_edit_lock', true ) );
 if ( (int) $lock[0] > time() - (int) apply_filters( 'wp_check_post_lock_window', 150 ) ) { throw new RuntimeException( 'Close the active editor and wait for its Core lock to expire before applying migration.' ); }
 $force = fn( $check, $last, $post ) => (int) $post->ID === (int) $id ? false : $check;
 add_filter( 'wp_save_post_revision_check_for_changes', $force, 10, 3 ); $revision = wp_save_post_revision( $id ); remove_filter( 'wp_save_post_revision_check_for_changes', $force );
 if ( ! $revision || is_wp_error( $revision ) || get_post_field( 'post_content', $revision ) !== $body ) { throw new RuntimeException( 'Original revision could not be verified.' ); }
 clean_post_cache( $id );
 if ( get_post_field( 'post_content', $id ) !== $body ) { throw new RuntimeException( 'Content changed during revision creation; refusing overwrite.' ); }
 $saved = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $plan['content'] ) ), true );
 if ( is_wp_error( $saved ) || get_post_field( 'post_content', $id ) !== $plan['content'] ) { throw new RuntimeException( 'Save failed; preserve the verified revision.' ); }
 return array( 'changed' => true, 'revision' => $revision, 'before' => hash( 'sha256', $body ), 'after' => hash( 'sha256', $plan['content'] ) );
}
