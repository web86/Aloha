<?php
/** Local catalog presentation; publication and provider normalization belong to Bridge. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'aloha-rental-cards', get_theme_file_uri( 'assets/css/rental-cards.css' ), array( 'aloha-foundation' ), (string) filemtime( get_theme_file_path( 'assets/css/rental-cards.css' ) ) );
	wp_register_script( 'aloha-rental-cards', get_theme_file_uri( 'assets/js/rental-cards.js' ), array( 'aloha-embla' ), (string) filemtime( get_theme_file_path( 'assets/js/rental-cards.js' ) ), true );
	if ( ! is_post_type_archive( 'rental' ) ) { return; }
	wp_enqueue_style( 'aloha-rental-catalog', get_theme_file_uri( 'assets/css/rental-catalog.css' ), array( 'aloha-rental-cards' ), (string) filemtime( get_theme_file_path( 'assets/css/rental-catalog.css' ) ) );
	wp_enqueue_script( 'aloha-rental-catalog', get_theme_file_uri( 'assets/js/rental-catalog.js' ), array( 'aloha-rental-cards' ), (string) filemtime( get_theme_file_path( 'assets/js/rental-catalog.js' ) ), true );
	wp_enqueue_script( 'aloha-rental-catalog-map', get_theme_file_uri( 'assets/js/rental-catalog-map.js' ), array( 'aloha-rental-catalog' ), (string) filemtime( get_theme_file_path( 'assets/js/rental-catalog-map.js' ) ), true );
}, 9 );

add_action( 'init', function () {
	register_block_type( 'aloha/rental-catalog', array( 'api_version' => 3, 'title' => __( 'Rental catalog', 'aloha-hello' ),
		'category' => 'theme', 'supports' => array( 'html' => false, 'inserter' => false, 'multiple' => false ), 'render_callback' => 'aloha_rental_catalog_render' ) );
	register_block_type( 'aloha/catalog-contact', array( 'api_version' => 3, 'title' => __( 'Catalog contact link', 'aloha-hello' ),
		'category' => 'theme', 'render_callback' => 'aloha_rental_catalog_contact' ) );
} );

function aloha_rental_catalog_areas(): array {
	$areas = array();
	foreach ( get_posts( array( 'post_type' => 'area', 'post_status' => 'publish', 'has_password' => false,
		'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC' ) ) ) as $area ) {
		$areas[ $area->ID ] = $area->post_title;
	}
	return $areas;
}

/** Reject arrays, signed numbers, overflow and unsupported choices equally in PHP/JS. */
function aloha_rental_catalog_filters( array $input, array $areas ): array {
	$integer = static function ( $value, int $max ): int {
		return is_scalar( $value ) && preg_match( '/\A[0-9]{1,9}\z/', (string) $value ) && (int) $value <= $max ? (int) $value : 0;
	};
	$area = $integer( $input['area_id'] ?? '', 999999999 );
	$beds = $input['bedrooms'] ?? '';
	return array( 'area_id' => isset( $areas[ $area ] ) ? $area : 0,
		'bedrooms' => is_string( $beds ) && in_array( $beds, array( '2', '3', '4' ), true ) ? $beds : '',
		'adults' => $integer( $input['adults'] ?? '', 100 ), 'children' => $integer( $input['children'] ?? '', 100 ) );
}

/** Small, public matching contract; future ratingScore can be added without changing cards. */
function aloha_rental_catalog_fields( array $item ): array {
	$confirmed = static fn( $key ) => ( $item['facts'][ $key ]['status'] ?? '' ) === 'available' ? $item['facts'][ $key ]['value'] : null;
	return array( 'area' => $item['area']['id'] ?? 0, 'bedrooms' => $confirmed( 'bedrooms' ), 'guests' => $confirmed( 'guests' ),
		'children' => ( $item['childrenPolicy']['status'] ?? '' ) === 'available' ? $item['childrenPolicy']['value'] : null );
}

