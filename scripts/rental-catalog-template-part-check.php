<?php
/** Only a disposable template-part override is written, never a Rental or existing override. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || home_url() !== 'http://127.0.0.1:8088') { exit(1); }
$existing = get_posts(['post_type'=>'wp_template_part','name'=>'catalog-help','post_status'=>'any','numberposts'=>1,
    'tax_query'=>[['taxonomy'=>'wp_theme','field'=>'name','terms'=>get_stylesheet()]]]);
$html = aloha_rental_catalog_render();
$assert = static function($value,$message){if(!$value)throw new RuntimeException($message);};
$assert(substr_count($html,'aloha-catalog-help')===1,'Exactly one editable CTA');
$assert(strpos($html,'aloha-catalog-help')>strpos($html,'data-catalog-empty'),'CTA follows the empty state');
$assert(strpos($html,'aloha-catalog-help')<strpos($html,'<aside class="aloha-catalog-map"'),'CTA belongs to Results before the map');
if ($existing) { WP_CLI::line('PASS: existing Site Editor override preserved; single CTA in Results'); return; }
$id=0;
try {
    $id=wp_insert_post(['post_type'=>'wp_template_part','post_name'=>'catalog-help','post_title'=>'Disposable catalog help override',
        'post_status'=>'publish','post_content'=>'<!-- wp:paragraph --><p>catalog-help-override-fixture</p><!-- /wp:paragraph -->'],true);
    $assert(is_int($id)&&$id>365,'Template fixture creation');
    $terms=wp_set_object_terms($id,get_stylesheet(),'wp_theme');$assert(!is_wp_error($terms),'Theme association');
    $html=aloha_rental_catalog_render();
    $assert(substr_count($html,'catalog-help-override-fixture')===1,'Site Editor content overrides the file part once');
    $assert(!str_contains($html,'Not sure which one?'),'No duplicated file CTA');
    WP_CLI::line('PASS: native template part, single CTA in Results, Site Editor database override');
} finally { if (is_int($id)&&$id>365) { wp_delete_post($id,true); } }
