<?php
/**
 * Side promo banners: two small banners next to the home page slideshow
 * (WP admin → Home Page → Side Banners). Rendered by the child theme.
 *
 * Links are stored as entered ("/shop/" stays relative), so they keep working
 * after the site moves to another domain.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_BANNERS_OPTION = 'ebookstore_promo_banners';
const EBOOKSTORE_BANNERS_PAGE   = 'ebookstore-promo-banners';
const EBOOKSTORE_BANNER_STYLES  = array( 'navy', 'coral', 'gold', 'cream' );
const EBOOKSTORE_BANNER_COVERS  = array( 'none', 'featured', 'new', 'bestsellers' );

/**
 * Defaults for the two banners.
 *
 * @return array[]
 */
function ebookstore_promo_banner_defaults() {
	return array(
		array(
			'enabled' => '1',
			'label'   => __( "Editor's Picks", 'ebook-store' ),
			'title'   => __( 'Hand-picked reads', 'ebook-store' ),
			'text'    => __( 'Our favourite eBooks right now.', 'ebook-store' ),
			'button'  => __( 'See the picks', 'ebook-store' ),
			'link'    => '/shop/?ebook_list=featured',
			'style'   => 'navy',
			'covers'  => 'featured',
			'image'   => 0,
		),
		array(
			'enabled' => '1',
			'label'   => __( 'New arrivals', 'ebook-store' ),
			'title'   => __( 'Fresh this month', 'ebook-store' ),
			'text'    => __( 'The latest eBooks, ready to download.', 'ebook-store' ),
			'button'  => __( 'Shop new eBooks', 'ebook-store' ),
			'link'    => '/shop/?orderby=date',
			'style'   => 'coral',
			'covers'  => 'new',
			'image'   => 0,
		),
	);
}

/**
 * All banners (saved values merged with defaults).
 *
 * @return array[]
 */
function ebookstore_promo_banners_all() {
	$saved   = (array) get_option( EBOOKSTORE_BANNERS_OPTION, array() );
	$banners = array();
	foreach ( ebookstore_promo_banner_defaults() as $i => $defaults ) {
		$banners[] = array_merge( $defaults, isset( $saved[ $i ] ) ? (array) $saved[ $i ] : array() );
	}
	return $banners;
}

/**
 * Banners to show on the website, with the link resolved to a full URL.
 *
 * @return array[]
 */
function ebookstore_get_promo_banners() {
	$banners = array();
	foreach ( ebookstore_promo_banners_all() as $banner ) {
		if ( '1' !== (string) $banner['enabled'] || '' === $banner['title'] ) {
			continue;
		}
		$banner['url']   = ebookstore_banner_url( $banner['link'] );
		$banner['image'] = ( $banner['image'] && wp_attachment_is_image( (int) $banner['image'] ) ) ? (int) $banner['image'] : 0;
		$banners[]       = $banner;
	}
	return $banners;
}

/**
 * Clean a link typed by the admin: "/path", "#anchor" or an http(s) URL.
 *
 * @param string $link Raw link.
 * @return string
 */
function ebookstore_sanitize_banner_link( $link ) {
	$link = trim( (string) $link );
	if ( '' === $link ) {
		return '';
	}
	if ( '#' === $link[0] ) {
		return '#' . preg_replace( '/[^A-Za-z0-9_-]/', '', substr( $link, 1 ) );
	}
	if ( '/' === $link[0] && ( ! isset( $link[1] ) || '/' !== $link[1] ) ) {
		// Keep it relative: path + query only.
		$clean = esc_url_raw( 'http://x' . $link );
		return (string) substr( $clean, strlen( 'http://x' ) );
	}
	return esc_url_raw( $link, array( 'http', 'https' ) );
}

/**
 * Full URL for a stored link.
 *
 * @param string $link Stored link.
 * @return string
 */
