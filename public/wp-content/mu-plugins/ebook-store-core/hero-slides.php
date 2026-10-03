<?php
/**
 * Hero Slides: banner/carousel slides for the home page, managed in
 * WP admin → Hero Slides. Images come from the Media Library.
 *
 * Front end (markup, CSS, JS) lives in the child theme; this file only stores
 * the slides and exposes them via ebookstore_get_hero_slides().
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_SLIDE_PT      = 'ebookstore_slide';
const EBOOKSTORE_HERO_OPTION   = 'ebookstore_hero_settings';
const EBOOKSTORE_HERO_SETTINGS = 'ebookstore-hero-settings';

/* -------------------------------------------------------------------------
 * Data model
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		register_post_type(
			EBOOKSTORE_SLIDE_PT,
			array(
				'labels'              => array(
					'name'          => __( 'Hero Slides', 'ebook-store' ),
					'singular_name' => __( 'Hero Slide', 'ebook-store' ),
					'menu_name'     => __( 'Hero Slides', 'ebook-store' ),
					'all_items'     => __( 'All Slides', 'ebook-store' ),
					'add_new'       => __( 'Add New Slide', 'ebook-store' ),
					'add_new_item'  => __( 'Add New Hero Slide', 'ebook-store' ),
					'edit_item'     => __( 'Edit Hero Slide', 'ebook-store' ),
					'new_item'      => __( 'New Hero Slide', 'ebook-store' ),
					'search_items'  => __( 'Search Slides', 'ebook-store' ),
					'not_found'     => __( 'No slides yet. Click "Add New Slide" to create one.', 'ebook-store' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_position'       => 56,
				'menu_icon'           => 'dashicons-images-alt2',
				'supports'            => array( 'title', 'page-attributes' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}
);

/**
 * Slide fields: meta key => default.
 *
 * @return array
 */
function ebookstore_slide_fields() {
	return array(
		'_slide_image_desktop' => 0,
		'_slide_image_mobile'  => 0,
		'_slide_heading'       => '',
		'_slide_subheading'    => '',
		'_slide_button_text'   => '',
		'_slide_button_link'   => '',
		'_slide_enabled'       => '1',
	);
}

/**
 * Autoplay interval in seconds (0 = off). Admin setting first, then
 * HERO_AUTOPLAY_SECONDS from .env, then 6.
 *
 * @return int
 */
function ebookstore_hero_autoplay_seconds() {
	$settings = (array) get_option( EBOOKSTORE_HERO_OPTION, array() );
	if ( isset( $settings['autoplay'] ) && '' !== $settings['autoplay'] ) {
		return max( 0, min( 30, (int) $settings['autoplay'] ) );
	}
	$env = function_exists( 'env' ) ? env( 'HERO_AUTOPLAY_SECONDS', '' ) : '';
	return ( '' !== (string) $env && is_numeric( $env ) ) ? max( 0, min( 30, (int) $env ) ) : 6;
}

/**
 * Slides shown on the website: published, "Show on website" ticked and a
 * desktop image chosen; sorted by Order (then newest first).
 *
 * @return array[] Each: id, desktop_id, mobile_id, heading, subheading, button_text, button_link.
 */
function ebookstore_get_hero_slides() {
	$posts  = get_posts(
		array(
			'post_type'      => EBOOKSTORE_SLIDE_PT,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'no_found_rows'  => true,
		)
	);
	$slides = array();
	foreach ( $posts as $post ) {
		$desktop = (int) get_post_meta( $post->ID, '_slide_image_desktop', true );
		if ( '1' !== (string) get_post_meta( $post->ID, '_slide_enabled', true ) || ! $desktop || ! wp_attachment_is_image( $desktop ) ) {
			continue;
		}
		$mobile   = (int) get_post_meta( $post->ID, '_slide_image_mobile', true );
		$slides[] = array(
			'id'          => $post->ID,
			'desktop_id'  => $desktop,
			'mobile_id'   => ( $mobile && wp_attachment_is_image( $mobile ) ) ? $mobile : 0,
			'heading'     => (string) get_post_meta( $post->ID, '_slide_heading', true ),
			'subheading'  => (string) get_post_meta( $post->ID, '_slide_subheading', true ),
			'button_text' => (string) get_post_meta( $post->ID, '_slide_button_text', true ),
			'button_link' => (string) get_post_meta( $post->ID, '_slide_button_link', true ),
		);
	}
	return $slides;
}

