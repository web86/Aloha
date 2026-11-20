<?php
/** Extends the existing synthetic Rental fixture; never touches real Rentals. */
use AlohaWPBridge\{RentalEditor, RentalCatalogData, RentalListingBadge};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || home_url() !== 'http://127.0.0.1:8088') { exit(1); }
[$action, $marker] = $args + ['', ''];
if (!preg_match('/\Am24-fixture-[a-f0-9]{16}\z/', $marker)) { exit(1); }
$modes = ['public', 'empty', 'draft', 'related-gallery', 'related-single', 'related-none', 'related-other', 'related-private', 'related-unmapped'];
$posts = get_posts(['post_type' => 'rental', 'post_status' => ['publish', 'draft', 'private'], 'numberposts' => -1,
    'post_name__in' => array_map(static fn($mode) => $marker . '-' . $mode, $modes)]);
$available = static fn($value) => ['status' => 'available', 'value' => $value];
$items = [];
foreach ($posts as $post) {
    if ($post->ID <= 365 || !str_starts_with($post->post_name, $marker . '-')) { exit(1); }
    $mode = substr($post->post_name, strlen($marker) + 1);
    if ($action === 'prepare') {
        $snapshot = get_post_meta($post->ID, '_awb_source_snapshot', true);
        $snapshot = array_replace($snapshot, ['active' => $mode !== 'related-other', 'isListed' => true, 'beVisible' => true]);
        RentalEditor::writeSourceMeta($post->ID, '_awb_source_snapshot', $snapshot);
        $data = get_post_meta($post->ID, '_awb_source_data', true) ?: ['version' => 1, 'source' => []];
        $beds = match ($mode) { 'related-gallery' => 4, 'related-single' => 2, 'empty' => null, default => 3 };
        $capacity = match ($mode) { 'related-gallery' => 10, 'related-single' => 4, 'related-none' => 6, 'empty' => null, default => 8 };
        $data['source']['listing'] = ['bedrooms' => $beds === null ? ['status' => 'absent', 'value' => null] : $available($beds),
            'bathrooms' => $available(2.5), 'accommodates' => $capacity === null ? ['status' => 'unconfirmed', 'value' => null] : $available($capacity)];
        $data['source']['houseRules']['suitableForChildren'] = $mode === 'public' ? ['status' => 'unconfirmed', 'value' => null] : $available($mode !== 'related-gallery');
        $data['source']['minimumNightlyPrice'] = ['status' => 'available', 'amount' => '345.00', 'currency' => 'USD',
            'periodStart' => gmdate('Y-m-d'), 'periodEnd' => gmdate('Y-m-d', time() + 30 * DAY_IN_SECONDS), 'calculatedAt' => gmdate('Y-m-d\TH:i:s\Z')];
        if ($mode === 'related-single') { $data['staleSections']['minimumNightlyPrice'] = true; }
        if ($mode === 'empty') { unset($data['source']['minimumNightlyPrice']); }
        if ($mode === 'related-none' || $mode === 'empty') {
            unset($data['source']['publicLocation']); delete_post_meta($post->ID, AlohaWPBridge\RentalPresentation::MAP);
        }
        RentalEditor::writeSourceMeta($post->ID, '_awb_source_data', $data);
        update_post_meta($post->ID, RentalListingBadge::META, match ($mode) { 'related-gallery' => 'new', 'related-single' => 'luxe', default => 'none' });
    }
    $dto = RentalCatalogData::get($post->ID);
    $items[$mode] = ['id' => $post->ID, 'public' => $dto !== null];
}
if ($action === 'prepare') { WP_CLI::line(wp_json_encode($items)); return; }
if ($action !== 'check') { exit(1); }
$checks = 0;
$check = static function ($value, $message) use (&$checks) { ++$checks; if (!$value) { throw new RuntimeException($message); } };
$check($items['public']['public'] && !$items['draft']['public'] && !$items['related-private']['public'] && !$items['related-unmapped']['public'] && !$items['related-other']['public'], 'Publication exclusions');
$areas = aloha_rental_catalog_areas();
$check(aloha_rental_catalog_filters(['area_id' => [], 'bedrooms' => ['3'], 'adults' => '-2', 'children' => '999999999999'], $areas) === ['area_id' => 0, 'bedrooms' => '', 'adults' => 0, 'children' => 0], 'Invalid parameter normalization');
$fields = ['area' => 11, 'bedrooms' => 3, 'guests' => 8, 'children' => null];
$base = ['area_id' => 0, 'bedrooms' => '', 'adults' => 0, 'children' => 0];
foreach ([[$base, true], [array_replace($base, ['area_id' => 11, 'bedrooms' => '3', 'adults' => 6, 'children' => 2]), true],
    [array_replace($base, ['area_id' => 12]), false], [array_replace($base, ['bedrooms' => '2']), false],
    [array_replace($base, ['bedrooms' => '4']), false], [array_replace($base, ['adults' => 9]), false]] as [$filters, $expected]) {
    $check(aloha_rental_catalog_matches($fields, $filters) === $expected, 'Filter combination parity');
}
$check(!aloha_rental_catalog_matches(array_replace($fields, ['children' => false]), array_replace($base, ['children' => 1])), 'Confirmed child restriction');
$check(!aloha_rental_catalog_matches(array_replace($fields, ['bedrooms' => null]), array_replace($base, ['bedrooms' => '3'])), 'Unknown bedrooms');
$check(!aloha_rental_catalog_matches(array_replace($fields, ['guests' => null]), array_replace($base, ['adults' => 1])), 'Unknown capacity');
$http = 0; $guard = static function ($pre) use (&$http) { ++$http; return new WP_Error('catalog_offline'); };
add_filter('pre_http_request', $guard);
try { $html = do_blocks('<!-- wp:aloha/rental-catalog /-->'); } finally { remove_filter('pre_http_request', $guard); }
$check($http === 0 && str_contains($html, 'data-rental-catalog'), 'Server renderer is local');
foreach (['private-source-', 'PRIVATE_FIXTURE', 'assets.guesty.com', '_awb_source', 'private-photo-'] as $private) { $check(!str_contains($html, $private), 'HTML allowlist: ' . $private); }
WP_CLI::line('PASS catalog rendering/filter checks: ' . $checks . '; HTTP=0');
