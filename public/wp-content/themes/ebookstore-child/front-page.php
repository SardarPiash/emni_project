<?php
/**
 * Home page: hero, trust row and the eBook grid.
 *
 * The grid uses the [products] shortcode, so cards share the same markup and
 * hooks as the shop page (cover, badge, title, author, price, short
 * description, "View Details" and "Buy Now").
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ebookstore_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<div id="primary" class="content-area ebook-home">
	<main id="main" class="site-main">

		<?php get_template_part( 'template-parts/hero-default' ); ?>

		<div class="ebook-home__trust ebook-reveal">
			<?php ebookstore_child_trust_row( 'band' ); ?>
		</div>

		<section id="ebooks" class="ebook-section ebook-reveal" aria-labelledby="ebook-grid-title" tabindex="-1">
			<div class="ebook-section__header">
				<h2 id="ebook-grid-title" class="ebook-section__title"><?php esc_html_e( 'Our eBooks', 'ebookstore-child' ); ?></h2>
				<a class="ebook-section__link" href="<?php echo esc_url( $ebookstore_shop ); ?>">
					<?php esc_html_e( 'View all eBooks', 'ebookstore-child' ); ?>
					<?php echo ebookstore_child_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</a>
			</div>
			<?php echo do_shortcode( '[products limit="12" columns="4" orderby="date" order="DESC"]' ); ?>
		</section>

	</main>
</div>

<?php
get_footer();
