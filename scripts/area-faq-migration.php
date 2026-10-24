<?php
/** Conservative CLI-only Details → Core Accordion conversion; no runtime/save hook. */
function aloha_area_faq_migration_plan( $body ) {
 $sections = array();
 $walk = function ( $blocks ) use ( &$walk, &$sections ) {
  foreach ( $blocks as $block ) {
   if ( 'core/group' === $block['blockName'] && in_array( 'aloha-area-faq', explode( ' ', $block['attrs']['className'] ?? '' ), true ) ) { $sections[] = $block; }
   $walk( $block['innerBlocks'] );
  }
 };
 $walk( parse_blocks( $body ) );
 if ( 1 !== count( $sections ) ) { throw new RuntimeException( 'Expected exactly one Area FAQ section. No content was changed.' ); }
 $section = $sections[0]; $details = array(); $accordions = array();
 foreach ( $section['innerBlocks'] as $block ) {
  if ( 'core/details' === $block['blockName'] ) { $details[] = $block; }
  elseif ( 'core/accordion' === $block['blockName'] ) { $accordions[] = $block; }
  elseif ( 'core/paragraph' !== $block['blockName'] || 'aloha-eyebrow' !== ( $block['attrs']['className'] ?? '' ) ) { throw new RuntimeException( 'Unexpected FAQ section content; controlled manual replacement is required.' ); }
 }
 if ( 1 === count( $accordions ) && ! $details ) { return array( 'changed' => false, 'content' => $body, 'questions' => count( $accordions[0]['innerBlocks'] ) ); }
 if ( $accordions ) { throw new RuntimeException( 'Mixed or multiple FAQ accordions require manual review.' ); }
 foreach ( array( 'core/accordion', 'core/accordion-item', 'core/accordion-heading', 'core/accordion-panel' ) as $name ) {
  if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) { throw new RuntimeException( 'Core Accordion is unavailable. WordPress 6.9+ is required.' ); }
 }
 // Locate the exact section bytes, including balanced nested Group comments.
 preg_match_all( '~<!--\s*(/?)wp:([a-z0-9_/-]+)\s+(.*?)-->~s', $body, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
 $stack = array(); $ranges = array();
 foreach ( $tokens as $token ) {
  $name = $token[2][0]; $offset = $token[0][1];
  if ( '/' === $token[1][0] ) {
   $open = array_pop( $stack );
   if ( ! $open || $open['name'] !== $name ) { throw new RuntimeException( 'Unbalanced Gutenberg comments; migration refused.' ); }
   if ( 'group' === $name ) {
    $raw = substr( $body, $open['offset'], $offset + strlen( $token[0][0] ) - $open['offset'] );
    if ( ( parse_blocks( $raw )[0] ?? null ) === $section ) { $ranges[] = array( $raw, $open['offset'] ); }
   }
  } elseif ( ! str_ends_with( rtrim( $token[3][0] ), '/' ) ) { $stack[] = array( 'name' => $name, 'offset' => $offset ); }
 }
 if ( $stack || 1 !== count( $ranges ) ) { throw new RuntimeException( 'Ambiguous FAQ byte range; migration refused.' ); }
 list( $raw_section, $section_offset ) = $ranges[0];
 preg_match_all( '~<!-- wp:details\b.*?<!-- /wp:details -->~s', $raw_section, $matches, PREG_OFFSET_CAPTURE );
 if ( count( $matches[0] ) !== count( $details ) ) { throw new RuntimeException( 'Nested or ambiguous Details need manual review.' ); }
 $validate = function ( $blocks ) use ( &$validate ) {
  foreach ( $blocks as $block ) {
   if ( null === $block['blockName'] && '' === trim( $block['innerHTML'] ) ) { continue; }
   if ( ! in_array( $block['blockName'], array( 'core/paragraph', 'core/list', 'core/list-item', 'core/group', 'core/heading', 'core/quote', 'core/image', 'core/buttons', 'core/button', 'core/separator', 'core/spacer', 'core/code' ), true ) ) { throw new RuntimeException( 'Unsupported answer block (' . ( $block['blockName'] ?: 'unwrapped HTML' ) . '); replace this FAQ manually without discarding content.' ); }
   $validate( $block['innerBlocks'] );
  }
 };
 $items = ''; $answers = array(); $titles = array();
 foreach ( $matches[0] as $i => list( $raw, $start ) ) {
  $block = $details[$i];
  if ( ( parse_blocks( $raw )[0] ?? null ) !== $block || ! is_array( $block['attrs'] ) || array_diff( array_keys( $block['attrs'] ), array( 'showContent' ) ) || ( isset( $block['attrs']['showContent'] ) && ! is_bool( $block['attrs']['showContent'] ) ) ) { throw new RuntimeException( 'Custom Details attributes need manual review.' ); }
  if ( ! preg_match( '~^<!-- wp:details\b.*?-->\s*<details\b([^>]*)>\s*<summary>(.*?)</summary>(.*)</details>\s*<!-- /wp:details -->$~s', $raw, $parts ) ) { throw new RuntimeException( 'Custom Details/summary markup cannot be migrated safely.' ); }
  $tag = new WP_HTML_Tag_Processor( '<details' . $parts[1] . '>' ); $tag->next_tag();
  if ( array_diff( $tag->get_attribute_names_with_prefix( '' ), array( 'class', 'open' ) ) || 'wp-block-details' !== $tag->get_attribute( 'class' ) || ( null !== $tag->get_attribute( 'open' ) ) !== (bool) ( $block['attrs']['showContent'] ?? false ) ) { throw new RuntimeException( 'Custom Details HTML attributes or conflicting open state need manual review.' ); }
  $title = $parts[2]; $answer = $parts[3];
  if ( '' === trim( wp_strip_all_tags( $title ) ) || wp_kses_post( $title ) !== $title || preg_match( '~<(?!/?(?:strong|em|s|del|code|sub|sup|mark|span)\b)[^>]*>~i', $title ) ) { throw new RuntimeException( 'Unsupported or interactive question formatting cannot be nested in a Core toggle button.' ); }
  $inline = new WP_HTML_Tag_Processor( $title );
  while ( $inline->next_tag() ) { if ( $inline->get_attribute_names_with_prefix( '' ) ) { throw new RuntimeException( 'Question HTML attributes require manual formatting review.' ); } }
  $validate( parse_blocks( $answer ) );
  if ( $i && trim( substr( $raw_section, $matches[0][$i-1][1] + strlen( $matches[0][$i-1][0] ), $start - $matches[0][$i-1][1] - strlen( $matches[0][$i-1][0] ) ) ) ) { throw new RuntimeException( 'Questions are interleaved with other content; manual replacement required.' ); }
  $items .= aloha_area_faq_item( $title, $answer, (bool) ( $block['attrs']['showContent'] ?? false ) );
  $answers[] = $answer; $titles[] = $title;
 }
 if ( $details ) {
  $offset = $section_offset + $matches[0][0][1];
  $last = end( $matches[0] ); $length = $section_offset + $last[1] + strlen( $last[0] ) - $offset;
 } else {
  if ( ! preg_match( '~</div>\s*<!-- /wp:group -->\s*$~s', $raw_section, $closing, PREG_OFFSET_CAPTURE ) ) { throw new RuntimeException( 'Unsupported empty FAQ wrapper.' ); }
  $offset = $section_offset + $closing[0][1]; $length = 0;
 }
 $replacement = aloha_area_faq_accordion( $items );
 return array( 'changed' => true, 'questions' => count( $details ), 'titles' => $titles, 'answers' => $answers, 'offset' => $offset, 'length' => $length, 'replacement' => $replacement, 'content' => substr_replace( $body, $replacement, $offset, $length ) );
}
function aloha_area_faq_migrate( $id, $backup ) {
 $body = get_post_field( 'post_content', $id ); $plan = aloha_area_faq_migration_plan( $body );
 if ( ! $plan['changed'] ) { return array( 'changed' => false, 'questions' => $plan['questions'] ); }
 if ( $body !== $backup ) { throw new RuntimeException( 'Backup does not match current content; stale migration refused.' ); }
 $force = function ( $check, $last, $post ) use ( $id ) { return (int) $post->ID === (int) $id ? false : $check; };
 add_filter( 'wp_save_post_revision_check_for_changes', $force, 10, 3 );
 $revision = wp_save_post_revision( $id ); remove_filter( 'wp_save_post_revision_check_for_changes', $force );
 if ( ! $revision || is_wp_error( $revision ) || get_post_field( 'post_content', $revision ) !== $body ) { throw new RuntimeException( 'A verified original revision could not be created.' ); }
 $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $plan['content'] ) ), true );
 if ( is_wp_error( $result ) || get_post_field( 'post_content', $id ) !== $plan['content'] ) { throw new RuntimeException( 'Save failed; restore the verified revision.' ); }
 return array( 'changed' => true, 'questions' => $plan['questions'], 'revision' => $revision, 'before' => hash( 'sha256', $body ), 'after' => hash( 'sha256', $plan['content'] ) );
}
