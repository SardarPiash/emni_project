<?php
/**
 * Home page: hero (carousel or default), trust row and the home sections
 * (Bestsellers, Editor's Picks, Browse by Category, New Arrivals, promo strip —
 * managed in WP admin → Home Page → Sections).
 *
 * If every section is switched off, a plain grid of the newest eBooks is shown
 * so the page never looks empty.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ebookstore_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<div id="primary" class="content-area ebook-home">
	<main id="main" class="site-main">

		<?php ebookstore_child_hero(); ?>

		<div class="ebook-home__trust ebook-reveal">
			<?php ebookstore_child_trust_row( 'band' ); ?>
		</div>

		<?php if ( ! ebookstore_child_home_sections() ) : ?>
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
		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
