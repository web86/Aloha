<?php
/** wp eval-file ... plan|apply /private/read-only/body-backup. No automatic migration. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || 'http://127.0.0.1:8088' !== untrailingslashit( home_url() ) ) { exit( 1 ); }
if ( ! current_user_can( 'edit_post', 68 ) ) { WP_CLI::error( 'Use --user with permission to edit Area 68.' ); }
require_once __DIR__ . '/area-faq-migration.php';
$post = get_post( 68 );
if ( ! $post || 'area' !== $post->post_type || 'mauna-lani' !== $post->post_name ) { WP_CLI::error( 'Expected local Mauna Lani Area 68.' ); }
try {
	if ( 'apply' === ( $args[0] ?? '' ) ) {
		$backup = isset( $args[1] ) && is_readable( $args[1] ) ? file_get_contents( $args[1] ) : false;
		if ( false === $backup ) { throw new RuntimeException( 'A readable, verified post_content backup is required.' ); }
		$result = aloha_area_faq_migrate( 68, $backup );
	} else { $result = aloha_area_faq_migration_plan( $post->post_content ); unset( $result['content'] ); }
	WP_CLI::line( wp_json_encode( $result ) );
} catch ( Throwable $e ) { WP_CLI::error( $e->getMessage() ); }
