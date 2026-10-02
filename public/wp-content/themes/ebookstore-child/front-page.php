<?php
/**
 * Home page: hero + responsive grid of eBooks.
 *
 * Uses the [products] shortcode, so cards share the same markup and hooks
 * as the shop page (cover, title, author, price, short description,
 * "View Details" and "Buy Now").
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ebookstore_name    = get_bloginfo( 'name' );
$ebookstore_tagline = ebookstore_child_env( 'STORE_TAGLINE' );
$ebookstore_shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<div id="primary" class="content-area ebook-home">
	<main id="main" class="site-main">

		<section class="ebook-hero" aria-labelledby="ebook-hero-title">
			<p class="ebook-hero__eyebrow"><?php esc_html_e( 'Digital books, delivered instantly', 'ebookstore-child' ); ?></p>
			<h1 id="ebook-hero-title" class="ebook-hero__title"><?php echo esc_html( $ebookstore_name ); ?></h1>
			<?php if ( $ebookstore_tagline ) : ?>
				<p class="ebook-hero__tagline"><?php echo esc_html( $ebookstore_tagline ); ?></p>
			<?php endif; ?>
			<div class="ebook-hero__actions">
				<a class="button ebook-btn ebook-btn--buy ebook-btn--large" href="#ebooks"><?php esc_html_e( 'Browse eBooks', 'ebookstore-child' ); ?></a>
			</div>
			<ul class="ebook-hero__features">
				<li><?php echo ebookstore_child_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Instant PDF download', 'ebookstore-child' ); ?></li>
				<li><?php echo ebookstore_child_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Read on any device', 'ebookstore-child' ); ?></li>
				<li><?php echo ebookstore_child_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Secure checkout', 'ebookstore-child' ); ?></li>
			</ul>
		</section>

		<section id="ebooks" class="ebook-section" aria-labelledby="ebook-grid-title" tabindex="-1">
			<div class="ebook-section__header">
				<h2 id="ebook-grid-title" class="ebook-section__title"><?php esc_html_e( 'Our eBooks', 'ebookstore-child' ); ?></h2>
				<a class="ebook-section__link" href="<?php echo esc_url( $ebookstore_shop ); ?>"><?php esc_html_e( 'View all eBooks', 'ebookstore-child' ); ?></a>
			</div>
			<?php echo do_shortcode( '[products limit="12" columns="4" orderby="date" order="DESC"]' ); ?>
		</section>

	</main>
</div>

<?php
get_footer();
