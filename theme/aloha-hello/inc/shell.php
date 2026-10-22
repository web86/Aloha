<?php
/** Shared presentation, editable Navigation posts and verified-contact settings. */
defined( 'ABSPATH' ) || exit;

function aloha_destinations() {
	static $registry;
	if ( null === $registry ) {
		$registry = json_decode( file_get_contents( __DIR__ . '/destinations.json' ), true );
	}
	return $registry;
}

function aloha_shell_menu_id( $location ) {
	return absint( get_option( 'aloha_shell_menus', array() )[ $location ] ?? 0 );
}

function aloha_shell_install() {
	wp_get_theme()->delete_pattern_cache();
	$ids = get_option( 'aloha_shell_menus', array() );
	foreach ( array( 'primary', 'footer', 'legal' ) as $location ) {
		if ( ! empty( $ids[ $location ] ) && 'wp_navigation' === get_post_type( $ids[ $location ] ) && 'publish' === get_post_status( $ids[ $location ] ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Aloha ' . ucfirst( $location ), 'post_content' => aloha_shell_menu_content( $location ) ), true );
		if ( is_wp_error( $id ) ) { return $id; }
		$ids[ $location ] = $id;
	}
	update_option( 'aloha_shell_menus', $ids );
	return $ids;
}

function aloha_shell_menu_content( $location ) {
	$registry = aloha_destinations();
	$link = function ( $key, $label = '' ) use ( $registry ) {
		$dest = $registry['destinations'][ $key ];
		return '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $label ?: $dest[0], 'url' => $dest[1], 'kind' => 'custom' ) ) . ' /-->';
	};
	$content = '';
	foreach ( $registry[ $location ] as $entry ) {
		if ( 'legal' === $location ) { $content .= $link( $entry ); continue; }
		if ( empty( $entry[2] ) ) { $content .= $link( $entry[0], $entry[1] ); continue; }
		$content .= '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => $entry[1], 'url' => $registry['destinations'][ $entry[0] ][1], 'kind' => 'custom' ) ) . ' -->';
		foreach ( $entry[2] as $key ) { $content .= $link( $key ); }
		$content .= '<!-- /wp:navigation-submenu -->';
	}
	return $content;
}

add_action( 'after_switch_theme', 'aloha_shell_install' );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'aloha-shell install', function () {
		$result = aloha_shell_install();
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( wp_json_encode( $result ) );
	} );
}

function aloha_shell_contact() {
	return wp_parse_args( get_option( 'aloha_shell_contact', array() ), array( 'phone' => '', 'email' => '', 'verified' => false ) );
}

function aloha_shell_action( $kind, $icon_only = false ) {
	$contact = aloha_shell_contact();
	$label = 'call' === $kind ? 'Call' : 'Book Now';
	$icon = 'call' === $kind ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3h3l2 4-3 2a12 12 0 0 0 6 6l2-3 4 2v3a3 3 0 0 1-3 3A18 18 0 0 1 4 6a3 3 0 0 1 3-3Z"/></svg>' : '';
	$class = 'aloha-button ' . ( $icon_only ? 'aloha-icon-action' : ( 'call' === $kind ? 'aloha-button--secondary' : '' ) );
	if ( 'call' === $kind && $contact['verified'] && $contact['phone'] ) {
		return '<a class="' . esc_attr( $class ) . '" href="tel:' . esc_attr( $contact['phone'] ) . '" aria-label="Call our team">' . $icon . ( $icon_only ? '' : $label ) . '</a>';
	}
	return '<button type="button" class="' . esc_attr( $class ) . '" data-aloha-action="' . esc_attr( $kind ) . '" aria-haspopup="dialog" aria-label="' . esc_attr( $label . ' — demo information' ) . '">' . $icon . ( $icon_only ? '' : $label ) . '</button>';
}

function aloha_shell_footer_links() {
	$id = aloha_shell_menu_id( 'footer' );
	$post = $id ? get_post( $id ) : null;
	if ( ! $post ) {
		return '';
	}
	$html = '<nav class="aloha-footer-groups" aria-label="Footer">';
	foreach ( parse_blocks( $post->post_content ) as $group ) {
		if ( 'core/navigation-submenu' !== $group['blockName'] ) {
			continue;
		}
		$html .= '<details class="aloha-footer-group" open><summary>' . esc_html( $group['attrs']['label'] ?? '' ) . '</summary><ul>';
		foreach ( $group['innerBlocks'] as $item ) {
			$html .= render_block( $item );
		}
		$html .= '</ul></details>';
	}
	return $html . '</nav>';
}

