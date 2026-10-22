<?php
/** Native template parts + neutral scroll/focus proof. No WordPress Page. */
defined( 'ABSPATH' ) || exit;
if ( ! aloha_is_shell_preview() ) { return; }
$shell_header = do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
$shell_footer = do_blocks( '<!-- wp:template-part {"slug":"footer"} /-->' );
?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class( 'aloha-shell-preview' ); ?>><?php wp_body_open(); ?>
<a class="aloha-skip-link" href="#shell-main">Skip to content</a><div class="wp-site-blocks">
<?php echo $shell_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered template part. ?>
<main id="shell-main" tabindex="-1" class="aloha-container">
<section class="aloha-section"><p class="aloha-eyebrow">M1.2 · LOCAL SHELL PREVIEW</p><h1>A shared frame<br>for what comes next.</h1><p>This is a navigation and scroll proof, with the actual Header and Footer template parts. It is not the home page.</p><div class="aloha-notice">Planned navigation destinations open a local explanation here. Canonical routes stay reserved for their future owners. No call, booking, payment or submission is made.</div></section>
<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
<section class="aloha-section" style="border-top:1px solid var(--aloha-color-surface-soft)"><p class="aloha-eyebrow">Scroll proof <?php echo (int) $i; ?></p><h2>Space to test the shell.</h2><p>Scroll the page to inspect the sticky header, then use the keyboard to reach the footer groups. Open a dropdown with its separate control: the parent destination remains a link.</p><p>Long labels and readable text should fit the container at narrow widths. The mobile quick actions reserve space below the content and hide while a field has focus.</p><a href="#shell-field">Continue to the field example</a></section>
<?php endfor; ?>
<section id="shell-field" class="aloha-section"><h2>Focus and keyboard proof.</h2><label for="shell-test-input">Sample field · no submission</label><input id="shell-test-input" placeholder="Focus here to test the bottom bar"><p><button type="button" disabled>Unavailable action</button></p></section>
</main>
<?php echo $shell_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered template part. ?>
</div><?php wp_footer(); ?></body></html>
