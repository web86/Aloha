<?php
/** Area media model + Hero acceptance in the existing disposable WordPress only. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_area_hero_test'; $state = get_option( $key );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'rewrite_rules' ), get_option( 'aloha_content_area_routes' ), get_option( 'aloha_shell_menus' ), get_option( 'permalink_structure' ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
 foreach ( $state['media'] as $id ) { wp_delete_attachment( $id, true ); }
 if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'Hero cleanup changed original content/settings.' ); }
 delete_option( $key ); WP_CLI::success( 'Hero fixtures removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'Hero fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array(), 'media' => array() );
update_option( $key, $state, false );
$checks = array();
$check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $type, $title, $extra = array() ) use ( &$state, $key ) {
 $id = wp_insert_post( array_merge( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'publish' ), $extra ), true );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state[ 'attachment' === $type ? 'media' : 'posts' ][] = $id; update_option( $key, $state, false ); return $id;
};
$body = aloha_area_pattern_content( 'area-start' );
$one = $create( 'area', 'Interactive Hero fixture', array( 'post_content' => $body, 'post_excerpt' => 'Isolated video and film acceptance fixture.' ) );
$two = $create( 'area', 'Independent Hero fixture', array( 'post_content' => $body ) );
$video = $create( 'attachment', 'Unavailable WebM metadata fixture', array( 'post_mime_type' => 'video/webm', 'guid' => home_url( '/wp-content/uploads/hero-unavailable.webm' ), 'post_status' => 'inherit' ) );
$wrong = $create( 'attachment', 'Image type fixture', array( 'post_mime_type' => 'image/jpeg', 'guid' => home_url( '/wp-content/uploads/hero-type-fixture.jpg' ), 'post_status' => 'inherit' ) );
$rest = function ( $meta, $id = null ) use ( $one ) {
 $req = new WP_REST_Request( 'POST', '/wp/v2/area/' . ( $id ?? $one ) ); $req->set_param( 'meta', $meta ); return rest_do_request( $req );
};
$registered = get_registered_meta_keys( 'post', 'area' );
foreach ( array( 'aloha_area_loop_video_id' => 'integer', 'aloha_area_film_url' => 'string', 'aloha_area_film_label' => 'string' ) as $field => $type ) {
 $check( $registered[$field]['type'] === $type && $registered[$field]['show_in_rest'] && $registered[$field]['revisions_enabled'], 'Registered REST/revisioned ' . $field );
}
$check( Aloha\Content\AREA_FILM_LABEL_DEFAULT === get_post_meta( $one, 'aloha_area_film_label', true ) && ! metadata_exists( 'post', $one, 'aloha_area_film_label' ), 'Default film label is available without backfilling postmeta' );
foreach ( array( -2, 1.5, true, array(), $wrong, $one, 999999 ) as $value ) {
 $check( 0 === Aloha\Content\area_loop_video_id( $value ), 'Invalid video input normalizes safely: ' . wp_json_encode( $value ) );
}
$check( $video === Aloha\Content\area_loop_video_id( $video ), 'MP4/WebM attachment ID accepted' );
foreach ( array( 'https://youtu.be/AlohaTest01?si=abc', 'https://www.youtube.com/watch?v=AlohaTest01&t=20', 'https://m.youtube.com/shorts/AlohaTest01', 'https://youtube.com/embed/AlohaTest01' ) as $url ) {
 $check( 'AlohaTest01' === Aloha\Content\area_youtube_id( $url ), 'Supported YouTube form: ' . $url );
}
foreach ( array( 'javascript:alert(1)', 'https://youtube.com.evil.test/watch?v=AlohaTest01', 'https://example.test/AlohaTest01', 'https://user@youtube.com/watch?v=AlohaTest01', 'https://youtube.com/watch?v[]=AlohaTest01', 'https://youtube.com/playlist?list=AlohaTest01', '<iframe src="https://youtube.com"></iframe>' ) as $url ) {
 $check( '' === Aloha\Content\area_youtube_id( $url ), 'Invalid film rejected: ' . $url );
}
$before = get_post( $one )->to_array();
foreach ( array( array( 'aloha_area_loop_video_id' => $wrong ), array( 'aloha_area_loop_video_id' => -1 ), array( 'aloha_area_loop_video_id' => 'bad' ), array( 'aloha_area_film_url' => 'https://example.test/video' ), array( 'aloha_area_film_label' => array( 'bad' ) ) ) as $invalid ) {
 // Cross a clock tick so a late REST failure cannot hide post_modified writes.
 usleep( 1100000 );
 $response = $rest( $invalid );
 $current = get_post( $one )->to_array();
 $changed = array_keys( array_filter( $current, fn( $value, $field ) => ! array_key_exists( $field, $before ) || $before[$field] !== $value, ARRAY_FILTER_USE_BOTH ) );
 $check( 400 === $response->get_status() && $current === $before, 'REST rejects invalid media before post changes: ' . wp_json_encode( array( 'input' => $invalid, 'status' => $response->get_status(), 'changed' => $changed, 'error' => $response->get_data()['code'] ?? '' ) ) );
}
$response = $rest( array( 'aloha_area_loop_video_id' => $video, 'aloha_area_film_url' => 'https://youtu.be/AlohaTest01?si=discard', 'aloha_area_film_label' => '<b>Discover & explore</b>' ) );
$check( 200 === $response->get_status() && $video === (int) get_post_meta( $one, 'aloha_area_loop_video_id', true ), 'Administrator saves video through native REST' );
$check( 'https://www.youtube.com/watch?v=AlohaTest01' === get_post_meta( $one, 'aloha_area_film_url', true ), 'YouTube URL sanitized to canonical video link' );
$check( 'Discover & explore' === get_post_meta( $one, 'aloha_area_film_label', true ), 'Film label saves as plain sanitized text' );
$revisions = wp_get_post_revisions( $one ); $saved_revision = 0;
foreach ( $revisions as $revision ) { if ( 'https://www.youtube.com/watch?v=AlohaTest01' === get_post_meta( $revision->ID, 'aloha_area_film_url', true ) ) { $saved_revision = $revision->ID; break; } }
$check( $saved_revision > 0 && $video === (int) get_post_meta( $saved_revision, 'aloha_area_loop_video_id', true ), 'Native revisions capture media-only REST changes' );
$check( 'Discover & explore' === get_post_meta( $saved_revision, 'aloha_area_film_label', true ), 'Core revision captures the custom film label' );
$rest( array( 'aloha_area_loop_video_id' => 0, 'aloha_area_film_url' => '', 'aloha_area_film_label' => '' ) );
$check( 0 === (int) get_post_meta( $one, 'aloha_area_loop_video_id', true ) && '' === get_post_meta( $one, 'aloha_area_film_url', true ), 'Both media values can be removed' );
wp_restore_post_revision( $saved_revision );
$check( $video === (int) get_post_meta( $one, 'aloha_area_loop_video_id', true ) && 'https://www.youtube.com/watch?v=AlohaTest01' === get_post_meta( $one, 'aloha_area_film_url', true ), 'Core revision restore recovers both fields' );
$check( 'Discover & explore' === get_post_meta( $one, 'aloha_area_film_label', true ), 'Native revision restore also restores film label' );
$check( $body === get_post_field( 'post_content', $one ), 'Media save/remove/restore preserves native Area body' );
$check( 0 === (int) get_post_meta( $two, 'aloha_area_loop_video_id', true ) && '' === get_post_meta( $two, 'aloha_area_film_url', true ), 'Second Area has independent empty media' );
wp_set_current_user( 0 );
$check( $rest( array( 'aloha_area_film_url' => '' ) )->get_status() >= 401, 'Anonymous REST write denied' );
$auth = $registered['aloha_area_film_url']['auth_callback'];
$check( ! $auth( false, 'aloha_area_film_url', $one ), 'Meta capability rejects users without edit_post' );
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$context = new WP_Block( array( 'blockName' => 'aloha/area-hero', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'postId' => $one, 'postType' => 'area' ) );
$poster = get_option( 'aloha_area_presentation_fixtures' )['manifest']['media']['hero'];
set_post_thumbnail( $one, $poster );
$caption = get_the_post_thumbnail_caption( $one );
$html = aloha_area_hero( array(), '', $context );
$check( ! str_contains( $html, 'aloha-area-image-note' ) && $caption && $caption === get_the_post_thumbnail_caption( $one ), 'Frontend omits the reference badge while Media Library caption is preserved' );
$check( str_contains( $html, 'Discover &amp; explore' ) && ! str_contains( $html, '<b>' ), 'Custom film label is escaped in the Hero' );
$check( str_contains( $html, 'aria-label="Pause background video" title="Pause background video" hidden><svg' ), 'Background control is a hidden-by-default accessible icon button' );
$rest( array( 'aloha_area_film_label' => '' ) );
$check( str_contains( aloha_area_hero( array(), '', $context ), 'Watch the area film · 2 min' ), 'Cleared optional film label falls back to the default' );
$check( str_contains( $html, 'muted autoplay loop playsinline preload="none"' ) && str_contains( $html, 'data-aloha-loop-src=' ) && ! str_contains( $html, '<video src=' ), 'Video deferred until browser motion/data checks' );
$check( str_contains( $html, 'data-aloha-youtube="AlohaTest01"' ) && ! str_contains( $html, '<iframe' ), 'Validated film trigger without eager iframe' );
$check( str_contains( $html, 'Explore the area' ) && ! str_contains( $html, 'See 0 homes' ) && ! post_type_exists( 'rental' ), 'Neutral CTA without fake Rentals provider/count' );
$rest( array( 'aloha_area_loop_video_id' => 0, 'aloha_area_film_url' => '' ) );
$context->context['postId'] = $two;
$check( ! str_contains( aloha_area_hero( array(), '', $context ), '<video' ) && ! str_contains( aloha_area_hero( array(), '', $context ), 'data-aloha-youtube' ) && ! str_contains( aloha_area_hero( array(), '', $context ), 'aloha-area-video-toggle' ), 'Empty media leaves plain Hero and no dead film control' );
// Start the editor scenario like an existing Area with no stored label override.
delete_post_meta( $one, 'aloha_area_film_label' );
$manifest = array( 'area' => $one, 'other' => $two, 'url' => get_permalink( $one ), 'other_url' => get_permalink( $two ), 'checks' => $checks );
echo wp_json_encode( $manifest, JSON_PRETTY_PRINT );
