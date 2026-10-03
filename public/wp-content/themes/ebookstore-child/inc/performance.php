<?php
/**
 * Image loading: product covers below the fold load lazily.
 *
 * WordPress only decides lazy/eager for images in the post content, so cover
 * images printed by WooCommerce shortcodes on the home page were all loaded
 * immediately. Rules here:
 * - home page: every product cover is lazy (the hero is above them);
 * - shop / category / search pages: the first row (4 covers) loads immediately,
 *   the rest lazily; product pages: related eBooks are lazy;
 * - images that explicitly ask for high priority (hero) are never changed.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr ) {
		static $count = 0;

		if ( is_admin() || empty( $attr['class'] ) || false === strpos( $attr['class'], 'attachment-woocommerce_thumbnail' ) ) {
			return $attr;
		}
		++$count;
		$listing = is_shop() || is_product_taxonomy() || is_search();

		if ( isset( $attr['fetchpriority'] ) && 'high' === $attr['fetchpriority'] && ( is_front_page() || ( $listing && $count <= 4 ) ) ) {
			return $attr; // Hero cover / first visible cover chosen on purpose.
		}

		$eager = $listing && $count <= 4;

		if ( $eager ) {
			unset( $attr['loading'] );
		} else {
			$attr['loading'] = 'lazy';
			unset( $attr['fetchpriority'] );
		}
		$attr['decoding'] = 'async';
		return $attr;
	},
	20
);
