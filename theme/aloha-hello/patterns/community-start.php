<?php
/**
 * Title: Community — starting composition
 * Slug: aloha-hello/community-start
 * Categories: aloha-community
 * Post Types: community
 * Description: Editable editorial foundations. The split hero is supplied by the Community template.
 */
defined( 'ABSPATH' ) || exit;
// Reuse ordinary Core markup helpers; no additional pattern framework.
foreach ( array(
	'about' => array( 'About the community', 'Editorial placeholder: introduce the setting, character and who this community suits. Replace this text with checked information before publishing.' ),
	'details' => array( 'Community details', 'Editorial placeholder: prepare verified community information for the next design stage. Confirm access rules and any factual claims.' ),
	'location' => array( 'Location and getting around', 'Editorial placeholder: describe the location and practical travel advice. Confirm distances and access before publishing.' ),
	'homeowners' => array( 'For homeowners', 'Editorial placeholder: prepare the homeowner introduction. The rental estimate form will be added in a later stage.' ),
) as $key => $section ) {
	echo aloha_area_group( 'aloha-community-section', aloha_area_group( 'aloha-container', aloha_area_heading( $section[0] ) . aloha_area_paragraph( $section[1] ) ), 'section', 'community-' . $key );
}
