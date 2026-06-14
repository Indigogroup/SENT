<?php
defined( 'ABSPATH' ) || exit;

/**
 * Builds and queries the WooCommerce product category tree.
 */
class Woo_PSL_Category_Tree {

	/**
	 * Returns the full category hierarchy as a nested array.
	 * Each element: { id, name, slug, children[] }
	 */
	public static function get_tree(): array {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		// Index by ID.
		$by_id = [];
		foreach ( $terms as $term ) {
			$by_id[ $term->term_id ] = [
				'id'       => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => $term->parent,
				'children' => [],
			];
		}

		// Build parent-child relationships.
		$roots = [];
		foreach ( $by_id as $id => &$node ) {
			if ( $node['parent'] && isset( $by_id[ $node['parent'] ] ) ) {
				$by_id[ $node['parent'] ]['children'][] = &$node;
			} else {
				$roots[] = &$node;
			}
		}
		unset( $node );

		return $roots;
	}

	/**
	 * Returns a flat map of parent_id => [child_ids].
	 */
	public static function get_children_map(): array {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'id=>parent',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$map = [];
		foreach ( $terms as $id => $parent ) {
			if ( $parent ) {
				$map[ (int) $parent ][] = (int) $id;
			}
		}

		return $map;
	}

	/**
	 * Given a list of checked category IDs and the children map,
	 * returns the "effective" (terminal) category IDs.
	 *
	 * Logic:
	 * - If a checked category has no checked children → it is terminal.
	 * - If it has checked children → skip it (the children will handle it).
	 */
	public static function get_effective_categories( array $checked_ids, array $children_map ): array {
		$checked_set = array_flip( $checked_ids );
		$effective   = [];

		foreach ( $checked_ids as $cat_id ) {
			$children         = $children_map[ $cat_id ] ?? [];
			$checked_children = array_filter( $children, fn( $c ) => isset( $checked_set[ $c ] ) );

			if ( empty( $checked_children ) ) {
				$effective[] = (int) $cat_id;
			}
		}

		return array_unique( $effective );
	}

	/**
	 * Returns all descendant IDs (inclusive) of a given category.
	 */
	public static function get_all_descendants( int $cat_id, array $children_map ): array {
		$result = [ $cat_id ];
		$queue  = [ $cat_id ];

		while ( ! empty( $queue ) ) {
			$current  = array_shift( $queue );
			$children = $children_map[ $current ] ?? [];
			foreach ( $children as $child ) {
				$result[] = (int) $child;
				$queue[]  = (int) $child;
			}
		}

		return array_unique( $result );
	}

	/**
	 * Expands effective categories to include all descendants.
	 */
	public static function expand_to_descendants( array $effective_ids, array $children_map ): array {
		$all = [];
		foreach ( $effective_ids as $cat_id ) {
			$all = array_merge( $all, self::get_all_descendants( (int) $cat_id, $children_map ) );
		}
		return array_unique( $all );
	}

	/**
	 * Returns category names for a list of IDs.
	 */
	public static function get_names( array $cat_ids ): array {
		if ( empty( $cat_ids ) ) {
			return [];
		}

		$names = [];
		foreach ( $cat_ids as $id ) {
			$term = get_term( (int) $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}
}
