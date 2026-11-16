<?php

namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function nearby_place_editor_url( $id = 0 ) {
	$url = admin_url( 'edit.php?post_type=' . NEARBY_PLACE_TYPE . '&page=aloha-nearby-place' );
	return $id ? add_query_arg( 'place_id', $id, $url ) : $url;
}

add_action( 'admin_menu', function () {
	$list = 'edit.php?post_type=' . NEARBY_PLACE_TYPE;
	add_submenu_page(
		$list, __( 'Nearby Place', 'aloha-content' ), __( 'Add Nearby Place', 'aloha-content' ),
		NEARBY_PLACE_CAPABILITY, 'aloha-nearby-place', __NAMESPACE__ . '\render_nearby_place_editor'
	);
	remove_submenu_page( $list, 'post-new.php?post_type=' . NEARBY_PLACE_TYPE );
} );

add_action( 'load-post-new.php', function () {
	if ( NEARBY_PLACE_TYPE === ( $_GET['post_type'] ?? null ) && current_user_can( NEARBY_PLACE_CAPABILITY ) ) {
		wp_safe_redirect( nearby_place_editor_url() );
		exit;
	}
} );

add_action( 'load-post.php', function () {
	if ( 'edit' !== ( $_GET['action'] ?? null ) ) {
		return;
	}
	$id = sanitize_area_id( $_GET['post'] ?? null );
	if ( $id > 0 && NEARBY_PLACE_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
		wp_safe_redirect( nearby_place_editor_url( $id ) );
		exit;
	}
} );

function nearby_editable_place( $id ) {
	$post = $id > 0 ? get_post( $id ) : null;
	if ( 0 !== $id && ( ! $post || NEARBY_PLACE_TYPE !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'edit_post', $id ) ) ) {
		wp_die( esc_html__( 'You cannot edit this Nearby Place.', 'aloha-content' ), '', array( 'response' => 403 ) );
	}
	return $post;
}

