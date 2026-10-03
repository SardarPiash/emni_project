<?php
/**
 * Home page hero carousel (Hero Slides from the mu-plugin).
 * One slide = static banner; two or more = carousel with arrows, dots,
 * swipe, keyboard and a pause button (assets/js/hero-carousel.js).
 *
 * @package EbookStoreChild
 *
 * @var array $args { slides: array[], autoplay: int }
 */

defined( 'ABSPATH' ) || exit;

$ebookstore_slides   = isset( $args['slides'] ) ? (array) $args['slides'] : array();
$ebookstore_autoplay = isset( $args['autoplay'] ) ? (int) $args['autoplay'] : 0;
$ebookstore_count    = count( $ebookstore_slides );
if ( ! $ebookstore_count ) {
	return;
}
$ebookstore_multi = $ebookstore_count > 1;
?>
<section class="ebook-carousel<?php echo $ebookstore_multi ? ' ebook-carousel--multi' : ''; ?>"
	aria-roledescription="carousel"
	aria-label="<?php esc_attr_e( 'Featured', 'ebookstore-child' ); ?>"
	data-autoplay="<?php echo esc_attr( (string) $ebookstore_autoplay ); ?>">

	<h1 class="screen-reader-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>

	<div class="ebook-carousel__viewport" id="ebook-carousel-slides">
		<?php foreach ( $ebookstore_slides as $ebookstore_i => $ebookstore_slide ) : ?>
			<?php
			$ebookstore_first = 0 === $ebookstore_i;
			$ebookstore_label = sprintf( /* translators: 1: slide number, 2: total */ __( '%1$d of %2$d', 'ebookstore-child' ), $ebookstore_i + 1, $ebookstore_count );
			?>
			<div class="ebook-carousel__slide<?php echo $ebookstore_first ? ' is-active' : ''; ?>"
				id="ebook-slide-<?php echo esc_attr( (string) ( $ebookstore_i + 1 ) ); ?>"
				role="group"
				aria-roledescription="slide"
				aria-label="<?php echo esc_attr( $ebookstore_label ); ?>"
				<?php echo $ebookstore_first ? '' : 'aria-hidden="true" inert'; ?>>

				<picture class="ebook-carousel__media">
					<?php if ( $ebookstore_slide['mobile_id'] ) : ?>
						<source media="(max-width: 767px)" srcset="<?php echo esc_url( (string) wp_get_attachment_image_url( $ebookstore_slide['mobile_id'], 'full' ) ); ?>">
					<?php endif; ?>
					<?php
					echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
						$ebookstore_slide['desktop_id'],
						'full',
						false,
						array(
							'class'         => 'ebook-carousel__image',
							'alt'           => '',
							'sizes'         => '100vw',
							'loading'       => $ebookstore_first ? 'eager' : 'lazy',
							'fetchpriority' => $ebookstore_first ? 'high' : 'low',
							'decoding'      => $ebookstore_first ? 'sync' : 'async',
						)
					);
					?>
				</picture>
				<div class="ebook-carousel__shade" aria-hidden="true"></div>

				<div class="ebook-carousel__content">
					<?php if ( $ebookstore_slide['heading'] ) : ?>
						<h2 class="ebook-carousel__title"><?php echo esc_html( $ebookstore_slide['heading'] ); ?></h2>
					<?php endif; ?>
					<?php if ( $ebookstore_slide['subheading'] ) : ?>
						<p class="ebook-carousel__text"><?php echo esc_html( $ebookstore_slide['subheading'] ); ?></p>
					<?php endif; ?>
					<?php if ( $ebookstore_slide['button_text'] && $ebookstore_slide['button_link'] ) : ?>
						<a class="button ebook-btn ebook-btn--buy ebook-btn--large" href="<?php echo esc_url( $ebookstore_slide['button_link'] ); ?>"><?php echo esc_html( $ebookstore_slide['button_text'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $ebookstore_multi ) : ?>
		<div class="ebook-carousel__controls">
			<button type="button" class="ebook-carousel__arrow ebook-carousel__arrow--prev" aria-controls="ebook-carousel-slides" aria-label="<?php esc_attr_e( 'Previous slide', 'ebookstore-child' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<div class="ebook-carousel__dots">
				<?php for ( $ebookstore_d = 1; $ebookstore_d <= $ebookstore_count; $ebookstore_d++ ) : ?>
					<button type="button" class="ebook-carousel__dot"
						aria-controls="<?php echo esc_attr( 'ebook-slide-' . $ebookstore_d ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Go to slide %d', 'ebookstore-child' ), $ebookstore_d ) ); ?>"
						<?php echo 1 === $ebookstore_d ? 'aria-current="true"' : ''; ?>
						data-slide="<?php echo esc_attr( (string) ( $ebookstore_d - 1 ) ); ?>"></button>
				<?php endfor; ?>
			</div>
			<button type="button" class="ebook-carousel__arrow ebook-carousel__arrow--next" aria-controls="ebook-carousel-slides" aria-label="<?php esc_attr_e( 'Next slide', 'ebookstore-child' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<button type="button" class="ebook-carousel__pause" hidden
				data-label-pause="<?php esc_attr_e( 'Pause slideshow', 'ebookstore-child' ); ?>"
				data-label-play="<?php esc_attr_e( 'Play slideshow', 'ebookstore-child' ); ?>"
				aria-label="<?php esc_attr_e( 'Pause slideshow', 'ebookstore-child' ); ?>">
				<svg class="ebook-carousel__icon-pause" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 5h3v14H7zM14 5h3v14h-3z" fill="currentColor"/></svg>
				<svg class="ebook-carousel__icon-play" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
			</button>
		</div>
		<p class="screen-reader-text ebook-carousel__status" aria-live="polite" aria-atomic="true"></p>
	<?php endif; ?>
</section>
