<?php
/** Real WordPress rendering and theme styles; no database page or navigation entry. */
defined( 'ABSPATH' ) || exit;
if ( 'local' !== wp_get_environment_type() || ! function_exists( 'aloha_is_style_showcase' ) || ! aloha_is_style_showcase() ) {
	return;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'aloha-showcase' ); ?>>
<?php wp_body_open(); ?>
<a class="aloha-skip-link" href="#main">Skip to design system</a>
<div class="wp-site-blocks">
<main id="main" class="aloha-container aloha-section" tabindex="-1">
<?php
$content = file_get_contents( __DIR__ . '/showcase-content.html' );
echo do_blocks( str_replace( '{{THEME_URL}}', esc_url( get_theme_file_uri() ), $content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted local source, rendered by core blocks.
?>
</main>
</div>
<?php wp_footer(); ?>
</body>
</html>
