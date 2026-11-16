<?php

namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

const NEARBY_PLACE_TYPE = 'nearby_place';
const NEARBY_PLACE_DETAILS = '_aloha_nearby_details';
const NEARBY_PLACE_AREA = '_aloha_nearby_area_id';
const NEARBY_PLACE_CAPABILITY = 'manage_options';

function register_nearby_places() {
	register_post_type( NEARBY_PLACE_TYPE, array(
		'labels' => array(
			'name'          => __( 'Nearby Places', 'aloha-content' ),
			'singular_name' => __( 'Nearby Place', 'aloha-content' ),
			'add_new_item'  => __( 'Add Nearby Place', 'aloha-content' ),
			'edit_item'     => __( 'Edit Nearby Place', 'aloha-content' ),
		),
		'public'                => false,
		'publicly_queryable'    => false,
		'exclude_from_search'   => true,
		'show_ui'               => true,
		'show_in_rest'          => true,
		'show_in_nav_menus'     => false,
		'has_archive'           => false,
		'rewrite'               => false,
		'query_var'             => false,
		'menu_icon'             => 'dashicons-location',
		'supports'              => array( 'title' ),
		'capability_type'       => array( 'nearby_place', 'nearby_places' ),
		'map_meta_cap'          => true,
		'capabilities'          => array_fill_keys( array(
			'read', 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts',
			'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts',
			'delete_private_posts', 'delete_published_posts',
		), NEARBY_PLACE_CAPABILITY ),
		'rest_namespace'        => 'aloha-content/v1',
		'rest_base'             => 'nearby-places',
		'rest_controller_class' => Nearby_Places_Controller::class,
	) );
	foreach ( array( NEARBY_PLACE_DETAILS => 'object', NEARBY_PLACE_AREA => 'integer' ) as $key => $type ) {
		register_post_meta( NEARBY_PLACE_TYPE, $key, array(
			'type'          => $type,
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => static function ( $allowed, $key, $id ) {
				return current_user_can( 'edit_post', $id );
			},
		) );
	}
}

function nearby_requires_coordinates( $status ) {
	return in_array( $status, array( 'publish', 'future', 'private' ), true );
}

/** Validate the pair together so a single metadata write cannot leave invalid coordinates. */
function nearby_place_details( $value, $status ) {
	if ( ! is_array( $value ) || array_diff_key( $value, array_flip( array( 'latitude', 'longitude', 'category' ) ) ) ) {
		return new \WP_Error( 'aloha_nearby_details', __( 'Invalid place details.', 'aloha-content' ) );
	}

	$details = array();
	foreach ( array( 'latitude' => 90, 'longitude' => 180 ) as $key => $limit ) {
		$input = $value[ $key ] ?? null;
		if ( is_string( $input ) ) {
			$input = trim( $input );
		}
		$details[ $key ] = null;
		if ( null === $input || '' === $input ) {
			continue;
		}
		$numeric = is_int( $input ) || is_float( $input )
			|| ( is_string( $input ) && strlen( $input ) <= 32 && preg_match( '/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)\z/', $input ) );
		if ( ! $numeric || ! is_finite( (float) $input ) || abs( (float) $input ) > $limit ) {
			return new \WP_Error( 'aloha_nearby_coordinates', __( 'Enter a latitude from −90 to 90 and a longitude from −180 to 180, using decimal degrees.', 'aloha-content' ) );
		}
		$details[ $key ] = (float) $input;
	}

	if ( nearby_requires_coordinates( $status ) && ( null === $details['latitude'] || null === $details['longitude'] ) ) {
		return new \WP_Error( 'aloha_nearby_coordinates_required', __( 'Enter both coordinates before publishing a Nearby Place.', 'aloha-content' ) );
	}

	$category = $value['category'] ?? '';
	if ( ! is_string( $category ) || strlen( $category ) > 480 ) {
		return new \WP_Error( 'aloha_nearby_category', __( 'Enter a short text label for the category.', 'aloha-content' ) );
	}
	$details['category'] = sanitize_text_field( $category );
	return $details;
}

/** An optional Area reference only resolves while the Area is published. */
function nearby_published_area( $id ) {
	$area = $id > 0 ? get_post( $id ) : null;
	return $area && 'area' === $area->post_type && 'publish' === $area->post_status ? $area : null;
}

