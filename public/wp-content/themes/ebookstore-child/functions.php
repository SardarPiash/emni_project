<?php
/**
 * eBook Store Child — theme functions.
 *
 * Only hooks/filters are used; Storefront and WooCommerce files are never edited.
 * Store-wide values (name, tagline, emails, links) come from .env via env().
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Read a value from .env (empty string if not set).
 *
 * @param string $key     .env key.
 * @param string $default Fallback.
 * @return string
 */
function ebookstore_child_env( $key, $default = '' ) {
	$value = function_exists( 'env' ) ? env( $key, $default ) : $default;
	return is_scalar( $value ) ? (string) $value : $default;
}

/**
 * Resolve a link from .env: a site path ("/terms") becomes a full URL only if a
 * published page exists at that path; full URLs are returned as-is.
 *
 * @param string $value Path or URL.
 * @return string URL, or '' if the link should be hidden.
 */
function ebookstore_child_link( $value ) {
	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}
	if ( '/' === $value[0] ) {
		$page = get_page_by_path( trim( $value, '/' ) );
		return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
	}
	return wp_http_validate_url( $value ) ? $value : '';
}

/**
 * File version for cache busting.
 *
 * @param string $relative Path inside the child theme.
 * @return string
 */
function ebookstore_child_asset_version( $relative ) {
	$file = get_stylesheet_directory() . '/' . $relative;
	return file_exists( $file ) ? (string) filemtime( $file ) : '0.1.0';
}

/* -------------------------------------------------------------------------
 * Components (Phase 9 redesign)
 * ---------------------------------------------------------------------- */

require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/trust.php';
require_once __DIR__ . '/inc/badges.php';
require_once __DIR__ . '/inc/hero.php';

/* -------------------------------------------------------------------------
 * Assets: self-hosted fonts, main stylesheet, no Google Fonts / Customizer CSS
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_dequeue_style( 'storefront-fonts' );

		wp_enqueue_style(
			'ebookstore-main',
			get_stylesheet_directory_uri() . '/assets/css/main.css',
			array( 'storefront-style', 'storefront-woocommerce-style' ),
			ebookstore_child_asset_version( 'assets/css/main.css' )
		);
	},
	20
);

add_action(
	'wp_head',
	static function () {
		foreach ( array( 'inter-latin-var.woff2', 'merriweather-latin-var.woff2' ) as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_stylesheet_directory_uri() . '/assets/fonts/' . $font )
			);
		}
	},
	2
);

// Colors come from our CSS variables, not the Storefront Customizer.
add_filter( 'storefront_customizer_css', '__return_empty_string' );
add_filter( 'storefront_customizer_woocommerce_css', '__return_empty_string' );
add_filter( 'storefront_gutenberg_block_editor_customizer_css', '__return_empty_string' );
add_filter( 'storefront_google_font_families', '__return_empty_array' );

/* -------------------------------------------------------------------------
 * Storefront layout adjustments (parent hooks are registered after this file
 * loads, so changes run on "init").
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		// Header: Storefront's search widget is replaced by our own (inc/header.php).
		remove_action( 'storefront_header', 'storefront_product_search', 40 );

		// Footer: replace widgets + Storefront credit with our footer.
		remove_action( 'storefront_footer', 'storefront_footer_widgets', 10 );
		remove_action( 'storefront_footer', 'storefront_credit', 20 );
		add_action( 'storefront_footer', 'ebookstore_child_footer', 20 );

		// Single product: no sticky "Add to cart" bar, no prev/next arrows.
		remove_action( 'storefront_after_footer', 'storefront_sticky_single_add_to_cart', 999 );
		remove_action( 'woocommerce_after_single_product_summary', 'storefront_single_product_pagination', 30 );

		// Shop toolbar only above the grid (Storefront repeats sorting + result count below it).
		remove_action( 'woocommerce_after_shop_loop', 'storefront_sorting_wrapper', 9 );
		remove_action( 'woocommerce_after_shop_loop', 'woocommerce_catalog_ordering', 10 );
		remove_action( 'woocommerce_after_shop_loop', 'woocommerce_result_count', 20 );
		remove_action( 'woocommerce_after_shop_loop', 'storefront_sorting_wrapper_close', 31 );
		add_action( 'woocommerce_before_shop_loop', 'ebookstore_child_sort_label', 9 );

		// Product cards: author + short description + View Details / Buy Now.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		add_action( 'woocommerce_after_shop_loop_item_title', 'ebookstore_child_loop_author', 4 );
		add_action( 'woocommerce_after_shop_loop_item_title', 'ebookstore_child_loop_excerpt', 15 );
		add_action( 'woocommerce_after_shop_loop_item', 'ebookstore_child_loop_buttons', 10 );

		// Product page: author, Buy Now button, delivery notes.
		add_action( 'woocommerce_single_product_summary', 'ebookstore_child_single_author', 6 );
		add_action( 'woocommerce_before_add_to_cart_button', 'ebookstore_child_single_buy_now', 5 );
		add_action( 'woocommerce_single_product_summary', 'ebookstore_child_single_notes', 35 );
	}
);

// No breadcrumb on the home page.
add_action(
	'wp',
	static function () {
		if ( is_front_page() ) {
			remove_action( 'storefront_before_content', 'woocommerce_breadcrumb', 10 );
		}
	}
);

// Mobile bottom bar: account + cart only (no search).
add_filter(
	'storefront_handheld_footer_bar_links',
	static function ( $links ) {
		unset( $links['search'] );
		return $links;
	}
);

// Grids: 12 eBooks per page, 4 related eBooks.
add_filter( 'loop_shop_per_page', static fn() => 12 );
add_filter( 'storefront_loop_columns', static fn() => 4 );
add_filter(
	'woocommerce_output_related_products_args',
	static function ( $args ) {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	},
	20
);

add_filter( 'woocommerce_product_single_add_to_cart_text', static fn() => __( 'Add to Cart', 'ebookstore-child' ) );

/* -------------------------------------------------------------------------
 * Product card (shop, home, categories, related)
 * ---------------------------------------------------------------------- */

