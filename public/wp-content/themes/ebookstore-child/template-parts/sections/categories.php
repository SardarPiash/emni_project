<?php
/**
 * Home section: "Browse by Category" cards (name, number of eBooks and a
 * picture: the category image if set, otherwise the newest cover).
 *
 * Categories: the ones chosen in WP admin → Home Page → Sections (in that
 * order); none chosen = the biggest categories, up to "count".
 *
 * @package EbookStoreChild
 *
 * @var array $args { id, title, count, terms }
 */

defined( 'ABSPATH' ) || exit;

$ebookstore_chosen = array_filter( array_map( 'absint', (array) ( $args['terms'] ?? array() ) ) );
$ebookstore_terms  = get_terms(
	$ebookstore_chosen
		? array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'include'    => $ebookstore_chosen,
			'orderby'    => 'include',
		)
		: array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => (int) $args['count'],
		)
);
if ( is_wp_error( $ebookstore_terms ) || ! $ebookstore_terms ) {
	return;
}
$ebookstore_shop = wc_get_page_permalink( 'shop' );
?>
<section id="<?php echo esc_attr( $args['id'] ); ?>" class="ebook-section ebook-section--categories ebook-reveal" aria-labelledby="<?php echo esc_attr( $args['id'] . '-title' ); ?>" tabindex="-1">
	<div class="ebook-section__header">
		<h2 id="<?php echo esc_attr( $args['id'] . '-title' ); ?>" class="ebook-section__title"><?php echo esc_html( $args['title'] ); ?></h2>
		<a class="ebook-section__link" href="<?php echo esc_url( $ebookstore_shop ); ?>">
			<?php esc_html_e( 'All eBooks', 'ebookstore-child' ); ?>
			<?php echo ebookstore_child_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</a>
	</div>
	<ul class="ebook-cats">
		<?php foreach ( $ebookstore_terms as $ebookstore_term ) : ?>
			<?php
			$ebookstore_image = (int) get_term_meta( $ebookstore_term->term_id, 'thumbnail_id', true );
			if ( ! $ebookstore_image || ! wp_attachment_is_image( $ebookstore_image ) ) {
				$ebookstore_latest = wc_get_products(
					array(
						'status'   => 'publish',
						'limit'    => 1,
						'orderby'  => 'date',
						'order'    => 'DESC',
						'category' => array( $ebookstore_term->slug ),
					)
				);
				$ebookstore_image  = $ebookstore_latest ? (int) $ebookstore_latest[0]->get_image_id() : 0;
			}
			?>
			<li class="ebook-cats__item">
				<a class="ebook-cats__link" href="<?php echo esc_url( get_term_link( $ebookstore_term ) ); ?>">
					<span class="ebook-cats__cover" aria-hidden="true">
						<?php
						if ( $ebookstore_image ) {
							echo wp_get_attachment_image( $ebookstore_image, 'woocommerce_thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
						}
						?>
					</span>
					<span class="ebook-cats__text">
						<span class="ebook-cats__name"><?php echo esc_html( $ebookstore_term->name ); ?></span>
						<span class="ebook-cats__count">
							<?php
							/* translators: %d: number of eBooks */
							echo esc_html( sprintf( _n( '%d eBook', '%d eBooks', $ebookstore_term->count, 'ebookstore-child' ), $ebookstore_term->count ) );
							?>
						</span>
					</span>
					<?php echo ebookstore_child_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