function aloha_rental_catalog_matches( array $fields, array $filters ): bool {
	if ( $filters['area_id'] && $fields['area'] !== $filters['area_id'] ) { return false; }
	if ( $filters['bedrooms'] !== '' && ( $fields['bedrooms'] === null ||
		( '4' === $filters['bedrooms'] ? $fields['bedrooms'] < 4 : $fields['bedrooms'] !== (int) $filters['bedrooms'] && $fields['bedrooms'] !== (float) $filters['bedrooms'] ) ) ) { return false; }
	$guests = $filters['adults'] + $filters['children'];
	return ( ! $guests || ( $fields['guests'] !== null && $fields['guests'] >= $guests ) ) && ( ! $filters['children'] || false !== $fields['children'] );
}

function aloha_rental_catalog_url( array $filters ): string {
	return add_query_arg( array_filter( $filters, static fn( $value ) => '' !== $value && 0 !== $value ), get_post_type_archive_link( 'rental' ) );
}

function aloha_rental_catalog_chips( array $filters, array $areas ): array {
	$chips = array();
	if ( $filters['area_id'] ) { $chips['area_id'] = $areas[ $filters['area_id'] ]; }
	if ( $filters['bedrooms'] ) { $chips['bedrooms'] = sprintf( __( '%s bedrooms', 'aloha-hello' ), '4' === $filters['bedrooms'] ? '4+' : $filters['bedrooms'] ); }
	foreach ( array( 'adults' => __( '%d adults', 'aloha-hello' ), 'children' => __( '%d children', 'aloha-hello' ) ) as $key => $label ) {
		if ( $filters[ $key ] ) { $chips[ $key ] = sprintf( $label, $filters[ $key ] ); }
	}
	return $chips;
}

function aloha_rental_catalog_contact(): string {
	$contact = aloha_shell_contact();
	$url = '';
	if ( $contact['verified'] ) {
		$url = $contact['phone'] ? 'tel:' . preg_replace( '/[^+0-9]/', '', $contact['phone'] ) : ( is_email( $contact['email'] ) ? 'mailto:' . $contact['email'] : '' );
	}
	if ( ! $url ) {
		$page = get_page_by_path( 'contact' );
		if ( $page && 'publish' === $page->post_status && '' === $page->post_password ) { $url = get_permalink( $page ); }
	}
	return $url ? '<a class="aloha-button aloha-button--secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Help me choose', 'aloha-hello' ) . '</a>' : '<button type="button" disabled aria-describedby="catalog-contact-pending">' . esc_html__( 'Help me choose', 'aloha-hello' ) . '</button><p id="catalog-contact-pending">' . esc_html__( 'Contact options coming soon.', 'aloha-hello' ) . '</p>';
}

