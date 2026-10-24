<?php
/** Native Core SSR and conservative conversion acceptance; disposable WordPress only. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) ) { exit( 1 ); }
require_once __DIR__ . '/area-faq-migration.php';
wp_set_current_user( get_user_by( 'login', 'aloha_test_admin' )->ID );
$key = 'aloha_faq_test'; $state = get_option( $key );
$fingerprint = fn() => hash( 'sha256', wp_json_encode( array( get_posts( array( 'post_type' => array( 'post', 'page', 'area', 'community', 'attachment', 'wp_navigation' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ), get_option( 'rewrite_rules' ), get_option( 'aloha_content_area_routes' ), get_option( 'aloha_shell_menus' ), get_option( 'permalink_structure' ) ) ) );
if ( 'cleanup' === ( $args[0] ?? '' ) ) {
 foreach ( array_reverse( $state['posts'] ) as $id ) { wp_delete_post( $id, true ); }
 if ( $fingerprint() !== $state['baseline'] ) { WP_CLI::error( 'FAQ cleanup changed original content/settings.' ); }
 delete_option( $key ); WP_CLI::success( 'FAQ fixtures removed; original content/settings preserved.' ); return;
}
if ( $state ) { WP_CLI::error( 'FAQ fixtures already exist.' ); }
$state = array( 'baseline' => $fingerprint(), 'posts' => array() ); update_option( $key, $state, false );
$checks = array(); $check = function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$create = function ( $title, $body, $status = 'publish' ) use ( &$state, $key ) {
 $id = wp_insert_post( wp_slash( array( 'post_type' => 'area', 'post_status' => $status, 'post_title' => $title, 'post_content' => $body ) ), true );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $state['posts'][] = $id; update_option( $key, $state, false ); return $id;
};
foreach ( array( 'core/accordion', 'core/accordion-item', 'core/accordion-heading', 'core/accordion-panel' ) as $name ) {
 $check( WP_Block_Type_Registry::get_instance()->is_registered( $name ), 'Installed Core block registered: ' . $name );
}
$answer = "\n" . aloha_area_paragraph( 'An <strong>editorial answer</strong> with an <a href="' . esc_url( home_url( '/?faq-link=verified' ) ) . '">editorial link</a>.' ) . "\n" . '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>First checked point</li><!-- /wp:list-item --><!-- wp:list-item --><li>Second point<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>A nested <em>detail</em></li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --></ul><!-- /wp:list -->' . "\n" . aloha_area_group( 'faq-answer-group', aloha_area_paragraph( 'A second paragraph, retained verbatim.' ) );
$faq = fn( $items ) => aloha_area_group( 'aloha-area-faq', aloha_area_paragraph( 'Questions guests ask', 'aloha-eyebrow' ) . aloha_area_faq_accordion( $items ) );
$wrap = fn( $content ) => aloha_area_group( 'aloha-area-section aloha-area-advice-section', aloha_area_group( 'aloha-container aloha-area-advice-grid', aloha_area_pattern_content( 'area-practical-advice' ) . $content ), 'section' );
$urls = array(); $bodies = array(); $ids = array();
foreach ( array( 0, 1, 5, 10, 20 ) as $n ) {
 $items = ''; for ( $i = 0; $i < $n; $i++ ) { $items .= aloha_area_faq_item( 'Editorial question ' . ( $i + 1 ), $answer ); }
 $body = $wrap( $faq( $items ) ); $bodies[$n] = $body;
 $html = do_blocks( $body );
 $check( substr_count( $html, 'type="button" class="wp-block-accordion-heading__toggle"' ) === $n && substr_count( $html, 'class="wp-block-accordion-panel' ) === $n, 'Core SSR emits all ' . $n . ' questions and answer panels' );
 $check( ! $n || str_contains( $html, 'data-wp-interactive="core/accordion"' ) && str_contains( $html, 'aria-controls=' ) && str_contains( $html, 'aria-labelledby=' ) && ! str_contains( $html, ' hidden' ), 'Core Interactivity/ARIA and readable pre-JavaScript answers for ' . $n . ' questions' );
 $id = $create( 'FAQ count ' . $n, $body ); $urls[$n] = get_permalink( $id ); $ids[$n] = $id;
}
$check( 1 === substr_count( aloha_area_pattern_content( 'area-faq' ), '<!-- wp:accordion-item ' ) && ! str_contains( aloha_area_pattern_content( 'area-faq' ), 'wp:details' ) && str_contains( aloha_area_pattern_content( 'area-faq' ), 'Editorial question' ), 'Starter uses one clear editorial placeholder and no Details or invented facts' );
$check( ! str_contains( aloha_area_pattern_content( 'area-faq' ), 'templateLock' ) && ! str_contains( aloha_area_pattern_content( 'area-faq' ), '"lock"' ), 'Theme imposes no FAQ quantity/content lock' );
$legacy_details = '';
for ( $i = 0; $i < 5; $i++ ) { $legacy_details .= '<!-- wp:details' . ( $i ? '' : ' {"showContent":true}' ) . ' --><details class="wp-block-details"' . ( $i ? '' : ' open' ) . '><summary>Question <em>' . ( $i + 1 ) . '</em> &amp; context</summary>' . $answer . '</details><!-- /wp:details -->' . "\n"; }
$legacy = aloha_area_group( 'aloha-area-faq', aloha_area_paragraph( 'Questions guests ask', 'aloha-eyebrow' ) . $legacy_details );
$body = "<!-- wp:paragraph --><p>Prefix — unchanged</p><!-- /wp:paragraph -->\n" . $legacy . "\n<!-- wp:paragraph --><p>Suffix</p><!-- /wp:paragraph -->";
$plan = aloha_area_faq_migration_plan( $body );
$check( $plan['questions'] === 5 && $plan['titles'][0] === 'Question <em>1</em> &amp; context' && count( array_filter( $plan['answers'], fn( $raw ) => $raw === $answer ) ) === 5, 'Migration preserves all titles, inline formatting and exact nested answer bytes' );
$check( substr_replace( $plan['content'], substr( $body, $plan['offset'], $plan['length'] ), $plan['offset'], strlen( $plan['replacement'] ) ) === $body, 'Every byte outside Details question range is unchanged, including FAQ label/wrappers' );
$check( str_contains( $plan['replacement'], '"openByDefault":true' ) && 1 === substr_count( $plan['replacement'], 'is-open' ), 'Original independent/open Details state maps to Core openByDefault' );
$migration = $create( 'FAQ migration fixture', $body ); $meta = get_post_meta( $migration );
$rejected = false; try { aloha_area_faq_migrate( $migration, $body . 'stale' ); } catch ( RuntimeException $e ) { $rejected = true; }
$check( $rejected && get_post_field( 'post_content', $migration ) === $body, 'Stale backup refuses to write the Area' );
$result = aloha_area_faq_migrate( $migration, $body );
$check( $result['changed'] && get_post_field( 'post_content', $result['revision'] ) === $body && $meta === get_post_meta( $migration ), 'Verified original revision and unchanged Area meta' );
$revisions = count( wp_get_post_revisions( $migration ) );
$check( ! aloha_area_faq_migrate( $migration, $body )['changed'] && count( wp_get_post_revisions( $migration ) ) === $revisions, 'Repeat conversion is a write-free no-op' );
$empty = aloha_area_group( 'aloha-area-faq', aloha_area_paragraph( 'Questions guests ask', 'aloha-eyebrow' ) );
$check( 0 === aloha_area_faq_migration_plan( $empty )['questions'] && ! aloha_area_faq_migration_plan( aloha_area_faq_migration_plan( $empty )['content'] )['changed'], 'Empty legacy FAQ converts without invented questions and remains idempotent' );
foreach ( array( str_replace( '<em>', '<em data-custom="keep">', $legacy ), $legacy . $legacy, str_replace( '<details class=', '<details data-custom="keep" class=', $legacy ), str_replace( '<summary>', '<summary data-custom="keep">', $legacy ), str_replace( '<summary>Question', '<summary><a href="/">Question</a>', $legacy ), str_replace( $answer, '<!-- wp:html --><div>Custom answer HTML</div><!-- /wp:html -->', $legacy ), str_replace( '<!-- wp:details -->', '<!-- wp:details {"anchor":"custom"} -->', $legacy ) ) as $i => $unsupported ) {
 $rejected = false; try { aloha_area_faq_migration_plan( $unsupported ); } catch ( RuntimeException $e ) { $rejected = true; }
 $check( $rejected, 'Ambiguous/custom/unsupported FAQ refuses migration before writing: ' . $i );
}
$check( str_contains( do_blocks( $legacy ), '<details ' ), 'Saved legacy Details remain supported and are never automatically rewritten' );
// A normal Area editor, with rich standard content inside the first Accordion panel.
$editor_body = str_replace( aloha_area_pattern_content( 'area-faq' ), $faq( aloha_area_faq_item( 'Editorial <em>question</em>', $answer ) ), aloha_area_pattern_content( 'area-start' ) );
$editor = $create( 'FAQ Gutenberg fixture', $editor_body, 'draft' );
$unscoped = $create( 'Scoped accordion styles fixture', $wrap( $faq( aloha_area_faq_item( 'Scoped question', $answer ) ) ) . aloha_area_group( 'aloha-container faq-unscoped', aloha_area_faq_accordion( aloha_area_faq_item( 'Unscoped question', $answer ) ) ) );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'urls' => $urls, 'ids' => $ids, 'editor' => $editor, 'editor_url' => get_permalink( $editor ), 'scoped_url' => get_permalink( $unscoped ), 'bodies' => $bodies, 'version' => get_bloginfo( 'version' ) ) ) );
