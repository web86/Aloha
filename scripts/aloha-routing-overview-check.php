<?php
/** Targeted read-only admin diagnostics checks in the existing disposable WP. */
if ( ! defined( 'ALOHA_CONTENT_TEST_SITE' ) || ! ALOHA_CONTENT_TEST_SITE || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Disposable Aloha Content test site only.' );
}

$checks = array();
$check = function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $label ); }
	$checks[] = $label;
};
$snapshot = function () {
	global $wpdb;
	$data = array();
	foreach ( $wpdb->tables() as $table ) {
		$rows = array_map( 'wp_json_encode', $wpdb->get_results( "SELECT * FROM `$table`", ARRAY_A ) );
		sort( $rows );
		$data[ $table ] = hash( 'sha256', wp_json_encode( $rows ) );
	}
	return $data;
};
$render = function () { ob_start(); Aloha\Content\render_routing_overview(); return ob_get_clean(); };
$diagnostic = fn( $data, $name ) => array_column( $data['diagnostics'], 1, 0 )[ $name ];
$admin = get_user_by( 'login', 'aloha_test_admin' );
wp_set_current_user( $admin->ID );
// A cold Core hierarchy cache must not turn a read-only overview into a writer.
$hierarchy = get_option( 'category_children', null );
delete_option( 'category_children' );
$baseline = $snapshot();
$option_names = array( 'rewrite_rules', 'aloha_content_area_routes', 'aloha_content_guide_categories', 'aloha_content_investor_category', 'permalink_structure', 'category_base', 'tag_base', 'aloha_shell_menus' );
$stable_options = array_map( 'get_option', $option_names );
$mutations = array();
$watch = function ( $query ) use ( &$mutations ) {
	if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query ) ) { $mutations[] = $query; }
	return $query;
};
add_filter( 'query', $watch );
$http_attempts = 0; $generations = 0;
$http_watch = function () use ( &$http_attempts ) { ++$http_attempts; return new WP_Error( 'unexpected_http', 'Overview must not issue HTTP requests.' ); };
$rewrite_watch = function ( $rules ) use ( &$generations ) { ++$generations; return $rules; };
add_filter( 'pre_http_request', $http_watch ); add_filter( 'rewrite_rules_array', $rewrite_watch );
$data = Aloha\Content\routing_overview_data();
$html = $render();
remove_filter( 'query', $watch );
remove_filter( 'pre_http_request', $http_watch ); remove_filter( 'rewrite_rules_array', $rewrite_watch );
$check( str_contains( $html, 'Aloha Content — Routing Overview' ), 'Administrator renders the read-only screen' );
$check( $baseline === $snapshot() && ! $mutations, 'Rendering and collecting diagnostics perform no database writes (all WP tables)' );
$check( null === get_option( 'category_children', null ), 'Inspection does not populate a cold Core category hierarchy cache' );
$check( 0 === $http_attempts && 0 === $generations, 'No HTTP attempts or rewrite generations during inspection' );
$check( ! preg_match( '/<(form|input|button)\b/i', $html ), 'No Save/Reset/Rebuild/Flush form controls' );
$check( ! str_contains( $html, ABSPATH ), 'No server path disclosed' );
$check( count( $data['routes'] ) === 6, 'Exactly six existing route families' );
$check( array_column( $data['routes'], 'path' ) === array(
	'/' . Aloha\Content\area_base() . '/{area}/',
	'/' . Aloha\Content\area_base() . '/{area}/{community}/',
	'/' . Aloha\Content\guide_base() . '/',
	'/' . Aloha\Content\guide_base() . '/{rubric}/',
	'/' . Aloha\Content\guide_base() . '/{rubric}/{article}/',
	'/' . Aloha\Content\investor_base() . '/{article}/',
), 'URL structures use the active configuration' );
$check( ! post_type_exists( 'rental' ) && str_contains( $html, 'No active rental route' ), 'Absent GuestyBridge is informational, not active' );
$check( str_contains( $html, 'Intentionally disabled' ), 'Investor archive is explicitly disabled' );
$check( str_contains( $diagnostic( $data, 'Routing signature' ), 'Matches' ), 'Current signature is compared with the saved value' );
$check( str_contains( $data['routes']['Area']['registration'], 'Registered' ), 'Persisted Area rules and permastruct verified' );

