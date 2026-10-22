<?php
/** One contact source; no production values seeded from unverified mockups. */
defined( 'ABSPATH' ) || exit;
add_action( 'admin_init', function () {
	register_setting( 'aloha_shell', 'aloha_shell_mega_menu', array( 'type' => 'array', 'sanitize_callback' => 'aloha_mega_sanitize' ) );
	add_settings_section( 'aloha_mega', 'Mega Menu', function () {
		echo '<p>Editorial content only. Edit links and their order in Site Editor → Navigation. CTA uses the current parent URL. All fields are optional; remove an image to use a text composition.</p>';
	}, 'aloha-shell' );
	foreach ( aloha_mega_sections() as $key => $label ) {
		add_settings_field( 'aloha_mega_' . $key, $label, 'aloha_mega_settings_field', 'aloha-shell', 'aloha_mega', array( 'key' => $key ) );
	}

	register_setting( 'aloha_shell', 'aloha_shell_contact', array( 'type' => 'array', 'sanitize_callback' => function ( $input ) {
		$input = is_array( $input ) ? $input : array();
		$phone = sanitize_text_field( $input['phone'] ?? '' );
		$email = is_string( $input['email'] ?? null ) ? $input['email'] : '';
		return array( 'phone' => preg_replace( '/[^+0-9]/', '', $phone ), 'email' => sanitize_email( $email ), 'verified' => ! empty( $input['verified'] ) );
	} ) );
} );
add_action( 'admin_menu', function () {
	add_options_page( 'Aloha shell', 'Aloha shell', 'manage_options', 'aloha-shell', function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c = aloha_shell_contact();
		?><div class="wrap"><h1>Aloha shell</h1><p>Edit Aloha Primary, Aloha Footer and Aloha Legal in <a href="<?php echo esc_url( admin_url( 'site-editor.php?path=/navigation' ) ); ?>">Site Editor → Navigation</a>. One Primary menu serves desktop and mobile. Footer groups also share one source.</p><form action="options.php" method="post">
		<?php settings_fields( 'aloha_shell' ); ?>
		<table class="form-table"><tr><th><label for="aloha-phone">Phone (international)</label></th><td><input id="aloha-phone" name="aloha_shell_contact[phone]" value="<?php echo esc_attr( $c['phone'] ); ?>" type="tel"></td></tr><tr><th><label for="aloha-email">Email</label></th><td><input id="aloha-email" name="aloha_shell_contact[email]" value="<?php echo esc_attr( $c['email'] ); ?>" type="email"></td></tr><tr><th>Publication</th><td><label><input name="aloha_shell_contact[verified]" type="checkbox" value="1" <?php checked( $c['verified'] ); ?>> I have verified these contacts and approve their publication</label><p>Unverified values do not appear in public output. Call stays in demo mode until verification. Book Now is a demo action during M1.2.</p></td></tr></table>
		<?php do_settings_sections( 'aloha-shell' ); submit_button(); ?></form></div><?php
	} );
} );


function aloha_mega_settings_field( $args ) {
	$key = $args['key'];
	$option = get_option( 'aloha_shell_mega_menu', array() );
	$value = is_array( $option[ $key ] ?? null ) ? $option[ $key ] : array();
	echo '<fieldset class="aloha-mega-settings"><legend class="screen-reader-text">' . esc_html( aloha_mega_sections()[ $key ] ) . '</legend>';
	foreach ( array( 'heading' => 'Heading', 'description' => 'Description', 'cta_label' => 'CTA Label' ) as $field => $label ) {
		$id = 'aloha-mega-' . $key . '-' . $field;
		$name = 'aloha_shell_mega_menu[' . $key . '][' . $field . ']';
		echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br>';
		if ( 'description' === $field ) {
			echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value[ $field ] ?? '' ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value[ $field ] ?? '' ) . '">';
		}
		echo '</p>';
	}
	$id = absint( $value['image_id'] ?? 0 );
	echo '<div class="aloha-media-field"><p>Image</p><div class="aloha-media-preview">';
	if ( aloha_mega_image_valid( $id ) ) { echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'max-width:150px;height:auto' ) ); }
	echo '</div><p><label><input type="checkbox" name="aloha_shell_mega_menu[' . esc_attr( $key ) . '][image_left]" value="1" ' . checked( ! empty( $value['image_left'] ), true, false ) . '> Show image on the left</label></p><input type="hidden" name="aloha_shell_mega_menu[' . esc_attr( $key ) . '][image_id]" value="' . esc_attr( $id ) . '"><button type="button" class="button aloha-media-select">Choose / replace image</button> <button type="button" class="button aloha-media-remove">Remove image</button><p class="description">Media Library attachment; alternative text is edited in Media Library.</p><p class="aloha-media-status" role="status"></p></div></fieldset>';
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'settings_page_aloha-shell' !== $hook || ! current_user_can( 'manage_options' ) ) { return; }
	wp_enqueue_media();
	wp_enqueue_script( 'aloha-shell-settings', get_theme_file_uri( 'assets/js/shell-settings.js' ), array( 'media-views' ), (string) filemtime( get_theme_file_path( 'assets/js/shell-settings.js' ) ), true );
} );
