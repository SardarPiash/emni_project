<?php
/**
 * Home page hero: Hero Slides carousel (if any slides are shown) or the
 * default hero. Carousel assets load only on the home page, only when needed.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slides to show (empty if the mu-plugin feature is unavailable).
 *
 * @return array[]
 */
function ebookstore_child_hero_slides() {
	static $slides = null;
	if ( null === $slides ) {
		$slides = function_exists( 'ebookstore_get_hero_slides' ) ? ebookstore_get_hero_slides() : array();
	}
	return $slides;
}

/**
 * Side promo banners to show (empty if the mu-plugin feature is unavailable).
 *
 * @return array[]
 */
function ebookstore_child_promo_banners() {
	static $banners = null;
	if ( null === $banners ) {
		$banners = function_exists( 'ebookstore_get_promo_banners' ) ? ebookstore_get_promo_banners() : array();
	}
	return $banners;
}

/**
 * Print the hero: slideshow with the side banners next to it, or the default
 * hero with the banners in a row below it.
 */
function ebookstore_child_hero() {
	$slides  = ebookstore_child_hero_slides();
	$banners = ebookstore_child_promo_banners();

	if ( ! $slides ) {
		get_template_part( 'template-parts/hero-default' );
		if ( $banners ) {
			echo '<div class="ebook-hero-grid ebook-hero-grid--row">';
			get_template_part( 'template-parts/promo-banners', null, array( 'banners' => $banners ) );
			echo '</div>';
		}
		return;
	}

	echo '<div class="ebook-hero-grid' . ( $banners ? ' has-banners' : '' ) . '">';
	get_template_part(
		'template-parts/hero-carousel',
		null,
		array(
			'slides'   => $slides,
			'autoplay' => function_exists( 'ebookstore_hero_autoplay_seconds' ) ? ebookstore_hero_autoplay_seconds() : 0,
		)
	);
	if ( $banners ) {
		get_template_part( 'template-parts/promo-banners', null, array( 'banners' => $banners ) );
	}
	echo '</div>';
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_front_page() || ! ebookstore_child_hero_slides() ) {
			return;
		}
		wp_enqueue_style(
			'ebookstore-carousel',
			get_stylesheet_directory_uri() . '/assets/css/carousel.css',
			array( 'ebookstore-components' ),
			ebookstore_child_asset_version( 'assets/css/carousel.css' )
		);
		if ( count( ebookstore_child_hero_slides() ) > 1 ) {
			wp_enqueue_script(
				'ebookstore-carousel',
				get_stylesheet_directory_uri() . '/assets/js/hero-carousel.js',
				array(),
				ebookstore_child_asset_version( 'assets/js/hero-carousel.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	},
	22
);

// Preload the first slide image so the banner appears fast (largest paint).
add_action(
	'wp_head',
	static function () {
		if ( ! is_front_page() ) {
			return;
		}
		$slides = ebookstore_child_hero_slides();
		if ( ! $slides ) {
			return;
		}
		$first   = $slides[0];
		$desktop = wp_get_attachment_image_url( $first['desktop_id'], 'full' );
		$mobile  = $first['mobile_id'] ? wp_get_attachment_image_url( $first['mobile_id'], 'full' ) : '';
		if ( $mobile ) {
			printf( '<link rel="preload" as="image" href="%s" media="(max-width: 767px)" fetchpriority="high">' . "\n", esc_url( $mobile ) );
			printf( '<link rel="preload" as="image" href="%s" media="(min-width: 768px)" fetchpriority="high">' . "\n", esc_url( (string) $desktop ) );
		} elseif ( $desktop ) {
			printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $desktop ) );
		}
	},
	3
);