/**
 * Clean a button link: a site path ("/shop/"), an anchor ("#ebooks") or a full http(s) URL.
 *
 * @param string $link Raw link.
 * @return string
 */
function ebookstore_sanitize_slide_link( $link ) {
	$link = trim( (string) $link );
	if ( '' === $link ) {
		return '';
	}
	if ( '#' === $link[0] ) {
		return '#' . preg_replace( '/[^A-Za-z0-9_-]/', '', substr( $link, 1 ) );
	}
	if ( '/' === $link[0] && ( ! isset( $link[1] ) || '/' !== $link[1] ) ) {
		return esc_url_raw( home_url( $link ) );
	}
	return esc_url_raw( $link, array( 'http', 'https' ) );
}

/* -------------------------------------------------------------------------
 * Admin: edit screen
 * ---------------------------------------------------------------------- */

add_filter(
	'enter_title_here',
	static function ( $text, $post ) {
		return ( $post && EBOOKSTORE_SLIDE_PT === $post->post_type ) ? __( 'Slide name (only shown to you in the admin)', 'ebook-store' ) : $text;
	},
	10,
	2
);

add_action(
	'add_meta_boxes_' . EBOOKSTORE_SLIDE_PT,
	static function () {
		add_meta_box( 'ebookstore-slide', __( 'Slide content', 'ebook-store' ), 'ebookstore_render_slide_box', EBOOKSTORE_SLIDE_PT, 'normal', 'high' );
	}
);

/**
 * One image picker field.
 *
 * @param string $key   Meta key.
 * @param int    $id    Attachment ID.
 * @param string $label Label.
 * @param string $help  Help text.
 */
function ebookstore_slide_image_field( $key, $id, $label, $help ) {
	$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	?>
	<div class="ebookstore-image-field" data-field="<?php echo esc_attr( $key ); ?>">
		<p><strong><?php echo esc_html( $label ); ?></strong></p>
		<div class="ebookstore-image-field__preview">
			<?php if ( $url ) : ?>
				<img src="<?php echo esc_url( $url ); ?>" alt="">
			<?php else : ?>
				<span class="ebookstore-image-field__empty"><?php esc_html_e( 'No image chosen', 'ebook-store' ); ?></span>
			<?php endif; ?>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $id ); ?>">
		<p>
			<button type="button" class="button ebookstore-image-field__choose"><?php esc_html_e( 'Choose image', 'ebook-store' ); ?></button>
			<button type="button" class="button-link ebookstore-image-field__remove"<?php echo $id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'ebook-store' ); ?></button>
		</p>
		<p class="description"><?php echo esc_html( $help ); ?></p>
	</div>
	<?php
}

/**
 * Render the "Slide content" box.
 *
 * @param WP_Post $post Slide.
 */