function aloha_render_shell_slot( $attributes ) {
	$slot = $attributes['slot'] ?? '';
	if ( 'brand' === $slot || 'brand-light' === $slot ) {
		$light = 'brand-light' === $slot;
		return '<a class="aloha-brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="Aloha Hello home"><img src="' . esc_url( get_theme_file_uri( 'assets/images/aloha-logo-' . ( $light ? 'light' : 'dark' ) . '.svg' ) ) . '" width="322" height="80" alt="Aloha Hello Vacation Rentals"></a>';
	}
	if ( 'header-actions' === $slot ) {
		$c = aloha_shell_contact();
		$text = $c['verified'] && $c['phone'] ? $c['phone'] : 'Contact information';
		return '<div class="aloha-header-actions"><div class="aloha-header-call">' . aloha_shell_action( 'call', true ) . '<span><small>CALL US · TEAM</small><span>' . esc_html( $text ) . '</span></span></div><div class="aloha-header-book">' . aloha_shell_action( 'book' ) . '</div></div>';
	}
	if ( 'footer' === $slot ) {
		$c = aloha_shell_contact();
		$contact = '<p class="aloha-contact-pending">Contact details pending verification.</p>';
		if ( $c['verified'] && ( $c['phone'] || $c['email'] ) ) {
			$contact = '<p class="aloha-contact-values">';
			if ( $c['phone'] ) {
				$contact .= '<a href="tel:' . esc_attr( $c['phone'] ) . '">' . esc_html( $c['phone'] ) . '</a><br>';
			}
			if ( $c['email'] ) {
				$contact .= '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>';
			}
			$contact .= '</p>';
		}
		$legal = do_blocks( '<!-- wp:navigation ' . wp_json_encode( array( 'ref' => aloha_shell_menu_id( 'legal' ), 'overlayMenu' => 'never', 'className' => 'aloha-legal' ) ) . ' /-->' );
		return '<div class="aloha-container aloha-footer-inner"><div class="aloha-footer-top"><div class="aloha-footer-identity">' . aloha_render_shell_slot( array( 'slot' => 'brand-light' ) ) . $contact . '<p>Aloha Hello Vacation Rentals</p></div>' . aloha_shell_footer_links() . '</div><div class="aloha-footer-legal"><span>© ' . esc_html( wp_date( 'Y' ) ) . ' Aloha Hello Vacation Rentals</span>' . $legal . '</div></div>';
	}
	if ( 'utilities' === $slot ) {
		return '<div class="aloha-bottom-bar" aria-label="Quick actions">' . aloha_shell_action( 'call' ) . aloha_shell_action( 'book' ) . '</div><dialog class="aloha-demo-dialog" aria-labelledby="aloha-demo-title" aria-describedby="aloha-demo-message"><h2 id="aloha-demo-title">Demo information</h2><p id="aloha-demo-message"></p><button type="button" data-aloha-close>Close</button></dialog>';
	}
	return '';
}

add_action( 'init', function () {
	register_block_type( 'aloha/shell-slot', array(
		'api_version' => 3, 'title' => 'Aloha shell element', 'category' => 'theme',
		'attributes' => array( 'slot' => array( 'type' => 'string' ) ),
		'supports' => array( 'html' => false, 'inserter' => false ),
		'render_callback' => 'aloha_render_shell_slot',
		'editor_script' => 'aloha-shell-editor',
	) );
	wp_register_script( 'aloha-shell-editor', get_theme_file_uri( 'assets/js/shell-editor.js' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ), '0.2.0', true );
} );

add_action( 'enqueue_block_assets', function () {
	if ( is_admin() ) {
		wp_enqueue_style( 'aloha-shell-editor-preview', get_theme_file_uri( 'assets/css/shell.css' ), array(), (string) filemtime( get_theme_file_path( 'assets/css/shell.css' ) ) );
	}
} );

// Planned links remain canonical. Only the local shell preview intercepts them.
function aloha_mark_planned_link( $html, $block ) {
	$url = $block['attrs']['url'] ?? '';
	if ( $url && home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) === ( str_starts_with( $url, '/' ) ? home_url( $url ) : $url ) ) {
		$current = new WP_HTML_Tag_Processor( $html );
		if ( $current->next_tag( 'a' ) ) { $current->set_attribute( 'aria-current', 'page' ); }
		$html = $current->get_updated_html();
	}
	if ( 'local' !== wp_get_environment_type() ) { return $html; }
	foreach ( aloha_destinations()['destinations'] as $key => $dest ) {
		$matches = $url === $dest[1] || $url === home_url( $dest[1] );
		$id = $matches ? url_to_postid( home_url( $dest[1] ) ) : 0;
		if ( $matches && ( ! $id || 'publish' !== get_post_status( $id ) ) ) {
			$p = new WP_HTML_Tag_Processor( $html );
			if ( $p->next_tag( 'a' ) ) {
				$p->set_attribute( 'data-aloha-destination', $key );
				$p->set_attribute( 'data-aloha-stage', $dest[2] );
				$p->set_attribute( 'title', 'Planned destination · ' . $dest[2] );
			}
			return $p->get_updated_html();
		}
	}
	return $html;
}
add_filter( 'render_block_core/navigation-link', 'aloha_mark_planned_link', 10, 2 );
add_filter( 'render_block_core/navigation-submenu', 'aloha_mark_planned_link', 10, 2 );

// Keep separate parent links + Core disclosure buttons, with explicit activation.
// A hover-open popup would remain under the pointer after Escape in Core's hover mode.
add_filter( 'render_block_core/navigation', function ( $html, $block ) {
	if ( ! str_contains( $block['attrs']['className'] ?? '', 'aloha-primary-nav' ) ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( 'LI' ) ) {
		$p->remove_attribute( 'data-wp-on--pointerenter' );
		$p->remove_attribute( 'data-wp-on--pointerleave' );
	}
	return $p->get_updated_html();
}, 20, 2 );

require_once __DIR__ . '/shell-settings.php';
