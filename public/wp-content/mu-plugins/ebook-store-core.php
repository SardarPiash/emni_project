<?php
/**
 * Plugin Name: eBook Store Core
 * Description: Site-wide store customizations driven by .env (always active).
 * Version:     0.1.0
 * Author:      eBook Store
 * License:     GPL-2.0-or-later
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Options that always follow .env, so changing .env is enough.
 * Map: WordPress option name => .env key.
 */
function ebookstore_env_options() {
	return array(
		'blogname'             => 'STORE_NAME',
		'blogdescription'      => 'STORE_TAGLINE',
		'timezone_string'      => 'SITE_TIMEZONE',
		'woocommerce_currency' => 'STORE_BASE_CURRENCY',
	);
}

foreach ( ebookstore_env_options() as $ebookstore_option => $ebookstore_env_key ) {
	add_filter(
		'pre_option_' . $ebookstore_option,
		static function ( $pre ) use ( $ebookstore_env_key ) {
			$value = function_exists( 'env' ) ? env( $ebookstore_env_key ) : null;
			return ( is_string( $value ) && '' !== $value ) ? $value : $pre;
		}
	);
}
unset( $ebookstore_option, $ebookstore_env_key );

/**
 * Show a note under fields that are controlled by .env.
 */
add_action(
	'admin_footer-options-general.php',
	static function () {
		$fields = array();
		foreach ( ebookstore_env_options() as $option => $key ) {
			if ( function_exists( 'env' ) && '' !== (string) env( $key, '' ) ) {
				$fields[ $option ] = $key;
			}
		}
		if ( ! $fields ) {
			return;
		}
		?>
		<script>
		( function () {
			var fields = <?php echo wp_json_encode( $fields ); ?>;
			Object.keys( fields ).forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( ! el ) { return; }
				el.disabled = true;
				var p = document.createElement( 'p' );
				p.className = 'description';
				p.textContent = 'Controlled by ' + fields[ id ] + ' in the .env file.';
				el.parentNode.appendChild( p );
			} );
		} )();
		</script>
		<?php
	}
);

/**
 * Apply the default download limit and expiry from .env
 * (STORE_DOWNLOAD_LIMIT, STORE_DOWNLOAD_EXPIRY_DAYS) the first time a
 * product is saved as downloadable, if those fields were left empty.
 * Afterwards the admin can change them per product as usual.
 *
 * @param WC_Product $product Product being saved.
 */
function ebookstore_apply_download_defaults( $product ) {
	if ( ! $product->is_downloadable() || $product->get_meta( '_ebookstore_download_defaults' ) ) {
		return;
	}

	$limit  = (string) env( 'STORE_DOWNLOAD_LIMIT', '' );
	$expiry = (string) env( 'STORE_DOWNLOAD_EXPIRY_DAYS', '' );

	if ( -1 === (int) $product->get_download_limit() && ctype_digit( $limit ) && (int) $limit > 0 ) {
		$product->set_download_limit( (int) $limit );
	}
	if ( -1 === (int) $product->get_download_expiry() && ctype_digit( $expiry ) && (int) $expiry > 0 ) {
		$product->set_download_expiry( (int) $expiry );
	}

	$product->update_meta_data( '_ebookstore_download_defaults', 'yes' );
}
add_action( 'woocommerce_before_product_object_save', 'ebookstore_apply_download_defaults' );

/**
 * URL of the "Buy Now" action for a product: adds it to the cart (once)
 * and goes straight to checkout.
 *
 * @param int|WC_Product $product Product or product ID.
 * @return string Empty string if the product does not exist.
 */
function ebookstore_buy_now_url( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product ) {
		return '';
	}
	return add_query_arg( 'ebook-buy-now', $product->get_id(), wc_get_checkout_url() );
}

/**
 * Handle "Buy Now" links (?ebook-buy-now=<product ID>).
 *
 * Runs after WooCommerce's own add-to-cart handler (wp_loaded, 20).
 * Products are sold individually, so an eBook already in the cart is not
 * added again (avoids WooCommerce's "cannot add another" error).
 */
