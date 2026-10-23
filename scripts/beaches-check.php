<?php
/** Real SSR/migration checks, exclusively on disposable WordPress. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) ) { exit( 1 ); }
require_once __DIR__ . '/area-beaches-migration.php';
require_once __DIR__ . '/area-reference-content.php';
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_beaches_test'; $state = get_option( $key );
$snapshot = fn() => array( 'posts' => get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), 'rewrite' => get_option( 'rewrite_rules' ), 'signature' => get_option( 'aloha_content_area_routes' ), 'menus' => get_option( 'aloha_shell_menus' ), 'permalink' => get_option( 'permalink_structure' ) );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( $snapshot() ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
 foreach ( $state['media'] as $id ) { wp_delete_attachment( $id, true ); }
 if ( $fingerprint() !== $state['baseline'] ) {
  $now = $snapshot(); $old_posts = array_column( $state['snapshot']['posts'], null, 'ID' ); $new_posts = array_column( $now['posts'], null, 'ID' );
  $diff = array( 'added' => array_values( array_diff( array_keys( $new_posts ), array_keys( $old_posts ) ) ), 'removed' => array_values( array_diff( array_keys( $old_posts ), array_keys( $new_posts ) ) ), 'changed' => array(), 'settings' => array(), 'added_details' => array() );
  foreach ( $diff['added'] as $id ) { $diff['added_details'][$id] = array( 'type' => $new_posts[$id]->post_type, 'status' => $new_posts[$id]->post_status, 'parent' => $new_posts[$id]->post_parent, 'title' => $new_posts[$id]->post_title ); }
  foreach ( $old_posts as $id => $post ) { if ( isset( $new_posts[$id] ) && $post != $new_posts[$id] ) { $diff['changed'][$id] = array_keys( array_diff_assoc( (array) $post, (array) $new_posts[$id] ) ); } }
  foreach ( array( 'rewrite', 'signature', 'menus', 'permalink' ) as $name ) { if ( $now[$name] !== $state['snapshot'][$name] ) { $diff['settings'][] = $name; } }
  WP_CLI::error( 'Beaches cleanup changed original content/settings: ' . wp_json_encode( $diff ) );
 }
 delete_option( $key ); WP_CLI::success( 'Beaches fixtures removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'Beaches fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'snapshot' => $snapshot(), 'posts' => array(), 'media' => array() ); update_option( $key, $state, false );
$checks = array();
$check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $title, $body ) use ( &$state, $key ) {
 $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $body ) ), true );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state['posts'][] = $id; update_option( $key, $state, false ); return $id;
};
$base_media = array_filter( get_option( 'aloha_area_presentation_fixtures' )['manifest']['media'], 'aloha_beaches_image_id' ); // Area regression deliberately deletes one of its own images.
require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ( array( array( 450, 1100 ), array( 1500, 450 ), array( 1000, 1000 ) ) as $i => list( $w, $h ) ) {
 $path = wp_tempnam( 'beaches-test.jpg' ); $image = imagecreatetruecolor( $w, $h );
 imagefill( $image, 0, 0, imagecolorallocate( $image, 50 + $i * 40, 110, 130 ) );
 imagefilledellipse( $image, (int) ( $w * .2 ), (int) ( $h * .3 ), (int) ( $w * .25 ), (int) ( $h * .25 ), imagecolorallocate( $image, 230, 185, 80 ) );
 imagestring( $image, 5, 30, 30, 'ISOLATED BEACH IMAGE ' . $i, imagecolorallocate( $image, 255, 255, 255 ) ); imagejpeg( $image, $path, 70 ); imagedestroy( $image );
 $id = media_handle_sideload( array( 'name' => 'beaches-test-' . $i . '.jpg', 'tmp_name' => $path ), 0, 'Beaches image fixture ' . $i );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state['media'][] = $id; update_option( $key, $state, false ); update_post_meta( $id, '_wp_attachment_image_alt', 'Isolated beach image ' . $i );
}
$cards = array();
for ( $i = 0; $i < 12; $i++ ) { $cards[] = array( 'title' => 'Editorial beach ' . ( $i + 1 ), 'description' => 0 === $i ? str_repeat( 'Long editorial description for layout testing. ', 10 ) : 'Verify local conditions and access before publishing.', 'imageId' => $i < 3 ? $state['media'][$i] : $base_media[ array_keys( $base_media )[ $i % count( $base_media ) ] ], 'url' => 0 === $i ? home_url( '/' ) : '', 'focalPoint' => array( 'x' => .2, 'y' => .3 ) ); }
$block = fn( $values ) => '<!-- wp:aloha/beaches ' . wp_json_encode( array( 'cards' => $values ) ) . ' /-->';
$wrap = fn( $html ) => '<!-- wp:group {"className":"aloha-container"} --><div class="wp-block-group aloha-container">' . $html . '</div><!-- /wp:group -->';
$urls = array();
foreach ( array( 0, 1, 4, 5, 8, 12 ) as $n ) {
 $html = aloha_beaches_render( array( 'cards' => array_slice( $cards, 0, $n ) ) );
 $check( substr_count( $html, '<article ' ) === $n && substr_count( $html, '<img ' ) === $n, 'SSR includes all ' . $n . ' cards and their images' );
 $check( ( $n > 4 ) === str_contains( $html, 'is-carousel' ) && ( $n > 4 ? str_contains( $html, 'aria-controls=' ) && str_contains( $html, 'controls" hidden' ) : ! str_contains( $html, 'data-beaches-direction' ) ), 'Grid/carousel progressive controls for ' . $n . ' cards' );
 $id = $create( 'Beaches count ' . $n, $wrap( $block( array_slice( $cards, 0, $n ) ) ) ); $urls[$n] = get_permalink( $id );
}
$registry = WP_Block_Type_Registry::get_instance()->get_registered( 'aloha/beaches' );
$check( $registry && $registry->is_dynamic() && isset( $registry->attributes['cards'] ) && ! isset( $registry->attributes['urls'] ), 'One dynamic collection with attachment IDs, no independent image URL list' );
$html = aloha_beaches_render( array( 'cards' => $cards ) );
$check( str_contains( $html, 'srcset=' ) && str_contains( $html, 'sizes=' ) && str_contains( $html, 'loading="lazy"' ) && str_contains( $html, 'object-position:20.00% 30.00%' ), 'Responsive lazy images and editorial focal point' );
$check( 2 === substr_count( $html, '<a href=' ), 'Only the explicitly linked card has image/title destinations' );
$bad = $cards[0]; $bad['imageId'] = 99999999; $bad['url'] = 'javascript:alert(1)'; $bad['title'] = '<script>Bad</script> & title'; $bad['description'] = '<script>alert(1)</script><em>Safe emphasis</em>';
$html = aloha_beaches_render( array( 'cards' => array( $bad ) ) );
$check( ! str_contains( $html, '<img' ) && str_contains( $html, 'is-text-only' ) && ! str_contains( $html, '<script' ) && ! str_contains( $html, '<a ' ) && str_contains( $html, '<em>Safe emphasis</em>' ), 'Deleted attachment and unsafe input render a safe text card' );
$check( array( 'x' => 0, 'y' => 1 ) === aloha_beaches_focal( array( 'x' => -1, 'y' => 9 ) ) && array( 'x' => .5, 'y' => .5 ) === aloha_beaches_focal( array( 'x' => 'invalid' ) ), 'Focal coordinates clamp and default safely' );
$check( '' === aloha_beaches_render( array( 'cards' => array( array() ) ) ) && '/editorial-destination/' === aloha_beaches_url( '/editorial-destination/' ), 'Empty card has no fake slot and relative editorial links work' );
$check( str_contains( aloha_area_pattern_content( 'area-beaches' ), '<!-- wp:aloha/beaches /-->' ) && ! str_contains( aloha_area_pattern_content( 'area-beaches' ), 'Beach to explore' ), 'Starter uses an empty editable Beaches collection' );
$legacy = '<!-- wp:group {"className":"aloha-area-beaches"} --><div class="wp-block-group aloha-area-beaches"><!-- wp:group {"className":"aloha-area-beach-grid"} --><div class="wp-block-group aloha-area-beach-grid">';
foreach ( array_slice( $cards, 0, 4 ) as $i => $card ) {
 $image = \Aloha\AreaReference\image_block( $card['imageId'] );
 $title = esc_html( $card['title'] );
 if ( $card['url'] ) { $title = '<a href="' . esc_url( $card['url'] ) . '">' . $title . '</a>'; }
 $legacy .= '<!-- wp:group {"className":"aloha-area-beach"} --><div class="wp-block-group aloha-area-beach"><!-- wp:group {"className":"aloha-area-photo"} --><div class="wp-block-group aloha-area-photo">' . $image . '</div><!-- /wp:group --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $title . '</h3><!-- /wp:heading --><!-- wp:paragraph --><p>' . $card['description'] . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
}
$legacy .= '</div><!-- /wp:group --></div><!-- /wp:group -->';
$body = "<!-- wp:paragraph --><p>Prefix — untouched</p><!-- /wp:paragraph -->\n" . $legacy . "\n<!-- wp:paragraph --><p>Suffix</p><!-- /wp:paragraph -->";
$migration = $create( 'Legacy Beaches migration', $body ); $plan = aloha_area_beaches_migration_plan( $body );
$check( substr_replace( $plan['content'], substr( $body, $plan['offset'], $plan['length'] ), $plan['offset'], strlen( $plan['replacement'] ) ) === $body, 'Migration preserves every byte outside the legacy grid' );
$check( array_column( $plan['cards'], 'imageId' ) === array_column( array_slice( $cards, 0, 4 ), 'imageId' ) && array_column( $plan['cards'], 'title' ) === array_column( array_slice( $cards, 0, 4 ), 'title' ) && $plan['cards'][0]['url'] === $cards[0]['url'] && $plan['cards'][0]['description'] === $cards[0]['description'], 'Migration retains image order, titles, descriptions and editorial destination' );
$meta = get_post_meta( $migration );
$rejected = false; try { aloha_area_beaches_migrate( $migration, $body . 'stale' ); } catch ( RuntimeException $e ) { $rejected = true; }
$check( $rejected && get_post_field( 'post_content', $migration ) === $body, 'Stale backup refuses all writes' );
$result = aloha_area_beaches_migrate( $migration, $body );
$check( $result['changed'] && get_post_field( 'post_content', $result['revision'] ) === $body && $meta === get_post_meta( $migration ), 'Verified pre-migration revision and unchanged metadata' );
$revisions = count( wp_get_post_revisions( $migration ) );
$check( ! aloha_area_beaches_migrate( $migration, $body )['changed'] && count( wp_get_post_revisions( $migration ) ) === $revisions, 'Repeated migration is a write-free no-op' );
foreach ( array( $legacy . $legacy, str_replace( 'alt="', 'alt="Changed ', $body ), str_replace( '<p>Verify local conditions', '<p><script>bad</script>Verify local conditions', $body ) ) as $i => $ambiguous ) {
 $rejected = false; try { aloha_area_beaches_migration_plan( $ambiguous ); } catch ( RuntimeException $e ) { $rejected = true; }
 $check( $rejected, 'Ambiguous/changed editorial content refuses migration: ' . $i );
}
// An empty native Image is a deliberate placeholder, never a fabricated attachment.
$empty_image = '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img alt=""/></figure><!-- /wp:image -->';
$missing_photo_body = str_replace( \Aloha\AreaReference\image_block( $cards[3]['imageId'] ), $empty_image, $body );
$check( 0 === aloha_area_beaches_migration_plan( $missing_photo_body )['cards'][3]['imageId'], 'Native empty photo placeholder migrates as a text-only card' );
$rejected = false; try { aloha_area_beaches_migration_plan( str_replace( '<div class="wp-block-group aloha-area-beach-grid">', '<div class="wp-block-group aloha-area-beach-grid">Extra editorial content', $body ) ); } catch ( RuntimeException $e ) { $rejected = true; }
$check( $rejected, 'Unexpected free HTML cannot be silently discarded' );
$check( '' === aloha_beaches_render( array( 'cards' => array( array( 'title' => array(), 'description' => array(), 'imageId' => 'not-an-id' ) ) ) ), 'Malformed attribute values produce no warnings or broken slots' );
$check( str_contains( do_blocks( $legacy ), 'aloha-area-beach-grid' ), 'Unmigrated native Beaches still render' );
$multiple = $create( 'Two independent Beaches carousels', $wrap( $block( $cards ) . $block( array_slice( $cards, 0, 8 ) ) ) );
$check( ! post_type_exists( 'beach' ) && ! taxonomy_exists( 'beach' ) && ! metadata_exists( 'post', $migration, 'aloha_beaches' ), 'No new content type, taxonomy or parallel post meta collection' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'cards' => $cards, 'urls' => $urls, 'media' => $state['media'], 'multiple_url' => get_permalink( $multiple ) ) ) );