function render_nearby_place_editor() {
	$id = sanitize_area_id( $_GET['place_id'] ?? 0 );
	$post = nearby_editable_place( $id );
	$details = nearby_place_details( nearby_saved_details( $id ), 'draft' );
	if ( is_wp_error( $details ) ) {
		$details = array( 'category' => '', 'latitude' => null, 'longitude' => null );
	}
	$areas = get_posts( array(
		'post_type'      => 'area',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'       => array( 'title' => 'ASC', 'ID' => 'ASC' ),
	) );
	$new = ! $post || 'auto-draft' === $post->post_status;
	$published = 'publish' === ( $post->post_status ?? null );
	$area_id = nearby_saved_area( $id );
	$list_url = admin_url( 'edit.php?post_type=' . NEARBY_PLACE_TYPE );
	?>
	<div class="wrap">
		<h1><?php echo $new ? esc_html__( 'Add Nearby Place', 'aloha-content' ) : esc_html__( 'Edit Nearby Place', 'aloha-content' ); ?></h1>
		<hr class="wp-header-end">
		<p><a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'All Nearby Places', 'aloha-content' ); ?></a></p>
		<?php if ( isset( $_GET['aloha_nearby_saved'] ) ) : ?>
			<div class="notice notice-success inline is-dismissible"><p><?php esc_html_e( 'Nearby Place saved.', 'aloha-content' ); ?></p></div>
		<?php endif; ?>
		<?php if ( get_post_meta( $id, NEARBY_PLACE_AREA, true ) && ! $area_id ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The previous Area is unavailable. Choose another Area or leave the field empty.', 'aloha-content' ); ?></p></div>
		<?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="aloha_save_nearby_place">
			<input type="hidden" name="place_id" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( 'aloha_save_nearby_place_' . $id ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="aloha-place-name"><?php esc_html_e( 'Name', 'aloha-content' ); ?></label></th>
					<td><input class="regular-text" id="aloha-place-name" name="name" type="text" value="<?php echo esc_attr( $new ? '' : $post->post_title ); ?>" required></td>
				</tr>
				<?php
				foreach ( array(
					'latitude'  => __( 'Latitude', 'aloha-content' ),
					'longitude' => __( 'Longitude', 'aloha-content' ),
				) as $key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="aloha-place-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input id="aloha-place-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="text" inputmode="decimal" maxlength="32" value="<?php echo esc_attr( $details[ $key ] ?? '' ); ?>" aria-describedby="aloha-place-coordinate-help">
						<?php if ( 'latitude' === $key ) : ?>
							<p id="aloha-place-coordinate-help" class="description"><?php esc_html_e( 'Use decimal degrees with a decimal point. Both coordinates are required to publish.', 'aloha-content' ); ?></p>
						<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="aloha-place-category"><?php esc_html_e( 'Category', 'aloha-content' ); ?></label></th>
					<td><input class="regular-text" id="aloha-place-category" name="category" type="text" maxlength="120" value="<?php echo esc_attr( $details['category'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="aloha-place-area"><?php esc_html_e( 'Area', 'aloha-content' ); ?></label></th>
					<td>
						<select id="aloha-place-area" name="area_id">
							<option value="0"><?php esc_html_e( 'No Area', 'aloha-content' ); ?></option>
							<?php foreach ( $areas as $area ) : ?>
								<option value="<?php echo esc_attr( $area->ID ); ?>" <?php selected( $area_id, $area->ID ); ?>><?php echo esc_html( $area->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="place_status" value="publish" class="button button-primary"><?php echo $published ? esc_html__( 'Update', 'aloha-content' ) : esc_html__( 'Publish', 'aloha-content' ); ?></button>
				<button type="submit" name="place_status" value="draft" class="button"><?php echo $published ? esc_html__( 'Save as Draft', 'aloha-content' ) : esc_html__( 'Save Draft', 'aloha-content' ); ?></button>
			</p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_aloha_save_nearby_place', function () {
	if ( ! current_user_can( NEARBY_PLACE_CAPABILITY ) ) {
		wp_die( esc_html__( 'You cannot edit Nearby Places.', 'aloha-content' ), '', array( 'response' => 403 ) );
	}

	$id = sanitize_area_id( $_POST['place_id'] ?? null );
	nearby_editable_place( $id );
	check_admin_referer( 'aloha_save_nearby_place_' . $id );
	$status = $_POST['place_status'] ?? null;
	$name = is_string( $_POST['name'] ?? null ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( ! in_array( $status, array( 'draft', 'publish' ), true ) || '' === $name ) {
		wp_die( esc_html__( 'Enter a name and choose how to save this place.', 'aloha-content' ), '', array( 'response' => 400, 'back_link' => true ) );
	}
	if ( 'publish' === $status && ! current_user_can( get_post_type_object( NEARBY_PLACE_TYPE )->cap->publish_posts ) ) {
		wp_die( esc_html__( 'You cannot publish Nearby Places.', 'aloha-content' ), '', array( 'response' => 403 ) );
	}

	$area = nearby_area_input( wp_unslash( $_POST['area_id'] ?? '0' ) );
	$details = nearby_place_details( wp_unslash( array(
		'latitude'  => $_POST['latitude'] ?? null,
		'longitude' => $_POST['longitude'] ?? null,
		'category'  => $_POST['category'] ?? '',
	) ), $status );
	foreach ( array( $area, $details ) as $part ) {
		if ( is_wp_error( $part ) ) {
			wp_die( esc_html( $part->get_error_message() ), '', array( 'response' => 400, 'back_link' => true ) );
		}
	}

	$save = $id ? 'wp_update_post' : 'wp_insert_post';
	$saved = $save( wp_slash( array(
		'ID'          => $id,
		'post_type'   => NEARBY_PLACE_TYPE,
		'post_title'  => $name,
		'post_status' => $status,
		'meta_input'  => array( NEARBY_PLACE_DETAILS => $details, NEARBY_PLACE_AREA => $area ),
	) ), true );
	if ( is_wp_error( $saved ) || ! $saved || get_post_status( $saved ) !== $status
		|| nearby_place_details( nearby_saved_details( $saved ), $status ) !== $details
		|| nearby_saved_area( $saved ) !== $area ) {
		wp_die( esc_html__( 'WordPress could not save this Nearby Place.', 'aloha-content' ), '', array( 'response' => 500, 'back_link' => true ) );
	}
	wp_safe_redirect( add_query_arg( 'aloha_nearby_saved', '1', nearby_place_editor_url( $saved ) ) );
	exit;
} );

add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( NEARBY_PLACE_TYPE === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'] );
	}
	return $actions;
}, 10, 2 );

add_filter( 'bulk_actions-edit-' . NEARBY_PLACE_TYPE, function ( $actions ) {
	unset( $actions['edit'] );
	return $actions;
} );
