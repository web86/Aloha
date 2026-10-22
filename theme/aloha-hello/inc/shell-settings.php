<?php
/** One contact source; no production values seeded from unverified mockups. */
defined( 'ABSPATH' ) || exit;
add_action( 'admin_init', function () {
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
		<?php submit_button(); ?></form></div><?php
	} );
} );