function ebookstore_handle_buy_now() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same as WooCommerce's ?add-to-cart= links.
	if ( empty( $_GET['ebook-buy-now'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$product_id = absint( wp_unslash( $_GET['ebook-buy-now'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$product    = wc_get_product( $product_id );

	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		wc_add_notice( __( 'Sorry, this eBook is not available for purchase right now.', 'ebook-store' ), 'error' );
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}

	$in_cart = WC()->cart->find_product_in_cart( WC()->cart->generate_cart_id( $product_id ) );

	if ( ! $in_cart && false === WC()->cart->add_to_cart( $product_id, 1 ) ) {
		wp_safe_redirect( $product->get_permalink() );
		exit;
	}

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}
add_action( 'wp_loaded', 'ebookstore_handle_buy_now', 30 );

/**
 * Disable the WordPress emoji script and styles: browsers render emoji
 * natively, and this avoids loading images from s.w.org on every page.
 */
function ebookstore_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'ebookstore_disable_emoji' );

/* -------------------------------------------------------------------------
 * Currencies (CURCY – Multi Currency for WooCommerce)
 * ---------------------------------------------------------------------- */

/**
 * Currencies and exchange rate always come from .env:
 * STORE_BASE_CURRENCY (rate 1), STORE_SECONDARY_CURRENCY and
 * STORE_GBP_EXCHANGE_RATE (1 base = X secondary). Uses CURCY's own
 * "wmc_settings_args" filter, so the plugin files are never edited.
 *
 * @param array $params CURCY settings.
 * @return array
 */
function ebookstore_currency_settings_from_env( $params ) {
	$base   = strtoupper( (string) env( 'STORE_BASE_CURRENCY', 'USD' ) );
	$second = strtoupper( (string) env( 'STORE_SECONDARY_CURRENCY', '' ) );
	$rate   = (float) env( 'STORE_GBP_EXCHANGE_RATE', 0 );

	if ( '' === $second || $rate <= 0 || $second === $base ) {
		return $params;
	}

	$params['currency_default']  = $base;
	$params['currency']          = array( $base, $second );
	$params['currency_rate']     = array( 1, $rate );
	$params['currency_rate_fee'] = array( 0, 0 );
	$params['currency_hidden']   = array( 0, 0 );
	$params['currency_decimals'] = array( 2, 2 );
	$params['currency_pos']      = array( 'left', 'left' );
	$params['currency_custom']   = array( '', '' );

	return $params;
}
add_filter( 'wmc_settings_args', 'ebookstore_currency_settings_from_env' );

/**
 * Show the real payment currency and amount in the checkout order summary
 * (and the cart totals), e.g. "You will be charged in GBP: £7.89".
 * The row is part of the order review, so it updates when the currency changes.
 */
function ebookstore_payment_currency_row() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$currency = get_woocommerce_currency();
	?>
	<tr class="ebook-charge-notice">
		<td colspan="2">
			<?php
			printf(
				/* translators: 1: currency code, 2: formatted order total */
				esc_html__( 'You will be charged in %1$s: %2$s', 'ebook-store' ),
				'<strong>' . esc_html( $currency ) . '</strong>',
				'<strong>' . wp_kses_post( WC()->cart->get_total() ) . '</strong>'
			);
			?>
		</td>
	</tr>
	<?php
}
add_action( 'woocommerce_review_order_after_order_total', 'ebookstore_payment_currency_row' );
add_action( 'woocommerce_cart_totals_after_order_total', 'ebookstore_payment_currency_row' );

/* -------------------------------------------------------------------------
 * Checkout: digital products only need name, email and country
 * ---------------------------------------------------------------------- */

/**
 * Keep only first name, last name, email and country in the billing form
 * (checkout and My Account). Country stays because it is needed for the
 * payment gateway, fraud checks and any future US/UK tax setup.
 *
 * @param array $fields Billing fields.
 * @return array
 */
function ebookstore_billing_fields( $fields ) {
	$keep = array(
		'billing_first_name' => 10,
		'billing_last_name'  => 20,
		'billing_email'      => 30,
		'billing_country'    => 40,
	);

	foreach ( array_keys( $fields ) as $key ) {
		if ( ! isset( $keep[ $key ] ) ) {
			unset( $fields[ $key ] );
		}
	}

	foreach ( $keep as $key => $priority ) {
		if ( isset( $fields[ $key ] ) ) {
			$fields[ $key ]['priority'] = $priority;
		}
	}

	if ( isset( $fields['billing_email'] ) ) {
		$fields['billing_email']['label']       = __( 'Email address', 'ebook-store' );
		$fields['billing_email']['description'] = __( 'Your eBook will be sent to this email.', 'ebook-store' );
		$fields['billing_email']['class']       = array( 'form-row-wide' );
	}

	return $fields;
}
add_filter( 'woocommerce_billing_fields', 'ebookstore_billing_fields', 20 );

// No order notes for digital products.
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
