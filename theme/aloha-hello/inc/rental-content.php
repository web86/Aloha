<?php
/** Public Rental content. The Bridge supplies the projection. */
defined( 'ABSPATH' ) || exit;

function aloha_rental_overview( array $rental ): void {
	$size = aloha_rental_fact( $rental, 'areaSquareFeet' );
	$bedrooms = aloha_rental_fact( $rental, 'bedrooms' );
	$beds = aloha_rental_fact( $rental, 'beds' );
	$facts = array(
		array( __( 'Guests', 'aloha-hello' ), aloha_rental_fact( $rental, 'guests' ), '' ),
		array( __( 'Bedrooms', 'aloha-hello' ), $bedrooms, $beds ? sprintf( __( '%s beds', 'aloha-hello' ), $beds ) : '' ),
		array( __( 'Bathrooms', 'aloha-hello' ), aloha_rental_fact( $rental, 'bathrooms' ), '' ),
		array( __( 'Size', 'aloha-hello' ), $size, $size !== null ? 'ft²' : '' ),

	);
	foreach ( $rental['factsStripCustom'] ?? array_fill( 0, 4, array( 'title' => '', 'value' => '' ) ) as $row ) {
		$facts[] = array( $row['title'] ?: ' ', $row['value'] !== '' ? $row['value'] : null, '' );
	}
	?>
	<dl class="aloha-rental-facts" aria-label="<?php esc_attr_e( 'Property overview', 'aloha-hello' ); ?>">
		<?php foreach ( $facts as [ $label, $value, $unit ] ) : ?>
			<div><dt><?php echo esc_html( $label ); ?></dt><dd>
				<strong><?php echo esc_html( $value ?? '—' ); ?></strong>
				<?php if ( $unit !== '' ) : ?><span><?php echo esc_html( $unit ); ?></span><?php endif; ?>
			</dd></div>
		<?php endforeach; ?>
	</dl>
	<?php
}

