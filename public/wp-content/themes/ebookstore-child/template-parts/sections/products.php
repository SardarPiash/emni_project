<?php
/**
 * Home section: a row of eBook cards (Bestsellers, Editor's Picks, New Arrivals).
 * Grid on desktop, swipeable row on phones. Uses WooCommerce's [products]
 * shortcode, so cards are identical to the shop and cached by WooCommerce.
 *
 * @package EbookStoreChild
 *
 * @var array $args { id, title, link, link_text, shortcode }
 */

defined( 'ABSPATH' ) || exit;

$ebookstore_output = do_shortcode( $args['shortcode'] );
if ( false === strpos( $ebookstore_output, 'type-product' ) ) {
	return; // Nothing to show (e.g. no featured eBooks yet).
}
$ebookstore_heading_id = $args['id'] . '-title';
?>
<section id="<?php echo esc_attr( $args['id'] ); ?>" class="ebook-section ebook-section--row ebook-reveal" aria-labelledby="<?php echo esc_attr( $ebookstore_heading_id ); ?>" tabindex="-1">
	<div class="ebook-section__header">
		<h2 id="<?php echo esc_attr( $ebookstore_heading_id ); ?>" class="ebook-section__title"><?php echo esc_html( $args['title'] ); ?></h2>
		<a class="ebook-section__link" href="<?php echo esc_url( $args['link'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: section title */ __( 'View all: %s', 'ebookstore-child' ), $args['title'] ) ); ?>">
			<?php echo esc_html( $args['link_text'] ); ?>
			<?php echo ebookstore_child_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</a>
	</div>
	<?php echo $ebookstore_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce shortcode output. ?>
</section>
