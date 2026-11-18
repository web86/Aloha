<?php
/** Rental detail presentation. All provider data comes from the local Bridge projection. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'rental' ) ) { return; }
	$path = 'assets/css/rental.css';
	wp_enqueue_style( 'aloha-rental', get_theme_file_uri( $path ), array( 'aloha-foundation', 'aloha-media-gallery' ), (string) filemtime( get_theme_file_path( $path ) ) );
	wp_enqueue_style( 'aloha-media-gallery' );
	wp_enqueue_style( 'aloha-media-dialog' );
	wp_enqueue_script( 'aloha-media-dialog' );
	$js = 'assets/js/rental-gallery.js';
	wp_enqueue_script( 'aloha-rental-gallery', get_theme_file_uri( $js ), array( 'aloha-media-dialog' ), (string) filemtime( get_theme_file_path( $js ) ), true );
} );

add_action( 'init', function () {
	register_block_type( 'aloha/rental-detail', array(
		'api_version'     => 3,
		'title'           => __( 'Rental detail', 'aloha-hello' ),
		'category'        => 'theme',
		'uses_context'    => array( 'postId', 'postType' ),
		'supports'        => array( 'html' => false, 'inserter' => false, 'multiple' => false ),
		'render_callback' => 'aloha_rental_detail_render',
	) );
} );

function aloha_rental_fact( array $rental, string $key ): ?string {
	$field = $rental['facts'][ $key ] ?? null;
	if ( ! is_array( $field ) || ! in_array( $field['status'] ?? '', array( 'available', 'stale' ), true ) ) { return null; }
	$value = $field['value'] ?? null;
	if ( ! is_numeric( $value ) || (float) $value <= 0 ) { return null; }
	return number_format_i18n( (float) $value, is_float( $value ) && floor( $value ) !== $value ? 1 : 0 );
}

function aloha_rental_photo_collection( array $rental ): array {
	$photos = array();
	foreach ( $rental['gallery']['photos'] ?? array() as $row ) {
		$img = $row['image'] ?? null;
		if ( ! is_array( $img ) || ! is_string( $img['full'] ?? null ) || ! is_string( $img['src'] ?? null )
			|| empty( $img['width'] ) || empty( $img['height'] ) ) { continue; }
		$attachment = (int) ( $row['attachmentId'] ?? 0 );
		$tile       = wp_get_attachment_image_src( $attachment, 'medium_large' );
		$ratio      = round( $img['width'] / $img['height'], 5 );
		$desktop = 'calc(clamp(360px, 35vw, 470px) * ' . $ratio . ')';
		$mobile  = 'calc(clamp(312px, 108vw, 450px) * ' . $ratio . ')';
		$photos[] = array(
			'id' => $attachment, 'url' => $img['full'], 'src' => $img['src'],
			'width' => (int) $img['width'], 'height' => (int) $img['height'],
			'srcset' => (string) ( $img['srcset'] ?? '' ), 'alt' => (string) ( $img['alt'] ?? '' ),
			'tileSrc' => $tile[0] ?? $img['src'],
			'tileWidth' => $tile[1] ?? (int) $img['width'], 'tileHeight' => $tile[2] ?? (int) $img['height'],
			'singleSizes' => '(min-width: 1280px) min(1200px, ' . $desktop . '), (min-width: 768px) min(calc(100vw - 80px), ' . $desktop . '), '
				. '(min-width: 390px) min(89.5vw, ' . $mobile . '), min(calc(100vw - 40px), ' . $mobile . ')',
			'caption' => (string) ( $img['caption'] ?? '' ), 'labelKey' => $row['labelKey'] ?? null,
		);
	}
	return $photos;
}

function aloha_rental_gallery_render( array $rental ): string {
	$photos = aloha_rental_photo_collection( $rental );
	if ( ! $photos ) { return ''; }
	$count  = count( $photos );
	$tiles  = min( 5, $count );
	$id     = wp_unique_id( 'aloha-rental-gallery-' );
	// These slots follow the grid tracks and the shared 1280px container and its gutters.
	$mobile = '(min-width: 390px) 89.5vw, calc(100vw - 40px)';
	$half   = '(min-width: 390px) calc(44.75vw - 5px), calc(50vw - 25px)';
	$lead   = '(min-width: 1280px) 590px, (min-width: 768px) calc(50vw - 50px), ' . $mobile;
	$side   = '(min-width: 1280px) 295px, (min-width: 768px) calc(25vw - 25px), ' . $half;
	$split  = '(min-width: 1280px) 794px, (min-width: 768px) calc(66.67vw - 60px), ' . $mobile;
	$third  = '(min-width: 1280px) 397px, (min-width: 768px) calc(33.34vw - 30px), ';
	$slots  = array(
		array( $photos[0]['singleSizes'] ),
		array( $split, $third . $mobile ),
		array( $split, $third . $half, $third . $half ),
		array( $lead, $side, $side, '(min-width: 1280px) 600px, (min-width: 768px) calc(50vw - 40px), ' . $mobile ),
		array( $lead, $side, $side, $side, $side ),
	);
	$photo_label = __( 'Open photo %1$d of %2$d', 'aloha-hello' );
	$more_label  = __( 'Show all %d photos', 'aloha-hello' );
	ob_start();
	?>
	<section class="aloha-rental-gallery" aria-label="<?php esc_attr_e( 'Rental photos', 'aloha-hello' ); ?>">
		<div class="aloha-media-gallery" data-aloha-gallery="<?php echo esc_attr( $id ); ?>" data-aloha-media-title="<?php echo esc_attr( $rental['title'] . ' — photos' ); ?>" data-rental-gallery
			data-rental-sizes="<?php echo esc_attr( wp_json_encode( $slots ) ); ?>" data-rental-photo-label="<?php echo esc_attr( $photo_label ); ?>" data-rental-more-label="<?php echo esc_attr( $more_label ); ?>">
			<?php if ( ! empty( $rental['gallery']['labels'] ) ) : ?>
				<div class="aloha-rental-gallery-filters" role="group" aria-label="<?php esc_attr_e( 'Filter photos', 'aloha-hello' ); ?>">
					<button type="button" data-rental-filter="all" data-count="<?php echo esc_attr( $count ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="true"><?php esc_html_e( 'All photos', 'aloha-hello' ); ?></button>
					<?php foreach ( $rental['gallery']['labels'] as $label ) : ?>
						<button type="button" data-rental-filter="<?php echo esc_attr( $label['id'] ); ?>" data-count="<?php echo esc_attr( $label['count'] ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="false"><?php echo esc_html( $label['name'] ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="aloha-gallery-mosaic" id="<?php echo esc_attr( $id ); ?>" data-count="<?php echo esc_attr( $tiles ); ?>">
				<?php foreach ( array_slice( $photos, 0, 5 ) as $index => $photo ) : ?>
					<figure class="aloha-gallery-tile">
						<a class="aloha-gallery-photo" href="<?php echo esc_url( $photo['url'] ); ?>" data-aloha-photo="<?php echo esc_attr( $index ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( sprintf( $photo_label, $index + 1, $count ) ); ?>">
							<img src="<?php echo esc_url( $photo['tileSrc'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ); ?>"
								width="<?php echo esc_attr( $photo['tileWidth'] ); ?>" height="<?php echo esc_attr( $photo['tileHeight'] ); ?>"
								loading="<?php echo $index ? 'lazy' : 'eager'; ?>" decoding="async" fetchpriority="<?php echo $index ? 'auto' : 'high'; ?>"
								<?php if ( $photo['srcset'] ) : ?>srcset="<?php echo esc_attr( $photo['srcset'] ); ?>" sizes="<?php echo esc_attr( $slots[ $tiles - 1 ][ $index ] ); ?>"<?php endif; ?>>
						</a>
						<?php if ( 4 === $index ) : ?>
							<a class="aloha-gallery-more" href="#<?php echo esc_attr( $id . '-photos' ); ?>" data-aloha-photo="0" aria-haspopup="dialog">
								<?php echo esc_html( sprintf( $more_label, $count ) ); ?>
							</a>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
			<script type="application/json" class="aloha-gallery-data"><?php echo wp_json_encode( $photos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
			<noscript><ol id="<?php echo esc_attr( $id . '-photos' ); ?>">
				<?php foreach ( $photos as $photo ) : ?>
					<li><a href="<?php echo esc_url( $photo['url'] ); ?>"><?php echo esc_html( $photo['alt'] ?: __( 'Rental photo', 'aloha-hello' ) ); ?></a></li>
				<?php endforeach; ?>
			</ol></noscript>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

function aloha_rental_related( array $rental ): string {
	$area = (int) ( $rental['area']['id'] ?? 0 );
	$community = (int) ( $rental['community']['id'] ?? 0 );
	if ( ! $area || ! class_exists( '\AlohaWPBridge\RentalPublicData' ) ) { return ''; }
	$query = new WP_Query( array( 'post_type' => 'rental', 'post_status' => 'publish',
		'post__not_in' => array( $rental['id'] ), 'posts_per_page' => 12, 'no_found_rows' => true,
		'meta_key' => '_awb_area_id', 'meta_value' => $area, 'orderby' => 'date', 'order' => 'DESC',
	) );
	$items = array();
	foreach ( $query->posts as $post ) {
		$data = \AlohaWPBridge\RentalPublicData::get( (int) $post->ID );
		if ( ! $data ) { continue; }
		$items[] = $data;
	}
	usort( $items, static fn( $a, $b ) => (int) ( ( $b['community']['id'] ?? 0 ) === $community )
		<=> (int) ( ( $a['community']['id'] ?? 0 ) === $community ) );
	$items = array_slice( $items, 0, 3 );
	if ( ! $items ) { return ''; }
	ob_start();
	?>
	<section class="aloha-rental-related aloha-section--unit" aria-labelledby="aloha-rental-related-title">
		<div class="aloha-container">
			<h2 id="aloha-rental-related-title"><?php esc_html_e( 'More vacation rentals', 'aloha-hello' ); ?></h2>
			<div class="aloha-rental-related-grid">
				<?php foreach ( $items as $item ) :
					$cover = $item['gallery']['photos'][0]['image'] ?? $item['featuredImage'];
					?>
					<article class="aloha-rental-related-card">
						<a href="<?php echo esc_url( $item['url'] ); ?>">
							<?php if ( is_array( $cover ) ) : ?>
								<img src="<?php echo esc_url( $cover['src'] ); ?>" alt="<?php echo esc_attr( $cover['alt'] ?: $item['title'] ); ?>" loading="lazy" decoding="async" width="<?php echo esc_attr( $cover['width'] ); ?>" height="<?php echo esc_attr( $cover['height'] ); ?>">
							<?php endif; ?>
							<h3><?php echo esc_html( $item['title'] ); ?></h3>
							<?php if ( $item['area'] ) : ?><p><?php echo esc_html( $item['area']['name'] ); ?></p><?php endif; ?>
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/** Decorative bed symbols reflect Guesty bed type; labels remain available as text. */
function aloha_rental_bed_icon( string $type ): string {
	$paths = array(
		'BUNK_BED' => '<rect x="5" y="6" width="22" height="5" rx="1"/><rect x="5" y="19" width="22" height="5" rx="1"/><path d="M5 5v23M27 5v23M9 12v7M9 16h5"/>',
		'SOFA_BED' => '<path d="M6 16v-4a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4v4M4 16h24v9H4zM7 25v3M25 25v3"/>',
		'CRIB' => '<rect x="4" y="8" width="24" height="15" rx="2"/><path d="M9 9v13M14 9v13M19 9v13M24 9v13M7 23v4M25 23v4"/>',
		'SINGLE_BED' => '<path d="M5 10h5v7H5zM10 11h17v6H10zM4 17h24v7H4zM6 24v4M26 24v4"/>',
	);
	$path = $paths[ $type ] ?? '<path d="M4 13h24v11H4zM4 12V8h8v5M12 10h16v3M6 24v4M26 24v4"/>';
	return '<svg viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function aloha_rental_detail_render( $attributes, $content, $block ): string {
	$id = (int) ( $block->context['postId'] ?? get_the_ID() );
	if ( $id < 1 || get_post_type( $id ) !== 'rental'
		|| ! class_exists( '\AlohaWPBridge\RentalPublicData' ) ) { return ''; }
	$rental = \AlohaWPBridge\RentalPublicData::get( $id );
	if ( null === $rental && is_preview() ) {
		$rental = \AlohaWPBridge\RentalPublicData::preview( $id );
	}
	if ( null === $rental ) { return ''; }
	$facts = array();
	foreach ( array( 'guests' => __( 'guests', 'aloha-hello' ), 'bedrooms' => __( 'bedrooms', 'aloha-hello' ),
		'bathrooms' => __( 'bathrooms', 'aloha-hello' ), 'beds' => __( 'beds', 'aloha-hello' ) ) as $key => $label ) {
		$value = aloha_rental_fact( $rental, $key );
		if ( null !== $value ) { $facts[] = $value . ' ' . $label; }
	}
	$size = aloha_rental_fact( $rental, 'areaSquareFeet' );
	if ( $size ) { $facts[] = $size . ' ft²'; }
	$gallery = aloha_rental_gallery_render( $rental );
	$about = array_filter( $rental['about'], static fn( $value ) => is_string( $value ) && $value !== '' );
	if ( ! $about && $rental['excerpt'] ) { $about['summary'] = $rental['excerpt']; }
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'aloha-rental-detail' ) ); ?>>
		<div class="aloha-container aloha-rental-intro">
			<nav class="aloha-rental-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'aloha-hello' ); ?>">
				<a href="<?php echo esc_url( home_url( '/vacation-rentals/' ) ); ?>"><?php esc_html_e( 'Vacation rentals', 'aloha-hello' ); ?></a>
				<?php if ( $rental['area'] ) : ?><span aria-hidden="true">/</span><a href="<?php echo esc_url( $rental['area']['url'] ); ?>"><?php echo esc_html( $rental['area']['name'] ); ?></a><?php endif; ?>
				<span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html( $rental['title'] ); ?></span>
			</nav>
			<div class="aloha-rental-heading">
				<div>
					<h1><?php echo esc_html( $rental['title'] ); ?></h1>
					<?php if ( $rental['usp'] ) : ?><p class="aloha-rental-usp"><?php echo esc_html( $rental['usp'] ); ?></p><?php endif; ?>
				</div>
				<div class="aloha-rental-actions" data-rental-actions>
					<button type="button" data-rental-message="<?php esc_attr_e( 'Save is coming soon.', 'aloha-hello' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.8 4.6a5.3 5.3 0 0 0-7.5 0L12 5.9l-1.3-1.3a5.3 5.3 0 0 0-7.5 7.5L12 21l8.8-8.9a5.3 5.3 0 0 0 0-7.5Z"/></svg>
						<?php esc_html_e( 'Save', 'aloha-hello' ); ?>
					</button>
					<button type="button" data-rental-message="<?php esc_attr_e( 'Share is coming soon.', 'aloha-hello' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 15V3m-4 4 4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
						<?php esc_html_e( 'Share', 'aloha-hello' ); ?>
					</button>
					<span class="aloha-rental-action-status" role="status"></span>
				</div>
			</div>
			<?php if ( $facts ) : ?><p class="aloha-rental-facts"><?php echo esc_html( implode( ' · ', $facts ) ); ?></p><?php endif; ?>
		</div>
		<div class="aloha-container"><?php echo $gallery; // Escaped inside renderer. ?></div>
		<div class="aloha-container aloha-rental-body">
			<div class="aloha-rental-content">
				<?php if ( ! empty( $rental['highlights'] ) ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-highlights-title">
						<h2 id="aloha-rental-highlights-title"><?php esc_html_e( 'What makes this home special', 'aloha-hello' ); ?></h2>
						<div class="aloha-rental-highlights">
							<?php foreach ( $rental['highlights'] as $highlight ) : ?>
								<article class="aloha-rental-highlight">
									<?php if ( is_array( $highlight['image'] ) ) : ?><img src="<?php echo esc_url( $highlight['image']['src'] ); ?>" alt="<?php echo esc_attr( $highlight['image']['alt'] ?: $highlight['headline'] ); ?>" loading="lazy" decoding="async"><?php endif; ?>
									<div><h3><?php echo esc_html( $highlight['headline'] ); ?></h3><p><?php echo esc_html( $highlight['description'] ); ?></p></div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>
				<?php if ( $about ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-about-title">
						<h2 id="aloha-rental-about-title"><?php esc_html_e( 'About this vacation rental', 'aloha-hello' ); ?></h2>
						<?php foreach ( $about as $key => $paragraph ) : ?>
							<?php if ( 'summary' !== $key ) : ?><h3><?php echo esc_html( array( 'space' => __( 'The space', 'aloha-hello' ), 'neighborhood' => __( 'The neighborhood', 'aloha-hello' ), 'transit' => __( 'Getting around', 'aloha-hello' ) )[ $key ] ?? ucfirst( $key ) ); ?></h3><?php endif; ?>
							<p><?php echo nl2br( esc_html( $paragraph ) ); ?></p>
						<?php endforeach; ?>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $rental['sleeping'] ) ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-sleep-title">
						<h2 id="aloha-rental-sleep-title"><?php esc_html_e( 'Where everyone sleeps', 'aloha-hello' ); ?></h2>
						<div class="aloha-rental-sleeping">
							<?php foreach ( $rental['sleeping'] as $room ) : ?>
								<article class="aloha-rental-sleep-card">
									<div class="aloha-rental-bed-icons" aria-hidden="true">
										<?php foreach ( $room['beds'] as $bed ) : ?>
											<?php echo aloha_rental_bed_icon( $bed['type'] ); // SVG paths are hardcoded above. ?>
										<?php endforeach; ?>
									</div>
									<h3><?php echo esc_html( $room['name'] ); ?></h3>
									<p><?php
										$beds = array();
										foreach ( $room['beds'] as $bed ) {
											$beds[] = (int) $bed['quantity'] . ' ' . ucwords( strtolower( str_replace( '_', ' ', $bed['type'] ) ) );
										}
										echo esc_html( implode( ', ', $beds ) );
									?></p>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $rental['amenities']['groups'] ) || ! empty( $rental['amenities']['unmapped'] ) ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-amenities-title">
						<h2 id="aloha-rental-amenities-title"><?php esc_html_e( "What's included", 'aloha-hello' ); ?></h2>
						<div class="aloha-rental-amenities">
							<?php foreach ( $rental['amenities']['groups'] as $group ) : ?>
								<div><h3><?php echo esc_html( $group['name'] ); ?></h3><ul><?php foreach ( $group['items'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></div>
							<?php endforeach; ?>
							<?php if ( $rental['amenities']['unmapped'] ) : ?>
								<div><h3><?php esc_html_e( 'Other amenities', 'aloha-hello' ); ?></h3><ul><?php foreach ( $rental['amenities']['unmapped'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></div>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $rental['nearby'] ) ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-nearby-title">
						<h2 id="aloha-rental-nearby-title"><?php esc_html_e( 'Close by', 'aloha-hello' ); ?></h2>
						<ul class="aloha-rental-nearby"><?php foreach ( $rental['nearby'] as $place ) : ?>
							<li><span><?php echo esc_html( $place['name'] ); ?></span><strong><?php echo esc_html( (string) $place['minutes'] . ' ' . $place['mode'] ); ?></strong></li>
						<?php endforeach; ?></ul>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $rental['reviews'] ) ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-reviews-title">
						<h2 id="aloha-rental-reviews-title"><?php esc_html_e( 'Guests said', 'aloha-hello' ); ?></h2>
						<div class="aloha-rental-reviews"><?php foreach ( $rental['reviews'] as $review ) : ?>
							<blockquote class="aloha-rental-review">
								<p class="aloha-rental-review-score"><?php echo esc_html( number_format_i18n( $review['rating']['value'], 1 ) . ' / ' . $review['rating']['max'] ); ?> <span><?php echo esc_html( $review['channel'] ); ?></span></p>
								<?php foreach ( $review['text'] as $part => $line ) : ?>
									<p><?php echo esc_html( $line ); ?></p>
								<?php endforeach; ?>
								<?php if ( $review['date'] ) : ?><footer><time datetime="<?php echo esc_attr( $review['date'] ); ?>"><?php echo esc_html( wp_date( 'F Y', strtotime( $review['date'] . ' UTC' ) ) ); ?></time></footer><?php endif; ?>
							</blockquote>
						<?php endforeach; ?></div>
					</section>
				<?php endif; ?>
				<?php
				$rules = array(
					'smokingAllowed' => __( 'Smoking', 'aloha-hello' ), 'petsAllowed' => __( 'Pets', 'aloha-hello' ),
					'eventsAllowed' => __( 'Events', 'aloha-hello' ), 'suitableForChildren' => __( 'Children', 'aloha-hello' ),
					'suitableForInfants' => __( 'Infants', 'aloha-hello' ),
				);
				$showRules = false;
				foreach ( $rules as $key => $label ) {
					if ( in_array( $rental['houseRules'][ $key ]['status'] ?? '', array( 'available', 'stale' ), true )
						&& is_bool( $rental['houseRules'][ $key ]['value'] ?? null ) ) { $showRules = true; }
				}
				$hasTimedRules = false;
				foreach ( array( 'minimumAge', 'quietHoursEnabled', 'checkIn', 'checkOut' ) as $key ) {
					$hasTimedRules = $hasTimedRules || in_array( $rental['houseRules'][ $key ]['status'] ?? '', array( 'available', 'stale' ), true );
				}
				if ( $showRules || $hasTimedRules ) :
				?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-rules-title">
						<h2 id="aloha-rental-rules-title"><?php esc_html_e( 'House rules', 'aloha-hello' ); ?></h2>
						<dl class="aloha-rental-rules">
							<?php foreach ( $rules as $key => $label ) :
								$field = $rental['houseRules'][ $key ] ?? array();
								if ( ! in_array( $field['status'] ?? '', array( 'available', 'stale' ), true ) || ! is_bool( $field['value'] ?? null ) ) { continue; }
								?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $field['value'] ? __( 'Allowed', 'aloha-hello' ) : __( 'Not allowed', 'aloha-hello' ) ); ?></dd></div>
							<?php endforeach; ?>
							<?php if ( in_array( $rental['houseRules']['minimumAge']['status'] ?? '', array( 'available', 'stale' ), true ) ) : ?>
								<div><dt><?php esc_html_e( 'Minimum guest age', 'aloha-hello' ); ?></dt><dd><?php echo esc_html( (string) $rental['houseRules']['minimumAge']['value'] ); ?></dd></div>
							<?php endif; ?>
							<?php $quiet = $rental['houseRules']; if ( ( $quiet['quietHoursEnabled']['value'] ?? null ) === true && ( $quiet['quietHoursStart']['value'] ?? null ) && ( $quiet['quietHoursEnd']['value'] ?? null ) ) : ?>
								<div><dt><?php esc_html_e( 'Quiet hours', 'aloha-hello' ); ?></dt><dd><?php echo esc_html( $quiet['quietHoursStart']['value'] . '–' . $quiet['quietHoursEnd']['value'] ); ?></dd></div>
							<?php endif; ?>
							<?php foreach ( array( 'checkIn' => __( 'Check-in', 'aloha-hello' ), 'checkOut' => __( 'Check-out', 'aloha-hello' ) ) as $key => $label ) :
								$field = $quiet[ $key ] ?? array();
								if ( ! in_array( $field['status'] ?? '', array( 'available', 'stale' ), true ) || ! is_string( $field['value'] ?? null ) ) { continue; }
								?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $field['value'] ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>
			</div>
			<aside class="aloha-rental-booking" aria-labelledby="aloha-rental-booking-title">
				<div class="aloha-rental-booking-card">
					<p class="aloha-eyebrow"><?php esc_html_e( 'Plan your stay', 'aloha-hello' ); ?></p>
					<h2 id="aloha-rental-booking-title"><?php esc_html_e( 'Stay with Aloha', 'aloha-hello' ); ?></h2>
					<p><?php esc_html_e( 'Availability and pricing will be available here once the booking integration is connected.', 'aloha-hello' ); ?></p>
					<p class="aloha-rental-booking-note"><?php esc_html_e( 'Online reservations and payments are not enabled yet.', 'aloha-hello' ); ?></p>
				</div>
			</aside>
		</div>
		<?php echo aloha_rental_related( $rental ); // Generated and escaped by renderer. ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