function aloha_rental_highlights( array $highlights ): void {
	if ( ! $highlights ) { return; }
	?>
	<section class="aloha-rental-section" aria-labelledby="aloha-rental-highlights-title" data-rental-highlights>
		<div class="aloha-rental-section-heading">
			<h2 id="aloha-rental-highlights-title"><?php echo wp_kses( __( 'What makes this home <em>special</em>', 'aloha-hello' ), array( 'em' => array() ) ); ?></h2>
			<div class="aloha-rental-carousel-controls" hidden>
				<button type="button" data-rental-prev aria-label="<?php esc_attr_e( 'Previous highlight', 'aloha-hello' ); ?>">←</button>
				<button type="button" data-rental-next aria-label="<?php esc_attr_e( 'Next highlight', 'aloha-hello' ); ?>">→</button>
			</div>
		</div>
		<div class="aloha-rental-highlights-viewport" data-rental-viewport tabindex="0" role="region" aria-labelledby="aloha-rental-highlights-title">
			<div class="aloha-rental-highlights">
				<?php foreach ( $highlights as $highlight ) : ?>
					<article class="aloha-rental-highlight">
						<?php if ( is_array( $highlight['image'] ) ) : $image = $highlight['image']; ?>
							<img src="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>"
								width="<?php echo esc_attr( $image['width'] ); ?>" height="<?php echo esc_attr( $image['height'] ); ?>" loading="lazy" decoding="async"
								<?php if ( $image['srcset'] ) : ?>srcset="<?php echo esc_attr( $image['srcset'] ); ?>" sizes="(min-width: 900px) 260px, (min-width: 600px) 42vw, 78vw"<?php endif; ?>>
						<?php endif; ?>
						<h3><?php echo esc_html( $highlight['headline'] ); ?></h3>
						<p><?php echo esc_html( $highlight['description'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

function aloha_rental_more_button( string $controls, string $label, string $close = '' ): void {
	$close = $close ?: __( 'Show less ↑', 'aloha-hello' );
	?>
	<button type="button" class="aloha-rental-more-button" data-rental-more
		data-open-label="<?php echo esc_attr( $label ); ?>" data-close-label="<?php echo esc_attr( $close ); ?>"
		aria-controls="<?php echo esc_attr( $controls ); ?>" aria-expanded="false" hidden><?php echo esc_html( $label ); ?></button>
	<?php
}

function aloha_rental_about( array $rental ): void {
	$html = $rental['aboutHtml'] ?? '';
	if ( $html === '' ) { return; }
	$location = $rental['community']['name'] ?? $rental['area']['name'] ?? '';
	$emphasis = '<em>' . esc_html__( 'vacation rental', 'aloha-hello' ) . '</em>';
	$title = $location ? sprintf( esc_html__( 'About this %1$s %2$s', 'aloha-hello' ), esc_html( $location ), $emphasis )
		: sprintf( esc_html__( 'About this %s', 'aloha-hello' ), $emphasis );
	$id = wp_unique_id( 'aloha-rental-about-' );
	?>
	<section class="aloha-rental-section aloha-rental-about" aria-labelledby="aloha-rental-about-title" data-rental-description>
		<h2 id="aloha-rental-about-title"><?php echo $title; // Escaped while assembling the heading. ?></h2>
		<div class="aloha-rental-description" id="<?php echo esc_attr( $id ); ?>" data-description-content><?php echo wp_kses_post( $html ); ?></div>
		<?php aloha_rental_more_button( $id, __( 'Read the full description ↓', 'aloha-hello' ) ); ?>
	</section>
	<?php
}

function aloha_rental_amenities( array $amenities ): void {
	$groups = $amenities['groups'];
	if ( $amenities['unmapped'] ) {
		$groups[] = array( 'name' => __( 'Other amenities', 'aloha-hello' ), 'items' => $amenities['unmapped'] );
	}
	if ( ! $groups ) { return; }
	$id = wp_unique_id( 'aloha-rental-amenities-' );
	$overflow = false;
	$count = array_sum( array_map( static fn( $group ) => count( $group['items'] ), $groups ) );
	?>
	<section class="aloha-rental-section" aria-labelledby="aloha-rental-amenities-title" data-rental-disclosure>
		<h2 id="aloha-rental-amenities-title"><?php echo wp_kses( __( "What's <em>included</em>", 'aloha-hello' ), array( 'em' => array() ) ); ?></h2>
		<div class="aloha-rental-amenities" id="<?php echo esc_attr( $id ); ?>">
			<?php foreach ( $groups as $index => $group ) : $hide_group = $index >= 6; ?>
				<div class="aloha-rental-amenities-group"<?php if ( $hide_group ) : $overflow = true; ?> data-rental-extra<?php endif; ?>>
					<h3><?php echo esc_html( $group['name'] ); ?></h3>
					<ul>
						<?php foreach ( $group['items'] as $item_index => $item ) : ?>
							<li<?php if ( ! $hide_group && $item_index >= 4 ) : $overflow = true; ?> data-rental-extra<?php endif; ?>><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( $overflow ) { aloha_rental_more_button( $id, sprintf( __( 'Show all %d amenities', 'aloha-hello' ), $count ) ); } ?>
	</section>
	<?php
}

function aloha_rental_nearby( array $places, ?array $home = null, string $title = '' ): void {
	if ( ! $places ) { return; }
	$points = array();
	foreach ( $places as $index => $place ) {
		$lat = $place['latitude'] ?? null;
		$lng = $place['longitude'] ?? null;
		if ( ( is_int( $lat ) || is_float( $lat ) ) && ( is_int( $lng ) || is_float( $lng ) )
			&& is_finite( $lat ) && is_finite( $lng ) && abs( $lat ) <= 90 && abs( $lng ) <= 180 ) {
			$points[ $index ] = array( 'lat' => $lat, 'lng' => $lng, 'name' => $place['name'], 'number' => $index + 1 );
		}
	}
	$config = apply_filters( 'aloha_rental_maps_config', array(
		'key' => defined( 'ALOHA_GOOGLE_MAPS_BROWSER_KEY' ) ? ALOHA_GOOGLE_MAPS_BROWSER_KEY : '',
		'mapId' => defined( 'ALOHA_GOOGLE_MAPS_MAP_ID' ) ? ALOHA_GOOGLE_MAPS_MAP_ID : '',
	) );
	$can_map = $points && is_string( $config['key'] ?? null ) && trim( $config['key'] ) !== ''
		&& is_string( $config['mapId'] ?? null ) && trim( $config['mapId'] ) !== '';
	$id = wp_unique_id( 'aloha-rental-map-' );
	if ( $can_map ) {
		wp_enqueue_script( 'aloha-rental-map' );
	}
	?>
	<section class="aloha-rental-section" aria-labelledby="aloha-rental-nearby-title"<?php if ( $can_map ) : ?> data-rental-map<?php endif; ?>>
		<h2 id="aloha-rental-nearby-title"><?php echo wp_kses( __( 'Close <em>by</em>', 'aloha-hello' ), array( 'em' => array() ) ); ?></h2>
		<div class="aloha-rental-nearby-layout">
			<ol class="aloha-rental-nearby">
				<?php foreach ( $places as $index => $place ) : ?>
					<li>
						<div>
							<span data-map-place-name><?php echo esc_html( $place['name'] ); ?></span>
							<?php if ( $can_map && isset( $points[ $index ] ) ) : ?>
								<button class="aloha-rental-place-button" type="button" hidden data-map-point="<?php echo esc_attr( $index ); ?>" aria-pressed="false" aria-controls="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $place['name'] ); ?></button>
							<?php endif; ?>
						</div>
						<?php if ( $place['minutes'] !== null ) : ?><strong><?php echo esc_html( $place['minutes'] . ' ' . $place['mode'] ); ?></strong><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="aloha-rental-map">
				<?php if ( $can_map ) : ?>
					<div class="aloha-rental-map-surface" id="<?php echo esc_attr( $id ); ?>" data-map-surface role="region" aria-label="<?php esc_attr_e( 'Map of nearby places', 'aloha-hello' ); ?>" hidden></div>
					<div class="aloha-rental-map-message" data-map-message>
						<p data-map-status role="status"><?php esc_html_e( 'The interactive map is unavailable.', 'aloha-hello' ); ?></p>
					</div>
					<script type="application/json" data-map-config><?php echo wp_json_encode( array(
						'key' => $config['key'], 'mapId' => $config['mapId'], 'points' => (object) $points,
						'home' => $home ? $home + array( 'name' => $title ) : null,
						'loading' => __( 'Loading Google Maps…', 'aloha-hello' ), 'error' => __( 'The map could not be loaded. The places are listed here.', 'aloha-hello' ),
					), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
				<?php else : ?>
					<p class="aloha-rental-map-message"><?php echo esc_html( $points ? __( 'The interactive map is unavailable.', 'aloha-hello' ) : __( 'Map locations are unavailable for these places.', 'aloha-hello' ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

function aloha_rental_reviews( array $rental ): void {
	$link = $rental['reviewLink'] ?? null;
	if ( ! $rental['reviews'] && ! $link ) { return; }
	?>
	<section class="aloha-rental-section" aria-labelledby="aloha-rental-reviews-title">
		<div class="aloha-rental-section-heading">
			<h2 id="aloha-rental-reviews-title"><?php echo wp_kses( __( 'Guests <em>said</em>', 'aloha-hello' ), array( 'em' => array() ) ); ?></h2>
			<?php if ( $link ) : ?>
				<a class="aloha-rental-text-link" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"
					aria-label="<?php esc_attr_e( 'View all reviews for this home on Airbnb (opens in a new tab)', 'aloha-hello' ); ?>"><?php
					echo esc_html( $link['count'] !== null ? sprintf( __( 'All %d reviews', 'aloha-hello' ), $link['count'] ) : __( 'All reviews on Airbnb', 'aloha-hello' ) );
				?></a>
			<?php endif; ?>
		</div>
		<div class="aloha-rental-reviews"><?php foreach ( $rental['reviews'] as $review ) : ?>
			<figure class="aloha-rental-review">
				<blockquote><?php foreach ( $review['text'] as $part => $line ) : ?>
					<p><?php echo esc_html( $line ); ?></p>
				<?php endforeach; ?></blockquote>
				<figcaption><?php echo esc_html( $review['channel'] ); ?>
					<?php if ( $review['date'] ) : ?> · <time datetime="<?php echo esc_attr( $review['date'] ); ?>"><?php echo esc_html( wp_date( 'F Y', strtotime( $review['date'] . ' UTC' ) ) ); ?></time><?php endif; ?>
				</figcaption>
			</figure>
		<?php endforeach; ?></div>
	</section>
	<?php
}

function aloha_rental_contact( array $rental ): void {
	$contact = $rental['contact'] ?? null;
	if ( ! $contact ) { return; }
	$subject = sprintf( __( 'Question about %s', 'aloha-hello' ), $rental['title'] );
	?>
	<section class="aloha-rental-contact" aria-labelledby="aloha-rental-contact-title">
		<div class="aloha-rental-contact-person">
			<span class="aloha-rental-contact-picture">
				<?php if ( $contact['image'] ) : ?><img data-contact-avatar src="<?php echo esc_url( $contact['image'] ); ?>" alt="" width="72" height="72" loading="lazy"><?php endif; ?>
				<span class="aloha-rental-contact-avatar" aria-hidden="true"<?php echo $contact['image'] ? ' hidden' : ''; ?>><?php echo esc_html( mb_substr( $contact['name'], 0, 1 ) ); ?></span>
			</span>
			<div><h2 id="aloha-rental-contact-title"><?php esc_html_e( 'Questions about this home?', 'aloha-hello' ); ?></h2>
				<p><?php echo nl2br( esc_html( $contact['introduction'] ) ); ?></p>
			</div>
		</div>
		<a href="<?php echo esc_url( 'mailto:' . $contact['email'] . '?subject=' . rawurlencode( $subject ) ); ?>"><?php echo esc_html( sprintf( __( 'Ask %s', 'aloha-hello' ), $contact['firstName'] ) ); ?></a>
	</section>
	<?php
}

function aloha_rental_rules( array $rental ): void {
	$source = $rental['houseRules'];
	$info = $rental['guestInformation'] ?? array();
	$value = static fn( $key ) => in_array( $source[ $key ]['status'] ?? '', array( 'available', 'stale' ), true ) ? ( $source[ $key ]['value'] ?? null ) : null;
	$times = array();
	foreach ( array( 'checkIn' => __( 'In', 'aloha-hello' ), 'checkOut' => __( 'Out', 'aloha-hello' ) ) as $key => $label ) {
		if ( is_string( $value( $key ) ) && $value( $key ) !== '' ) { $times[] = $label . ' ' . $value( $key ); }
	}
	$summary = array_filter( array(
		__( 'Check-in / out', 'aloha-hello' ) => implode( ' / ', $times ),
		__( 'Cancellation', 'aloha-hello' ) => $info['cancellation'] ?? '',
		__( 'Deposit', 'aloha-hello' ) => $info['deposit'] ?? '',
	) );
	$rules = array();
	foreach ( array( 'smokingAllowed' => __( 'Smoking', 'aloha-hello' ), 'petsAllowed' => __( 'Pets', 'aloha-hello' ),
		'eventsAllowed' => __( 'Events', 'aloha-hello' ), 'suitableForChildren' => __( 'Children', 'aloha-hello' ),
		'suitableForInfants' => __( 'Infants', 'aloha-hello' ) ) as $key => $label ) {
		if ( is_bool( $value( $key ) ) ) { $rules[ $label ] = $value( $key ) ? __( 'Allowed', 'aloha-hello' ) : __( 'Not allowed', 'aloha-hello' ); }
	}
	if ( is_numeric( $value( 'minimumAge' ) ) ) { $rules[ __( 'Minimum guest age', 'aloha-hello' ) ] = $value( 'minimumAge' ); }
	if ( $value( 'quietHoursEnabled' ) === true && $value( 'quietHoursStart' ) && $value( 'quietHoursEnd' ) ) {
		$rules[ __( 'Quiet hours', 'aloha-hello' ) ] = $value( 'quietHoursStart' ) . '–' . $value( 'quietHoursEnd' );
	}
	foreach ( array( 'checkInEnd' => __( 'Check-in closes', 'aloha-hello' ), 'timezone' => __( 'Local timezone', 'aloha-hello' ) ) as $key => $label ) {
		if ( is_string( $value( $key ) ) && $value( $key ) !== '' ) { $rules[ $label ] = $value( $key ); }
	}
	if ( ! $summary && ! $rules ) { return; }
	$id = wp_unique_id( 'aloha-rental-rules-' );
	?>
	<section class="aloha-rental-section" aria-labelledby="aloha-rental-rules-title" data-rental-disclosure>
		<h2 id="aloha-rental-rules-title"><?php echo wp_kses( __( 'House <em>rules</em>', 'aloha-hello' ), array( 'em' => array() ) ); ?></h2>
		<dl class="aloha-rental-rule-summary"><?php foreach ( $summary as $label => $text ) : ?>
			<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $text ); ?></dd></div>
		<?php endforeach; ?></dl>
		<?php if ( $rules ) : ?>
			<div id="<?php echo esc_attr( $id ); ?>" data-rental-extra>
				<dl class="aloha-rental-rules"><?php foreach ( $rules + $summary as $label => $text ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $text ); ?></dd></div>
				<?php endforeach; ?></dl>
			</div>
			<?php aloha_rental_more_button( $id, __( 'All house rules & guest info →', 'aloha-hello' ) ); ?>
		<?php endif; ?>
	</section>
	<?php
}
