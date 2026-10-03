<?php
/**
 * Home page sections: settings (WP admin → Home Page → Sections) and the
 * "Editor's Picks" shop list used by the section's "View all" link.
 * The sections are rendered by the child theme.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_SECTIONS_OPTION = 'ebookstore_home_sections';
const EBOOKSTORE_SECTIONS_PAGE   = 'ebookstore-home-sections';

/**
 * Defaults, in display order.
 *
 * @return array
 */
function ebookstore_home_section_defaults() {
	return array(
		'bestsellers' => array(
			'label'   => __( 'Bestsellers', 'ebook-store' ),
			'help'    => __( 'eBooks with the most sales.', 'ebook-store' ),
			'enabled' => '1',
			'title'   => __( 'Bestsellers', 'ebook-store' ),
			'count'   => 4,
		),
		'featured'    => array(
			'label'   => __( "Editor's Picks", 'ebook-store' ),
			'help'    => __( 'eBooks marked as "Featured" (star icon in Products).', 'ebook-store' ),
			'enabled' => '1',
			'title'   => __( "Editor's Picks", 'ebook-store' ),
			'count'   => 4,
		),
		'categories'  => array(
			'label'   => __( 'Browse by Category', 'ebook-store' ),
			'help'    => __( 'Category cards. Choose the categories and their order below, or leave all unticked to show the biggest categories automatically.', 'ebook-store' ),
			'enabled' => '1',
			'title'   => __( 'Browse by Category', 'ebook-store' ),
			'count'   => 8,
			'terms'   => array(),
		),
		'new'         => array(
			'label'   => __( 'New Arrivals', 'ebook-store' ),
			'help'    => __( 'The most recently added eBooks.', 'ebook-store' ),
			'enabled' => '1',
			'title'   => __( 'New Arrivals', 'ebook-store' ),
			'count'   => 4,
		),
		'promo'       => array(
			'label'   => __( 'Promotional strip', 'ebook-store' ),
			'help'    => __( 'A short message band with a button to the shop.', 'ebook-store' ),
			'enabled' => '1',
			'title'   => __( 'Instant download, read on any device', 'ebook-store' ),
			'text'    => __( 'Every eBook is a PDF you can read on your phone, tablet or computer. Pay in US dollars or British pounds.', 'ebook-store' ),
			'button'  => __( 'Browse all eBooks', 'ebook-store' ),
		),
	);
}

/**
 * Current settings merged with defaults (display order kept).
 *
 * @return array
 */
function ebookstore_home_sections() {
	$saved    = (array) get_option( EBOOKSTORE_SECTIONS_OPTION, array() );
	$sections = array();
	foreach ( ebookstore_home_section_defaults() as $key => $defaults ) {
		$sections[ $key ] = array_merge( $defaults, isset( $saved[ $key ] ) ? (array) $saved[ $key ] : array() );
	}
	return $sections;
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT,
			__( 'Home page sections', 'ebook-store' ),
			__( 'Sections', 'ebook-store' ),
			'edit_pages',
			EBOOKSTORE_SECTIONS_PAGE,
			'ebookstore_render_sections_page'
		);
	},
	20
);

/**
 * Render the Sections page.
 */
function ebookstore_render_sections_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$sections = ebookstore_home_sections();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after redirect.
	$saved = isset( $_GET['saved'] );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Home page sections', 'ebook-store' ); ?></h1>
		<p><?php esc_html_e( 'These sections appear on the home page below the slideshow, in this order. Untick a section to hide it.', 'ebook-store' ); ?></p>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ebook-store' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ebookstore_home_sections">
			<?php wp_nonce_field( 'ebookstore_home_sections' ); ?>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Show', 'ebook-store' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Section', 'ebook-store' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title on the website', 'ebook-store' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Items', 'ebook-store' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sections as $key => $s ) : ?>
						<tr>
							<td><input type="checkbox" id="ebookstore-<?php echo esc_attr( $key ); ?>-enabled" name="sections[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( '1', (string) $s['enabled'] ); ?>></td>
							<td>
								<label for="ebookstore-<?php echo esc_attr( $key ); ?>-enabled"><strong><?php echo esc_html( $s['label'] ); ?></strong></label><br>
								<span class="description"><?php echo esc_html( $s['help'] ); ?></span>
							</td>
							<td>
								<input type="text" class="regular-text" maxlength="80" aria-label="<?php echo esc_attr( $s['label'] . ' — ' . __( 'title', 'ebook-store' ) ); ?>" name="sections[<?php echo esc_attr( $key ); ?>][title]" value="<?php echo esc_attr( $s['title'] ); ?>">
								<?php if ( 'categories' === $key ) : ?>
									<?php ebookstore_render_section_terms( (array) $s['terms'] ); ?>
								<?php endif; ?>
								<?php if ( 'promo' === $key ) : ?>
									<br><textarea class="large-text" rows="2" maxlength="200" aria-label="<?php esc_attr_e( 'Promotional strip — text', 'ebook-store' ); ?>" name="sections[promo][text]"><?php echo esc_textarea( $s['text'] ); ?></textarea>
									<br><input type="text" class="regular-text" maxlength="30" aria-label="<?php esc_attr_e( 'Promotional strip — button text', 'ebook-store' ); ?>" name="sections[promo][button]" value="<?php echo esc_attr( $s['button'] ); ?>">
								<?php endif; ?>
							</td>
							<td>
								<?php if ( isset( $s['count'] ) ) : ?>
									<select name="sections[<?php echo esc_attr( $key ); ?>][count]" aria-label="<?php echo esc_attr( $s['label'] . ' — ' . __( 'number of items', 'ebook-store' ) ); ?>">
										<?php foreach ( array( 4, 8, 12 ) as $n ) : ?>
											<option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( (int) $s['count'], $n ); ?>><?php echo esc_html( (string) $n ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save changes', 'ebook-store' ) ); ?>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_ebookstore_home_sections',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ebook-store' ), 403 );
		}
		check_admin_referer( 'ebookstore_home_sections' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$input = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : array();
		$clean = array();
		foreach ( ebookstore_home_section_defaults() as $key => $defaults ) {
			$row           = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
			$clean[ $key ] = array(
				'enabled' => empty( $row['enabled'] ) ? '0' : '1',
				'title'   => mb_substr( sanitize_text_field( $row['title'] ?? '' ), 0, 80 ) ?: $defaults['title'],
			);
			if ( isset( $defaults['count'] ) ) {
				$count                  = (int) ( $row['count'] ?? $defaults['count'] );
				$clean[ $key ]['count'] = in_array( $count, array( 4, 8, 12 ), true ) ? $count : $defaults['count'];
			}
			if ( 'categories' === $key ) {
				$clean[ $key ]['terms'] = ebookstore_clean_section_terms( $row );
			}
			if ( 'promo' === $key ) {
				$clean[ $key ]['text']   = mb_substr( sanitize_textarea_field( $row['text'] ?? '' ), 0, 200 );
				$clean[ $key ]['button'] = mb_substr( sanitize_text_field( $row['button'] ?? '' ), 0, 30 ) ?: $defaults['button'];
			}
		}
		update_option( EBOOKSTORE_SECTIONS_OPTION, $clean, false );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT . '&page=' . EBOOKSTORE_SECTIONS_PAGE . '&saved=1' ) );
		exit;
	}
);

