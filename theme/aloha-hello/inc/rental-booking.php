<?php
/** Booking presentation consumes the Bridge's public DTOs; no provider calls or price math. */
defined( 'ABSPATH' ) || exit;

function aloha_rental_money( array $price ): string {
	$currency = $price['currency'] ?? ''; $amount = $price['amount'] ?? null;
	if ( ! is_string( $amount ) || ! preg_match( '/^\d+(?:\.\d{1,3})?$/', $amount ) || ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/', $currency ) ) { return ''; }
	if ( class_exists( 'NumberFormatter' ) ) {
		$formatter = new NumberFormatter( str_replace( '_', '-', get_locale() ), NumberFormatter::CURRENCY );
		if ( preg_match( '/\.0+$/', $amount ) || ! str_contains( $amount, '.' ) ) { $formatter->setAttribute( NumberFormatter::MAX_FRACTION_DIGITS, 0 ); $formatter->setAttribute( NumberFormatter::MIN_FRACTION_DIGITS, 0 ); }
		$formatted = $formatter->formatCurrency( (float) $amount, $currency );
		if ( is_string( $formatted ) ) { return $formatted; }
	}
	return $currency . ' ' . number_format_i18n( (float) $amount, strlen( explode( '.', $amount )[1] ?? '' ) );
}

function aloha_rental_booking( array $rental ): void {
	$id = (int) $rental['id'];
	try { $zone = new DateTimeZone( $rental['facts']['timezone']['value'] ?? 'UTC' ); } catch ( Throwable $error ) { $zone = new DateTimeZone( 'UTC' ); }
	$config = array( 'rentalId' => $id, 'today' => ( new DateTimeImmutable( 'now', $zone ) )->format( 'Y-m-d' ), 'api' => rest_url( 'aloha-bridge/v1/rentals/' . $id . '/' ), 'preview' => array(), 'nonce' => '' );
	$config['locale'] = str_replace( '_', '-', get_locale() );
	$config['partialNotice'] = __( 'Some charges are estimated or not itemized', 'aloha-hello' );
	if ( is_preview() && current_user_can( 'edit_post', $id ) ) {
		foreach ( array( 'preview_id', 'preview_nonce', 'awb_preview_id', 'awb_preview_nonce' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) { $config['preview'][ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); }
		}
		$config['nonce'] = wp_create_nonce( 'wp_rest' );
	}
	// Embed only the Bridge's public allowlist: opening an existing snapshot needs no fetch.
	if ( class_exists( '\AlohaWPBridge\Pricing\LocalCalendar' ) ) {
		$calendar = \AlohaWPBridge\Pricing\LocalCalendar::availability( $id );
		if ( is_array( $calendar ) ) { $config['calendar'] = $calendar; }
	}
	$minimum = $rental['minimumNightlyPrice'] ?? array();
	$price = ( $minimum['status'] ?? '' ) === 'available' ? aloha_rental_money( $minimum ) : '';
	$capacity = (int) ( $rental['facts']['guests']['value'] ?? 0 );
	?>
	<aside class="aloha-rental-booking" aria-labelledby="aloha-rental-booking-title" data-booking-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
		<div class="aloha-rental-booking-card">
			<h2 class="screen-reader-text" id="aloha-rental-booking-title"><?php esc_html_e( 'Plan your stay', 'aloha-hello' ); ?></h2>
			<div class="aloha-booking-top">
				<p data-booking-heading><?php if ( $price ) : ?><span class="aloha-booking-from"><?php esc_html_e( 'from', 'aloha-hello' ); ?></span> <strong><?php echo esc_html( $price ); ?></strong> <span class="aloha-booking-from"><?php esc_html_e( '/ night', 'aloha-hello' ); ?></span><?php else : ?><?php esc_html_e( 'Check dates for pricing', 'aloha-hello' ); ?><?php endif; ?></p>
				<span class="aloha-booking-instant"><span aria-hidden="true">ϟ</span> <?php esc_html_e( 'Instant booking', 'aloha-hello' ); ?></span>
			</div>
			<div class="aloha-booking-fields">
				<button type="button" data-booking-dates="in" aria-haspopup="dialog" aria-expanded="false" aria-controls="aloha-booking-calendar"><span><?php esc_html_e( 'Check-in', 'aloha-hello' ); ?></span><strong data-check-in><?php esc_html_e( 'Add date', 'aloha-hello' ); ?></strong></button>
				<button type="button" data-booking-dates="out" aria-haspopup="dialog" aria-expanded="false" aria-controls="aloha-booking-calendar"><span><?php esc_html_e( 'Checkout', 'aloha-hello' ); ?></span><strong data-check-out><?php esc_html_e( 'Add date', 'aloha-hello' ); ?></strong></button>
				<details class="aloha-booking-guests">
					<summary><span><?php esc_html_e( 'Guests', 'aloha-hello' ); ?></span><strong data-guest-summary><?php echo esc_html( $capacity === 1 ? __( '1 guest', 'aloha-hello' ) : __( '2 guests', 'aloha-hello' ) ); ?></strong><span class="aloha-booking-chevron" aria-hidden="true">⌄</span></summary>
					<div class="aloha-booking-party">
						<?php foreach ( array( 'adults' => __( 'Adults', 'aloha-hello' ), 'children' => __( 'Children', 'aloha-hello' ) ) as $kind => $label ) : ?>
							<div class="aloha-booking-party-row">
								<div><label for="aloha-booking-<?php echo esc_attr( $kind ); ?>"><?php echo esc_html( $label ); ?></label><?php if ( $kind === 'children' ) : ?><small><?php esc_html_e( 'Ages 2–12', 'aloha-hello' ); ?></small><?php endif; ?></div>
								<div class="aloha-booking-stepper">
									<button type="button" data-guest-step="<?php echo esc_attr( $kind . ':-1' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Fewer %s', 'aloha-hello' ), strtolower( $label ) ) ); ?>">−</button>
									<input id="aloha-booking-<?php echo esc_attr( $kind ); ?>" data-guests="<?php echo esc_attr( $kind ); ?>" type="number" min="<?php echo $kind === 'adults' ? '1' : '0'; ?>" max="<?php echo esc_attr( $capacity ?: 99 ); ?>" value="<?php echo $kind === 'adults' ? ( $capacity === 1 ? '1' : '2' ) : '0'; ?>" inputmode="numeric">
									<button type="button" data-guest-step="<?php echo esc_attr( $kind . ':1' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'More %s', 'aloha-hello' ), strtolower( $label ) ) ); ?>">+</button>
								</div>
							</div>
						<?php endforeach; ?>
						<p data-guests-help><?php esc_html_e( 'At least one adult. Guest composition is confirmed during booking.', 'aloha-hello' ); ?></p>
						<button type="button" class="aloha-booking-text-button" data-guests-done><?php esc_html_e( 'Done', 'aloha-hello' ); ?></button>
					</div>
				</details>
			</div>
			<div data-booking-price hidden>
				<div class="aloha-booking-total"><strong><?php esc_html_e( 'Total', 'aloha-hello' ); ?></strong><strong data-total-amount aria-live="polite"></strong></div>
				<details class="aloha-booking-breakdown"><summary><?php esc_html_e( 'View details', 'aloha-hello' ); ?></summary><div><h3><?php esc_html_e( 'Price details', 'aloha-hello' ); ?></h3><dl data-price-lines></dl><p data-price-detail-note></p></div></details>
			</div>
			<p class="aloha-booking-status" data-booking-status role="status" aria-live="polite"></p>
			<button type="button" class="aloha-booking-primary" data-booking-action><span class="aloha-booking-spinner" aria-hidden="true" hidden></span><span data-action-text><?php esc_html_e( 'Check availability', 'aloha-hello' ); ?></span></button>
			<p class="aloha-booking-charge" data-booking-charge hidden><?php esc_html_e( 'You won’t be charged yet', 'aloha-hello' ); ?></p>
			<div class="aloha-booking-save" data-booking-save><strong><?php esc_html_e( 'Book direct and save 15%', 'aloha-hello' ); ?></strong> <?php esc_html_e( 'compared to Airbnb', 'aloha-hello' ); ?></div>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to check dates and estimated prices.', 'aloha-hello' ); ?></p></noscript>
		</div>
		<div id="aloha-booking-calendar" class="aloha-booking-calendar" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Booking dates', 'aloha-hello' ); ?>" hidden>
			<button type="button" class="screen-reader-text" data-calendar-close aria-label="<?php esc_attr_e( 'Close calendar', 'aloha-hello' ); ?>">×</button>
			<p class="screen-reader-text" data-calendar-help role="status" aria-live="polite"></p>
			<div class="aloha-calendar-loading" data-calendar-loading hidden><span class="aloha-booking-spinner" aria-hidden="true"></span><?php esc_html_e( 'Loading availability…', 'aloha-hello' ); ?></div>
			<div data-calendar-surface><input data-calendar-input type="text" tabindex="-1" aria-hidden="true" hidden></div>
			<p data-calendar-note>Minimum stay varies by date</p>
			<button type="button" class="aloha-booking-text-button" data-calendar-retry hidden><?php esc_html_e( 'Retry', 'aloha-hello' ); ?></button>
		</div>
	</aside>
	<?php
}
