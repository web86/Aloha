<?php
/** Native CTA image and byte-preserving migration checks, disposable WordPress only. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) ) { exit( 1 ); }
require_once __DIR__ . '/area-cta-image-migration.php';
$key = 'aloha_cta_image_test'; $state = get_option( $key );
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'rewrite_rules' ), get_option( 'permalink_structure' ), get_option( 'aloha_content_area_routes' ), get_option( 'aloha_shell_menus' ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( $state['posts'] as $id ) { wp_delete_post( $id, true ); }
 if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'CTA cleanup changed original content/settings.' ); }
 delete_option( $key ); WP_CLI::success( 'CTA fixtures removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'CTA fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array() ); update_option( $key, $state, false );
$checks = array(); $check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $body, $title ) use ( &$state, $key ) { $id = wp_insert_post( wp_slash( array( 'post_type' => 'area', 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $body ) ) ); $state['posts'][] = $id; update_option( $key, $state, false ); return $id; };
$pattern = aloha_area_pattern_content( 'area-owner-cta' ); $slot = aloha_area_advisor_image();
$check( 1 === substr_count( $pattern, 'CTA portrait (optional)' ) && ! str_contains( $slot, '"id"' ) && ! str_contains( $slot, 'src=' ), 'Starter uses one optional native Image without an unapproved asset' );
$check( 1 === substr_count( aloha_area_pattern_content( 'area-start' ), 'CTA portrait (optional)' ), 'Area starter delegates to the same CTA pattern' );
$legacy = str_replace( $slot, '', $pattern );
$body = aloha_area_paragraph( 'Unchanged before &amp; custom text' ) . $legacy . aloha_area_paragraph( 'Unchanged after' );
$plan = aloha_area_cta_image_plan( $body );
$check( $plan['changed'] && substr_replace( $plan['content'], '', $plan['offset'], strlen( $plan['replacement'] ) ) === $body, 'Migration inserts only one Image; all preexisting bytes remain exact' );
$check( ! aloha_area_cta_image_plan( $plan['content'] )['changed'], 'Migration is idempotent' );
foreach ( array( '', $body . $body, str_replace( 'aloha-area-advisor-copy', 'custom-copy', $body ) ) as $bad ) {
 try { aloha_area_cta_image_plan( $bad ); $ok = false; } catch ( RuntimeException $e ) { $ok = true; }
 $check( $ok, 'Ambiguous/missing/custom CTA safely refused: ' . count( $checks ) );
}
$migration = $create( $body, 'CTA migration fixture' );
try { aloha_area_cta_image_migrate( $migration, 'stale' ); $ok = false; } catch ( RuntimeException $e ) { $ok = true; }
$check( $ok && get_post_field( 'post_content', $migration ) === $body, 'Stale backup cannot overwrite content' );
$result = aloha_area_cta_image_migrate( $migration, $body );
$check( get_post_field( 'post_content', $result['revision'] ) === $body && get_post_field( 'post_content', $migration ) === $plan['content'], 'Native original revision and exact targeted save verified' );
$check( ! aloha_area_cta_image_migrate( $migration, $body )['changed'], 'Repeated application leaves content/revisions alone' );
$base = get_option( 'aloha_area_presentation_fixtures' )['manifest']['media']; $media = array_values( array_filter( $base, 'aloha_beaches_image_id' ) );
$photo = function ( $id ) {
 $alt = get_post_meta( $id, '_wp_attachment_image_alt', true ); $src = wp_get_attachment_image_src( $id, 'medium' )[0];
 return '<!-- wp:image ' . wp_json_encode( array( 'id' => $id, 'sizeSlug' => 'medium', 'linkDestination' => 'none', 'className' => 'aloha-area-advisor-image' ) ) . ' --><figure class="wp-block-image size-medium aloha-area-advisor-image"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $id . '"/></figure><!-- /wp:image -->';
};
$visual = $create( str_replace( $slot, $photo( $media[0] ), $pattern ), 'CTA with test image — not a production portrait' );
$empty = $create( $pattern, 'CTA without image' );
$long = $create( str_replace( array( $slot, 'A little help <em>choosing?</em>', 'Tell us what a good island stay looks like for you.' ), array( $photo( $media[0] ), str_repeat( 'Choosing an island home ', 6 ), str_repeat( 'Tell us what a good island stay looks like for you. ', 12 ) ), $pattern ), 'CTA long copy fixture' );
$editor = $create( $pattern, 'CTA native Image editor fixture' );
$check( 'core/image' === parse_blocks( $slot )[0]['blockName'], 'Image uses standard Core attributes/Media Library, no new post meta or block type' );
$state['manifest'] = array( 'visual' => $visual, 'visual_url' => get_permalink( $visual ), 'empty_url' => get_permalink( $empty ), 'long_url' => get_permalink( $long ), 'editor' => $editor, 'media' => array_slice( $media, 0, 2 ), 'checks' => $checks ); update_option( $key, $state, false );
WP_CLI::line( wp_json_encode( $state['manifest'] ) );
