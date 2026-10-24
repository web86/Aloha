<?php
/** Curated Guide presentation; content and route ownership stay in Aloha Content/Core. */
defined( 'ABSPATH' ) || exit;
add_action( 'init', function () {
 wp_register_style( 'aloha-guide-highlights', get_theme_file_uri( 'assets/css/guide-highlights.css' ), array( 'aloha-foundation' ), (string) filemtime( get_theme_file_path( 'assets/css/guide-highlights.css' ) ) );
 wp_register_script( 'aloha-guide-highlights-editor', get_theme_file_uri( 'blocks/guide-highlights/editor.js' ), array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-api-fetch' ), (string) filemtime( get_theme_file_path( 'blocks/guide-highlights/editor.js' ) ), true );
 register_block_type( get_theme_file_path( 'blocks/guide-highlights' ) );
} );
add_action( 'after_setup_theme', function () { add_editor_style( 'assets/css/guide-highlights.css' ); } );
function aloha_guide_highlights_ids( $attrs ) {
 $seen = array(); $groups = array();
 foreach ( array( 'featuredIds' => 2, 'additionalIds' => 6 ) as $key => $limit ) {
  $groups[$key] = array();
  foreach ( is_array( $attrs[$key] ?? null ) ? $attrs[$key] : array() as $id ) {
   if ( ! is_int( $id ) || $id < 1 || isset( $seen[$id] ) ) { continue; }
   if ( count( $groups[$key] ) >= $limit ) { break; }
   $groups[$key][] = $id; $seen[$id] = true;
  }
 }
 return $groups;
}
function aloha_guide_highlight( $id ) {
 if ( ! function_exists( '\Aloha\Content\editorial_post_route' ) ) { return null; }
 $post = get_post( $id );
 if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || $post->post_password ) { return null; }
 $route = \Aloha\Content\editorial_post_route( $id );
 if ( ! $route || is_wp_error( $route ) || 'guide' !== $route['family'] ) { return null; }
 $image = (int) get_post_thumbnail_id( $id ); $file = $image ? get_attached_file( $image ) : '';
 if ( $image && ( ! wp_attachment_is_image( $image ) || 'trash' === get_post_status( $image ) || ! $file || ( ! wp_is_stream( $file ) && ! file_exists( $file ) ) ) ) { $image = 0; }
 return array( 'id' => (int) $id, 'available' => true, 'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'excerpt' => wp_trim_words( html_entity_decode( wp_strip_all_tags( strip_shortcodes( get_the_excerpt( $post ) ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 32, '…' ), 'url' => get_permalink( $post ), 'imageId' => $image, 'thumbnail' => $image ? wp_get_attachment_image_url( $image, 'medium' ) : '', 'alt' => $image ? (string) get_post_meta( $image, '_wp_attachment_image_alt', true ) : '' );
}
function aloha_guide_highlights_render( $attrs ) {
 $html = ''; $groups = aloha_guide_highlights_ids( $attrs );
 foreach ( array( 'featuredIds' => 'featured', 'additionalIds' => 'additional' ) as $key => $class ) {
  $cards = '';
  foreach ( $groups[$key] as $id ) {
   $item = aloha_guide_highlight( $id ); if ( ! $item ) { continue; }
   $cards .= '<a class="aloha-guide-card' . ( $item['imageId'] ? '' : ' has-no-image' ) . '" href="' . esc_url( $item['url'] ) . '">';
   if ( $item['imageId'] ) {
    $cards .= wp_get_attachment_image( $item['imageId'], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => 'featured' === $class ? '(min-width: 1280px) 588px, (min-width: 768px) 46vw, 92vw' : '(min-width: 1280px) 384px, (min-width: 1024px) 30vw, (min-width: 600px) 46vw, 92vw' ) );
   }
   $cards .= '<div class="aloha-guide-caption"><h3>' . esc_html( $item['title'] ) . '</h3>' . ( $item['excerpt'] ? '<p>' . esc_html( $item['excerpt'] ) . '</p>' : '' ) . '</div></a>';
  }
  if ( $cards ) { $html .= '<div class="aloha-guide-' . $class . '">' . $cards . '</div>'; }
 }
 return $html ? '<div ' . get_block_wrapper_attributes( array( 'class' => 'aloha-guide-highlights' ) ) . '>' . $html . '</div>' : '';
}
// Editor-only, read-only search. It never changes categories, posts, routes or options.
add_action( 'rest_api_init', function () {
 register_rest_route( 'aloha/v1', '/guide-highlights', array(
  'methods' => WP_REST_Server::READABLE,
  'permission_callback' => fn() => current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ),
  'args' => array( 'search' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ), 'include' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'maxItems' => 8, 'default' => array() ) ),
  'callback' => function ( $request ) {
   $map = function_exists( '\Aloha\Content\guide_terms' ) ? \Aloha\Content\guide_terms() : null;
   $valid = $map && ! is_wp_error( $map ); $items = array();
   if ( $request['include'] ) {
    foreach ( array_unique( $request['include'] ) as $id ) { $items[] = aloha_guide_highlight( $id ) ?: array( 'id' => (int) $id, 'available' => false ); }
   } elseif ( $valid ) {
    $query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 's' => $request['search'], 'posts_per_page' => 20, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => array_values( $map['rubrics'] ), 'include_children' => true ) ) ) );
    foreach ( $query->posts as $post ) { $item = aloha_guide_highlight( $post->ID ); if ( $item ) { $items[] = $item; } }
   }
   return rest_ensure_response( array( 'configured' => (bool) $valid, 'items' => $items ) );
  },
 ) );
} );
