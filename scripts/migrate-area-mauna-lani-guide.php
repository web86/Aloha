<?php
/** wp eval-file ... plan|apply /private/mapping.json [/private/backup.html] --user=<editor> */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
if ( 'local' !== wp_get_environment_type() || 'http://127.0.0.1:8088' !== untrailingslashit( home_url() ) || ! current_user_can( 'edit_post', 68 ) || 'area' !== get_post_type( 68 ) || 'mauna-lani' !== get_post_field( 'post_name', 68 ) ) { WP_CLI::error( 'Local Mauna Lani CLI with edit capability required.' ); }
require_once __DIR__ . '/area-guide-highlights-migration.php';
$mode = $args[0] ?? 'plan'; $mapping = ! empty( $args[1] ) && is_readable( $args[1] ) ? json_decode( file_get_contents( $args[1] ), true ) : null;
if ( ! in_array( $mode, array( 'plan', 'apply' ), true ) || ! is_array( $mapping ) ) { WP_CLI::error( 'Use plan|apply mapping.json [backup.html]. No writes performed.' ); }
try {
 $body = get_post_field( 'post_content', 68 ); $plan = aloha_guide_migration_plan( $body, $mapping );
 if ( 'plan' === $mode ) { unset( $plan['content'], $plan['replacement'] ); WP_CLI::line( wp_json_encode( $plan ) ); }
 else { if ( empty( $args[2] ) || ! is_readable( $args[2] ) ) { throw new RuntimeException( 'An exact private post_content backup is required.' ); } WP_CLI::line( wp_json_encode( aloha_guide_migrate( 68, $mapping, file_get_contents( $args[2] ) ) ) ); }
} catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
