<?php
/**
 * Product badges: "Bestseller" (top sellers by WooCommerce's sales count,
 * only products that actually sold) and "New" (published recently).
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_CHILD_BESTSELLER_COUNT = 3;   // How many products get the badge.
const EBOOKSTORE_CHILD_NEW_DAYS         = 30;  // "New" for this many days.

/**
 * IDs of the current bestsellers (cached for an hour).
 *
 * @return int[]
 */
function ebookstore_child_bestseller_ids() {
	$ids = get_transient( 'ebookstore_bestseller_ids' );
	if ( false === $ids ) {
		$ids = array();
		$candidates = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => EBOOKSTORE_CHILD_BESTSELLER_COUNT,
				'orderby'  => 'meta_value_num',
				'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'    => 'DESC',
			)
		);
		foreach ( $candidates as $candidate ) {
			if ( (int) $candidate->get_total_sales() > 0 ) {
				$ids[] = $candidate->get_id();
			}
		}
		set_transient( 'ebookstore_bestseller_ids', $ids, HOUR_IN_SECONDS );
	}
	return array_map( 'intval', (array) $ids );
}

// Refresh the list when sales change.
add_action( 'woocommerce_order_status_completed', static fn() => delete_transient( 'ebookstore_bestseller_ids' ) );
add_action( 'woocommerce_order_status_processing', static fn() => delete_transient( 'ebookstore_bestseller_ids' ) );

/**
 * Badge for a product, or null.
 *
 * @param WC_Product $product Product.
 * @return array|null array( 'type' => ..., 'label' => ... )
 */
function ebookstore_child_badge( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return null;
	}
	if ( in_array( $product->get_id(), ebookstore_child_bestseller_ids(), true ) ) {
		return array(
			'type'  => 'bestseller',
			'label' => __( 'Bestseller', 'ebookstore-child' ),
		);
	}
	$created = $product->get_date_created();
	if ( $created && ( time() - $created->getTimestamp() ) < EBOOKSTORE_CHILD_NEW_DAYS * DAY_IN_SECONDS ) {
		return array(
			'type'  => 'new',
			'label' => __( 'New', 'ebookstore-child' ),
		);
	}
	return null;
}

/**
 * Print the badge.
 *
 * @param WC_Product $product Product.
 */
function ebookstore_child_print_badge( $product ) {
	$badge = ebookstore_child_badge( $product );
	if ( $badge ) {
		printf(
			'<span class="ebook-badge ebook-badge--%1$s">%2$s</span>',
			esc_attr( $badge['type'] ),
			esc_html( $badge['label'] )
		);
	}
}

// Product cards: badge over the cover (inside the card link, before the image).
add_action(
	'woocommerce_before_shop_loop_item_title',
	static function () {
		global $product;
		ebookstore_child_print_badge( $product );
	},
	5
);

// Product page: badge above the title.
add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		ebookstore_child_print_badge( $product );
	},
	4
);
