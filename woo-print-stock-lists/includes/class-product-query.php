<?php
defined( 'ABSPATH' ) || exit;

/**
 * Queries WooCommerce products based on category selection and stock status.
 */
class Woo_PSL_Product_Query {

	/**
	 * Returns an array of product rows for the given category IDs.
	 *
	 * Each row:
	 * [
	 *   'sku'   => string,
	 *   'name'  => string,
	 *   'stock' => int|float,
	 * ]
	 *
	 * Variable products: every variation with positive stock appears as a separate row.
	 * Simple products: only those with positive (> 0) stock are included.
	 *
	 * @param int[] $cat_ids Final (expanded) list of category IDs to query.
	 * @return array
	 */
	public static function get_rows( array $cat_ids ): array {
		if ( empty( $cat_ids ) ) {
			return [];
		}

		$args = [
			'post_type'      => [ 'product' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $cat_ids,
					'operator' => 'IN',
				],
			],
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$query       = new WP_Query( $args );
		$product_ids = $query->posts;

		$rows = [];

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				$rows = array_merge( $rows, self::get_variation_rows( $product ) );
			} elseif ( $product->is_type( 'simple' ) ) {
				$row = self::get_simple_row( $product );
				if ( $row ) {
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	/**
	 * Returns a row for a simple/non-variable product, or null if stock <= 0.
	 */
	private static function get_simple_row( \WC_Product $product ): ?array {
		$stock = self::get_stock( $product );

		if ( $stock <= 0 ) {
			return null;
		}

		return [
			'sku'   => $product->get_sku() ?: '—',
			'name'  => $product->get_name(),
			'stock' => $stock,
		];
	}

	/**
	 * Returns rows for all variations of a variable product with positive stock.
	 */
	private static function get_variation_rows( \WC_Product_Variable $product ): array {
		$rows       = [];
		$variations = $product->get_available_variations( 'objects' );

		if ( empty( $variations ) ) {
			return [];
		}

		foreach ( $variations as $variation ) {
			if ( ! ( $variation instanceof \WC_Product_Variation ) ) {
				continue;
			}

			$stock = self::get_stock( $variation );

			if ( $stock <= 0 ) {
				continue;
			}

			// Build a descriptive name: Parent name + attribute values.
			$attr_labels = [];
			foreach ( $variation->get_variation_attributes() as $attr => $value ) {
				if ( '' !== $value ) {
					$term = get_term_by( 'slug', $value, str_replace( 'attribute_', '', $attr ) );
					$attr_labels[] = $term ? $term->name : $value;
				}
			}

			$name = $product->get_name();
			if ( ! empty( $attr_labels ) ) {
				$name .= ' – ' . implode( ', ', $attr_labels );
			}

			$rows[] = [
				'sku'   => $variation->get_sku() ?: $product->get_sku() ?: '—',
				'name'  => $name,
				'stock' => $stock,
			];
		}

		return $rows;
	}

	/**
	 * Returns the stock quantity for a product.
	 * – If stock management is enabled: returns the numeric stock quantity.
	 * – If not managed: returns 1 if status is "instock", else 0.
	 */
	private static function get_stock( \WC_Product $product ): float {
		if ( $product->managing_stock() ) {
			return (float) $product->get_stock_quantity();
		}

		return $product->is_in_stock() ? 1.0 : 0.0;
	}
}
