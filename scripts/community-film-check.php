<?php
/** Community film data and render acceptance, disposable WordPress only. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_community_film_test'; $state = get_option( $key );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'rewrite_rules' ), get_option( 'aloha_content_area_routes' ), get_option( 'aloha_shell_menus' ), get_option( 'permalink_structure' ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
 require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $state['users'] as $id ) { wp_delete_user( $id ); }
 if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'Community film cleanup changed baseline content/settings.' ); }
 delete_option( $key ); WP_CLI::success( 'Community film fixtures removed; baseline content, meta and routing preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'Community film fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array(), 'users' => array() );
$save = function () use ( &$state, $key ) { update_option( $key, $state, false ); }; $save();
$checks = array(); $check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { WP_CLI::error( $label ); } $checks[] = $label; };
$create = function ( $type, $title, $extra = array() ) use ( &$state, $save ) {
 $id = wp_insert_post( wp_slash( array_merge( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'publish' ), $extra ) ), true );
 if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
 $state['posts'][] = $id; $save(); return $id;
};
$area = $create( 'area', 'Mauna Lani' );
$other_area = $create( 'area', 'Film relation validation area' );
$media = get_option( 'aloha_area_presentation_fixtures' )['manifest']['media'];
$body = WP_Block_Patterns_Registry::get_instance()->get_registered( 'aloha-hello/community-start' )['content'];
$one = $create( 'community', 'Ka Milo', array( 'post_content' => $body, 'post_excerpt' => 'Larger villas in a gated, walkable community with its own two-acre pool club. Vacation rentals by Aloha Hello.', 'meta_input' => array( 'aloha_area_id' => $area ) ) );
$two = $create( 'community', 'Independent film community', array( 'post_content' => $body, 'meta_input' => array( 'aloha_area_id' => $area ) ) );
$empty = $create( 'community', 'Community without film', array( 'post_content' => $body, 'meta_input' => array( 'aloha_area_id' => $area ) ) );
set_post_thumbnail( $one, $media['hero'] );
$rest = function ( $fields, $id = null ) use ( $one ) { $r = new WP_REST_Request( 'POST', '/wp/v2/community' . ( false === $id ? '' : '/' . ( $id ?? $one ) ) ); $r->set_body_params( $fields ); return rest_do_request( $r ); };
$snapshot = fn( $id ) => array( get_post( $id )->to_array(), get_post_meta( $id ), array_keys( wp_get_post_revisions( $id ) ) );
$registered = get_registered_meta_keys( 'post', 'community' );
foreach ( array( 'aloha_community_film_url', 'aloha_community_film_label' ) as $field ) {
 $check( 'string' === $registered[$field]['type'] && $registered[$field]['show_in_rest'] && $registered[$field]['revisions_enabled'], 'Registered optional string REST/revision field: ' . $field );
 $check( '' === get_post_meta( $one, $field, true ) && ! metadata_exists( 'post', $one, $field ), 'Default empty without backfilling: ' . $field );
}
foreach ( array( 'https://youtu.be/AlohaTest01?si=x', 'https://www.youtube.com/watch?v=AlohaTest01&t=20', 'http://m.youtube.com/shorts/AlohaTest01', 'https://youtube.com/embed/AlohaTest01', 'https://youtube.com/live/AlohaTest01' ) as $url ) {
 $check( ! Aloha\Content\community_media_error( array( 'aloha_community_film_url' => $url ) ) && 'https://www.youtube.com/watch?v=AlohaTest01' === Aloha\Content\sanitize_area_film_url( $url ), 'Established YouTube validator/canonicalization accepts: ' . $url );
}
foreach ( array( 'javascript:alert(1)', 'https://youtube.com.evil.test/watch?v=AlohaTest01', 'https://user@youtube.com/watch?v=AlohaTest01', 'https://youtube.com:443/watch?v=AlohaTest01', 'https://youtube.com/watch?v[]=AlohaTest01', 'https://youtube.com/playlist?list=AlohaTest01', '<iframe src="https://youtube.com"></iframe>', 'https://example.test/video', true, 1, array(), null ) as $value ) {
 usleep( 1100000 ); $before = $snapshot( $one );
 $response = $rest( array( 'title' => 'Must not be written', 'content' => 'Must not be written', 'excerpt' => 'Must not be written', 'meta' => array( 'aloha_area_id' => $other_area, 'aloha_community_film_url' => $value, 'aloha_community_film_label' => 'Must not be written' ) ) );
 $check( 400 === $response->get_status() && $before === $snapshot( $one ), 'Invalid URL rejected atomically before post, relation, meta and revision writes: ' . wp_json_encode( $value ) );
}
$before = $snapshot( $one );
$response = $rest( array( 'title' => 'Must not change', 'meta' => array( 'aloha_community_film_url' => 'https://youtu.be/AlohaTest01', 'aloha_community_film_label' => array( 'invalid' ) ) ) );
$check( 400 === $response->get_status() && $before === $snapshot( $one ), 'Invalid label type rejected atomically' );
$count = wp_count_posts( 'community' );
$response = $rest( array( 'title' => 'Must not be created', 'status' => 'draft', 'meta' => array( 'aloha_community_film_url' => 'https://example.test/video' ) ), false );
$check( 400 === $response->get_status() && $count == wp_count_posts( 'community' ), 'Invalid film refuses creation of a new Community' );
$response = $rest( array( 'meta' => array( 'aloha_community_film_url' => 'https://youtu.be/AlohaTest01?si=discard', 'aloha_community_film_label' => '<b>Discover & explore</b>' ) ) );
$check( 200 === $response->get_status() && 'https://www.youtube.com/watch?v=AlohaTest01' === get_post_meta( $one, 'aloha_community_film_url', true ) && 'Discover & explore' === get_post_meta( $one, 'aloha_community_film_label', true ), 'Administrator saves canonical URL and sanitized plain-text label' );
$revision = 0; foreach ( wp_get_post_revisions( $one ) as $r ) { if ( 'https://www.youtube.com/watch?v=AlohaTest01' === get_post_meta( $r->ID, 'aloha_community_film_url', true ) && 'Discover & explore' === get_post_meta( $r->ID, 'aloha_community_film_label', true ) ) { $revision = $r->ID; break; } }
$check( $revision > 0, 'Native revision captures media-only URL and label edits' );
$rest( array( 'meta' => array( 'aloha_community_film_url' => '', 'aloha_community_film_label' => '' ) ) );
$check( '' === get_post_meta( $one, 'aloha_community_film_url', true ), 'URL can be removed without removing the label field' );
wp_restore_post_revision( $revision );
$check( 'https://www.youtube.com/watch?v=AlohaTest01' === get_post_meta( $one, 'aloha_community_film_url', true ) && 'Discover & explore' === get_post_meta( $one, 'aloha_community_film_label', true ), 'Native revision restore recovers both film fields' );
$check( $body === get_post_field( 'post_content', $one ) && $area === (int) get_post_meta( $one, 'aloha_area_id', true ), 'Save/remove/revision restore preserves Gutenberg body and Area' );
$before = $snapshot( $one );
$deny = fn() => false; add_filter( 'auth_post_meta_aloha_community_film_url_for_community', $deny, 100 );
$response = $rest( array( 'title' => 'Not authorized', 'meta' => array( 'aloha_community_film_url' => '' ) ) );
remove_filter( 'auth_post_meta_aloha_community_film_url_for_community', $deny, 100 );
$check( 403 === $response->get_status() && $before === $snapshot( $one ), 'Meta-specific permission denial occurs before title/meta changes' );
foreach ( array( 'subscriber', 'author' ) as $role ) {
 $user = wp_insert_user( array( 'user_login' => 'community_film_' . $role, 'user_pass' => wp_generate_password(), 'role' => $role ) ); $state['users'][] = $user; $save();
 wp_set_current_user( $user );
 $check( ! current_user_can( 'edit_post_meta', $one, 'aloha_community_film_url' ) && $rest( array( 'meta' => array( 'aloha_community_film_url' => '' ) ) )->get_status() >= 401 && $before === $snapshot( $one ), $role . ' cannot edit another author’s Community film' );
}
wp_set_current_user( 0 );
$check( $rest( array( 'meta' => array( 'aloha_community_film_url' => '' ) ) )->get_status() >= 401, 'Anonymous REST write refused' );
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$before = $snapshot( $one );
$result = wp_update_post( array( 'ID' => $one, 'post_title' => 'Must not change', 'meta_input' => array( 'aloha_community_film_url' => 'https://example.test/not-youtube' ) ), true );
$check( is_wp_error( $result ) && $before === $snapshot( $one ), 'Core wp_update_post(meta_input) rejects invalid film before post writes' );
$render = fn( $id ) => aloha_community_hero( array(), '', new WP_Block( array( 'blockName' => 'aloha/community-hero', 'attrs' => array() ), array( 'postId' => $id, 'postType' => 'community' ) ) );
$check( str_contains( $render( $one ), 'Discover &amp; explore' ) && ! str_contains( $render( $one ), '<b>' ) && ! str_contains( $render( $one ), '<iframe' ), 'Custom label escaped; film trigger has no eager iframe' );
$rest( array( 'meta' => array( 'aloha_community_film_label' => '' ) ) );
$check( str_contains( $render( $one ), 'Ka Milo in 90 seconds' ), 'Empty override uses exact PNG label for the current title' );
wp_update_post( array( 'ID' => $one, 'post_title' => 'Renamed film community' ) );
$check( str_contains( $render( $one ), 'Renamed film community in 90 seconds' ), 'Default label follows current Community title, without hardcoded Ka Milo copy' );
wp_update_post( array( 'ID' => $one, 'post_title' => 'Ka Milo' ) );
$rest( array( 'meta' => array( 'aloha_community_film_url' => 'https://youtu.be/AlohaTest02', 'aloha_community_film_label' => 'Second community film' ) ), $two );
$check( str_contains( $render( $one ), 'data-aloha-youtube="AlohaTest01"' ) && str_contains( $render( $two ), 'data-aloha-youtube="AlohaTest02"' ), 'Two Communities use independent films' );
update_post_meta( $area, 'aloha_area_film_url', 'https://youtu.be/AlohaTest03' );
wp_update_post( array( 'ID' => $area, 'post_content' => '<!-- wp:aloha/media-gallery ' . wp_json_encode( array( 'ids' => array( $media['hero'], $media['bay'] ) ) ) . ' /-->' ) );
set_post_thumbnail( $area, $media['hero'] );
$check( ! str_contains( $render( $empty ), 'aloha-community-film' ) && ! str_contains( $render( $empty ), 'data-aloha-youtube' ), 'Missing Community URL hides control entirely; Area film is never inherited' );
// Intentional corrupt legacy metadata bypasses validation only in the disposable DB.
global $wpdb;
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $empty, 'meta_key' => 'aloha_community_film_url', 'meta_value' => 'https://example.test/legacy-invalid' ) ); wp_cache_delete( $empty, 'post_meta' );
$check( ! str_contains( $render( $empty ), 'data-aloha-youtube' ), 'Renderer also refuses a corrupt legacy URL' );
delete_post_meta( $empty, 'aloha_community_film_url' );
$no_image = $create( 'community', 'Ka Milo', array( 'post_content' => $body, 'post_excerpt' => get_post_field( 'post_excerpt', $one ), 'meta_input' => array( 'aloha_area_id' => $area, 'aloha_community_film_url' => 'https://youtu.be/AlohaTest01' ) ) );
echo wp_json_encode( array( 'one' => $one, 'two' => $two, 'empty' => $empty, 'url' => get_permalink( $one ), 'other_url' => get_permalink( $two ), 'empty_url' => get_permalink( $empty ), 'no_image_url' => get_permalink( $no_image ), 'area_url' => get_permalink( $area ), 'checks' => $checks ), JSON_PRETTY_PRINT );