function nearby_area_input( $value ) {
	$id = is_int( $value ) || is_string( $value ) ? sanitize_area_id( $value ) : -1;
	if ( 0 === $id || nearby_published_area( $id ) ) {
		return $id;
	}
	return new \WP_Error( 'aloha_nearby_area', __( 'Choose a published Area or leave Area empty.', 'aloha-content' ) );
}

function nearby_saved_area( $id ) {
	$value = nearby_area_input( get_post_meta( $id, NEARBY_PLACE_AREA, true ) );
	return is_wp_error( $value ) ? 0 : $value;
}

function nearby_saved_details( $id ) {
	return metadata_exists( 'post', $id, NEARBY_PLACE_DETAILS ) ? get_post_meta( $id, NEARBY_PLACE_DETAILS, true ) : array();
}

add_filter( 'wp_insert_post_empty_content', function ( $empty, $data ) {
	$id = (int) ( $data['ID'] ?? 0 );
	$previous = $id ? get_post( $id ) : null;
	$is_place = NEARBY_PLACE_TYPE === $data['post_type'];
	$was_place = NEARBY_PLACE_TYPE === ( $previous->post_type ?? null );
	if ( ! $is_place && ! $was_place ) {
		return $empty;
	}
	if ( ( $previous && $is_place !== $was_place )
		|| ( get_current_user_id() && ! current_user_can( NEARBY_PLACE_CAPABILITY ) ) ) {
		return true;
	}
	if ( 'trash' === $data['post_status'] ) {
		return $empty;
	}
	if ( nearby_requires_coordinates( $data['post_status'] ) && '' === sanitize_text_field( $data['post_title'] ) ) {
		return true;
	}

	$details = $data['meta_input'] ?? array();
	if ( ! is_array( $details ) ) {
		return true;
	}
	$value = array_key_exists( NEARBY_PLACE_DETAILS, $details )
		? wp_unslash( $details[ NEARBY_PLACE_DETAILS ] ) : ( $id ? nearby_saved_details( $id ) : array() );
	$error = nearby_place_details( $value, $data['post_status'] );
	if ( ! is_wp_error( $error ) && array_key_exists( NEARBY_PLACE_AREA, $details ) ) {
		$error = nearby_area_input( $details[ NEARBY_PLACE_AREA ] );
	}
	if ( is_wp_error( $error ) ) {
		report_block( $error );
		return true;
	}
	return $empty;
}, 10, 2 );

// wp_publish_post() and the scheduler bypass the insert filter.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( NEARBY_PLACE_TYPE !== $post->post_type || ! nearby_requires_coordinates( $new ) ) {
		return;
	}
	$error = nearby_place_details( nearby_saved_details( $post->ID ), $new );
	if ( is_wp_error( $error ) || '' === sanitize_text_field( $post->post_title ) ) {
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
		if ( is_wp_error( $error ) ) {
			report_block( $error );
		}
	}
}, 0, 3 );

foreach ( array( 'add', 'update' ) as $operation ) {
	add_filter( $operation . '_post_metadata', function ( $check, $id, $key, $value ) use ( $operation ) {
		if ( ! in_array( $key, array( NEARBY_PLACE_DETAILS, NEARBY_PLACE_AREA ), true ) || NEARBY_PLACE_TYPE !== get_post_type( $id ) ) {
			return $check;
		}
		$validation = NEARBY_PLACE_AREA === $key ? nearby_area_input( $value ) : nearby_place_details( $value, get_post_status( $id ) );
		if ( ( get_current_user_id() && ! current_user_can( 'edit_post', $id ) )
			|| is_wp_error( $validation )
			|| ( 'add' === $operation && metadata_exists( 'post', $id, $key ) ) ) {
			return false;
		}
		return $check;
	}, 10, 4 );
}

foreach ( array( 'pre_trash_post', 'pre_delete_post' ) as $hook ) {
	add_filter( $hook, function ( $check, $post ) {
		if ( NEARBY_PLACE_TYPE === $post->post_type && get_current_user_id() && ! current_user_can( 'delete_post', $post->ID ) ) {
			return false;
		}
		return $check;
	}, 10, 2 );
}

function can_remove_nearby_meta( $id, $key ) {
	return ! empty( $GLOBALS['aloha_content_deleting'][ $id ] )
		|| ( ( NEARBY_PLACE_AREA === $key || ! nearby_requires_coordinates( get_post_status( $id ) ) )
			&& ( ! get_current_user_id() || current_user_can( 'edit_post', $id ) ) );
}

