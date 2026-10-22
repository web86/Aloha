<?php
/** Native categories; explicit, conflict-safe installation, never a request-time seed. */
namespace Aloha\Content;

defined( 'ABSPATH' ) || exit;

function guide_rubrics() {
	return array( 'things-to-do' => 'Things to Do', 'food-and-drink' => 'Food & Drink', 'top-spots' => 'Top Spots', 'events' => 'Events' );
}

function editorial_category_error( $message ) {
	return new \WP_Error( 'aloha_content_categories', $message, array( 'status' => 409 ) );
}

// Shared by exactly two editorial families. IDs are saved by the caller in
// their existing shape; no automatic adoption, term rewrite or data migration.
function editorial_categories( $definitions, $ids, $persist = null ) {
	foreach ( $definitions as $role => $definition ) {
		$id = (int) ( $ids[ $role ] ?? 0 );
		$parent_role = $definition['parent_role'] ?? null;
		$parent = $parent_role ? (int) ( $ids[ $parent_role ] ?? 0 ) : 0;
		if ( $id ) {
			$term = get_term( $id, 'category' );
			if ( ! $term || is_wp_error( $term ) || $term->slug !== $definition['slug'] || (int) $term->parent !== $parent || ( $parent_role && ! $parent ) ) {
				return editorial_category_error( 'Editorial category binding conflict: ' . $definition['slug'] . '. Review Categories; no existing term was changed.' );
			}
		} else {
			if ( ! $persist ) {
				return editorial_category_error( 'Editorial category is not installed: ' . $definition['slug'] . '. Run its category install/check command.' );
			}
			$conflicts = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'slug' => $definition['slug'] ) );
			if ( is_wp_error( $conflicts ) || $conflicts ) {
				return editorial_category_error( 'Unmanaged category slug already exists: ' . $definition['slug'] . '. Review ownership manually; nothing was adopted or changed.' );
			}
			if ( ! $parent_role || $parent ) {
				$names = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'name' => $definition['name'], 'parent' => $parent ) );
				if ( is_wp_error( $names ) || $names ) {
					return editorial_category_error( 'Conflicting category name: ' . $definition['name'] . '. Review it manually.' );
				}
			}
		}
	}
	if ( $persist ) {
		foreach ( $definitions as $role => $definition ) {
			if ( ! empty( $ids[ $role ] ) ) { continue; }
			$parent_role = $definition['parent_role'] ?? null;
			$result = wp_insert_term( $definition['name'], 'category', array( 'slug' => $definition['slug'], 'parent' => $parent_role ? $ids[ $parent_role ] : 0 ) );
			if ( is_wp_error( $result ) ) { return $result; }
			$ids[ $role ] = (int) $result['term_id'];
			$persist( $ids ); // Resume safely after a partial Core API failure.
		}
	}
	return $ids;
}

function guide_categories( $install = false ) {
	$map = get_option( 'aloha_content_guide_categories', array() );
	$definitions = array( 'parent' => array( 'slug' => 'big-island-guide', 'name' => 'Big Island Guide' ) );
	foreach ( guide_rubrics() as $slug => $label ) {
		$definitions[ $slug ] = array( 'slug' => $slug, 'name' => $label, 'parent_role' => 'parent' );
	}
	$shape = fn( $ids ) => array( 'parent' => $ids['parent'], 'rubrics' => array_intersect_key( $ids, guide_rubrics() ) );
	$ids = editorial_categories( $definitions, array( 'parent' => (int) ( $map['parent'] ?? 0 ) ) + ( $map['rubrics'] ?? array() ), $install ? function ( $ids ) use ( $shape ) { update_option( 'aloha_content_guide_categories', $shape( $ids ), false ); } : null );
	return is_wp_error( $ids ) ? $ids : $shape( $ids );
}
function guide_terms() { return guide_categories(); }
function install_guide_categories() { return guide_categories( true ); }

function investor_category( $install = false ) {
	$ids = editorial_categories(
		array( 'investor' => array( 'slug' => 'investor-guides', 'name' => 'Investor Guides' ) ),
		array( 'investor' => (int) get_option( 'aloha_content_investor_category', 0 ) ),
		$install ? function ( $ids ) { update_option( 'aloha_content_investor_category', $ids['investor'], false ); } : null
	);
	return is_wp_error( $ids ) ? $ids : (int) $ids['investor'];
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	foreach ( array( 'guide-categories', 'investor-category' ) as $command ) {
		\WP_CLI::add_command( 'aloha-content ' . $command, function ( $args ) use ( $command ) {
			if ( ! current_user_can( 'manage_categories' ) ) {
				\WP_CLI::error( 'Use --user=<administrator> with manage_categories capability.' );
			}
			$mode = $args[0] ?? 'check';
			if ( ! in_array( $mode, array( 'install', 'check' ), true ) ) { \WP_CLI::error( 'Use install or check.' ); }
			$result = 'guide-categories' === $command ? guide_categories( 'install' === $mode ) : investor_category( 'install' === $mode );
			if ( is_wp_error( $result ) ) { \WP_CLI::error( $result->get_error_message() ); }
			\WP_CLI::success( 'Editorial categories verified; existing names/content preserved.' );
		} );
	}
}
