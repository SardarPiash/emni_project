<?php
/**
 * Home page sections (settings from the mu-plugin: Home Page → Sections)
 * and the category filter in the shop toolbar.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the enabled home page sections in order. The first section gets the
 * id "ebooks" (target of the hero's "Browse eBooks" button).
 *
 * @return bool Whether any section was printed.
 */
function ebookstore_child_home_sections() {
	if ( ! function_exists( 'ebookstore_home_sections' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return false;
	}
	$shop    = wc_get_page_permalink( 'shop' );
	$printed = false;

	foreach ( ebookstore_home_sections() as $key => $section ) {
		if ( '1' !== (string) $section['enabled'] ) {
			continue;
		}
		$id = $printed ? 'home-' . $key : 'ebooks';

		ob_start();
		switch ( $key ) {
			case 'bestsellers':
				get_template_part(
					'template-parts/sections/products',
					null,
					array(
						'id'        => $id,
						'title'     => $section['title'],
						'link'      => add_query_arg( 'orderby', 'popularity', $shop ),
						'link_text' => __( 'View all', 'ebookstore-child' ),
						'shortcode' => sprintf( '[products limit="%d" columns="4" best_selling="true"]', (int) $section['count'] ),
					)
				);
				break;
			case 'featured':
				get_template_part(
					'template-parts/sections/products',
					null,
					array(
						'id'        => $id,
						'title'     => $section['title'],
						'link'      => add_query_arg( 'ebook_list', 'featured', $shop ),
						'link_text' => __( 'View all', 'ebookstore-child' ),
						'shortcode' => sprintf( '[products limit="%d" columns="4" visibility="featured" orderby="date" order="DESC"]', (int) $section['count'] ),
					)
				);
				break;
			case 'new':
				get_template_part(
					'template-parts/sections/products',
					null,
					array(
						'id'        => $id,
						'title'     => $section['title'],
						'link'      => add_query_arg( 'orderby', 'date', $shop ),
						'link_text' => __( 'View all', 'ebookstore-child' ),
						'shortcode' => sprintf( '[products limit="%d" columns="4" orderby="date" order="DESC"]', (int) $section['count'] ),
					)
				);
				break;
			case 'categories':
				get_template_part(
					'template-parts/sections/categories',
					null,
					array(
						'id'    => $id,
						'title' => $section['title'],
						'count' => (int) $section['count'],
					)
				);
				break;
			case 'promo':
				get_template_part(
					'template-parts/sections/promo',
					null,
					array(
						'id'     => $id,
						'title'  => $section['title'],
						'text'   => $section['text'],
						'button' => $section['button'],
					)
				);
				break;
		}
		$html = trim( (string) ob_get_clean() );
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates.
			$printed = true;
		}
	}
	return $printed;
}

/* -------------------------------------------------------------------------
 * Shop toolbar: category filter
 * ---------------------------------------------------------------------- */

/**
 * Category dropdown next to the sorting dropdown (shop and category pages).
 * Works without JavaScript via the "Go" button; with JS it navigates on change.
 */
function ebookstore_child_category_filter() {
	if ( ! woocommerce_products_will_display() ) {
		return;
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) || count( $terms ) < 2 ) {
		return;
	}
	$current = is_product_category() ? get_queried_object_id() : 0;
	$shop    = wc_get_page_permalink( 'shop' );
	?>
	<form class="ebook-catfilter" action="<?php echo esc_url( $shop ); ?>" method="get">
		<label class="ebook-sort-label" for="ebook-catfilter-select"><?php esc_html_e( 'Category', 'ebookstore-child' ); ?></label>
		<select id="ebook-catfilter-select" name="product_cat" class="ebook-catfilter__select" data-ebook-navigate>
			<option value="" data-url="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'All categories', 'ebookstore-child' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" data-url="<?php echo esc_url( get_term_link( $term ) ); ?>" <?php selected( $current, $term->term_id ); ?>>
					<?php echo esc_html( sprintf( '%s (%d)', $term->name, $term->count ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="button"><?php esc_html_e( 'Go', 'ebookstore-child' ); ?></button></noscript>
	</form>
	<?php
}
// Registered on "init" (priority 9) so it runs inside Storefront's toolbar wrapper
// (priority 9, registered earlier) and before the "Sort by" label (added on init 10).
add_action(
	'init',
	static function () {
		add_action( 'woocommerce_before_shop_loop', 'ebookstore_child_category_filter', 9 );
	},
	9
);