add_filter( 'delete_post_metadata', function ( $check, $id, $key, $value, $all ) {
	if ( in_array( $key, array( NEARBY_PLACE_DETAILS, NEARBY_PLACE_AREA ), true )
		&& ( $all || ( NEARBY_PLACE_TYPE === get_post_type( $id ) && ! can_remove_nearby_meta( $id, $key ) ) ) ) {
		return false;
	}
	return $check;
}, 10, 5 );

add_filter( 'update_post_metadata_by_mid', function ( $check, $mid, $value, $key ) {
	$meta = get_metadata_by_mid( 'post', $mid );
	if ( $meta && array_intersect( array( NEARBY_PLACE_DETAILS, NEARBY_PLACE_AREA ), array( $meta->meta_key, $key ) )
		&& NEARBY_PLACE_TYPE === get_post_type( $meta->post_id ) ) {
		return false;
	}
	return $check;
}, 10, 4 );

add_filter( 'delete_post_metadata_by_mid', function ( $check, $mid ) {
	$meta = get_metadata_by_mid( 'post', $mid );
	if ( $meta && in_array( $meta->meta_key, array( NEARBY_PLACE_DETAILS, NEARBY_PLACE_AREA ), true )
		&& NEARBY_PLACE_TYPE === get_post_type( $meta->post_id ) && ! can_remove_nearby_meta( $meta->post_id, $meta->meta_key ) ) {
		return false;
	}
	return $check;
}, 10, 2 );

/** Trusted PHP consumers must still have permission for this specific place. */
function get_nearby_place_coordinates( int $id ) {
	if ( ! current_user_can( NEARBY_PLACE_CAPABILITY ) ) {
		return new \WP_Error( 'aloha_nearby_forbidden', __( 'You cannot read Nearby Place coordinates.', 'aloha-content' ) );
	}
	$post = $id > 0 ? get_post( $id ) : null;
	if ( ! $post || NEARBY_PLACE_TYPE !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
		return new \WP_Error( 'aloha_nearby_not_found', __( 'Nearby Place not found.', 'aloha-content' ) );
	}
	if ( ! current_user_can( 'edit_post', $id ) ) {
		return new \WP_Error( 'aloha_nearby_forbidden', __( 'You cannot read Nearby Place coordinates.', 'aloha-content' ) );
	}
	$details = nearby_place_details( nearby_saved_details( $id ), $post->post_status );
	return is_wp_error( $details ) ? $details : array_intersect_key( $details, array( 'latitude' => true, 'longitude' => true ) );
}

final class Nearby_Places_Controller extends \WP_REST_Controller {
	public function register_routes() {
		register_rest_route( 'aloha-content/v1', '/nearby-places', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'get_items_permissions_check' ),
			'args'                => array(
				'search'   => array( 'type' => 'string', 'maxLength' => 120, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'areaId'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
			),
		) );
	}

	public function get_items_permissions_check( $request ) {
		return current_user_can( NEARBY_PLACE_CAPABILITY ) ? true : new \WP_Error(
			'aloha_nearby_forbidden', __( 'You cannot search Nearby Places.', 'aloha-content' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function get_items( $request ) {
		$args = array(
			'post_type'           => NEARBY_PLACE_TYPE,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'posts_per_page'      => $request['per_page'],
			'paged'               => $request['page'],
			'orderby'             => array( 'title' => 'ASC', 'ID' => 'ASC' ),
			's'                   => $request['search'],
			'search_columns'      => array( 'post_title' ),
		);
		if ( isset( $request['areaId'] ) ) {
			if ( ! nearby_published_area( $request['areaId'] ) ) {
				return new \WP_Error( 'aloha_nearby_area', __( 'Choose a published Area.', 'aloha-content' ), array( 'status' => 400 ) );
			}
			$args['meta_key'] = NEARBY_PLACE_AREA;
			$args['meta_value'] = $request['areaId'];
			$args['meta_type'] = 'UNSIGNED';
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$details = nearby_place_details( nearby_saved_details( $post->ID ), $post->post_status );
			$name = sanitize_text_field( $post->post_title );
			if ( '' === $name || is_wp_error( $details ) ) {
				continue;
			}
			$items[] = array(
				'id'       => $post->ID,
				'name'     => $name,
				'category' => '' === $details['category'] ? null : $details['category'],
				'areaId'   => nearby_saved_area( $post->ID ) ?: null,
				'status'   => $post->post_status,
			);
		}
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
}
