<?php
/** Rental detail presentation. All provider data comes from the local Bridge projection. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/rental-content.php';
require_once __DIR__ . '/rental-booking.php';

// These local faces are all used above the fold. Preload them only on Rental pages
// so fallback-font wrapping does not move the facts and gallery after first paint.
add_filter( 'wp_preload_resources', function ( $resources ) {
	if ( ! is_singular( 'rental' ) ) { return $resources; }
	foreach ( array( 'outfit-latin-variable.woff2', 'instrument-serif-latin-400-normal.woff2', 'instrument-serif-latin-400-italic.woff2' ) as $font ) {
		$resources[] = array( 'href' => get_theme_file_uri( 'assets/fonts/' . $font ), 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous' );
	}
	return $resources;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'rental' ) ) { return; }
	$path = 'assets/css/rental.css';
	wp_enqueue_style( 'aloha-rental', get_theme_file_uri( $path ), array( 'aloha-foundation', 'aloha-media-gallery' ), (string) filemtime( get_theme_file_path( $path ) ) );
	wp_enqueue_style( 'aloha-media-gallery' );
	wp_enqueue_style( 'aloha-media-dialog' );
	wp_enqueue_script( 'aloha-media-dialog' );
	$js = 'assets/js/rental-gallery.js';
	wp_enqueue_script( 'aloha-rental-gallery', get_theme_file_uri( $js ), array( 'aloha-media-dialog' ), (string) filemtime( get_theme_file_path( $js ) ), true );
	$content = 'assets/js/rental-content.js';
	wp_enqueue_script( 'aloha-rental-content', get_theme_file_uri( $content ), array( 'aloha-embla' ), (string) filemtime( get_theme_file_path( $content ) ), true );
	wp_enqueue_style( 'aloha-flatpickr', get_theme_file_uri( 'assets/lib/flatpickr/flatpickr.min.css' ), array(), '4.6.13' );
	wp_enqueue_script( 'aloha-flatpickr', get_theme_file_uri( 'assets/lib/flatpickr/flatpickr.min.js' ), array(), '4.6.13', true );
	wp_enqueue_style( 'aloha-rental-booking', get_theme_file_uri( 'assets/css/rental-booking.css' ), array( 'aloha-rental', 'aloha-flatpickr' ), (string) filemtime( get_theme_file_path( 'assets/css/rental-booking.css' ) ) );
	wp_enqueue_script( 'aloha-rental-booking', get_theme_file_uri( 'assets/js/rental-booking.js' ), array( 'aloha-flatpickr', 'wp-i18n' ), (string) filemtime( get_theme_file_path( 'assets/js/rental-booking.js' ) ), true );
	wp_set_script_translations( 'aloha-rental-booking', 'aloha-hello', get_theme_file_path( 'languages' ) );
	$map = 'assets/js/rental-map.js';
	wp_register_script( 'aloha-rental-map', get_theme_file_uri( $map ), array(), (string) filemtime( get_theme_file_path( $map ) ), true );
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
	if ( ! is_numeric( $value ) || (float) $value < 0 ) { return null; }
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
	$area = $rental['area'] ?? null;
	if ( ! $area || ! class_exists( '\AlohaWPBridge\RentalPublicData' ) ) { return ''; }
	$query = new WP_Query( array( 'post_type' => 'rental', 'post_status' => 'publish',
		'post__not_in' => array( $rental['id'] ), 'posts_per_page' => 12, 'no_found_rows' => true,
		'meta_key' => '_awb_area_id', 'meta_value' => $area['id'], 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
	) );
	$items = array();
	foreach ( $query->posts as $post ) {
		$data = \AlohaWPBridge\RentalPublicData::get( (int) $post->ID );
		if ( $data && ( $data['area']['id'] ?? 0 ) === $area['id'] ) { $items[] = $data; }
		if ( count( $items ) === 3 ) { break; }
	}
	if ( ! $items ) { return ''; }
	ob_start();
	?>
	<section class="aloha-rental-related aloha-section--unit" aria-labelledby="aloha-rental-related-title">
		<div class="aloha-container">
			<div class="aloha-rental-related-heading">
				<h2 id="aloha-rental-related-title"><?php esc_html_e( 'More vacation rentals', 'aloha-hello' ); ?> <?php if ( $area['name'] ?? '' ) : ?><em><?php echo esc_html( sprintf( __( 'in %s', 'aloha-hello' ), $area['name'] ) ); ?></em><?php endif; ?></h2>
				<a class="aloha-button aloha-button--secondary" href="<?php echo esc_url( $area['url'] ); ?>"><?php esc_html_e( 'See all homes', 'aloha-hello' ); ?></a>
			</div>
			<div class="aloha-rental-related-grid">
				<?php foreach ( $items as $item ) { aloha_rental_related_card( $item ); } ?>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

function aloha_rental_related_card( array $item ): void {
	$images = array_slice( array_column( $item['gallery']['photos'], 'image' ), 0, 6 );
	if ( ! $images && is_array( $item['featuredImage'] ) ) { $images[] = $item['featuredImage']; }
	$count = count( $images );
	$id = wp_unique_id( 'aloha-related-' );
	?>
	<article class="aloha-rental-related-card">
		<div class="aloha-related-gallery"<?php echo $count > 1 ? ' data-related-carousel' : ''; ?>>
			<?php if ( $count ) : ?>
				<div class="aloha-related-viewport"<?php if ( $count > 1 ) : ?> data-related-viewport tabindex="0" role="region" aria-label="<?php echo esc_attr( sprintf( __( '%s photos', 'aloha-hello' ), $item['title'] ) ); ?>"<?php endif; ?>>
					<div class="aloha-related-slides">
						<?php foreach ( $images as $index => $image ) : ?>
							<a class="aloha-related-slide" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'aloha-hello' ), $item['title'] ) ); ?>"<?php echo $index ? ' tabindex="-1"' : ''; ?>>
								<img <?php echo $index ? 'data-src' : 'src'; ?>="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>"
									width="<?php echo esc_attr( $image['width'] ); ?>" height="<?php echo esc_attr( $image['height'] ); ?>" loading="lazy" decoding="async"
									<?php if ( $image['srcset'] ) : ?><?php echo $index ? 'data-srcset' : 'srcset'; ?>="<?php echo esc_attr( $image['srcset'] ); ?>" sizes="(min-width: 1280px) 382px, (min-width: 1024px) calc((100vw - 136px) / 3), (min-width: 600px) calc((100vw - 88px) / 2), calc(100vw - 40px)"<?php endif; ?>>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<a class="aloha-related-placeholder" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'aloha-hello' ), $item['title'] ) ); ?>"><?php esc_html_e( 'Photos coming soon', 'aloha-hello' ); ?></a>
			<?php endif; ?>
			<?php if ( $item['badge'] ?? null ) : ?><span class="aloha-related-badge"><?php echo esc_html( strtoupper( $item['badge'] ) ); ?></span><?php endif; ?>
			<?php if ( $count > 1 ) : ?>
				<div data-related-controls hidden>
					<button class="aloha-related-arrow is-prev" type="button" data-related-prev aria-label="<?php echo esc_attr( sprintf( __( 'Previous photo of %s', 'aloha-hello' ), $item['title'] ) ); ?>">‹</button>
					<button class="aloha-related-arrow is-next" type="button" data-related-next aria-label="<?php echo esc_attr( sprintf( __( 'Next photo of %s', 'aloha-hello' ), $item['title'] ) ); ?>">›</button>
					<div class="aloha-related-dots" role="group" aria-label="<?php esc_attr_e( 'Choose a photo', 'aloha-hello' ); ?>">
						<?php foreach ( $images as $index => $_image ) : ?>
							<button type="button" data-related-dot="<?php echo esc_attr( $index ); ?>" aria-pressed="<?php echo $index ? 'false' : 'true'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Show photo %1$d of %2$s', 'aloha-hello' ), $index + 1, $item['title'] ) ); ?>"></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<p class="aloha-related-area"><?php echo esc_html( $item['area']['name'] ?? '' ); ?></p>
		<h3><a href="<?php echo esc_url( $item['url'] ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
		<p class="aloha-related-usp" id="<?php echo esc_attr( $id ); ?>" title="<?php echo esc_attr( $item['usp'] ); ?>"><?php echo esc_html( $item['usp'] ); ?></p>
		<ul class="aloha-related-facts" aria-label="<?php esc_attr_e( 'Property facts', 'aloha-hello' ); ?>">
			<?php foreach ( array( 'bedrooms' => __( '%s bedrooms', 'aloha-hello' ), 'bathrooms' => __( '%s baths', 'aloha-hello' ), 'guests' => __( '%s guests', 'aloha-hello' ) ) as $key => $label ) : $value = aloha_rental_fact( $item, $key ); ?>
				<?php if ( $value !== null ) : ?><li><?php echo esc_html( sprintf( $label, $value ) ); ?></li><?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<?php aloha_rental_related_price( $item['minimumNightlyPrice'] ?? null ); ?>
	</article>
	<?php
}

/** The shared source minimum is observed pricing, never an inferred stay total. */
function aloha_rental_related_price( ?array $pricing = null ): void {
	$valid = $pricing && ( $pricing['status'] ?? '' ) === 'available' && aloha_rental_money( $pricing ) !== '';
	?>
	<p class="aloha-related-price"><?php if ( $valid ) : ?>
		<?php esc_html_e( 'from', 'aloha-hello' ); ?> <strong><?php echo esc_html( aloha_rental_money( $pricing ) ); ?></strong> <?php esc_html_e( '/ night', 'aloha-hello' ); ?>
	<?php else : ?><?php esc_html_e( 'Check dates for pricing', 'aloha-hello' ); ?><?php endif; ?></p>
	<?php
}