function ebookstore_render_slide_box( $post ) {
	$v = array();
	foreach ( ebookstore_slide_fields() as $key => $default ) {
		$stored    = get_post_meta( $post->ID, $key, true );
		$v[ $key ] = ( '' === $stored && 'auto-draft' === $post->post_status ) ? $default : $stored;
	}
	wp_nonce_field( 'ebookstore_save_slide', 'ebookstore_slide_nonce' );
	?>
	<div class="ebookstore-slide-box">
		<div class="ebookstore-slide-box__images">
			<?php
			ebookstore_slide_image_field( '_slide_image_desktop', (int) $v['_slide_image_desktop'], __( 'Desktop image (required)', 'ebook-store' ), __( 'Wide landscape picture, ideally 1600 × 600 pixels. Text appears on the left, so keep the important part of the picture on the right.', 'ebook-store' ) );
			ebookstore_slide_image_field( '_slide_image_mobile', (int) $v['_slide_image_mobile'], __( 'Mobile image (optional)', 'ebook-store' ), __( 'Tall picture for phones, ideally 800 × 1000 pixels. If empty, the desktop image is used.', 'ebook-store' ) );
			?>
		</div>

		<p>
			<label for="ebookstore-slide-heading"><strong><?php esc_html_e( 'Heading', 'ebook-store' ); ?></strong></label><br>
			<input type="text" id="ebookstore-slide-heading" name="_slide_heading" class="widefat" maxlength="90" value="<?php echo esc_attr( $v['_slide_heading'] ); ?>">
			<span class="description"><?php esc_html_e( 'Short and clear, up to about 8 words.', 'ebook-store' ); ?></span>
		</p>
		<p>
			<label for="ebookstore-slide-subheading"><strong><?php esc_html_e( 'Subheading', 'ebook-store' ); ?></strong></label><br>
			<textarea id="ebookstore-slide-subheading" name="_slide_subheading" class="widefat" rows="2" maxlength="200"><?php echo esc_textarea( $v['_slide_subheading'] ); ?></textarea>
		</p>
		<p>
			<label for="ebookstore-slide-button-text"><strong><?php esc_html_e( 'Button text', 'ebook-store' ); ?></strong></label><br>
			<input type="text" id="ebookstore-slide-button-text" name="_slide_button_text" class="regular-text" maxlength="30" value="<?php echo esc_attr( $v['_slide_button_text'] ); ?>">
			<span class="description"><?php esc_html_e( 'e.g. "Browse eBooks". Leave empty for no button.', 'ebook-store' ); ?></span>
		</p>
		<p>
			<label for="ebookstore-slide-button-link"><strong><?php esc_html_e( 'Button link', 'ebook-store' ); ?></strong></label><br>
			<input type="text" id="ebookstore-slide-button-link" name="_slide_button_link" class="regular-text" value="<?php echo esc_attr( $v['_slide_button_link'] ); ?>" placeholder="/shop/">
			<span class="description"><?php esc_html_e( 'A page on this site (e.g. /shop/ or /product-category/finance/) or a full web address starting with https://', 'ebook-store' ); ?></span>
		</p>
		<p>
			<label>
				<input type="checkbox" name="_slide_enabled" value="1" <?php checked( '1', (string) $v['_slide_enabled'] ); ?>>
				<strong><?php esc_html_e( 'Show this slide on the website', 'ebook-store' ); ?></strong>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Slide order: use the "Order" box on the right (1 = first). Remember to click "Publish" or "Update" to save.', 'ebook-store' ); ?>
		</p>
	</div>
	<?php
}

add_action(
	'save_post_' . EBOOKSTORE_SLIDE_PT,
	static function ( $post_id ) {
		if ( ! isset( $_POST['ebookstore_slide_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ebookstore_slide_nonce'] ) ), 'ebookstore_save_slide' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array( '_slide_image_desktop', '_slide_image_mobile' ) as $key ) {
			$id = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
			update_post_meta( $post_id, $key, ( $id && wp_attachment_is_image( $id ) ) ? $id : 0 );
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		update_post_meta( $post_id, '_slide_heading', isset( $_POST['_slide_heading'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['_slide_heading'] ) ), 0, 90 ) : '' );
		update_post_meta( $post_id, '_slide_subheading', isset( $_POST['_slide_subheading'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['_slide_subheading'] ) ), 0, 200 ) : '' );
		update_post_meta( $post_id, '_slide_button_text', isset( $_POST['_slide_button_text'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['_slide_button_text'] ) ), 0, 30 ) : '' );
		update_post_meta( $post_id, '_slide_button_link', isset( $_POST['_slide_button_link'] ) ? ebookstore_sanitize_slide_link( wp_unslash( $_POST['_slide_button_link'] ) ) : '' );
		// phpcs:enable
		update_post_meta( $post_id, '_slide_enabled', ! empty( $_POST['_slide_enabled'] ) ? '1' : '0' );
	}
);

// Media Library picker on the slide edit screen.
add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || EBOOKSTORE_SLIDE_PT !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'ebookstore-hero-admin', plugins_url( 'assets/hero-slides-admin.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
		wp_localize_script(
			'ebookstore-hero-admin',
			'ebookstoreHeroAdmin',
			array(
				'title'  => __( 'Choose slide image', 'ebook-store' ),
				'button' => __( 'Use this image', 'ebook-store' ),
				'empty'  => __( 'No image chosen', 'ebook-store' ),
			)
		);
		wp_add_inline_style(
			'wp-admin',
			'.ebookstore-slide-box__images{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:8px}' .
			'.ebookstore-image-field__preview{display:flex;align-items:center;justify-content:center;min-height:120px;background:#f0f0f1;border:1px dashed #c3c4c7;border-radius:4px;overflow:hidden}' .
			'.ebookstore-image-field__preview img{display:block;max-width:100%;height:auto}' .
			'.ebookstore-image-field__empty{color:#646970}'
		);
	}
);

