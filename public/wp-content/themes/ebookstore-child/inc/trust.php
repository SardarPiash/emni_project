<?php
/**
 * Trust elements: a clean icon row ("Instant PDF download", "Secure checkout"...).
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trust items: icon => text.
 *
 * @return array
 */
function ebookstore_child_trust_items() {
	return array(
		'download' => __( 'Instant PDF download', 'ebookstore-child' ),
		'lock'     => __( 'Secure checkout', 'ebookstore-child' ),
		'mail'     => __( 'Delivered to your email', 'ebookstore-child' ),
		'devices'  => __( 'Read on any device', 'ebookstore-child' ),
	);
}

/**
 * Output the trust row.
 *
 * @param string $variant "band" (home page, full width), "compact" (product page, cart, checkout).
 */
function ebookstore_child_trust_row( $variant = 'compact' ) {
	?>
	<ul class="ebook-trust ebook-trust--<?php echo esc_attr( $variant ); ?>" aria-label="<?php esc_attr_e( 'Why buy from us', 'ebookstore-child' ); ?>">
		<?php foreach ( ebookstore_child_trust_items() as $icon => $text ) : ?>
			<li class="ebook-trust__item">
				<span class="ebook-trust__icon"><?php echo ebookstore_child_icon( $icon, 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="ebook-trust__text"><?php echo esc_html( $text ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

add_action(
	'init',
	static function () {
		// Product page: replaces the earlier text list of delivery notes.
		remove_action( 'woocommerce_single_product_summary', 'ebookstore_child_single_notes', 35 );
		add_action(
			'woocommerce_single_product_summary',
			static function () {
				global $product;
				if ( $product && $product->is_downloadable() ) {
					ebookstore_child_trust_row( 'compact' );
				}
			},
			35
		);

		// Cart: under "Proceed to checkout".
		add_action( 'woocommerce_proceed_to_checkout', static fn() => ebookstore_child_trust_row( 'compact' ), 30 );

		// Checkout: under "Place order" (re-rendered with the payment box, server-side).
		add_action( 'woocommerce_review_order_after_submit', static fn() => ebookstore_child_trust_row( 'compact' ) );
	},
	20 // After functions.php registered the notes action at default priority.
);
