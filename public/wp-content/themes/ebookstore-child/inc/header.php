<?php
/**
 * Header: product search and cart link with item count.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search box in the top header row (next to the store name).
 * Uses WooCommerce's product search form (child-theme template override:
 * woocommerce/product-searchform.php).
 */
function ebookstore_child_header_search() {
	if ( function_exists( 'get_product_search_form' ) ) {
		echo '<div class="ebook-search">';
		get_product_search_form();
		echo '</div>';
	}
}
add_action( 'storefront_header', 'ebookstore_child_header_search', 32 );

/**
 * Cart link: bag icon + item count badge + subtotal.
 *
 * Replaces Storefront's pluggable storefront_cart_link(). Storefront's AJAX
 * cart fragment calls this same function, so the count updates live.
 */
function storefront_cart_link() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$count = WC()->cart->get_cart_contents_count();
	?>
	<a class="cart-contents ebook-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<span class="ebook-cart__icon">
			<?php echo ebookstore_child_icon( 'bag', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="ebook-cart__count count" aria-hidden="true"><?php echo esc_html( (string) $count ); ?></span>
		</span>
		<span class="ebook-cart__total"><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></span>
		<span class="screen-reader-text">
			<?php
			/* translators: %d: number of items in the cart */
			echo esc_html( sprintf( _n( 'Cart: %d item', 'Cart: %d items', $count, 'ebookstore-child' ), $count ) );
			?>
		</span>
	</a>
	<?php
}

/* -------------------------------------------------------------------------
 * Header layout
 *   Top bar:   store name | search | account + cart
 *   Nav strip: menu (All eBooks has a category dropdown) | currency switcher
 * On phones the top bar holds store name + account + cart, the search goes
 * full width below, and the strip holds the Menu button + currency switcher.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		// Cart moves from the navigation strip to the top bar (after the search).
		remove_action( 'storefront_header', 'storefront_header_cart', 60 );
		add_action( 'storefront_header', 'ebookstore_child_header_actions', 36 );

		// Account and cart are in the header now, so no bottom bar on phones.
		remove_action( 'storefront_footer', 'storefront_handheld_footer_bar', 999 );
	}
);

/**
 * Account link + cart (top bar, right side).
 */
function ebookstore_child_header_actions() {
	$logged_in = is_user_logged_in();
	$label     = $logged_in ? __( 'My Account', 'ebookstore-child' ) : __( 'Sign in', 'ebookstore-child' );
	?>
	<div class="ebook-header-actions">
		<a class="ebook-account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
			<?php echo ebookstore_child_icon( 'user', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="ebook-account__label"><?php echo esc_html( $label ); ?></span>
		</a>
		<?php
		if ( function_exists( 'storefront_header_cart' ) ) {
			storefront_header_cart();
		}
		?>
	</div>
	<?php
}

/**
 * "All eBooks" menu item: add the product categories as a dropdown.
 * Automatic, so new categories appear without editing the menu.
 *
 * @param WP_Post[] $items Menu items.
 * @param stdClass  $args  wp_nav_menu() arguments.
 * @return WP_Post[]
 */
function ebookstore_child_menu_categories( $items, $args ) {
	if ( empty( $args->theme_location ) || ! in_array( $args->theme_location, array( 'primary', 'handheld' ), true ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return $items;
	}
	$shop   = untrailingslashit( wc_get_page_permalink( 'shop' ) );
	$parent = null;
	foreach ( $items as $item ) {
		if ( ! $item->menu_item_parent && untrailingslashit( $item->url ) === $shop ) {
			$parent = $item;
			break;
		}
	}
	if ( ! $parent ) {
		return $items;
	}
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent === (int) $parent->ID ) {
			return $items; // The admin added their own sub-items: keep them.
		}
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return $items;
	}

	$current  = is_product_category() ? get_queried_object_id() : 0;
	$children = array();
	$entries  = array_merge(
		array(
			array(
				'title'  => __( 'All eBooks', 'ebookstore-child' ),
				'url'    => wc_get_page_permalink( 'shop' ),
				'object' => 0,
			),
		),
		array_map(
			static function ( $term ) {
				return array(
					'title'  => $term->name,
					'url'    => get_term_link( $term ),
					'object' => (int) $term->term_id,
				);
			},
			$terms
		)
	);
	foreach ( $entries as $i => $entry ) {
		$is_current = $entry['object'] && $current === $entry['object'];
		$child      = new WP_Post( (object) array( 'ID' => -1000 - $i ) );
		foreach (
			array(
				'db_id'                 => -1000 - $i,
				'menu_item_parent'      => (string) $parent->ID,
				'object_id'             => $entry['object'],
				'object'                => $entry['object'] ? 'product_cat' : 'page',
				'type'                  => $entry['object'] ? 'taxonomy' : 'custom',
				'title'                 => $entry['title'],
				'url'                   => $entry['url'],
				'target'                => '',
				'attr_title'            => '',
				'description'           => '',
				'xfn'                   => '',
				'classes'               => array( 'menu-item', 'ebook-menu-cat', $is_current ? 'current-menu-item' : '' ),
				'current'               => $is_current,
				'current_item_ancestor' => false,
				'current_item_parent'   => false,
				'menu_order'            => $parent->menu_order,
				'post_type'             => 'nav_menu_item',
			) as $key => $value
		) {
			$child->$key = $value;
		}
		if ( $is_current ) {
			$parent->classes[] = 'current-menu-ancestor';
		}
		$children[] = $child;
	}

	// WordPress adds this class before this filter runs, so add it here.
	$parent->classes[] = 'menu-item-has-children';

	// Insert the children right after their parent.
	$out = array();
	foreach ( $items as $item ) {
		$out[] = $item;
		if ( $item === $parent ) {
			$out = array_merge( $out, $children );
		}
	}
	return $out;
}
add_filter( 'wp_nav_menu_objects', 'ebookstore_child_menu_categories', 10, 2 );

/**
 * Highlight "Bestsellers" / "New Arrivals" (shop links with ?orderby=…) when
 * that list is open; "All eBooks" is then not highlighted.
 *
 * @param string[] $classes Item classes.
 * @param WP_Post  $item    Menu item.
 * @return string[]
 */
function ebookstore_child_menu_current( $classes, $item ) {
	if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
		return $classes;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for highlighting.
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
	$query   = (string) wp_parse_url( (string) $item->url, PHP_URL_QUERY );
	parse_str( $query, $vars );
	$item_orderby = isset( $vars['orderby'] ) ? sanitize_key( $vars['orderby'] ) : '';

	if ( $item_orderby ) {
		if ( $item_orderby === $orderby ) {
			$classes[] = 'current-menu-item';
		}
	} elseif ( in_array( $orderby, array( 'popularity', 'date' ), true ) && untrailingslashit( (string) $item->url ) === untrailingslashit( wc_get_page_permalink( 'shop' ) ) ) {
		$classes = array_diff( $classes, array( 'current-menu-item', 'current_page_item' ) );
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'ebookstore_child_menu_current', 10, 2 );

// The bottom bar is removed, so its script is not needed.
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_dequeue_script( 'storefront-handheld-footer-bar' );
	},
	100
);