/** Decorative bed symbols reflect Guesty bed type; labels remain available as text. */
function aloha_rental_bed_icon( string $type ): string {
	// Basic plan-view bed geometry from the supplied Unit Design export.
	$width = array( 'KING_BED' => 40, 'QUEEN_BED' => 34, 'SINGLE_BED' => 22 )[ $type ] ?? 0;
	if ( $width ) {
		$pillow = $width === 40 ? 15 : ( $width === 34 ? 12 : 14 );
		$paths = '<rect x="0" y="0" width="' . $width . '" height="34" rx="3"/><rect x="4" y="4" width="' . $pillow . '" height="6" rx="2"/>';
		if ( $width > 22 ) {
			$paths .= '<rect x="' . ( $width - $pillow - 4 ) . '" y="4" width="' . $pillow . '" height="6" rx="2"/>';
		}
		return '<svg viewBox="-1 -1 ' . ( $width + 2 ) . ' 36" width="' . ( $width + 2 ) . '" height="36" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">'
			. $paths . '<path d="M0 16h' . $width . '" opacity=".45"/></svg>';
	}
	$paths = array(
		'BUNK_BED' => '<rect x="5" y="6" width="22" height="5" rx="1"/><rect x="5" y="19" width="22" height="5" rx="1"/><path d="M5 5v23M27 5v23M9 12v7M9 16h5"/>',
		'SOFA_BED' => '<path d="M6 16v-4a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4v4M4 16h24v9H4zM7 25v3M25 25v3"/>',
		'CRIB' => '<rect x="4" y="8" width="24" height="15" rx="2"/><path d="M9 9v13M14 9v13M19 9v13M24 9v13M7 23v4M25 23v4"/>',
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
	$gallery = aloha_rental_gallery_render( $rental );
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'aloha-rental-detail', 'data-listing-badge' => $rental['badge'] ?? '' ) ); ?>>
		<div class="aloha-container aloha-rental-intro">
			<nav class="aloha-rental-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'aloha-hello' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'aloha-hello' ); ?></a><span aria-hidden="true">/</span>
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
		</div>
		<div class="aloha-container"><?php echo $gallery; // Escaped inside renderer. ?></div>
		<div class="aloha-container aloha-rental-body">
			<?php aloha_rental_overview( $rental ); ?>
			<?php aloha_rental_booking( $rental ); ?>
			<div class="aloha-rental-content">
				<?php aloha_rental_highlights( $rental['highlights'] ); ?>
				<?php aloha_rental_about( $rental ); ?>
				<?php if ( ! empty( $rental['sleeping'] ) ) : ?>
					<section class="aloha-rental-section" aria-labelledby="aloha-rental-sleep-title">
						<h2 id="aloha-rental-sleep-title"><?php echo wp_kses( __( 'Where everyone <em>sleeps</em>', 'aloha-hello' ), array( 'em' => array() ) ); ?></h2>
						<div class="aloha-rental-sleeping">
							<?php foreach ( $rental['sleeping'] as $room ) : ?>
								<article class="aloha-rental-sleep-card">
									<div class="aloha-rental-bed-icons" aria-hidden="true">
										<?php foreach ( $room['beds'] as $bed ) : ?>
											<?php echo aloha_rental_bed_icon( $bed['type'] ); // Static SVG geometry. ?>
										<?php endforeach; ?>
									</div>
									<h3><?php echo esc_html( $room['name'] ); ?></h3>
									<p><?php
										$beds = array();
										foreach ( $room['beds'] as $bed ) {
											$beds[] = (int) $bed['quantity'] . ' × ' . ucwords( strtolower( str_replace( '_', ' ', $bed['type'] ) ) );
										}
										echo esc_html( implode( ', ', $beds ) );
										$bathrooms = $room['access']['bathroomAccess'] ?? array();
										if ( $bathrooms ) { echo ' · ' . esc_html( implode( ', ', $bathrooms ) ); }
									?></p>
									<?php
									$access = $room['access'] ?? array( 'level' => '', 'steps' => null );
									$parts = array();
									if ( $access['level'] ) { $parts[] = $access['level'] === 'ground' ? __( 'Ground floor', 'aloha-hello' ) : __( 'Upstairs', 'aloha-hello' ); }
									if ( $access['steps'] !== null ) { $parts[] = $access['steps'] === 0 ? __( 'no steps', 'aloha-hello' ) : sprintf( __( '%d steps', 'aloha-hello' ), $access['steps'] ); }
									?>
									<?php if ( $parts ) : ?><span class="aloha-rental-access <?php echo $access['level'] === 'ground' ? 'is-ground' : ''; ?>"><?php echo esc_html( implode( ' · ', $parts ) ); ?></span><?php endif; ?>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>
				<?php aloha_rental_amenities( $rental['amenities'] ); ?>
				<?php aloha_rental_nearby( $rental['nearby'], $rental['publicMapLocation'] ?? null, $rental['title'] ); ?>
				<?php aloha_rental_reviews( $rental ); ?>
				<?php aloha_rental_contact( $rental ); ?>
				<?php aloha_rental_rules( $rental ); ?>
			</div>
		</div>
		<?php echo aloha_rental_related( $rental ); // Generated and escaped by renderer. ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
