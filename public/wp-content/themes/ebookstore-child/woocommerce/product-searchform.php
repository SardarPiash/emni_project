<?php
/**
 * Product search form — child theme override (eBook Store).
 *
 * Based on WooCommerce templates/product-searchform.php
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ebookstore_field_id = 'woocommerce-product-search-field-' . ( isset( $index ) ? absint( $index ) : 0 );
?>
<form role="search" method="get" class="woocommerce-product-search ebook-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $ebookstore_field_id ); ?>"><?php esc_html_e( 'Search eBooks', 'ebookstore-child' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $ebookstore_field_id ); ?>" class="search-field ebook-search__input" placeholder="<?php esc_attr_e( 'Search eBooks…', 'ebookstore-child' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
	<button type="submit" class="ebook-search__button" value="<?php esc_attr_e( 'Search', 'ebookstore-child' ); ?>">
		<?php echo function_exists( 'ebookstore_child_icon' ) ? ebookstore_child_icon( 'search', 20 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'ebookstore-child' ); ?></span>
	</button>
	<input type="hidden" name="post_type" value="product" />
</form>