function ebookstore_banner_url( $link ) {
	if ( '' === $link ) {
		return '';
	}
	if ( '/' === $link[0] ) {
		return home_url( $link );
	}
	if ( '#' === $link[0] ) {
		return home_url( '/' ) . $link;
	}
	return $link;
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT,
			__( 'Side banners', 'ebook-store' ),
			__( 'Side Banners', 'ebook-store' ),
			'edit_pages',
			EBOOKSTORE_BANNERS_PAGE,
			'ebookstore_render_banners_page'
		);
	},
	15
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( false === strpos( (string) $hook, EBOOKSTORE_BANNERS_PAGE ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'ebookstore-hero-admin', plugins_url( 'assets/hero-slides-admin.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
		wp_localize_script(
			'ebookstore-hero-admin',
			'ebookstoreHeroAdmin',
			array(
				'title'  => __( 'Choose banner image', 'ebook-store' ),
				'button' => __( 'Use this image', 'ebook-store' ),
				'empty'  => __( 'No image (book covers or colour only)', 'ebook-store' ),
			)
		);
		wp_add_inline_style(
			'wp-admin',
			'.ebookstore-banners{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px;max-width:1100px}' .
			'.ebookstore-banners .postbox{padding:4px 16px 12px;margin:0}' .
			'.ebookstore-banners .form-table th{width:150px}' .
			'.ebookstore-image-field__preview{display:flex;align-items:center;justify-content:center;min-height:90px;max-width:320px;background:#f0f0f1;border:1px dashed #c3c4c7;border-radius:4px;overflow:hidden}' .
			'.ebookstore-image-field__preview img{display:block;max-width:100%;height:auto}' .
			'.ebookstore-image-field__empty{color:#646970;padding:8px}'
		);
	}
);

/**
 * Render the Side Banners page.
 */
function ebookstore_render_banners_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$banners = ebookstore_promo_banners_all();
	$styles  = array(
		'navy'  => __( 'Navy', 'ebook-store' ),
		'coral' => __( 'Coral', 'ebook-store' ),
		'gold'  => __( 'Gold', 'ebook-store' ),
		'cream' => __( 'Cream', 'ebook-store' ),
	);
	$covers  = array(
		'none'        => __( 'No book covers', 'ebook-store' ),
		'featured'    => __( "Editor's Picks (Featured eBooks)", 'ebook-store' ),
		'new'         => __( 'Newest eBooks', 'ebook-store' ),
		'bestsellers' => __( 'Bestsellers', 'ebook-store' ),
	);
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after redirect.
	$saved = isset( $_GET['saved'] );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Side banners', 'ebook-store' ); ?></h1>
		<p><?php esc_html_e( 'Two small promotion banners shown next to the slideshow on the home page (under it on phones). Use them for offers, collections or a category.', 'ebook-store' ); ?></p>
		<p class="description"><?php esc_html_e( 'Background: choose a colour and, optionally, 2 book covers from a list — or upload your own image (about 800×500, the left side should be calm so the text stays readable). Links: "/shop/", "/product-category/fiction/", or a full https:// address.', 'ebook-store' ); ?></p>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Banners saved.', 'ebook-store' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ebookstore_promo_banners">
			<?php wp_nonce_field( 'ebookstore_promo_banners' ); ?>
			<div class="ebookstore-banners">
				<?php foreach ( $banners as $i => $b ) : ?>
					<?php
					$n     = $i + 1;
					$field = static function ( $name ) use ( $i ) {
						return 'banners[' . $i . '][' . $name . ']';
					};
					$id    = static function ( $name ) use ( $i ) {
						return 'ebookstore-banner-' . $i . '-' . $name;
					};
					?>
					<div class="postbox">
						<h2>
							<?php
							/* translators: %d: banner number */
							echo esc_html( sprintf( __( 'Banner %d', 'ebook-store' ), $n ) );
							echo esc_html( 1 === $n ? ' — ' . __( 'top', 'ebook-store' ) : ' — ' . __( 'bottom', 'ebook-store' ) );
							?>
						</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Show', 'ebook-store' ); ?></th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( $field( 'enabled' ) ); ?>" value="1" <?php checked( '1', (string) $b['enabled'] ); ?>> <?php esc_html_e( 'Show this banner on the website', 'ebook-store' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'label' ) ); ?>"><?php esc_html_e( 'Small label', 'ebook-store' ); ?></label></th>
								<td><input type="text" class="regular-text" maxlength="30" id="<?php echo esc_attr( $id( 'label' ) ); ?>" name="<?php echo esc_attr( $field( 'label' ) ); ?>" value="<?php echo esc_attr( $b['label'] ); ?>"><p class="description"><?php esc_html_e( 'e.g. "Limited offer" (optional)', 'ebook-store' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'ebook-store' ); ?></label></th>
								<td><input type="text" class="regular-text" maxlength="60" id="<?php echo esc_attr( $id( 'title' ) ); ?>" name="<?php echo esc_attr( $field( 'title' ) ); ?>" value="<?php echo esc_attr( $b['title'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'text' ) ); ?>"><?php esc_html_e( 'Text', 'ebook-store' ); ?></label></th>
								<td><input type="text" class="regular-text" maxlength="120" id="<?php echo esc_attr( $id( 'text' ) ); ?>" name="<?php echo esc_attr( $field( 'text' ) ); ?>" value="<?php echo esc_attr( $b['text'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'button' ) ); ?>"><?php esc_html_e( 'Button text', 'ebook-store' ); ?></label></th>
								<td><input type="text" class="regular-text" maxlength="24" id="<?php echo esc_attr( $id( 'button' ) ); ?>" name="<?php echo esc_attr( $field( 'button' ) ); ?>" value="<?php echo esc_attr( $b['button'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'link' ) ); ?>"><?php esc_html_e( 'Link', 'ebook-store' ); ?></label></th>
								<td><input type="text" class="regular-text code" maxlength="300" id="<?php echo esc_attr( $id( 'link' ) ); ?>" name="<?php echo esc_attr( $field( 'link' ) ); ?>" value="<?php echo esc_attr( $b['link'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'style' ) ); ?>"><?php esc_html_e( 'Colour', 'ebook-store' ); ?></label></th>
								<td>
									<select id="<?php echo esc_attr( $id( 'style' ) ); ?>" name="<?php echo esc_attr( $field( 'style' ) ); ?>">
										<?php foreach ( $styles as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $b['style'], $value ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id( 'covers' ) ); ?>"><?php esc_html_e( 'Book covers', 'ebook-store' ); ?></label></th>
								<td>
									<select id="<?php echo esc_attr( $id( 'covers' ) ); ?>" name="<?php echo esc_attr( $field( 'covers' ) ); ?>">
										<?php foreach ( $covers as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $b['covers'], $value ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Shows 2 covers from this list on the right side. Not used when an image is chosen.', 'ebook-store' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Image (optional)', 'ebook-store' ); ?></th>
								<td>
									<div class="ebookstore-image-field">
										<input type="hidden" name="<?php echo esc_attr( $field( 'image' ) ); ?>" value="<?php echo esc_attr( $b['image'] ? (string) $b['image'] : '' ); ?>">
										<div class="ebookstore-image-field__preview">
											<?php
											if ( $b['image'] && wp_attachment_is_image( (int) $b['image'] ) ) {
												echo wp_get_attachment_image( (int) $b['image'], 'medium' );
											} else {
												echo '<span class="ebookstore-image-field__empty">' . esc_html__( 'No image (book covers or colour only)', 'ebook-store' ) . '</span>';
											}
											?>
										</div>
										<p>
											<button type="button" class="button ebookstore-image-field__choose"><?php esc_html_e( 'Choose image', 'ebook-store' ); ?></button>
											<button type="button" class="button-link button-link-delete ebookstore-image-field__remove" <?php echo $b['image'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove image', 'ebook-store' ); ?></button>
										</p>
									</div>
								</td>
							</tr>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
			<?php submit_button( __( 'Save banners', 'ebook-store' ) ); ?>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_ebookstore_promo_banners',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ebook-store' ), 403 );
		}
		check_admin_referer( 'ebookstore_promo_banners' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$input = isset( $_POST['banners'] ) && is_array( $_POST['banners'] ) ? wp_unslash( $_POST['banners'] ) : array();
		$clean = array();
		foreach ( ebookstore_promo_banner_defaults() as $i => $defaults ) {
			$row     = isset( $input[ $i ] ) && is_array( $input[ $i ] ) ? $input[ $i ] : array();
			$image   = absint( $row['image'] ?? 0 );
			$style   = sanitize_key( $row['style'] ?? '' );
			$covers  = sanitize_key( $row['covers'] ?? '' );
			$clean[] = array(
				'enabled' => empty( $row['enabled'] ) ? '0' : '1',
				'label'   => mb_substr( sanitize_text_field( $row['label'] ?? '' ), 0, 30 ),
				'title'   => mb_substr( sanitize_text_field( $row['title'] ?? '' ), 0, 60 ),
				'text'    => mb_substr( sanitize_text_field( $row['text'] ?? '' ), 0, 120 ),
				'button'  => mb_substr( sanitize_text_field( $row['button'] ?? '' ), 0, 24 ),
				'link'    => ebookstore_sanitize_banner_link( mb_substr( (string) ( $row['link'] ?? '' ), 0, 300 ) ),
				'style'   => in_array( $style, EBOOKSTORE_BANNER_STYLES, true ) ? $style : $defaults['style'],
				'covers'  => in_array( $covers, EBOOKSTORE_BANNER_COVERS, true ) ? $covers : $defaults['covers'],
				'image'   => ( $image && wp_attachment_is_image( $image ) ) ? $image : 0,
			);
		}
		update_option( EBOOKSTORE_BANNERS_OPTION, $clean, false );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT . '&page=' . EBOOKSTORE_BANNERS_PAGE . '&saved=1' ) );
		exit;
	}
);
