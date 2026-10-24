<?php
/** Explicit CLI-only insertion of one native Image; existing bytes are never reserialized. */
function aloha_area_cta_image_plan( $body ) {
 $panels = array(); $stack = array();
 preg_match_all( '~<!--\s*(/?)wp:([a-z0-9_/-]+)\s+(.*?)-->~s', $body, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
 foreach ( $tokens as $token ) {
  $name = $token[2][0]; $offset = $token[0][1];
  if ( '/' === $token[1][0] ) {
   $open = array_pop( $stack );
   if ( ! $open || $open['name'] !== $name ) { throw new RuntimeException( 'Unbalanced blocks; no changes made.' ); }
   if ( $open['panel'] ) { $panels[] = array( $open['offset'], $offset + strlen( $token[0][0] ) - $open['offset'] ); }
  } elseif ( ! str_ends_with( rtrim( $token[3][0] ), '/' ) ) {
   $attrs = json_decode( trim( $token[3][0] ), true ) ?: array();
   $stack[] = array( 'name' => $name, 'offset' => $offset, 'panel' => 'group' === $name && in_array( 'aloha-area-advisor-panel', explode( ' ', $attrs['className'] ?? '' ), true ) );
  }
 }
 if ( $stack || 1 !== count( $panels ) ) { throw new RuntimeException( 'Expected exactly one CTA panel; review manually.' ); }
 list( $start, $length ) = $panels[0]; $raw = substr( $body, $start, $length ); $panel = parse_blocks( $raw )[0];
 foreach ( $panel['innerBlocks'] as $child ) {
  if ( 'core/image' === $child['blockName'] ) {
   if ( in_array( 'aloha-area-advisor-image', explode( ' ', $child['attrs']['className'] ?? '' ), true ) ) { return array( 'changed' => false, 'content' => $body ); }
   throw new RuntimeException( 'CTA already contains a custom image; review manually.' );
  }
 }
 if ( 'core/group' !== ( $panel['innerBlocks'][0]['blockName'] ?? '' ) || ! in_array( 'aloha-area-advisor-copy', explode( ' ', $panel['innerBlocks'][0]['attrs']['className'] ?? '' ), true ) ) { throw new RuntimeException( 'Custom CTA structure; no automatic replacement.' ); }
 // First child starts immediately after the outer Group wrapper; retain all original bytes.
 if ( ! preg_match( '~^<!-- wp:group\b.*?-->\s*<div\b[^>]*>(\s*)<!-- wp:group\b~s', $raw, $match ) ) { throw new RuntimeException( 'Custom CTA wrapper; review manually.' ); }
 $offset = $start + strlen( $match[0] ) - strlen( '<!-- wp:group' );
 $replacement = aloha_area_advisor_image() . "\n\n";
 return array( 'changed' => true, 'offset' => $offset, 'replacement' => $replacement, 'content' => substr_replace( $body, $replacement, $offset, 0 ) );
}
function aloha_area_cta_image_migrate( $id, $backup ) {
 $body = get_post_field( 'post_content', $id ); $plan = aloha_area_cta_image_plan( $body );
 if ( ! $plan['changed'] ) { return array( 'changed' => false ); }
 if ( $body !== $backup ) { throw new RuntimeException( 'Stale backup; refusing overwrite.' ); }
 $force = fn( $check, $last, $post ) => (int) $post->ID === (int) $id ? false : $check;
 add_filter( 'wp_save_post_revision_check_for_changes', $force, 10, 3 );
 $revision = wp_save_post_revision( $id ); remove_filter( 'wp_save_post_revision_check_for_changes', $force );
 if ( ! $revision || is_wp_error( $revision ) || get_post_field( 'post_content', $revision ) !== $body ) { throw new RuntimeException( 'Verified original revision could not be created.' ); }
 clean_post_cache( $id );
 if ( get_post_field( 'post_content', $id ) !== $body ) { throw new RuntimeException( 'Content changed during revision creation; refusing overwrite.' ); }
 $saved = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $plan['content'] ) ), true );
 if ( is_wp_error( $saved ) || get_post_field( 'post_content', $id ) !== $plan['content'] ) { throw new RuntimeException( 'Save failed; preserve verified revision.' ); }
 return array( 'changed' => true, 'revision' => $revision, 'offset' => $plan['offset'], 'insertedBytes' => strlen( $plan['replacement'] ), 'before' => hash( 'sha256', $body ), 'after' => hash( 'sha256', $plan['content'] ) );
}