/* -------------------------------------------------------------------------
 * Admin: list screen and help
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_' . EBOOKSTORE_SLIDE_PT . '_posts_columns',
	static function ( $columns ) {
		return array(
			'cb'                => $columns['cb'],
			'ebookstore_image'  => __( 'Image', 'ebook-store' ),
			'title'             => __( 'Slide name', 'ebook-store' ),
			'ebookstore_text'   => __( 'Heading', 'ebook-store' ),
			'ebookstore_order'  => __( 'Order', 'ebook-store' ),
			'ebookstore_status' => __( 'On website', 'ebook-store' ),
		);
	}
);

add_action(
	'manage_' . EBOOKSTORE_SLIDE_PT . '_posts_custom_column',
	static function ( $column, $post_id ) {
		switch ( $column ) {
			case 'ebookstore_image':
				$id = (int) get_post_meta( $post_id, '_slide_image_desktop', true );
				echo $id ? wp_get_attachment_image( $id, array( 160, 60 ), false, array( 'style' => 'width:160px;height:60px;object-fit:cover;border-radius:3px' ) ) : '—';
				break;
			case 'ebookstore_text':
				echo esc_html( (string) get_post_meta( $post_id, '_slide_heading', true ) );
				break;
			case 'ebookstore_order':
				echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
				break;
			case 'ebookstore_status':
				$shown = 'publish' === get_post_status( $post_id )
					&& '1' === (string) get_post_meta( $post_id, '_slide_enabled', true )
					&& (int) get_post_meta( $post_id, '_slide_image_desktop', true );
				echo $shown
					? '<span style="color:#008a20;font-weight:600">' . esc_html__( 'Shown', 'ebook-store' ) . '</span>'
					: '<span style="color:#646970">' . esc_html__( 'Hidden', 'ebook-store' ) . '</span>';
				break;
		}
	},
	10,
	2
);

// List sorted like the website: by Order, then newest.
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( is_admin() && $query->is_main_query() && EBOOKSTORE_SLIDE_PT === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
			$query->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				)
			);
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || EBOOKSTORE_SLIDE_PT !== $screen->post_type ) {
			return;
		}
		if ( 'edit' === $screen->base ) {
			printf(
				'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'How Hero Slides work:', 'ebook-store' ),
				esc_html__( 'Slides marked "Shown" appear at the top of the home page, in the order of their "Order" number. With one slide you get a single banner; with two or more, a slideshow. With no slides, a standard banner is shown. To hide a slide without deleting it, untick "Show this slide on the website".', 'ebook-store' ),
				esc_url( admin_url( 'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT . '&page=' . EBOOKSTORE_HERO_SETTINGS ) ),
				esc_html__( 'Slideshow settings', 'ebook-store' )
			);
		}
		if ( 'post' === $screen->base ) {
			global $post;
			if ( $post && 'publish' === $post->post_status && '1' === (string) get_post_meta( $post->ID, '_slide_enabled', true ) && ! (int) get_post_meta( $post->ID, '_slide_image_desktop', true ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'This slide is hidden on the website until you choose a desktop image.', 'ebook-store' ) . '</p></div>';
			}
		}
	}
);

/* -------------------------------------------------------------------------
 * Admin: settings (autoplay)
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT,
			__( 'Slideshow settings', 'ebook-store' ),
			__( 'Settings', 'ebook-store' ),
			'edit_pages',
			EBOOKSTORE_HERO_SETTINGS,
			'ebookstore_render_hero_settings'
		);
	}
);

/**
 * Render the settings page.
 */
