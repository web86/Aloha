<?php
/** Native, unsynced editorial blocks. No Area-specific copy or attachment IDs. */
defined( 'ABSPATH' ) || exit;

function aloha_area_group( $class, $content, $tag = 'div', $anchor = '' ) {
	$attrs = array( 'className' => $class, 'layout' => array( 'type' => 'default' ) );
	if ( 'div' !== $tag ) { $attrs['tagName'] = $tag; }
	if ( $anchor ) { $attrs['anchor'] = $anchor; }
	return '<!-- wp:group ' . wp_json_encode( $attrs ) . ' --><' . $tag . ( $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '' ) . ' class="wp-block-group ' . esc_attr( $class ) . '">' . $content . '</' . $tag . '><!-- /wp:group -->';
}
function aloha_area_heading( $text, $level = 2 ) {
	return '<!-- wp:heading ' . wp_json_encode( array( 'level' => $level ) ) . ' --><h' . $level . ' class="wp-block-heading">' . wp_kses_post( $text ) . '</h' . $level . '><!-- /wp:heading -->';
}
function aloha_area_paragraph( $text, $class = '' ) {
	return '<!-- wp:paragraph' . ( $class ? ' ' . wp_json_encode( array( 'className' => $class ) ) : '' ) . ' --><p' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . wp_kses_post( $text ) . '</p><!-- /wp:paragraph -->';
}
function aloha_area_photo( $label ) {
	return aloha_area_group( 'aloha-area-photo', '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img alt=""/></figure><!-- /wp:image -->' . aloha_area_paragraph( $label, 'aloha-area-placeholder' ) );
}
function aloha_area_button( $label, $destination ) {
	$url = aloha_destinations()['destinations'][ $destination ][1];
	return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-secondary aloha-area-planned-link"} --><div class="wp-block-button is-style-secondary aloha-area-planned-link"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
}
function aloha_area_pattern_content( $name ) {
	if ( 'area-introduction' === $name ) {
		return aloha_area_group( 'aloha-area-introduction', aloha_area_heading( 'What this area <em>is like</em>' ) . aloha_area_paragraph( 'Editorial placeholder: describe the landscape, atmosphere and everyday rhythm in your own words.' ) . aloha_area_paragraph( 'Explain who this area suits, and what visitors should consider before choosing a home.' ) . aloha_area_paragraph( 'Confirm local access, seasonal advice and any factual claims before publishing.' ) );
	}
	if ( 'area-quick-facts' === $name ) {
		$rows = '';
		foreach ( array( 'Setting', 'Beach access', 'Getting here', 'Getting around', 'Accessibility' ) as $label ) {
			$rows .= aloha_area_group( 'aloha-area-fact', aloha_area_paragraph( $label ) . aloha_area_paragraph( '<strong>To be verified</strong>' ) );
		}
		return aloha_area_group( 'aloha-area-facts', $rows );
	}
	if ( 'area-gallery' === $name ) {
		return aloha_area_group( 'aloha-area-section aloha-area-gallery-section', aloha_area_group( 'aloha-container', aloha_area_heading( 'The area <em>in pictures</em>' ) . '<!-- wp:aloha/media-gallery /-->' ), 'section' );
	}
	if ( 'area-beaches' === $name ) {
		return aloha_area_group( 'aloha-area-section aloha-area-beaches', aloha_area_group( 'aloha-container', aloha_area_group( 'aloha-area-section-heading', aloha_area_heading( 'Beaches <em>nearby</em>' ) . aloha_area_paragraph( 'Find a shoreline that suits your day. Confirm conditions and access before setting out.' ) ) . '<!-- wp:aloha/beaches /-->' ), 'section' );
	}
	if ( 'area-activities' === $name ) {
		$featured = ''; $cards = '';
		for ( $i = 0; $i < 8; ++$i ) {
			$card = aloha_area_group( 'aloha-area-activity', aloha_area_photo( 'Reference photo to be added' ) . aloha_area_group( 'aloha-area-activity-caption', aloha_area_heading( 'An island experience', 3 ) . aloha_area_paragraph( 'Editorial placeholder: describe the experience and verify practical details.' ) ) );
			if ( $i < 2 ) { $featured .= $card; } else { $cards .= $card; }
		}
		return aloha_area_group( 'aloha-area-section aloha-area-activities', aloha_area_group( 'aloha-container', aloha_area_heading( 'Things to see <em>and do</em>' ) . aloha_area_group( 'aloha-area-activity-featured', $featured ) . aloha_area_group( 'aloha-area-activity-grid', $cards ) ), 'section' );
	}
	if ( 'area-practical-advice' === $name ) {
		$content = aloha_area_heading( 'Staying here: <em>what to know</em>' );
		foreach ( array( 'Weather by season', 'Getting here and getting around', 'Groceries and everyday needs', 'Choosing the right area' ) as $title ) {
			$content .= aloha_area_heading( $title, 3 ) . aloha_area_paragraph( 'Editorial placeholder: add checked, useful local advice. Do not publish unverified travel times, access promises or other numerical claims.' );
		}
		return aloha_area_group( 'aloha-area-practical', $content );
	}
	if ( 'area-faq' === $name ) {
		$content = aloha_area_paragraph( 'Questions guests ask', 'aloha-eyebrow' );
		foreach ( array( 'Is this area right for my trip?', 'Will I need a rental car?', 'How do I get here?', 'How does this area compare?', 'When should I visit?' ) as $question ) {
			$content .= '<!-- wp:details --><details class="wp-block-details"><summary>' . esc_html( $question ) . '</summary>' . aloha_area_paragraph( 'Editorial placeholder: add a verified answer. Duplicate this Details block to add another question.' ) . '</details><!-- /wp:details -->';
		}
		return aloha_area_group( 'aloha-area-faq', $content );
	}
	if ( 'area-owner-cta' === $name ) {
		$advisor = aloha_area_group( 'aloha-area-section aloha-area-advisor', aloha_area_group( 'aloha-container', aloha_area_group( 'aloha-area-advisor-panel', aloha_area_group( 'aloha-area-advisor-copy', aloha_area_heading( 'A little help <em>choosing?</em>', 3 ) . aloha_area_paragraph( 'Tell us what a good island stay looks like for you.' ) ) . aloha_area_button( 'Help me choose', 'contact' ) ) ), 'section' );
		return $advisor . aloha_area_group( 'aloha-area-owner', aloha_area_group( 'aloha-container aloha-area-owner-inner', aloha_area_paragraph( 'Own a home here? Let’s talk about its rental potential.' ) . aloha_area_button( 'Free rental estimate', 'estimate' ) ), 'section' );
	}
	if ( 'area-start' === $name ) {
		return aloha_area_group( 'aloha-area-section aloha-area-intro-section', aloha_area_group( 'aloha-container aloha-area-intro-grid', aloha_area_pattern_content( 'area-introduction' ) . aloha_area_pattern_content( 'area-quick-facts' ) ), 'section', 'area-content' )
			. '<!-- wp:aloha/area-rentals /-->' . aloha_area_pattern_content( 'area-gallery' ) . aloha_area_pattern_content( 'area-beaches' ) . aloha_area_pattern_content( 'area-activities' )
			. '<!-- wp:aloha/area-communities /-->'
			. aloha_area_group( 'aloha-area-section aloha-area-advice-section', aloha_area_group( 'aloha-container aloha-area-advice-grid', aloha_area_pattern_content( 'area-practical-advice' ) . aloha_area_pattern_content( 'area-faq' ) ), 'section' ) . aloha_area_pattern_content( 'area-owner-cta' );
	}
	return '';
}
