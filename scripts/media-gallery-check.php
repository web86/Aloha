<?php
/** Real WordPress gallery and explicit migration acceptance; disposable site only. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) ) { exit( 1 ); }
require_once __DIR__ . '/area-gallery-migration.php';
require_once __DIR__ . '/area-reference-content.php';
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_media_gallery_test'; $state = get_option( $key );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'rewrite_rules' ), get_option( 'aloha_content_area_routes' ), get_option( 'aloha_shell_menus' ), get_option( 'permalink_structure' ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
 foreach ( $state['media'] as $id ) { wp_delete_attachment( $id, true ); }
 if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'Gallery cleanup changed original content/settings.' ); }
 delete_option( $key ); WP_CLI::success( 'Gallery fixtures removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'Gallery fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array(), 'media' => array() ); update_option( $key, $state, false );
$checks = array();
$check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $type, $title, $body = '' ) use ( &$state, $key ) {
 $id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $body ), true );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state['posts'][] = $id; update_option( $key, $state, false ); return $id;
};
$base_media = get_option( 'aloha_area_presentation_fixtures' )['manifest']['media'];
$ids = array_map( fn( $name ) => $base_media[$name], array( 'bay', 'golf', 'hero', 'manta', 'whale' ) );
require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
// Real attachments with small images and responsive variants. Never imported into :8088.
for ( $i = 6; $i <= 24; $i++ ) {
 $path = wp_tempnam( 'gallery-test.jpg' ); $image = imagecreatetruecolor( 1200, 800 );
 imagefill( $image, 0, 0, imagecolorallocate( $image, 30 + $i * 5, 80 + $i * 3, 140 ) );
 imagestring( $image, 5, 40, 40, 'ISOLATED GALLERY FIXTURE ' . $i, imagecolorallocate( $image, 255, 255, 255 ) ); imagejpeg( $image, $path, 70 ); imagedestroy( $image );
 $id = media_handle_sideload( array( 'name' => 'gallery-test-' . $i . '.jpg', 'tmp_name' => $path ), 0, 'Gallery test ' . $i );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state['media'][] = $id; update_option( $key, $state, false ); $ids[] = $id;
 update_post_meta( $id, '_wp_attachment_image_alt', 'Isolated photo ' . $i );
}
$gallery_block = fn( $photos ) => '<!-- wp:aloha/media-gallery ' . wp_json_encode( array( 'ids' => $photos ) ) . ' /-->';
$five = $create( 'area', 'Gallery five photos', $gallery_block( array_slice( $ids, 0, 5 ) ) );
update_post_meta( $five, 'aloha_area_film_url', 'https://youtu.be/AlohaTest01' );
$many = $create( 'page', 'Universal gallery twenty four', $gallery_block( $ids ) . $gallery_block( array_slice( $ids, 5, 2 ) ) );
$empty = $create( 'area', 'Empty gallery', $gallery_block( array() ) );
$registry = WP_Block_Type_Registry::get_instance()->get_registered( 'aloha/media-gallery' );
$check( $registry && $registry->is_dynamic() && isset( $registry->attributes['ids'] ) && ! isset( $registry->attributes['urls'] ), 'Dynamic block registered with ID-only collection' );
$context = new WP_Block( array( 'blockName' => 'aloha/media-gallery', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'postId' => $five ) );
foreach ( array( 0, 1, 2, 4, 5, 6, 12, 24 ) as $n ) {
 $html = aloha_media_gallery_render( array( 'ids' => array_slice( $ids, 0, $n ) ), $context );
 $check( 0 === $n ? '' === $html : substr_count( $html, '<img ' ) === min( 5, $n ) && str_contains( $html, 'data-count="' . min( 5, $n ) . '"' ), 'Mosaic has no empty slots for ' . $n . ' photographs' );
 if ( $n ) {
  preg_match( '~class="aloha-gallery-data">(.*?)</script>~s', $html, $match ); $data = json_decode( $match[1], true );
  $check( array_column( $data, 'id' ) === array_slice( $ids, 0, $n ), 'Viewer preserves all ordered IDs for ' . $n . ' photos' );
 }
}
$html = aloha_media_gallery_render( array( 'ids' => $ids ), $context );
$check( str_contains( $html, '+19 photos' ) && str_contains( $html, 'View all photos <span>(24)' ), 'Visible count derives from the valid collection' );
$check( ! str_contains( $html, 'Design reference photograph' ) && str_contains( wp_get_attachment_caption( $ids[0] ), 'Design reference photograph' ), 'Editorial reference notice stays in Media Library without returning to the public viewer' );
$check( str_contains( $html, 'srcset=' ) && str_contains( $html, 'sizes=' ) && str_contains( $html, 'loading="lazy"' ) && ! str_contains( $html, '<iframe' ), 'Responsive lazy mosaic without eager iframe' );
$check( substr_count( $html, '<li><a href=' ) === 24 && str_contains( $html, '<noscript>' ), 'All originals have no-JavaScript accessible photo links' );
$check( str_contains( $html, 'data-aloha-youtube="AlohaTest01"' ), 'Gallery reuses the Area film URL' );
$context->context['postId'] = $many;
$check( ! str_contains( aloha_media_gallery_render( array( 'ids' => $ids ), $context ), 'data-aloha-youtube' ), 'Universal Page has no independent film field' );
$check( array( $ids[0] ) === aloha_media_gallery_ids( array( $ids[0], $ids[0], -1, '5', true ) ), 'Invalid and duplicate IDs normalize without changing order' );
$check( 1 === count( aloha_media_gallery_photos( array( 999999, $ids[0], $five ) ) ), 'Deleted or non-image attachments do not create slots' );
// Byte-preserving migration including native pre-migration revision and idempotence.
$legacy = '<!-- wp:group {"className":"aloha-area-gallery-section"} --><div class="wp-block-group aloha-area-gallery-section"><!-- wp:gallery {"columns":3,"linkTo":"none","className":"aloha-area-gallery"} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped aloha-area-gallery">';
foreach ( array_slice( $ids, 0, 5 ) as $id ) { $legacy .= \Aloha\AreaReference\image_block( $id ); }
$legacy .= '</figure><!-- /wp:gallery --></div><!-- /wp:group -->';
$body = "<!-- wp:paragraph --><p>Prefix — untouched</p><!-- /wp:paragraph -->\n" . $legacy . "\n<!-- wp:paragraph --><p>Suffix</p><!-- /wp:paragraph -->";
$migration = $create( 'area', 'Legacy migration fixture', $body );
$plan = aloha_area_gallery_migration_plan( $body );
$check( array_slice( $ids, 0, 5 ) === $plan['ids'] && substr_replace( $plan['content'], substr( $body, $plan['offset'], $plan['length'] ), $plan['offset'], strlen( $plan['replacement'] ) ) === $body, 'Migration preserves every byte outside the gallery' );
$meta_before = get_post_meta( $migration );
$rejected = false; try { aloha_area_gallery_migrate( $migration, $body . 'stale' ); } catch ( RuntimeException $e ) { $rejected = true; }
$check( $rejected && get_post_field( 'post_content', $migration ) === $body, 'A stale backup cannot overwrite the current Area' );
$result = aloha_area_gallery_migrate( $migration, $body );
$check( $meta_before === get_post_meta( $migration ), 'Migration preserves existing Area metadata' );
$check( $result['changed'] && get_post_field( 'post_content', $result['revision'] ) === $body, 'Migration creates a verified restorable original revision' );
$revision_count = count( wp_get_post_revisions( $migration ) );
$check( ! aloha_area_gallery_migrate( $migration, $body )['changed'] && count( wp_get_post_revisions( $migration ) ) === $revision_count, 'Repeated migration is a write-free no-op' );
$rejected = false; try { aloha_area_gallery_migration_plan( str_replace( 'alt="', 'alt="custom ', $body ) ); } catch ( RuntimeException $e ) { $rejected = true; }
$check( $rejected, 'Different inline alt requires controlled manual replacement' );
$rejected = false; try { aloha_area_gallery_migration_plan( $legacy . $legacy ); } catch ( RuntimeException $e ) { $rejected = true; }
$check( $rejected, 'Ambiguous legacy galleries cannot be overwritten' );
$legacy_id = $create( 'area', 'Unmigrated legacy gallery', $legacy );
$check( str_contains( do_blocks( $legacy ), 'aloha-area-gallery' ), 'Unmigrated native galleries still render' );
$fixture = array( 'checks' => $checks, 'ids' => $ids, 'five' => $five, 'many' => $many, 'empty' => $empty, 'legacy' => $legacy_id, 'five_url' => get_permalink( $five ), 'many_url' => get_permalink( $many ), 'empty_url' => get_permalink( $empty ), 'legacy_url' => get_permalink( $legacy_id ) );
WP_CLI::line( wp_json_encode( $fixture ) );