function ebookstore_render_hero_settings() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$settings = (array) get_option( EBOOKSTORE_HERO_OPTION, array() );
	$value    = isset( $settings['autoplay'] ) ? (string) $settings['autoplay'] : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after redirect.
	$saved = isset( $_GET['saved'] );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Slideshow settings', 'ebook-store' ); ?></h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ebook-store' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ebookstore_hero_settings">
			<?php wp_nonce_field( 'ebookstore_hero_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ebookstore-autoplay"><?php esc_html_e( 'Change slide every', 'ebook-store' ); ?></label></th>
					<td>
						<input type="number" id="ebookstore-autoplay" name="autoplay" min="0" max="30" step="1" class="small-text" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( (string) ebookstore_hero_autoplay_seconds() ); ?>">
						<?php esc_html_e( 'seconds', 'ebook-store' ); ?>
						<p class="description"><?php esc_html_e( '0 = slides change only when the visitor clicks. Empty = default from the .env file (HERO_AUTOPLAY_SECONDS). Visitors can always pause the slideshow, and it never moves for visitors who prefer reduced motion.', 'ebook-store' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save changes', 'ebook-store' ) ); ?>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_ebookstore_hero_settings',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ebook-store' ), 403 );
		}
		check_admin_referer( 'ebookstore_hero_settings' );
		$raw = isset( $_POST['autoplay'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['autoplay'] ) ) ) : '';
		update_option( EBOOKSTORE_HERO_OPTION, array( 'autoplay' => ( '' === $raw ) ? '' : (string) max( 0, min( 30, (int) $raw ) ) ), false );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . EBOOKSTORE_SLIDE_PT . '&page=' . EBOOKSTORE_HERO_SETTINGS . '&saved=1' ) );
		exit;
	}
);

/* -------------------------------------------------------------------------
 * WP-CLI: wp ebookstore seed-slides  (3 starter slides, safe to run twice)
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Create the starter slides from sample-content/hero/.
	 */
	function ebookstore_cli_seed_slides() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$dir    = dirname( ABSPATH ) . '/sample-content/hero/';
		$shop   = wp_make_link_relative( wc_get_page_permalink( 'shop' ) );
		$slides = array(
			array( 'slide-1-open-book', 'Your next great read is one click away', 'Practical eBooks you can download instantly and read on any device.', 'Browse eBooks', $shop ),
			array( 'slide-2-fanned-pages', 'Learn something new this week', 'Guides on productivity, money, cooking, technology and more.', 'Explore eBooks', $shop ),
			array( 'slide-3-book-and-tea', 'Pay in US dollars or British pounds', 'Secure checkout and instant PDF delivery to your email.', 'Start shopping', $shop ),
		);

		foreach ( $slides as $i => list( $slug, $heading, $sub, $button, $link ) ) {
			$existing = get_posts(
				array(
					'post_type'   => EBOOKSTORE_SLIDE_PT,
					'post_status' => 'any',
					'meta_key'    => '_ebookstore_seed_slide', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'      => 'ids',
				)
			);
			if ( $existing ) {
				WP_CLI::log( "Skipped (exists): $heading" );
				continue;
			}

			$ids = array();
			foreach ( array( 'desktop', 'mobile' ) as $variant ) {
				$file = "$dir$slug-$variant.webp";
				if ( ! is_readable( $file ) ) {
					WP_CLI::error( "Missing image: $file" );
				}
				$tmp = wp_tempnam( basename( $file ) );
				copy( $file, $tmp );
				$id = media_handle_sideload(
					array(
						'name'     => "$slug-$variant.webp",
						'tmp_name' => $tmp,
					),
					0,
					$heading
				);
				if ( is_wp_error( $id ) ) {
					WP_CLI::error( $id->get_error_message() );
				}
				update_post_meta( $id, '_ebookstore_seed_slide', $slug );
				update_post_meta( $id, '_wp_attachment_image_alt', '' ); // Decorative: the heading carries the meaning.
				$ids[ $variant ] = $id;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => EBOOKSTORE_SLIDE_PT,
					'post_status' => 'publish',
					'post_title'  => $heading,
					'menu_order'  => $i + 1,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				WP_CLI::error( $post_id->get_error_message() );
			}
			update_post_meta( $post_id, '_slide_image_desktop', $ids['desktop'] );
			update_post_meta( $post_id, '_slide_image_mobile', $ids['mobile'] );
			update_post_meta( $post_id, '_slide_heading', $heading );
			update_post_meta( $post_id, '_slide_subheading', $sub );
			update_post_meta( $post_id, '_slide_button_text', $button );
			update_post_meta( $post_id, '_slide_button_link', home_url( $link ) );
			update_post_meta( $post_id, '_slide_enabled', '1' );
			update_post_meta( $post_id, '_ebookstore_seed_slide', $slug );
			WP_CLI::success( "Created slide #$post_id: $heading" );
		}
	}

	WP_CLI::add_command(
		'ebookstore seed-slides',
		'ebookstore_cli_seed_slides',
		array( 'shortdesc' => 'Create the 3 starter Hero Slides from sample-content/hero/ (safe to run twice).' )
	);
}
