<?php
/**
 * Home section: promotional strip.
 *
 * @package EbookStoreChild
 *
 * @var array $args { id, title, text, button }
 */

defined( 'ABSPATH' ) || exit;
?>
<section id="<?php echo esc_attr( $args['id'] ); ?>" class="ebook-promo ebook-reveal" aria-labelledby="<?php echo esc_attr( $args['id'] . '-title' ); ?>">
	<div class="ebook-promo__inner">
		<div class="ebook-promo__icons" aria-hidden="true">
			<?php echo ebookstore_child_icon( 'download', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<?php echo ebookstore_child_icon( 'devices', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</div>
		<div class="ebook-promo__text">
			<h2 id="<?php echo esc_attr( $args['id'] . '-title' ); ?>" class="ebook-promo__title"><?php echo esc_html( $args['title'] ); ?></h2>
			<?php if ( ! empty( $args['text'] ) ) : ?>
				<p><?php echo esc_html( $args['text'] ); ?></p>
			<?php endif; ?>
		</div>
		<a class="button ebook-btn ebook-btn--buy ebook-btn--large" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php echo esc_html( $args['button'] ); ?></a>
	</div>
</section>