/* -------------------------------------------------------------------------
 * "Browse by Category": chosen categories and their order
 * ---------------------------------------------------------------------- */

/**
 * Category picker inside the Sections table (tick + order number per category).
 *
 * @param int[] $chosen Chosen term IDs in display order.
 */
function ebookstore_render_section_terms( array $chosen ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return;
	}
	$chosen = array_values( array_map( 'intval', $chosen ) );
	?>
	<fieldset style="margin-top:10px">
		<legend><strong><?php esc_html_e( 'Categories to show', 'ebook-store' ); ?></strong> <span class="description"><?php esc_html_e( '(tick and number them 1, 2, 3 … — none ticked = automatic)', 'ebook-store' ); ?></span></legend>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:4px 16px;margin-top:6px">
			<?php foreach ( $terms as $term ) : ?>
				<?php
				$pos = array_search( (int) $term->term_id, $chosen, true );
				$cb  = 'ebookstore-cat-' . $term->term_id;
				?>
				<span>
					<input type="checkbox" id="<?php echo esc_attr( $cb ); ?>" name="sections[categories][terms][]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php checked( false !== $pos ); ?>>
					<input type="number" min="1" max="99" style="width:56px" name="sections[categories][order][<?php echo esc_attr( (string) $term->term_id ); ?>]" value="<?php echo esc_attr( false !== $pos ? (string) ( $pos + 1 ) : '' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: category name */ __( 'Position of %s', 'ebook-store' ), $term->name ) ); ?>">
					<label for="<?php echo esc_attr( $cb ); ?>"><?php echo esc_html( sprintf( '%s (%d)', $term->name, $term->count ) ); ?></label>
				</span>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e( 'The card picture is the category image (Products → Categories → edit → Thumbnail). Without one, the newest eBook cover of the category is used. Empty categories are not shown.', 'ebook-store' ); ?></p>
	</fieldset>
	<?php
}

/**
 * Chosen category IDs from the form, sorted by their order number.
 *
 * @param array $row Posted "categories" row.
 * @return int[]
 */
function ebookstore_clean_section_terms( array $row ) {
	$ids   = isset( $row['terms'] ) && is_array( $row['terms'] ) ? array_unique( array_map( 'absint', $row['terms'] ) ) : array();
	$order = isset( $row['order'] ) && is_array( $row['order'] ) ? $row['order'] : array();
	$list  = array();
	foreach ( $ids as $id ) {
		$term = $id ? get_term( $id, 'product_cat' ) : null;
		if ( $term && ! is_wp_error( $term ) ) {
			$pos    = isset( $order[ $id ] ) && '' !== $order[ $id ] ? absint( $order[ $id ] ) : 999;
			$list[] = array( $pos, $term->name, $id );
		}
	}
	usort(
		$list,
		static function ( $a, $b ) {
			return array( $a[0], $a[1] ) <=> array( $b[0], $b[1] );
		}
	);
	return array_slice( array_column( $list, 2 ), 0, 24 );
}

/* -------------------------------------------------------------------------
 * "Editor's Picks" list in the shop (?ebook_list=featured)
 * ---------------------------------------------------------------------- */

/**
 * Whether the shop is showing the Editor's Picks list.
 *
 * @return bool
 */
function ebookstore_is_featured_list() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	return isset( $_GET['ebook_list'] ) && 'featured' === sanitize_key( wp_unslash( $_GET['ebook_list'] ) );
}

add_action(
	'woocommerce_product_query',
	static function ( $query ) {
		if ( ! ebookstore_is_featured_list() ) {
			return;
		}
		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => 'featured',
		);
		$query->set( 'tax_query', $tax_query );
	}
);

add_filter(
	'woocommerce_page_title',
	static function ( $title ) {
		if ( ebookstore_is_featured_list() && is_shop() ) {
			$sections = ebookstore_home_sections();
			return $sections['featured']['title'];
		}
		return $title;
	}
);
