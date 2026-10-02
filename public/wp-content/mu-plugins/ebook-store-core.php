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
