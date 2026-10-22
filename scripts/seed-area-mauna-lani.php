<?php
/** Explicit one-time seed of the approved permanent LOCAL Area and reference media. */
require_once __DIR__ . '/area-reference-content.php';
if ( home_url() !== 'http://127.0.0.1:8088' || ( defined( 'ALOHA_CONTENT_TEST_SITE' ) && ALOHA_CONTENT_TEST_SITE ) ) { WP_CLI::error( 'Only the main local WordPress on 8088 may be seeded.' ); }
if ( ! current_user_can( 'publish_posts' ) || ! function_exists( 'aloha_area_pattern_content' ) ) { WP_CLI::error( 'Activate Aloha Hello and use an authorized editor.' ); }
$existing = get_page_by_path( 'mauna-lani', OBJECT, array( 'area' ) );
if ( $existing ) {
	echo wp_json_encode( array( 'id' => $existing->ID, 'url' => get_permalink( $existing ), 'existing_preserved' => true ) );
	return;
}
$id = wp_insert_post( array( 'post_type' => 'area', 'post_title' => 'Mauna Lani', 'post_name' => 'mauna-lani', 'post_status' => 'draft' ), true );
if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
$media = \Aloha\AreaReference\import_media( $id );
set_post_thumbnail( $id, $media['hero'] );
$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish', 'post_content' => \Aloha\AreaReference\content( $media ), 'post_excerpt' => 'A first look at the shoreline, local experiences and island rhythm. An editorial preview using reference photography.' ), true );
if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
echo wp_json_encode( array( 'id' => $id, 'url' => get_permalink( $id ), 'editor' => admin_url( 'post.php?post=' . $id . '&action=edit' ), 'media' => $media ), JSON_PRETTY_PRINT );
