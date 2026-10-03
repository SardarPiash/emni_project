<?php
/**
 * Default home page hero: store name, tagline, call to action and a small
 * stack of the latest eBook covers. Also used later as the fallback when no
 * Hero Slides exist.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

$ebookstore_name    = get_bloginfo( 'name' );
$ebookstore_tagline = ebookstore_child_env( 'STORE_TAGLINE' );
$ebookstore_shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$ebookstore_covers  = function_exists( 'wc_get_products' )
	? wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => 3,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	)
	: array();
?>
<section class="ebook-hero" aria-labelledby="ebook-hero-title">
	<div class="ebook-hero__inner">
		<div class="ebook-hero__content">
			<p class="ebook-hero__eyebrow"><?php esc_html_e( 'Digital books, delivered instantly', 'ebookstore-child' ); ?></p>
			<h1 id="ebook-hero-title" class="ebook-hero__title"><?php echo esc_html( $ebookstore_name ); ?></h1>
			<?php if ( $ebookstore_tagline ) : ?>
				<p class="ebook-hero__tagline"><?php echo esc_html( $ebookstore_tagline ); ?></p>
			<?php endif; ?>
			<div class="ebook-hero__actions">
				<a class="button ebook-btn ebook-btn--buy ebook-btn--large" href="#ebooks"><?php esc_html_e( 'Browse eBooks', 'ebookstore-child' ); ?></a>
				<a class="button ebook-btn ebook-btn--ghost ebook-btn--large" href="<?php echo esc_url( $ebookstore_shop ); ?>"><?php esc_html_e( 'View all', 'ebookstore-child' ); ?></a>
			</div>
		</div>

		<?php if ( count( $ebookstore_covers ) >= 3 ) : ?>
			<div class="ebook-hero__covers" aria-hidden="true">
				<?php foreach ( $ebookstore_covers as $ebookstore_i => $ebookstore_book ) : ?>
					<span class="ebook-hero__cover ebook-hero__cover--<?php echo esc_attr( (string) ( $ebookstore_i + 1 ) ); ?>">
						<?php
						echo wp_kses_post(
							$ebookstore_book->get_image(
								'woocommerce_thumbnail',
								array(
									'alt'           => '',
									'loading'       => 0 === $ebookstore_i ? 'eager' : 'lazy',
									'fetchpriority' => 0 === $ebookstore_i ? 'high' : 'auto',
								)
							)
						);
						?>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
