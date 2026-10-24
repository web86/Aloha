<?php
/** Disposable Community foundation fixtures and server acceptance. Never seed :8088. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_community_foundation_test';
$state = get_option( $key, array() );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'rewrite_rules' ), get_option( 'aloha_content_area_routes' ), get_option( 'permalink_structure' ), get_option( 'aloha_shell_menus' ) ) ) );
$phase = $args[0] ?? 'setup';
if ( 'cleanup' === $phase ) {
	foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
	if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'Community cleanup changed baseline content/settings.' ); }
	delete_option( $key ); WP_CLI::success( 'Community fixtures removed; baseline content, relations and routing preserved.' ); return;
}
if ( 'setup' !== $phase ) {
	$body = get_post_field( 'post_content', $state['visual'] );
	if ( $body !== $state['body'] ) { WP_CLI::error( 'Theme switch changed saved Community content.' ); }
	if ( 'other-theme' === $phase && get_post_type_object( 'community' )->template ) { WP_CLI::error( 'Community starter leaked to another theme.' ); }
	if ( 'restored-theme' === $phase ) {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$registry->unregister( 'aloha-hello/community-start' );
		$registry->register( 'aloha-hello/community-start', array( 'title' => 'Changed pattern', 'content' => '<!-- wp:paragraph --><p>Updated starter fixture.</p><!-- /wp:paragraph -->' ) );
		if ( get_post_field( 'post_content', $state['visual'] ) !== $body || str_contains( do_blocks( $body ), 'Updated starter fixture.' ) ) { WP_CLI::error( 'Pattern update changed saved Community.' ); }
	}
	WP_CLI::success( 'Community saved bytes preserved: ' . $phase ); return;
}
if ( $state ) { WP_CLI::error( 'Community fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array() );
$save = function () use ( &$state, $key ) { update_option( $key, $state, false ); };
$checks = array();
$check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $type, $title, $status = 'publish', $meta = array(), $body = '' ) use ( &$state, $save ) {
	$id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => $status, 'meta_input' => $meta, 'post_content' => $body ) ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$state['posts'][] = $id; $save(); return $id;
};
$save();
$registry = WP_Block_Patterns_Registry::get_instance();
$pattern = $registry->get_registered( 'aloha-hello/community-start' );
$body = $pattern['content'] ?? '';
$check( $body && 'core/group' === parse_blocks( $body )[0]['blockName'], 'Native community-start pattern registered with ordinary Core blocks' );
$check( ! preg_match( '/Ka Milo|Mauna Lani|"id"|wp:pattern|aloha\//', $body ), 'Starter is generic, unsynced and has no property copy, images or integration blocks' );
$check( 4 === substr_count( $body, '<section ' ), 'Starter has four editable editorial foundations, without implementing future components' );
$check( get_post_type_object( 'community' )->template === array( array( 'core/pattern', array( 'slug' => 'aloha-hello/community-start' ) ) ) && false === get_post_type_object( 'community' )->template_lock, 'Community uses unlocked native CPT block template' );
$template = get_block_template( get_stylesheet() . '//single-community', 'wp_template' );
$check( $template && str_contains( $template->content, 'aloha/community-hero' ) && str_contains( $template->content, 'wp:post-content' ) && 2 === substr_count( $template->content, 'wp:template-part' ), 'Native Community template contains shared header/footer, one dynamic hero and Post Content' );
$area = $create( 'area', 'Mauna Lani' );
$other = $create( 'area', 'Another island area' );
$editor_area = $create( 'area', 'Community editor area' ); // Unique beside the preceding Area visual fixtures.
$visual = $create( 'community', 'Ka Milo', 'publish', array( 'aloha_area_id' => $area ), $body );
$intro = 'Larger villas in a gated, walkable community with its own two-acre pool club. Vacation rentals by Aloha Hello.';
wp_update_post( array( 'ID' => $visual, 'post_excerpt' => $intro ) );
$media = get_option( 'aloha_area_presentation_fixtures' )['manifest']['media']['hero'];
set_post_thumbnail( $visual, $media );
$empty = $create( 'community', 'Ka Milo', 'publish', array( 'aloha_area_id' => $area ), $body );
wp_update_post( array( 'ID' => $empty, 'post_excerpt' => $intro ) );
$long = $create( 'community', str_repeat( 'A very long community name ', 5 ) . str_repeat( 'Unbroken', 22 ), 'publish', array( 'aloha_area_id' => $other ), $body );
wp_update_post( array( 'ID' => $long, 'post_excerpt' => str_repeat( 'A longer editorial introduction with practical details for guests. ', 14 ) ) );
set_post_thumbnail( $long, $media );
$draft = $create( 'community', 'Unassigned community', 'draft' );
$render = fn( $id ) => aloha_community_hero( array(), '', new WP_Block( array( 'blockName' => 'aloha/community-hero', 'attrs' => array() ), array( 'postId' => $id, 'postType' => get_post_type( $id ) ) ) );
$html = $render( $visual );
$check( str_contains( $html, '<h1>Ka Milo <em>at Mauna Lani</em></h1>' ) && str_contains( $html, $intro ), 'Hero uses current Community title/excerpt and selected Area title' );
$preview = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/aloha/community-hero' );
$preview->set_param( 'post_id', $visual );
$preview->set_param( 'context', 'edit' );
$preview_response = rest_do_request( $preview );
$preview_ok = 200 === $preview_response->get_status() && str_contains( $preview_response->get_data()['rendered'] ?? '', '<h1>Ka Milo <em>at Mauna Lani</em></h1>' );
if ( ! $preview_ok ) { WP_CLI::error( 'Block-renderer response: ' . wp_json_encode( $preview_response->get_data() ) ); }
$check( $preview_ok, 'Native authenticated block-renderer receives Community context and renders the same Hero' );
$check( str_contains( $html, esc_url( get_permalink( $area ) ) ) && str_contains( $html, 'aria-current="page"' ) && preg_match( '/Home.*Big Island.*Mauna Lani.*Ka Milo/s', $html ), 'Breadcrumb is Home → Big Island → linked Area → current Community' );
$check( str_contains( $html, 'srcset=' ) && str_contains( $html, 'sizes=' ) && str_contains( $html, 'loading="eager"' ) && str_contains( $html, 'fetchpriority="high"' ), 'Featured image uses responsive WP srcset/sizes and eager high priority loading' );
$check( ! str_contains( $render( $empty ), '<img' ) && str_contains( $render( $empty ), 'Community photo' ), 'No-image state has neutral gradient panel and no broken image' );
$check( ! str_contains( $render( $draft ), '<em>at ' ) && ! str_contains( $render( $draft ), 'aloha-community-hero-intro' ), 'Unassigned draft has no invented Area or excerpt derived from starter' );
$check( '' === $render( $area ), 'Hero refuses non-Community context' );
$check( ! str_contains( $html, '[N]' ) && ! str_contains( $html, 'iframe' ) && ! str_contains( $html, 'in 90 seconds' ) && 2 === substr_count( $html, 'data-aloha-info=' ), 'Both approved CTA roles exist without invented count, booking, owner form or video' );
$rules = get_option( 'rewrite_rules' );
update_post_meta( $visual, 'aloha_area_id', $other );
$check( str_contains( get_permalink( $visual ), '/another-island-area/' ) && str_contains( $render( $visual ), 'at Another island area' ) && ! str_contains( $render( $visual ), 'Mauna Lani' ), 'Changing only aloha_area_id updates title, breadcrumb and nested permalink' );
$check( $rules === get_option( 'rewrite_rules' ) && $body === get_post_field( 'post_content', $visual ), 'Changing Area preserves exact Gutenberg content and routing rules' );
update_post_meta( $visual, 'aloha_area_id', $area );
wp_update_post( array( 'ID' => $visual, 'post_title' => 'Renamed community', 'post_excerpt' => 'Updated introduction.' ) );
$check( str_contains( $render( $visual ), '<h1>Renamed community' ) && str_contains( $render( $visual ), 'Updated introduction.' ) && $body === get_post_field( 'post_content', $visual ), 'Post field edits update dynamic Hero without rewriting body' );
wp_update_post( array( 'ID' => $visual, 'post_title' => 'Ka Milo', 'post_excerpt' => $intro ) );
$saved_context = new WP_Block_Editor_Context( array( 'post' => get_post( $visual ) ) );
$check( array() === apply_filters( 'block_editor_settings_all', array( 'template' => get_post_type_object( 'community' )->template ), $saved_context )['template'], 'Saved documents, including intentionally empty ones, are not reseeded' );
$state['visual'] = $visual; $state['body'] = $body; $save();
echo wp_json_encode( array( 'visual' => $visual, 'visual_url' => get_permalink( $visual ), 'empty_url' => get_permalink( $empty ), 'long_url' => get_permalink( $long ), 'area' => $area, 'area_title' => get_the_title( $area ), 'editor_area' => $editor_area, 'editor_area_title' => get_the_title( $editor_area ), 'other' => $other, 'other_title' => get_the_title( $other ), 'media' => $media, 'template' => $template->content, 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ) ), JSON_PRETTY_PRINT );