// Simulate configuration changes after lifecycle has run. Inspection must report
// differences, never repair them or regenerate rules itself.
$area = fn() => 'islands/overview'; $guide = fn() => 'travel'; $investor = fn() => 'owners/research';
add_filter( 'aloha_content_area_base', $area ); add_filter( 'aloha_content_guide_slug', $guide ); add_filter( 'aloha_content_investor_base', $investor );
$before = $snapshot();
$configured = Aloha\Content\routing_overview_data();
$check( '/islands/overview/{area}/' === $configured['routes']['Area']['path'] && '/islands/overview/travel/{rubric}/{article}/' === $configured['routes']['Guide Article']['path'] && '/owners/research/{article}/' === $configured['routes']['Investor Article']['path'], 'Changed PHP filters are reflected without hardcoded bases' );
$check( str_contains( $diagnostic( $configured, 'Routing signature' ), 'requires attention' ) && str_contains( $configured['routes']['Area']['registration'], 'requires attention' ), 'Changed unsynchronized configuration is flagged, not shown as registered' );
$check( $before === $snapshot(), 'Changed-configuration inspection never synchronizes or writes' );
remove_filter( 'aloha_content_area_base', $area ); remove_filter( 'aloha_content_guide_slug', $guide ); remove_filter( 'aloha_content_investor_base', $investor );

$map = get_option( 'aloha_content_guide_categories' );
$investor_id = get_option( 'aloha_content_investor_category' );
$saved_rules = get_option( 'rewrite_rules' );
try {
	update_option( 'aloha_content_investor_category', 999999999 );
	$data = Aloha\Content\routing_overview_data();
	$check( str_contains( $diagnostic( $data, 'Investor category binding' ), 'requires attention' ), 'Deleted/missing bound Investor category is a configuration problem' );
	update_option( 'aloha_content_investor_category', $investor_id );
	$term = get_term( (int) $investor_id, 'category' );
	wp_update_term( $term->term_id, 'category', array( 'slug' => 'overview-damaged-binding' ) );
	try {
		$data = Aloha\Content\routing_overview_data();
		$check( str_contains( $diagnostic( $data, 'Investor category binding' ), 'requires attention' ), 'Damaged Investor slug binding is diagnosed without repair' );
	} finally { wp_update_term( $term->term_id, 'category', array( 'slug' => $term->slug ) ); }
	$bad_map = $map; $bad_map['rubrics']['things-to-do'] = 999999999;
	update_option( 'aloha_content_guide_categories', $bad_map );
	$data = Aloha\Content\routing_overview_data();
	$check( str_contains( $diagnostic( $data, 'Guide category bindings' ), 'requires attention' ), 'Missing Guide rubric is diagnosed' );
	update_option( 'aloha_content_guide_categories', $map );
	update_option( 'rewrite_rules', array() );
	$before = $snapshot(); $data = Aloha\Content\routing_overview_data();
	$check( str_contains( $data['routes']['Investor Article']['registration'], 'requires attention' ) && $before === $snapshot(), 'Missing persisted rules are reported without regeneration' );
} finally {
	update_option( 'aloha_content_guide_categories', $map );
	update_option( 'aloha_content_investor_category', $investor_id );
	update_option( 'rewrite_rules', $saved_rules );
}

// Isolate Page absence without modifying fixture records or the Pages resolver.
add_filter( 'aloha_content_guide_slug', $guide );
$data = Aloha\Content\routing_overview_data();
$content = array_column( $data['content'], 1, 0 );
$check( str_contains( $content['Guide Hub Page'], 'Content not created' ) && str_contains( $data['routes']['Guide Hub']['registration'], 'Registered' ), 'Missing Hub content is separate from Core Page route registration' );
remove_filter( 'aloha_content_guide_slug', $guide );

// Check escaping with a user-controlled category name, restore it immediately.
$term = get_term( (int) $investor_id, 'category' );
wp_update_term( $term->term_id, 'category', array( 'name' => 'Overview & <unsafe>' ) );
try { $html = $render(); $check( ! str_contains( $html, '<unsafe>' ) && str_contains( $html, 'Overview &amp;' ), 'Category output is escaped' ); }
finally { wp_update_term( $term->term_id, 'category', array( 'name' => $term->name ) ); }

wp_set_current_user( 0 );
$die_filter = fn() => function ( $message, $title, $args ) { throw new RuntimeException( 'denied:' . $args['response'] ); };
add_filter( 'wp_die_handler', $die_filter );
try {
	$denied = false;
	try { Aloha\Content\render_routing_overview(); } catch ( RuntimeException $error ) { $denied = 'denied:403' === $error->getMessage(); }
	$check( $denied, 'Direct render without manage_options refuses access with HTTP 403' );
} finally { remove_filter( 'wp_die_handler', $die_filter ); wp_set_current_user( $admin->ID ); }
// wp_update_term() intentionally invalidates Core's category_children cache.
// The no-write assertion above compares every table before/after inspection;
// fault-injection cleanup compares content and authoritative options, not cache IDs.
global $wpdb;
$final = $snapshot(); unset( $baseline[ $wpdb->options ], $final[ $wpdb->options ] );
$check( $baseline === $final && $stable_options === array_map( 'get_option', $option_names ), 'Fault injection restored all content, bindings, settings, rules and signature' );
if ( null !== $hierarchy ) { update_option( 'category_children', $hierarchy ); }
echo wp_json_encode( array( 'checks' => $checks ), JSON_PRETTY_PRINT ) . "\n";
