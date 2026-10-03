<?php
/**
 * Header: product search and cart link with item count.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search box in the top header row (next to the store name).
 * Uses WooCommerce's product search form (child-theme template override:
 * woocommerce/product-searchform.php).
 */
function ebookstore_child_header_search() {
	if ( function_exists( 'get_product_search_form' ) ) {
		echo '<div class="ebook-search">';
		get_product_search_form();
		echo '</div>';
	}
}
add_action( 'storefront_header', 'ebookstore_child_header_search', 32 );

/**
 * Cart link: bag icon + item count badge + subtotal.
 *
 * Replaces Storefront's pluggable storefront_cart_link(). Storefront's AJAX
 * cart fragment calls this same function, so the count updates live.
 */
function storefront_cart_link() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$count = WC()->cart->get_cart_contents_count();
	?>
	<a class="cart-contents ebook-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<span class="ebook-cart__icon">
			<?php echo ebookstore_child_icon( 'bag', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="ebook-cart__count count" aria-hidden="true"><?php echo esc_html( (string) $count ); ?></span>
		</span>
		<span class="ebook-cart__total"><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></span>
		<span class="screen-reader-text">
			<?php
			/* translators: %d: number of items in the cart */
			echo esc_html( sprintf( _n( 'Cart: %d item', 'Cart: %d items', $count, 'ebookstore-child' ), $count ) );
			?>
		</span>
	</a>
	<?php
}
