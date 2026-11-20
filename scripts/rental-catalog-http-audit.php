<?php
/** Optional local-only MU test probe. Remove after the browser suite. No payloads or URLs logged. */
if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') { return; }
$marker = $_SERVER['HTTP_X_ALOHA_CATALOG_TEST'] ?? '';
if (!is_string($marker) || !preg_match('/\Am24-fixture-[a-f0-9]{16}\z/', $marker)) { return; }
$calls = 0; $guesty = 0;
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$calls, &$guesty) {
    ++$calls;
    if (is_string($url) && str_contains(strtolower($url), 'guesty')) { ++$guesty; }
    return new WP_Error('catalog_test_offline', 'Provider access forbidden in catalog test');
}, PHP_INT_MAX, 3);
add_action('init', static function () { wp_cache_flush(); }, 1);
add_filter('aloha_rental_maps_config', static fn() => ($_SERVER['HTTP_X_ALOHA_CATALOG_MAP'] ?? '') === 'missing'
    ? ['key' => '', 'mapId' => ''] : ['key' => 'synthetic-browser-test', 'mapId' => 'synthetic-map-id']);
add_action('shutdown', static function () use (&$calls, &$guesty, $marker) {
    file_put_contents('/tmp/aloha-catalog-http-audit.jsonl', wp_json_encode(['marker' => $marker, 'httpCalls' => $calls, 'guestyCalls' => $guesty]) . "\n", FILE_APPEND | LOCK_EX);
});