/**
 * Author line under the title.
 */
function ebookstore_child_loop_author() {
	global $product;
	$author = $product ? $product->get_meta( '_ebook_author' ) : '';
	if ( $author ) {
		echo '<p class="ebook-author">' . esc_html( sprintf( /* translators: %s: author name */ __( 'by %s', 'ebookstore-child' ), $author ) ) . '</p>';
	}
}

/**
 * Short description on the card.
 */
function ebookstore_child_loop_excerpt() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$text = wp_strip_all_tags( $product->get_short_description() );
	if ( $text ) {
		echo '<p class="ebook-excerpt">' . esc_html( wp_trim_words( $text, 18 ) ) . '</p>';
	}
}

/**
 * "View Details" and "Buy Now" buttons.
 */
function ebookstore_child_loop_buttons() {
	global $product;
	if ( ! $product ) {
		return;
	}
	echo '<div class="ebook-actions">';
	printf(
		'<a class="button ebook-btn ebook-btn--secondary" href="%1$s" aria-label="%2$s">%3$s</a>',
		esc_url( $product->get_permalink() ),
		/* translators: %s: eBook title */
		esc_attr( sprintf( __( 'View details of %s', 'ebookstore-child' ), $product->get_name() ) ),
		esc_html__( 'View Details', 'ebookstore-child' )
	);
	if ( function_exists( 'ebookstore_buy_now_url' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		printf(
			'<a class="button ebook-btn ebook-btn--buy" href="%1$s" rel="nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( ebookstore_buy_now_url( $product ) ),
			/* translators: %s: eBook title */
			esc_attr( sprintf( __( 'Buy %s now', 'ebookstore-child' ), $product->get_name() ) ),
			esc_html__( 'Buy Now', 'ebookstore-child' )
		);
	}
	echo '</div>';
}

/* -------------------------------------------------------------------------
 * Single product page
 * ---------------------------------------------------------------------- */

/**
 * Author line under the product title.
 */
function ebookstore_child_single_author() {
	global $product;
	$author = $product ? $product->get_meta( '_ebook_author' ) : '';
	if ( $author ) {
		echo '<p class="ebook-author ebook-author--single">' . esc_html( sprintf( /* translators: %s: author name */ __( 'by %s', 'ebookstore-child' ), $author ) ) . '</p>';
	}
}

/**
 * "Buy Now" button inside the add-to-cart form (before "Add to Cart").
 */
function ebookstore_child_single_buy_now() {
	global $product;
	if ( ! $product || ! $product->is_type( 'simple' ) || ! function_exists( 'ebookstore_buy_now_url' ) ) {
		return;
	}
	printf(
		'<a class="button ebook-btn ebook-btn--buy ebook-btn--large" href="%1$s" rel="nofollow">%2$s</a>',
		esc_url( ebookstore_buy_now_url( $product ) ),
		esc_html__( 'Buy Now', 'ebookstore-child' )
	);
}

/**
 * Delivery notes under the buttons.
 */
function ebookstore_child_single_notes() {
	global $product;
	if ( ! $product || ! $product->is_downloadable() ) {
		return;
	}
	$notes = array(
		__( 'Instant PDF download after payment', 'ebookstore-child' ),
		__( 'Format: PDF — read on any phone, tablet or computer', 'ebookstore-child' ),
		__( 'Download link also sent to your email', 'ebookstore-child' ),
		__( 'Secure checkout', 'ebookstore-child' ),
	);
	echo '<ul class="ebook-notes" aria-label="' . esc_attr__( 'Delivery details', 'ebookstore-child' ) . '">';
	foreach ( $notes as $note ) {
		echo '<li>' . ebookstore_child_icon_check() . '<span>' . esc_html( $note ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}
	echo '</ul>';
}

/**
 * Small decorative check icon.
 *
 * @return string SVG markup.
 */
function ebookstore_child_icon_check() {
	return '<svg class="ebook-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>';
}

/* -------------------------------------------------------------------------
 * Footer
 * ---------------------------------------------------------------------- */

/**
 * Site footer: brand, shop links, information links, social links, copyright.
 */
function ebookstore_child_footer() {
	$name    = get_bloginfo( 'name' );
	$tagline = ebookstore_child_env( 'STORE_TAGLINE' );
	$email   = sanitize_email( ebookstore_child_env( 'STORE_SUPPORT_EMAIL' ) );

	$shop_links = array_filter(
		array(
			__( 'All eBooks', 'ebookstore-child' ) => wc_get_page_permalink( 'shop' ),
			__( 'My Account', 'ebookstore-child' ) => wc_get_page_permalink( 'myaccount' ),
			__( 'Cart', 'ebookstore-child' )       => wc_get_cart_url(),
		)
	);
	$info_links = array_filter(
		array(
			__( 'Privacy Policy', 'ebookstore-child' )     => ebookstore_child_link( ebookstore_child_env( 'LINK_PRIVACY_POLICY' ) ),
			__( 'Terms & Conditions', 'ebookstore-child' ) => ebookstore_child_link( ebookstore_child_env( 'LINK_TERMS' ) ),
		)
	);
	$social_links = array_filter(
		array(
			'Facebook'  => ebookstore_child_link( ebookstore_child_env( 'LINK_FACEBOOK' ) ),
			'Instagram' => ebookstore_child_link( ebookstore_child_env( 'LINK_INSTAGRAM' ) ),
			'X'         => ebookstore_child_link( ebookstore_child_env( 'LINK_X' ) ),
		)
	);
	?>
	<div class="ebook-footer">
		<div class="ebook-footer__col ebook-footer__brand">
			<p class="ebook-footer__name"><?php echo esc_html( $name ); ?></p>
			<?php if ( $tagline ) : ?>
				<p class="ebook-footer__tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<p class="ebook-footer__support">
					<?php esc_html_e( 'Questions? Email us at', 'ebookstore-child' ); ?>
					<a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<?php ebookstore_child_footer_links( __( 'Shop', 'ebookstore-child' ), $shop_links ); ?>
		<?php ebookstore_child_footer_links( __( 'Information', 'ebookstore-child' ), $info_links ); ?>
		<?php ebookstore_child_footer_links( __( 'Follow Us', 'ebookstore-child' ), $social_links, true ); ?>
	</div>
	<div class="ebook-footer__bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . $name ); ?>. <?php esc_html_e( 'All rights reserved.', 'ebookstore-child' ); ?></p>
		<p class="ebook-footer__trust"><?php esc_html_e( 'Instant PDF delivery · Secure checkout · Prices in USD or GBP', 'ebookstore-child' ); ?></p>
	</div>
	<?php
}

/**
 * One footer column of links (hidden when empty).
 *
 * @param string $title    Column heading.
 * @param array  $links    Label => URL.
 * @param bool   $external Open in a new tab.
 */
function ebookstore_child_footer_links( $title, array $links, $external = false ) {
	if ( ! $links ) {
		return;
	}
	?>
	<nav class="ebook-footer__col" aria-label="<?php echo esc_attr( $title ); ?>">
		<p class="ebook-footer__heading"><?php echo esc_html( $title ); ?></p>
		<ul>
			<?php foreach ( $links as $label => $url ) : ?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>"<?php echo $external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/* -------------------------------------------------------------------------
 * Currency switchers — uses CURCY's API, rendered with our markup.
 * Main switcher in the navigation bar, plus compact ones on the product
 * page, cart and checkout.
 * ---------------------------------------------------------------------- */

/**
 * Accessible USD / GBP switcher. Hidden if CURCY is not active.
 *
 * @param array $args {
 *     @type string $label   Visible label.
 *     @type string $variant "nav" (dark navigation bar) or "inline" (light page area).
 * }
 */
function ebookstore_child_currency_switcher( $args = array() ) {
	if ( ! class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
		return;
	}
	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'label'   => __( 'Currency', 'ebookstore-child' ),
			'variant' => 'nav',
		)
	);

	$data    = WOOMULTI_CURRENCY_F_Data::get_ins();
	$current = $data->get_current_currency();
	$links   = $data->get_links();
	if ( count( $links ) < 2 ) {
		return;
	}
	$tag = 'nav' === $args['variant'] ? 'nav' : 'div';
	?>
	<<?php echo esc_html( $tag ); ?> class="ebook-currency ebook-currency--<?php echo esc_attr( $args['variant'] ); ?>"<?php echo 'div' === $tag ? ' role="group"' : ''; ?> aria-label="<?php echo esc_attr( $args['label'] ); ?>">
		<span class="ebook-currency__label" aria-hidden="true"><?php echo esc_html( $args['label'] ); ?></span>
		<ul class="ebook-currency__list">
			<?php foreach ( $links as $code => $url ) : ?>
				<?php $active = ( $code === $current ); ?>
				<li>
					<a class="ebook-currency__option<?php echo $active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						rel="nofollow"
						<?php echo $active ? 'aria-current="true"' : ''; ?>
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: currency code */ __( 'Show prices in %s', 'ebookstore-child' ), $code ) ); ?>">
						<?php echo esc_html( $code . ' ' . html_entity_decode( get_woocommerce_currency_symbol( $code ), ENT_QUOTES, 'UTF-8' ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</<?php echo esc_html( $tag ); ?>>
	<?php
}

// Main switcher: navigation bar, between the menu (50) and the cart (60).
add_action(
	'storefront_header',
	static function () {
		ebookstore_child_currency_switcher();
	},
	55
);

// Product page: right under the price.
add_action(
	'woocommerce_single_product_summary',
	static function () {
		ebookstore_child_currency_switcher(
			array(
				'label'   => __( 'Show price in', 'ebookstore-child' ),
				'variant' => 'inline',
			)
		);
	},
	11
);

// Cart: top of the cart totals box.
add_action(
	'woocommerce_before_cart_totals',
	static function () {
		ebookstore_child_currency_switcher(
			array(
				'label'   => __( 'Pay in', 'ebookstore-child' ),
				'variant' => 'inline',
			)
		);
	}
);

// Checkout: inside the order box, above the summary table (which reloads via AJAX at priority 10).
add_action(
	'woocommerce_checkout_order_review',
	static function () {
		ebookstore_child_currency_switcher(
			array(
				'label'   => __( 'Pay in', 'ebookstore-child' ),
				'variant' => 'inline',
			)
		);
	},
	5
);

/* -------------------------------------------------------------------------
 * Cart: eBooks are sold individually — show the fixed quantity "1"
 * (WooCommerce outputs only a hidden field in that case).
 * ---------------------------------------------------------------------- */

add_filter(
	'woocommerce_cart_item_quantity',
	static function ( $quantity_html, $cart_item_key, $cart_item ) {
		if ( ! empty( $cart_item['data'] ) && $cart_item['data']->is_sold_individually() ) {
			$quantity_html .= '<span class="ebook-qty">' . esc_html( (string) $cart_item['quantity'] ) . '</span>';
		}
		return $quantity_html;
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Checkout wording
 * ---------------------------------------------------------------------- */

add_filter(
	'gettext_woocommerce',
	static function ( $translation, $text ) {
		if ( 'Billing details' === $text ) {
			return __( 'Your details', 'ebookstore-child' );
		}
		return $translation;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Performance: CURCY's switcher styles and flag sprites are not used
 * (the theme renders its own switcher). Its script stays — it handles
 * page-cache compatibility.
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_dequeue_style( 'woo-multi-currency' );
		wp_dequeue_style( 'wmc-flags' );
	},
	100
);

/**
 * Visible "Sort by" text next to the sorting dropdown (the dropdown keeps
 * WooCommerce's own aria-label for screen readers).
 */
function ebookstore_child_sort_label() {
	if ( ! woocommerce_products_will_display() ) {
		return;
	}
	echo '<span class="ebook-sort-label" aria-hidden="true">' . esc_html__( 'Sort by', 'ebookstore-child' ) . '</span>';
}
