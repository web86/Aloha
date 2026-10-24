<?php
/** Real SSR/editor-REST/migration fixtures, disposable WordPress only. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) ) { exit( 1 ); }
require_once __DIR__ . '/area-guide-highlights-migration.php';
require_once __DIR__ . '/area-reference-content.php';
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_guide_highlights_test'; $state = get_option( $key );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'aloha_content_guide_categories', null ), get_option( 'rewrite_rules' ), get_option( 'permalink_structure' ), get_option( 'aloha_content_area_routes' ), get_option( 'aloha_shell_menus' ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
 foreach ( $state['new_terms'] as $id ) { wp_delete_term( $id, 'category' ); }
 if ( null === $state['categories'] ) { delete_option( 'aloha_content_guide_categories' ); } else { update_option( 'aloha_content_guide_categories', $state['categories'] ); }
 if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'Guide Highlights cleanup changed original content/settings.' ); }
 delete_option( $key ); WP_CLI::success( 'Guide Highlights fixtures removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'Guide Highlights fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array(), 'categories' => get_option( 'aloha_content_guide_categories', null ), 'new_terms' => array() ); update_option( $key, $state, false );
$old_terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) );
$map = \Aloha\Content\guide_terms(); if ( is_wp_error( $map ) ) { $map = \Aloha\Content\install_guide_categories(); }
if ( is_wp_error( $map ) ) { WP_CLI::error( 'Disposable Guide categories unavailable.' ); }
$state['new_terms'] = array_values( array_diff( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) ), $old_terms ) ); update_option( $key, $state, false );
$checks = array(); $check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $type, $title, $extra = array() ) use ( &$state, $key ) {
 $id = wp_insert_post( wp_slash( array_merge( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'publish' ), $extra ) ), true ); if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state['posts'][] = $id; update_option( $key, $state, false ); return $id;
};
$base_media = get_option( 'aloha_area_presentation_fixtures' )['manifest']['media']; $valid_media = array_values( array_filter( $base_media, 'aloha_beaches_image_id' ) );
$media = array_map( fn( $key ) => aloha_beaches_image_id( $base_media[$key] ?? 0 ) ?: $valid_media[0], array( 'hero', 'bay', 'golf', 'manta', 'whale', 'luau', 'food', 'lava' ) );
$titles = array( 'Kalahuipua‘a fishponds & historic trail', 'Puakō petroglyphs', 'Golf on lava-lined fairways', 'Night snorkel with manta rays', 'Humpback whales, Dec–Apr', 'Sunset dinner & luau', 'Fresh poke & local food', 'Volcanoes National Park' );
$descriptions = array( '[Ancient Hawaiian fishponds, an easy shaded walk, turtles in the lagoon] · [5] min walk', '[Thousands of rock carvings on a 20-minute trail. Go early, it gets hot] · [5] min', 'Francis H. I‘i Brown North & South · [2] min', 'From Kona harbor · [40] min', 'From the shore or a boat · right here', '[Canoe House / Brown’s Beach House] · [5] min', '[Foodland Farms, food trucks] · [8] min', 'A full day trip · [~2.5 h]' );
$ids = array();
foreach ( $titles as $i => $title ) { $id = $create( 'post', $title, array( 'post_category' => array( $map['rubrics']['things-to-do'] ), 'post_excerpt' => $descriptions[$i], 'post_content' => '<!-- wp:paragraph --><p>Isolated design fixture; not verified travel advice.</p><!-- /wp:paragraph -->' ) ); set_post_thumbnail( $id, $media[$i % count( $media )] ); $ids[] = $id; }
$attrs = array( 'featuredIds' => array_slice( $ids, 0, 2 ), 'additionalIds' => array_slice( $ids, 2, 6 ) );
$block = fn( $a ) => '<!-- wp:aloha/guide-highlights ' . wp_json_encode( $a ) . ' /-->';
$wrap = fn( $content ) => aloha_area_group( 'aloha-area-section aloha-area-activities aloha-area-guide-section', aloha_area_group( 'aloha-container', aloha_area_heading( 'Things to see and do <em>in Mauna Lani</em>' ) . $content . aloha_area_paragraph( 'Each tile opens a Big Island Guide article with tips, times and photos.', 'aloha-guide-note' ) ), 'section' );
$page = $create( 'area', 'Guide Highlights visual fixture', array( 'post_content' => $wrap( $block( $attrs ) ) ) );
$editor = $create( 'page', 'Guide Highlights editor fixture', array( 'post_status' => 'draft', 'post_content' => '<!-- wp:paragraph --><p>Block insertion fixture.</p><!-- /wp:paragraph -->' ) );
$registry = WP_Block_Type_Registry::get_instance()->get_registered( 'aloha/guide-highlights' );
$check( $registry && $registry->is_dynamic() && isset( $registry->attributes['featuredIds'], $registry->attributes['additionalIds'] ) && ! isset( $registry->attributes['title'], $registry->attributes['imageId'], $registry->attributes['url'] ), 'Dynamic block stores only ordered Post IDs' );
$html = aloha_guide_highlights_render( $attrs );
$check( 8 === substr_count( $html, 'class="aloha-guide-card' ) && 8 === substr_count( $html, '<img ' ), 'SSR includes 2 featured and 6 additional images/articles' );
$check( str_contains( $html, 'srcset=' ) && str_contains( $html, 'sizes=' ) && str_contains( $html, 'loading="lazy"' ) && str_contains( $html, esc_url( get_permalink( $ids[0] ) ) ), 'Responsive lazy images and canonical Guide links' );
$check( '' === aloha_guide_highlights_render( array() ), 'Empty selection renders no empty cards' );
$check( array( 'featuredIds' => array( $ids[0], $ids[1] ), 'additionalIds' => array( $ids[2], $ids[3], $ids[4], $ids[5], $ids[6], $ids[7] ) ) === aloha_guide_highlights_ids( array( 'featuredIds' => array( $ids[0], $ids[0], -1, 'invalid', $ids[1], $ids[2] ), 'additionalIds' => $ids ) ), 'Limits, positive integer validation and cross-group deduplication' );
$original = get_post( $ids[0] ); $old_image = get_post_thumbnail_id( $ids[0] );
wp_update_post( array( 'ID' => $ids[0], 'post_title' => 'Updated Guide heading', 'post_name' => 'updated-guide-heading', 'post_excerpt' => 'Updated excerpt from the original post.' ) ); set_post_thumbnail( $ids[0], $media[1] );
$item = aloha_guide_highlight( $ids[0] ); $check( 'Updated Guide heading' === $item['title'] && str_contains( $item['url'], 'updated-guide-heading' ) && 'Updated excerpt from the original post.' === $item['excerpt'] && (int) $media[1] === $item['imageId'], 'Title, slug, excerpt and Featured Image update without block resave' );
wp_set_post_categories( $ids[0], array( $map['rubrics']['food-and-drink'] ) ); $check( str_contains( aloha_guide_highlight( $ids[0] )['url'], '/food-and-drink/' ), 'Permalink follows route rubric changes without stored URL' );
wp_set_post_categories( $ids[0], array( $map['rubrics']['things-to-do'] ) ); wp_update_post( array( 'ID' => $ids[0], 'post_title' => $original->post_title, 'post_name' => $original->post_name, 'post_excerpt' => $original->post_excerpt ) ); set_post_thumbnail( $ids[0], $old_image );
$bad = array();
foreach ( array( 'draft', 'private', 'trash' ) as $status ) { $id = $create( 'post', 'Unavailable ' . $status, array( 'post_status' => $status, 'post_category' => array( $map['rubrics']['things-to-do'] ) ) ); $bad[] = $id; $check( null === aloha_guide_highlight( $id ), 'Excluded public card: ' . $status ); }
$ordinary = $create( 'post', 'Ordinary non Guide' ); $bad[] = $ordinary; $check( null === aloha_guide_highlight( $ordinary ), 'Ordinary Post excluded from Guide selection' );
$investor = \Aloha\Content\investor_category(); if ( ! is_wp_error( $investor ) ) { $id = $create( 'post', 'Investor Highlights fixture', array( 'post_category' => array( $investor ) ) ); $bad[] = $id; $check( null === aloha_guide_highlight( $id ), 'Investor article excluded' ); }
$deleted = $create( 'post', 'Deleted Guide fixture', array( 'post_category' => array( $map['rubrics']['things-to-do'] ) ) ); wp_delete_post( $deleted, true ); $bad[] = $deleted;
$check( null === aloha_guide_highlight( $deleted ) && null === aloha_guide_highlight( $page ), 'Deleted post and non-Post type excluded' );
$password = $create( 'post', 'Password Guide fixture', array( 'post_password' => 'fixture-only', 'post_category' => array( $map['rubrics']['things-to-do'] ) ) ); $bad[] = $password; $check( null === aloha_guide_highlight( $password ), 'Password-protected post never leaks through public cards/search' );
wp_set_post_categories( $ids[0], array( 1 ) ); $check( null === aloha_guide_highlight( $ids[0] ), 'Removing Guide membership hides selected article immediately' ); wp_set_post_categories( $ids[0], array( $map['rubrics']['things-to-do'] ) );
$missing = $create( 'post', 'Guide without photo', array( 'post_category' => array( $map['rubrics']['things-to-do'] ) ) );
$check( str_contains( aloha_guide_highlights_render( array( 'featuredIds' => array( $missing ) ) ), 'has-no-image' ), 'Missing Featured Image has a clean linked fallback' );
$attachment = $create( 'attachment', 'Missing physical image', array( 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) ); update_attached_file( $attachment, '/var/www/html/wp-content/uploads/nonexistent-guide-fixture.jpg' ); update_post_meta( $missing, '_thumbnail_id', $attachment );
$check( 0 === aloha_guide_highlight( $missing )['imageId'], 'Broken/deleted physical attachment never produces broken image' );
$request = new WP_REST_Request( 'GET', '/aloha/v1/guide-highlights' ); $request->set_param( 'search', 'Kalahuipua' ); $response = rest_do_request( $request );
$check( 200 === $response->get_status() && array( $ids[0] ) === array_column( $response->get_data()['items'], 'id' ), 'Native authenticated search returns matching valid Guide article' );
$request->set_param( 'include', array_slice( $bad, 0, 8 ) ); $response = rest_do_request( $request ); $check( ! array_filter( $response->get_data()['items'], fn( $i ) => $i['available'] || isset( $i['title'] ) ), 'Unavailable editor selections are marked without leaking private titles' );
wp_set_current_user( 0 ); $check( in_array( rest_do_request( new WP_REST_Request( 'GET', '/aloha/v1/guide-highlights' ) )->get_status(), array( 401, 403 ), true ), 'Anonymous users cannot access editor search' ); wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$categories_backup = get_option( 'aloha_content_guide_categories' ); update_option( 'aloha_content_guide_categories', array() ); $check( null === aloha_guide_highlight( $ids[0] ) && false === rest_do_request( new WP_REST_Request( 'GET', '/aloha/v1/guide-highlights' ) )->get_data()['configured'], 'Missing category configuration fails closed without mutation' ); update_option( 'aloha_content_guide_categories', $categories_backup );
$check( str_contains( aloha_area_pattern_content( 'area-activities' ), '<!-- wp:aloha/guide-highlights /-->' ) && ! str_contains( aloha_area_pattern_content( 'area-activities' ), 'An island experience' ), 'Starter replaces only legacy activity cards with the new block' );
// Controlled legacy conversion with exact editorial equivalence.
$legacy_groups = array();
foreach ( array( 'featuredIds' => 'aloha-area-activity-featured', 'additionalIds' => 'aloha-area-activity-grid' ) as $group => $class ) {
 $legacy_cards = '';
 foreach ( $attrs[$group] as $id ) { $item = aloha_guide_highlight( $id ); $legacy_cards .= aloha_area_group( 'aloha-area-activity', aloha_area_group( 'aloha-area-photo', \Aloha\AreaReference\image_block( $item['imageId'] ) ) . aloha_area_group( 'aloha-area-activity-caption', aloha_area_heading( esc_html( $item['title'] ), 3 ) . aloha_area_paragraph( esc_html( $item['excerpt'] ) ) ) ); }
 $legacy_groups[] = aloha_area_group( $class, $legacy_cards );
}
$legacy = '<!-- wp:paragraph --><p>Before — untouched.</p><!-- /wp:paragraph -->' . $wrap( implode( '', $legacy_groups ) ) . '<!-- wp:paragraph --><p>After.</p><!-- /wp:paragraph -->';
$legacy_id = $create( 'area', 'Guide migration fixture', array( 'post_content' => $legacy ) ); $plan = aloha_guide_migration_plan( $legacy, $attrs );
$check( substr_replace( $plan['content'], substr( $legacy, $plan['offset'], $plan['length'] ), $plan['offset'], strlen( $plan['replacement'] ) ) === $legacy, 'Migration preserves all bytes outside the two legacy grids' );
$reject = false; try { aloha_guide_migrate( $legacy_id, $attrs, 'stale' ); } catch ( RuntimeException $e ) { $reject = true; } $check( $reject && $legacy === get_post_field( 'post_content', $legacy_id ), 'Stale backup refuses writes' );
$lock_rejected = false; update_post_meta( $legacy_id, '_edit_lock', time() . ':1' ); try { aloha_guide_migrate( $legacy_id, $attrs, $legacy ); } catch ( RuntimeException $e ) { $lock_rejected = true; } delete_post_meta( $legacy_id, '_edit_lock' ); $check( $lock_rejected && get_post_field( 'post_content', $legacy_id ) === $legacy, 'Active Core editor lock refuses concurrent migration' );
$meta = get_post_meta( $legacy_id ); $done = aloha_guide_migrate( $legacy_id, $attrs, $legacy );
$check( $done['changed'] && $legacy === get_post_field( 'post_content', $done['revision'] ) && get_post_meta( $legacy_id ) === $meta, 'Exact native revision precedes migration; editorial metadata preserved' );
$revision_count = count( wp_get_post_revisions( $legacy_id ) ); $check( ! aloha_guide_migrate( $legacy_id, $attrs, $legacy )['changed'] && count( wp_get_post_revisions( $legacy_id ) ) === $revision_count, 'Repeated conversion is write-free and idempotent' );
foreach ( array( str_replace( 'Kalahuipua', 'User edited Kalahuipua', $legacy ), str_replace( $legacy_groups[1], '<!-- wp:paragraph --><p>Extra content</p><!-- /wp:paragraph -->' . $legacy_groups[1], $legacy ), str_replace( 'class="aloha-area-activity-featured"', 'class="aloha-area-activity-featured"', $legacy ) . $legacy ) as $i => $ambiguous ) { $reject = false; try { aloha_guide_migration_plan( $ambiguous, $attrs ); } catch ( RuntimeException $e ) { $reject = true; } $check( $reject, 'Unsafe editorial conversion is refused: ' . $i ); }
$empty = $create( 'area', 'Empty Guide section', array( 'post_content' => $wrap( $block( array() ) ) ) );
$fewer = $create( 'area', 'Fewer selected highlights', array( 'post_content' => $wrap( $block( array( 'featuredIds' => array( $ids[0] ), 'additionalIds' => array( $ids[2], $missing ) ) ) ) ) );
$long = $create( 'post', str_repeat( 'Long editorial title ', 12 ), array( 'post_category' => array( $map['rubrics']['things-to-do'] ), 'post_excerpt' => str_repeat( 'Long excerpt for text enlargement. ', 30 ) ) ); set_post_thumbnail( $long, $media[0] );
$long_page = $create( 'area', 'Long Guide text', array( 'post_content' => $wrap( $block( array( 'featuredIds' => array( $long ), 'additionalIds' => array( $long ) ) ) ) ) );
$unavailable = $create( 'page', 'Unavailable selected Guide', array( 'post_status' => 'draft', 'post_content' => $block( array( 'featuredIds' => array( $bad[0] ), 'additionalIds' => array( $ordinary, $deleted ) ) ) ) );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'attrs' => $attrs, 'ids' => $ids, 'titles' => $titles, 'media' => $media, 'editor' => $editor, 'visual_url' => get_permalink( $page ), 'empty_url' => get_permalink( $empty ), 'fewer_url' => get_permalink( $fewer ), 'long_url' => get_permalink( $long_page ), 'unavailable_editor' => $unavailable, 'missing' => $missing, 'bad' => $bad ) ) );
