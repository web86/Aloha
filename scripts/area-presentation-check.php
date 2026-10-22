<?php
/** Area template/pattern/server tests; only the existing disposable WordPress. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
require_once __DIR__ . '/area-reference-content.php';
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_area_presentation_fixtures';
$state = get_option( $key, array() );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'aloha_shell_menus' ), get_option( 'aloha_shell_contact' ), get_option( 'aloha_shell_mega_menu' ), get_option( 'permalink_structure' ), get_option( 'aloha_content_area_routes' ) ) ) );
$phase = $args[0] ?? 'setup';
if ( in_array( $phase, array( 'prepare-switch', 'verify-other-theme', 'verify-restored-theme' ), true ) ) {
	$body = get_post_field( 'post_content', $state['manifest']['area'] );
	if ( 'prepare-switch' === $phase ) {
		$state['saved_body'] = $body; update_option( $key, $state, false );
	} else {
		if ( $body !== $state['saved_body'] ) { WP_CLI::error( 'Theme switch changed saved Area content.' ); }
		if ( 'verify-other-theme' === $phase && get_post_type_object( 'area' )->template ) { WP_CLI::error( 'Theme-specific starter leaked into another theme.' ); }
		if ( 'verify-restored-theme' === $phase ) {
			$registry = WP_Block_Patterns_Registry::get_instance();
			$registry->unregister( 'aloha-hello/area-start' );
			$registry->register( 'aloha-hello/area-start', array( 'title' => 'Changed pattern fixture', 'content' => '<!-- wp:paragraph --><p>Updated starter fixture.</p><!-- /wp:paragraph -->' ) );
			if ( get_post_field( 'post_content', $state['manifest']['area'] ) !== $body || str_contains( do_blocks( $body ), 'Updated starter fixture.' ) ) { WP_CLI::error( 'Pattern update changed saved content.' ); }
		}
	}
	WP_CLI::success( 'Saved Area content preserved: ' . $phase ); return;
}
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
	foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
	foreach ( $state['media'] as $id ) { wp_delete_attachment( $id, true ); }
	if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'Area presentation cleanup changed original content/settings.' ); }
	delete_option( $key ); WP_CLI::success( 'Area presentation fixtures and media removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'Area presentation fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array(), 'media' => array() );
$checks = array();
$check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$save = function () use ( &$state, $key ) { update_option( $key, $state, false ); };
$create = function ( $type, $title, $status = 'publish', $meta = array() ) use ( &$state, $save ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => $status, 'meta_input' => $meta, 'post_excerpt' => 'Isolated published editorial fixture.' ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$state['posts'][] = $id; $save(); return $id;
};
$save();
$area = $create( 'area', 'Area presentation fixture' );
$other = $create( 'area', 'Other area presentation fixture' );
$state['media'] = \Aloha\AreaReference\import_media( $area ); $save();
$media = $state['media'];
set_post_thumbnail( $area, $media['hero'] );
$content = \Aloha\AreaReference\content( $media );
wp_update_post( array( 'ID' => $area, 'post_content' => $content ) );
$communities = array();
foreach ( array( 'Fixture community A', 'Fixture community B', 'Fixture community C' ) as $title ) {
	$id = $create( 'community', $title, 'publish', array( 'aloha_area_id' => $area ) ); $communities[] = $id;
	set_post_thumbnail( $id, $media['bay'] );
}
$create( 'community', 'Hidden draft community', 'draft', array( 'aloha_area_id' => $area ) );
$create( 'community', 'Other area community', 'publish', array( 'aloha_area_id' => $other ) );
$context = new WP_Block( array( 'blockName' => 'aloha/area-communities', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'postId' => $area, 'postType' => 'area' ) );
$html = aloha_area_communities( array(), '', $context );
$check( 3 === substr_count( $html, '<article ' ) && ! str_contains( $html, 'Hidden draft' ) && ! str_contains( $html, 'Other area community' ), 'Community selection includes exactly current Area published relations' );
foreach ( $communities as $id ) { $check( str_contains( $html, esc_url( get_permalink( $id ) ) ) && str_contains( $html, get_the_title( $id ) ), 'Community cards use real title and nested permalink' ); }
$check( str_contains( $html, 'srcset=' ) && str_contains( $html, 'loading="lazy"' ), 'Community images are responsive and lazy-loaded' );
$empty = clone $context; $empty->context['postId'] = $other;
$check( str_contains( aloha_area_communities( array(), '', $empty ), 'Other area community' ), 'Another Area resolves its own data' );
$empty->context['postId'] = $create( 'area', 'Empty area presentation fixture' );
$empty_html = aloha_area_communities( array(), '', $empty );
$check( str_contains( $empty_html, 'as they are published' ) && ! str_contains( $empty_html, '<article ' ), 'Empty communities state has no invented cards or counts' );
$hero = aloha_area_hero( array(), '', $context );
$check( str_contains( $hero, 'Area presentation fixture' ) && str_contains( $hero, 'fetchpriority="high"' ) && str_contains( $hero, 'loading="eager"' ) && str_contains( $hero, 'srcset=' ), 'Hero uses current Area title, responsive eager featured image' );
$check( ! str_contains( aloha_area_hero( array(), '', $empty ), '<img' ), 'Missing featured image produces a safe text hero' );
$check( get_post_type_object( 'area' )->template === array( array( 'core/pattern', array( 'slug' => 'aloha-hello/area-start' ) ) ) && false === get_post_type_object( 'area' )->template_lock, 'Native unlocked CPT template references the starter pattern' );
$registry = WP_Block_Patterns_Registry::get_instance();
foreach ( array( 'start', 'introduction', 'quick-facts', 'gallery', 'beaches', 'activities', 'practical-advice', 'faq', 'owner-cta' ) as $part ) { $check( $registry->is_registered( 'aloha-hello/area-' . $part ), 'Native pattern registered: ' . $part ); }
$check( ! str_contains( aloha_area_pattern_content( 'area-start' ), 'Mauna Lani' ), 'Starter has no Mauna Lani copy or saved reference attachment IDs' );
$check( '' === render_block( parse_blocks( '<!-- wp:aloha/area-rentals /-->' )[0] ) && ! post_type_exists( 'rental' ), 'Rental slot is empty publicly and no rental CPT/provider exists' );
$template = get_block_template( get_stylesheet() . '//single-area', 'wp_template' );
$check( $template && str_contains( $template->content, 'aloha/area-hero' ) && str_contains( $template->content, 'wp:post-content' ), 'Native single-area.html contains contextual hero and editable Post Content' );
wp_update_post( array( 'ID' => $area, 'post_title' => 'Renamed area presentation fixture', 'post_name' => 'area-presentation-fixture' ) );
$check( $content === get_post_field( 'post_content', $area ), 'Title/slug changes preserve saved body' );
$state['manifest'] = array( 'area' => $area, 'other' => $other, 'empty' => $empty->context['postId'], 'url' => get_permalink( $area ), 'media' => $media, 'communities' => $communities, 'checks' => $checks ); $save();
echo wp_json_encode( $state['manifest'], JSON_PRETTY_PRINT );
