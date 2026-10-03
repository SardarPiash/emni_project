<?php
/**
 * Shop, category and search pages: layout.
 *
 * - Desktop: category list + collections in a sidebar on the left, products on
 *   the right. Phones/tablets: the categories become a row of chips.
 * - Toolbar above the grid: result count + sorting.
 * - Pagination only below the grid.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		// Storefront prints the pagination above the grid too; keep it only below.
		remove_action( 'woocommerce_before_shop_loop', 'storefront_woocommerce_pagination', 30 );

		add_action( 'woocommerce_before_shop_loop', 'ebookstore_child_shop_open', 5 );
		add_action( 'woocommerce_after_shop_loop', 'ebookstore_child_shop_close', 100 );
	}
);

/**
 * Whether the current loop is the main shop/category/search listing (not a
 * [products] shortcode on another page).
 *
 * @return bool
 */
function ebookstore_child_is_shop_listing() {
	return ( is_shop() || is_product_taxonomy() || is_search() ) && ! wc_get_loop_prop( 'is_shortcode' );
}

/**
 * Open the two-column layout and print the sidebar.
 */
function ebookstore_child_shop_open() {
	if ( ! ebookstore_child_is_shop_listing() ) {
		return;
	}
	echo '<div class="ebook-shop">';
	ebookstore_child_shop_nav();
	echo '<div class="ebook-shop__main">';
}

/**
 * Close the layout.
 */
function ebookstore_child_shop_close() {
	if ( ! ebookstore_child_is_shop_listing() ) {
		return;
	}
	echo '</div></div>';
}

/**
 * Sidebar: categories (with counts) and collections.
 */
function ebookstore_child_shop_nav() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'orderby'    => 'name',
		)
	);
	$terms = is_wp_error( $terms ) ? array() : $terms;
	$shop  = wc_get_page_permalink( 'shop' );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only state for highlighting.
	$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
	$featured = function_exists( 'ebookstore_is_featured_list' ) && ebookstore_is_featured_list();
	// phpcs:enable

	$current_term = is_product_category() ? get_queried_object_id() : 0;
	$is_all       = is_shop() && ! is_search() && ! $featured && ! in_array( $orderby, array( 'popularity', 'date' ), true );

	$sections = function_exists( 'ebookstore_home_sections' ) ? ebookstore_home_sections() : array();
	$lists    = array(
		array(
			'label'   => __( 'Bestsellers', 'ebookstore-child' ),
			'url'     => add_query_arg( 'orderby', 'popularity', $shop ),
			'current' => is_shop() && ! $featured && 'popularity' === $orderby,
		),
		array(
			'label'   => $sections['featured']['title'] ?? __( "Editor's Picks", 'ebookstore-child' ),
			'url'     => add_query_arg( 'ebook_list', 'featured', $shop ),
			'current' => $featured,
		),
		array(
			'label'   => __( 'New Arrivals', 'ebookstore-child' ),
			'url'     => add_query_arg( 'orderby', 'date', $shop ),
			'current' => is_shop() && ! $featured && 'date' === $orderby,
		),
	);
	$total = (int) wp_count_posts( 'product' )->publish;
	?>
	<aside class="ebook-shop__nav" aria-label="<?php esc_attr_e( 'Browse eBooks', 'ebookstore-child' ); ?>">
		<h2 class="ebook-shop__nav-title"><?php esc_html_e( 'Categories', 'ebookstore-child' ); ?></h2>
		<ul class="ebook-shop__cats">
			<li>
				<a class="ebook-shop__cat<?php echo $is_all ? ' is-current' : ''; ?>" href="<?php echo esc_url( $shop ); ?>"<?php echo $is_all ? ' aria-current="page"' : ''; ?>>
					<span class="ebook-shop__cat-name"><?php esc_html_e( 'All eBooks', 'ebookstore-child' ); ?></span>
					<span class="ebook-shop__count"><?php echo esc_html( (string) $total ); ?></span>
				</a>
			</li>
			<?php foreach ( $terms as $term ) : ?>
				<?php $is_current = $current_term === (int) $term->term_id; ?>
				<li>
					<a class="ebook-shop__cat<?php echo $is_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( get_term_link( $term ) ); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
						<span class="ebook-shop__cat-name"><?php echo esc_html( $term->name ); ?></span>
						<span class="ebook-shop__count"><?php echo esc_html( (string) $term->count ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<h2 class="ebook-shop__nav-title ebook-shop__nav-title--lists"><?php esc_html_e( 'Collections', 'ebookstore-child' ); ?></h2>
		<ul class="ebook-shop__lists">
			<?php foreach ( $lists as $list ) : ?>
				<li>
					<a class="ebook-shop__list<?php echo $list['current'] ? ' is-current' : ''; ?>" href="<?php echo esc_url( $list['url'] ); ?>"<?php echo $list['current'] ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $list['label'] ); ?>
						<?php echo ebookstore_child_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>
	<?php
}

// Pagination arrows: clearer labels than the default ← / →.
add_filter(
	'woocommerce_pagination_args',
	static function ( $args ) {
		$args['prev_text'] = '<span aria-hidden="true">&larr;</span> <span class="ebook-page-label">' . esc_html__( 'Previous', 'ebookstore-child' ) . '</span>';
		$args['next_text'] = '<span class="ebook-page-label">' . esc_html__( 'Next', 'ebookstore-child' ) . '</span> <span aria-hidden="true">&rarr;</span>';
		$args['end_size']  = 1;
		$args['mid_size']  = 1;
		return $args;
	}
);
