<?php
/**
 * Read-only local WordPress smoke:
 * wp eval-file scripts/rental-template-smoke.php
 *
 * Requires the Aloha Hello theme and the M2.4A Bridge branch to be active.
 * Does not create/publish/sync/import a Rental or write to WordPress.
 */
defined( 'ABSPATH' ) || exit;

$errors = array();
$assert = static function ( bool $condition, string $message ) use ( &$errors ): void {
	if ( ! $condition ) { $errors[] = $message; }
};

$assert( wp_get_theme()->get_stylesheet() === 'aloha-hello', 'Activate Aloha Hello for this smoke.' );
$assert( class_exists( '\AlohaWPBridge\RentalPublicData' ), 'M2.4A Bridge class is missing.' );
$assert( function_exists( 'aloha_rental_detail_render' ), 'Rental theme renderer is missing.' );
$assert( is_file( get_theme_file_path( 'templates/single-rental.html' ) ), 'single-rental.html missing.' );
$template = file_get_contents( get_theme_file_path( 'templates/single-rental.html' ) );
$blocks = parse_blocks( $template ?: '' );
$names = array_column( $blocks, 'blockName' );
$assert( in_array( 'core/template-part', $names, true ), 'Template header/footer missing.' );
$assert( in_array( 'core/group', $names, true ), 'Template main wrapper missing.' );
$assert( (bool) preg_match( '/<!-- wp:aloha\/rental-detail \/-->/', $template ?: '' ), 'Rental dynamic block missing.' );

if ( class_exists( '\AlohaWPBridge\RentalPublicData' ) && post_type_exists( 'rental' ) ) {
	$posts = get_posts( array(
		'post_type' => 'rental', 'post_status' => array( 'draft', 'pending', 'publish' ),
		'posts_per_page' => 20, 'orderby' => 'ID', 'order' => 'ASC',
	) );
	$published = null;
	foreach ( $posts as $post ) {
		$dto = \AlohaWPBridge\RentalPublicData::get( (int) $post->ID );
		if ( 'publish' !== $post->post_status ) {
			$assert( null === $dto, 'Draft leaked from public DTO (ID ' . $post->ID . ').' );
		} elseif ( '' === $post->post_password && \AlohaWPBridge\Rentals::hasGuestyMapping( (int) $post->ID ) ) {
			$assert( is_array( $dto ), 'Published mapped Rental has no DTO (ID ' . $post->ID . ').' );
			$published ??= $post;
		}
	}
	if ( $published ) {
		$http = 0;
		$guard = static function ( $pre, $args, $url ) use ( &$http ) {
			if ( is_string( $url ) && str_contains( $url, 'guesty' ) ) {
				++$http;
				return new WP_Error( 'unexpected_guesty_request', 'Frontend attempted a provider request.' );
			}
			return $pre;
		};
		add_filter( 'pre_http_request', $guard, 10, 3 );
		global $post;
		$previous = $post;
		$post = $published;
		setup_postdata( $post );
		$html = do_blocks( '<!-- wp:aloha/rental-detail /-->' );
		wp_reset_postdata();
		$post = $previous;
		remove_filter( 'pre_http_request', $guard, 10 );
		$assert( $http === 0, 'Rental render contacted Guesty.' );
		$assert( str_contains( $html, 'aloha-rental-detail' ), 'Rental dynamic block returned no page.' );
		$assert( ! str_contains( $html, '_awb_source_snapshot' ), 'Internal source metadata leaked.' );
		echo "Rendered published Rental: " . $published->ID . " (no provider calls)\n";
	} else {
		echo "No published mapped Rental: skipped public HTML rendering. Test Draft via an authorized WordPress Preview URL.\n";
	}
}
if ( $errors ) {
	foreach ( $errors as $error ) { fwrite( STDERR, "FAIL: $error\n" ); }
	exit( 1 );
}
echo "PASS: Rental theme bootstrapping and public DTO read-only smoke\n";
