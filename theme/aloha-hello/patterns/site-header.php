<?php
/**
 * Title: Aloha site header
 * Slug: aloha-hello/site-header
 * Categories: header
 * Inserter: no
 */
defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"aloha-site-header","layout":{"type":"default"}} -->
<div class="wp-block-group aloha-site-header">
<!-- wp:group {"className":"aloha-container aloha-header-inner","layout":{"type":"default"}} -->
<div class="wp-block-group aloha-container aloha-header-inner">
<!-- wp:aloha/shell-slot {"slot":"brand"} /-->
<!-- wp:navigation <?php echo wp_json_encode( array( 'ref' => aloha_shell_menu_id( 'primary' ), 'overlayMenu' => 'mobile', 'overlayBackgroundColor' => 'page', 'overlayTextColor' => 'mirage', 'showSubmenuIcon' => true, 'submenuVisibility' => 'hover', 'className' => 'aloha-primary-nav', 'ariaLabel' => 'Main' ) ); ?> /-->
<!-- wp:aloha/shell-slot {"slot":"header-actions"} /-->
</div><!-- /wp:group -->
</div><!-- /wp:group -->