function aloha_rental_catalog_render(): string {
	$areas = aloha_rental_catalog_areas();
	$filters = aloha_rental_catalog_filters( wp_unslash( $_GET ), $areas );
	$items = array();
	if ( class_exists( '\AlohaWPBridge\RentalCatalogData' ) ) {
		$query = new WP_Query( array( 'post_type' => 'rental', 'post_status' => 'publish', 'has_password' => false,
			'posts_per_page' => -1, 'no_found_rows' => true, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ), 'update_post_term_cache' => false ) );
		foreach ( $query->posts as $post ) {
			$item = \AlohaWPBridge\RentalCatalogData::get( (int) $post->ID );
			if ( $item ) { $items[] = $item; }
		}
	}
	$matches = array(); $points = array();
	foreach ( $items as $item ) {
		$matches[ $item['id'] ] = aloha_rental_catalog_matches( aloha_rental_catalog_fields( $item ), $filters );
		if ( $item['publicMapLocation'] ) {
			$price = $item['minimumNightlyPrice'];
			$points[] = array( 'id' => $item['id'], 'title' => $item['title'], 'url' => $item['url'], 'position' => $item['publicMapLocation'],
				'price' => 'available' === $price['status'] ? aloha_rental_money( $price ) : '',
				'image' => $item['gallery']['photos'][0]['image'] ?? null,
				'facts' => array( 'bedrooms' => 'available' === ( $item['facts']['bedrooms']['status'] ?? '' ) ? aloha_rental_fact( $item, 'bedrooms' ) : null,
					'guests' => 'available' === ( $item['facts']['guests']['status'] ?? '' ) ? aloha_rental_fact( $item, 'guests' ) : null ) );
		}
	}
	$count = count( array_filter( $matches ) );
	$config = apply_filters( 'aloha_rental_maps_config', array( 'key' => defined( 'ALOHA_GOOGLE_MAPS_BROWSER_KEY' ) ? ALOHA_GOOGLE_MAPS_BROWSER_KEY : '',
		'mapId' => defined( 'ALOHA_GOOGLE_MAPS_MAP_ID' ) ? ALOHA_GOOGLE_MAPS_MAP_ID : '' ), 0 );
	$map_config = array( 'key' => is_string( $config['key'] ?? null ) ? trim( $config['key'] ) : '',
		'mapId' => is_string( $config['mapId'] ?? null ) ? trim( $config['mapId'] ) : '', 'points' => $points,
		'loading' => __( 'Loading map…', 'aloha-hello' ), 'error' => __( 'Map unavailable. Explore the homes in the list.', 'aloha-hello' ),
		'privacy' => __( 'Map paused by your privacy preference. Explore the homes in the list.', 'aloha-hello' ) );
	ob_start(); ?>
	<section class="aloha-catalog aloha-container" data-rental-catalog>
		<nav class="aloha-catalog-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'aloha-hello' ); ?>"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span aria-hidden="true">›</span> Vacation Rentals</nav>
		<h1>Big Island <em>vacation rentals</em></h1>
		<p class="aloha-catalog-intro"><?php echo esc_html( sprintf( _n( '%d home on the Big Island, booked direct for 15%% less than Airbnb.', '%d homes on the Big Island, booked direct for 15%% less than Airbnb.', count( $items ), 'aloha-hello' ), count( $items ) ) ); ?></p>
		<div class="aloha-catalog-filter-sticky" data-catalog-sticky>
		<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'rental' ) ); ?>" class="aloha-catalog-filters" data-catalog-form aria-label="<?php esc_attr_e( 'Filter vacation rentals', 'aloha-hello' ); ?>">
			<div class="aloha-catalog-field aloha-catalog-dates"><span>Dates</span><button type="button" disabled aria-describedby="catalog-dates-note"><svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M6 2v4m8-4v4M3 8h14"/></svg>Add dates</button><small id="catalog-dates-note">Coming soon</small></div>
			<div class="aloha-catalog-field aloha-catalog-guests" data-guest-control>
				<span>Guests</span><button type="button" class="aloha-catalog-guest-toggle" data-guest-toggle aria-expanded="false" aria-controls="catalog-guests" hidden>Add guests</button>
				<div class="aloha-catalog-guest-popover" id="catalog-guests" data-guest-popover>
					<?php foreach ( array( 'adults' => 'Adults', 'children' => 'Children' ) as $key => $label ) : ?>
					<div class="aloha-catalog-guest-row"><label for="catalog-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label><div class="aloha-catalog-stepper">
						<button type="button" data-guest-step="-1" data-guest-field="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( 'Fewer ' . strtolower( $label ) ); ?>" hidden>−</button>
						<input id="catalog-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="number" min="0" max="100" inputmode="numeric" value="<?php echo esc_attr( $filters[ $key ] ); ?>" aria-describedby="catalog-capacity-note">
						<button type="button" data-guest-step="1" data-guest-field="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( 'More ' . strtolower( $label ) ); ?>" hidden>+</button>
					</div></div><?php endforeach; ?>
					<button type="button" class="aloha-catalog-guest-done" data-guest-done hidden>Done</button>
				</div>
			</div>
			<label class="aloha-catalog-field aloha-catalog-area"><span>Area</span><select name="area_id"><option value="0">All areas</option><?php foreach ( $areas as $id => $name ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filters['area_id'], $id ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
			<fieldset class="aloha-catalog-bedrooms"><legend>Bedrooms</legend><input type="radio" name="bedrooms" value="" aria-label="Any bedrooms" <?php checked( $filters['bedrooms'], '' ); ?> hidden><div><?php foreach ( array( '2' => '2', '3' => '3', '4' => '4+' ) as $value => $label ) : ?><label><input type="radio" name="bedrooms" value="<?php echo esc_attr( $value ); ?>" <?php checked( $filters['bedrooms'], (string) $value ); ?>><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></div></fieldset>
			<button class="aloha-catalog-search" type="submit">Search</button>
		</form>
		</div>
		<p class="aloha-catalog-note" id="catalog-capacity-note">Filters check local capacity only. Child suitability, availability and stay prices must be confirmed. Nightly rates are local calendar minimums.</p>
		<div class="aloha-catalog-layout" data-catalog-layout>
			<div class="aloha-catalog-results">
				<div class="aloha-catalog-toolbar"><p role="status" aria-live="polite" aria-atomic="true" data-catalog-count><?php echo esc_html( sprintf( _n( '%d home in current results', '%d homes in current results', $count, 'aloha-hello' ), $count ) ); ?></p><label class="aloha-catalog-sort"><span>Sort:</span><select aria-label="Sort vacation rentals"><option value="recommended">Recommended</option><option disabled>Price: low to high — coming soon</option><option disabled>Price: high to low — coming soon</option></select></label><button type="button" class="aloha-button--secondary" data-catalog-toggle aria-controls="catalog-map" aria-expanded="true" hidden>Hide map</button></div>
				<nav class="aloha-catalog-chips" aria-label="Active filters" data-catalog-chips>
					<?php foreach ( aloha_rental_catalog_chips( $filters, $areas ) as $key => $label ) : $without = $filters; $without[ $key ] = 'bedrooms' === $key ? '' : 0; ?><a href="<?php echo esc_url( aloha_rental_catalog_url( $without ) ); ?>" data-catalog-remove="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( 'Remove ' . $label ); ?>"><?php echo esc_html( $label ); ?> <span aria-hidden="true">×</span></a><?php endforeach; ?>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'rental' ) ); ?>" data-catalog-clear<?php echo array_filter( $filters ) ? '' : ' hidden'; ?>>Clear all</a>
				</nav>
				<div class="aloha-catalog-grid"><?php foreach ( $items as $item ) { aloha_rental_related_card( $item, array( 'catalog' => aloha_rental_catalog_fields( $item ), 'hidden' => ! $matches[ $item['id'] ],
					'sizes' => '(min-width: 1280px) 304px, (min-width: 1024px) calc((100vw - 136px) * .28), (min-width: 600px) calc((100vw - 104px) / 2), calc(100vw - 40px)' ) ); } ?></div>
				<p class="aloha-catalog-empty" data-catalog-empty<?php echo $count ? ' hidden' : ''; ?>><?php echo $items ? 'No homes match these filters. Try another area or fewer guests.' : 'Homes are being prepared. Please check back soon.'; ?></p>
			</div>
			<aside class="aloha-catalog-map" id="catalog-map" aria-label="Rental locations">
				<div class="aloha-catalog-map-sticky"><div data-catalog-map-surface hidden></div><div class="aloha-catalog-map-tools"><button type="button" class="aloha-catalog-move" disabled aria-describedby="catalog-move-note"><span aria-hidden="true">☐</span> Search as I move the map</button><small id="catalog-move-note">Coming soon</small></div><div class="aloha-catalog-map-zoom" aria-label="Map zoom"><button type="button" data-catalog-zoom="1" aria-label="Zoom in" disabled>+</button><button type="button" data-catalog-zoom="-1" aria-label="Zoom out" disabled>−</button></div><div class="aloha-catalog-map-preview" data-catalog-map-preview role="region" aria-label="Selected home" hidden></div><p data-catalog-map-message><?php echo esc_html( $points ? $map_config['error'] : __( 'Public map locations are not available yet. Explore the homes in the list.', 'aloha-hello' ) ); ?></p>
				<div class="aloha-catalog-legend"><span><i class="is-match" aria-hidden="true"></i>Available in current results</span><span><i aria-hidden="true"></i>Doesn't match your filters</span><small>Price from the local nightly minimum.</small></div></div>
			</aside>
		</div>
		<script type="application/json" data-catalog-map-config><?php echo wp_json_encode( $map_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
	</section>
	<?php return (string) ob_get_clean();
}
