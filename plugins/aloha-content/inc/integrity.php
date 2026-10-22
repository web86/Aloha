<?php
/** Integrity checks apply to Core APIs as well as the Gutenberg REST save. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function sanitize_area_id( $value ) {
	// Do not turn negative IDs into valid IDs, or truncate fractional values.
	return is_scalar( $value ) && ! is_bool( $value ) && false !== filter_var( $value, FILTER_VALIDATE_INT ) ? (int) $value : -1;
}

function needs_published_area( $status ) {
	return in_array( $status, array( 'publish', 'future', 'private' ), true );
}

function relation_error( $area_id, $status ) {
	$area_id = sanitize_area_id( $area_id );
	if ( 0 === $area_id && ! needs_published_area( $status ) ) {
		return null;
	}
	$area = $area_id > 0 ? get_post( $area_id ) : null;
	if ( ! $area || 'area' !== $area->post_type || in_array( $area->post_status, array( 'trash', 'auto-draft' ), true ) ) {
		return new \WP_Error( 'aloha_invalid_area', __( 'Choose an existing Area. Draft Communities may be saved without an Area.', 'aloha-content' ), array( 'status' => 400 ) );
	}
	if ( needs_published_area( $status ) && 'publish' !== $area->post_status ) {
		return new \WP_Error( 'aloha_area_not_published', __( 'Publish the selected Area before publishing or scheduling this Community.', 'aloha-content' ), array( 'status' => 409 ) );
	}
	return null;
}

function area_is_used( $area_id, $published_only = false ) {
	return (bool) get_posts( array(
		'post_type'      => 'community',
		'post_status'    => $published_only ? array( 'publish', 'future', 'private' ) : array_keys( get_post_stati() ),
		'meta_key'       => 'aloha_area_id',
		'meta_value'     => (int) $area_id,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
}

function area_in_use_error() {
	return new \WP_Error( 'aloha_area_in_use', __( 'This Area is used by Communities. Reassign those Communities or remove their Area relationship before deleting it. Published or scheduled Communities must keep a published Area.', 'aloha-content' ), array( 'status' => 409 ) );
}

function save_error( $data ) {
	$type = $data['post_type'] ?? get_post_type( $data['ID'] ?? 0 );
	$id = (int) ( $data['ID'] ?? 0 );
	$status = $data['post_status'] ?? ( $id ? get_post_status( $id ) : 'draft' );
	if ( $id && 'area' === get_post_type( $id ) ) {
		if ( ( 'area' !== $type || 'trash' === $status ) && area_is_used( $id ) ) {
			return area_in_use_error();
		}
		if ( 'publish' !== $status && area_is_used( $id, true ) ) {
			return area_in_use_error();
		}
	}
	if ( 'area' === $type ) {
		return area_slug_error( $data );
	}
	if ( 'post' === $type ) {
		return editorial_save_error( $data );
	}
	if ( 'community' === $type ) {
		$meta = $data['meta_input'] ?? array();
		$area_id = array_key_exists( 'aloha_area_id', $meta ) ? $meta['aloha_area_id'] : ( $id ? get_post_meta( $id, 'aloha_area_id', true ) : 0 );
		return relation_error( $area_id, $status );
	}
	return null;
}

function report_block( $error ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::warning( $error->get_error_message() );
	} elseif ( is_admin() && get_current_user_id() ) {
		set_transient( 'aloha_content_notice_' . get_current_user_id(), $error->get_error_message(), MINUTE_IN_SECONDS );
	}
}

// Core has no pre-insert WP_Error filter. Abort before any write; never silently publish/demote.
add_filter( 'wp_insert_post_empty_content', function ( $empty, $data ) {
	$error = save_error( $data );
	if ( $error ) {
		report_block( $error );
		return true;
	}
	return $empty;
}, 10, 2 );

foreach ( array( 'area', 'community', 'post' ) as $type ) {
	add_filter( 'rest_pre_insert_' . $type, function ( $prepared, $request ) use ( $type ) {
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$data = (array) $prepared;
		$data['post_type'] = $type;
		if ( 'post' === $type && isset( $request['categories'] ) ) {
			// Core has already checked term assignment permissions. Save proposed
			// categories with the post so validation sees one atomic publication.
			$data['post_category'] = array_map( 'intval', $request['categories'] );
		}
		if ( 'community' === $type && isset( $request['meta'] ) && array_key_exists( 'aloha_area_id', $request['meta'] ) ) {
			$id = (int) ( $data['ID'] ?? 0 );
			if ( $id && ! current_user_can( 'edit_post_meta', $id, 'aloha_area_id' ) ) {
				return new \WP_Error( 'rest_cannot_update', __( 'You cannot edit this Community’s Area.', 'aloha-content' ), array( 'status' => 403 ) );
			}
			// Core saves post fields before REST meta. Validate both together and make
			// the proposed relation available to the low-level guard on first publish.
			$data['meta_input']['aloha_area_id'] = null === $request['meta']['aloha_area_id'] ? 0 : $request['meta']['aloha_area_id'];
		}
		$error = save_error( $data );
		if ( $error ) {
			return $error;
		}
		if ( isset( $data['meta_input'] ) ) {
			$prepared->meta_input = $data['meta_input'];
		}
		if ( isset( $data['post_category'] ) ) {
			$prepared->post_category = $data['post_category'];
		}
		return $prepared;
	}, 10, 2 );
}

function metadata_error( $post_id, $value ) {
	if ( 'community' !== get_post_type( $post_id ) || ! empty( $GLOBALS['aloha_content_deleting'][ $post_id ] ) ) {
		return null;
	}
	return relation_error( $value, get_post_status( $post_id ) );
}

foreach ( array( 'add', 'update' ) as $operation ) {
	add_filter( $operation . '_post_metadata', function ( $check, $id, $key, $value ) use ( $operation ) {
		if ( 'aloha_area_id' !== $key || 'community' !== get_post_type( $id ) ) {
			return $check;
		}
		$error = metadata_error( $id, $value );
		if ( $error ) {
			report_block( $error );
			return false;
		}
		// single=true is a REST schema promise, not a DB uniqueness constraint.
		if ( 'add' === $operation && metadata_exists( 'post', $id, $key ) ) {
			return false;
		}
		return $check;
	}, 10, 4 );
}

add_filter( 'delete_post_metadata', function ( $check, $id, $key, $value, $all ) {
	if ( 'aloha_area_id' !== $key ) {
		return $check;
	}
	if ( $all ) {
		// Core supports deleting a meta key across every post; do not orphan a published Community.
		$ids = get_posts( array( 'post_type' => 'community', 'post_status' => array( 'publish', 'future', 'private' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => $key ) );
		$error = $ids ? relation_error( 0, 'publish' ) : null;
	} else {
		$error = metadata_error( $id, 0 );
	}
	if ( $error ) {
		report_block( $error );
		return false;
	}
	return $check;
}, 10, 5 );

// The by-meta-ID APIs bypass add/update/delete_post_meta filters.
add_filter( 'update_post_metadata_by_mid', function ( $check, $mid, $value, $key ) {
	$meta = get_metadata_by_mid( 'post', $mid );
	if ( ! $meta || ( 'aloha_area_id' !== $meta->meta_key && 'aloha_area_id' !== $key ) ) {
		return $check;
	}
	if ( 'aloha_area_id' === $meta->meta_key && $key && 'aloha_area_id' !== $key ) {
		$value = 0; // Renaming the key is removal of the relationship.
	}
	$error = metadata_error( $meta->post_id, $value );
	if ( $error ) {
		report_block( $error );
		return false;
	}
	if ( 'aloha_area_id' === $key && 'aloha_area_id' !== $meta->meta_key && metadata_exists( 'post', $meta->post_id, $key ) ) {
		return false;
	}
	return $check;
}, 10, 4 );

add_filter( 'delete_post_metadata_by_mid', function ( $check, $mid ) {
	$meta = get_metadata_by_mid( 'post', $mid );
	$error = $meta && 'aloha_area_id' === $meta->meta_key ? metadata_error( $meta->post_id, 0 ) : null;
	if ( $error ) {
		report_block( $error );
		return false;
	}
	return $check;
}, 10, 2 );

add_action( 'before_delete_post', function ( $id ) { $GLOBALS['aloha_content_deleting'][ $id ] = true; } );
add_action( 'deleted_post', function ( $id ) { unset( $GLOBALS['aloha_content_deleting'][ $id ] ); } );

foreach ( array( 'pre_trash_post', 'pre_delete_post' ) as $hook ) {
	add_filter( $hook, function ( $check, $post ) {
		if ( 'area' !== $post->post_type || ! area_is_used( $post->ID ) ) {
			return $check;
		}
		$error = area_in_use_error();
		report_block( $error );
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			wp_die( esc_html( $error->get_error_message() ), esc_html__( 'Area is in use', 'aloha-content' ), array( 'response' => 409, 'back_link' => true ) );
		}
		return false;
	}, 10, 2 );
}

add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( 'DELETE' === $request->get_method() && preg_match( '#^/wp/v2/area/(\d+)$#D', $request->get_route(), $match ) && current_user_can( 'delete_post', (int) $match[1] ) && area_is_used( (int) $match[1] ) ) {
		return area_in_use_error();
	}
	return $result;
}, 10, 3 );

// wp_publish_post() (including scheduled publication) bypasses wp_insert_post().
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'community' === $post->post_type && needs_published_area( $new ) && relation_error( get_post_meta( $post->ID, 'aloha_area_id', true ), $new ) ) {
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
		report_block( relation_error( get_post_meta( $post->ID, 'aloha_area_id', true ), $new ) );
	}
}, 0, 3 );

// Core normally restores meta by deleting then adding. Use one validated update for
// this key so the delete guard can remain strict, including during a revision restore.
add_filter( 'wp_post_revision_meta_keys', function ( $keys, $type ) {
	return 'community' === $type && doing_action( 'wp_restore_post_revision' ) ? array_values( array_diff( $keys, array( 'aloha_area_id' ) ) ) : $keys;
}, 10, 2 );

add_action( 'wp_restore_post_revision', function ( $id, $revision_id ) {
	if ( 'community' !== get_post_type( $id ) ) {
		return;
	}
	$area_id = metadata_exists( 'post', $revision_id, 'aloha_area_id' ) ? sanitize_area_id( get_post_meta( $revision_id, 'aloha_area_id', true ) ) : 0;
	if ( relation_error( $area_id, get_post_status( $id ) ) ) {
		// An old revision can refer to an Area removed since that revision. Preserve
		// restored content as a draft, requiring an explicit valid selection to publish.
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		if ( relation_error( $area_id, 'draft' ) ) {
			$area_id = 0;
		}
		report_block( new \WP_Error( 'aloha_revision_area', __( 'Revision restored as a draft: its Area is no longer available or published. Select a published Area before publishing.', 'aloha-content' ) ) );
	}
	update_post_meta( $id, 'aloha_area_id', $area_id );
}, 9, 2 );

add_action( 'admin_notices', function () {
	$key = 'aloha_content_notice_' . get_current_user_id();
	$message = get_transient( $key );
	if ( $message ) {
		delete_transient( $key );
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}
} );
