<?php
/**
 * Plugin Name:          eBook Dummy Payment Gateway (TEST ONLY)
 * Description:          Simulates successful or failed payments for testing the eBook Store. No money is charged. Remove before going live.
 * Version:              1.0.0
 * Author:               eBook Store
 * License:              GPL-2.0-or-later
 * Requires at least:    6.5
 * Requires PHP:         8.0
 * Requires Plugins:     woocommerce
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 * Text Domain:          ebook-dummy-gateway
 *
 * Fully independent: nothing else in the project depends on this plugin.
 * It is active only when DUMMY_GATEWAY_ENABLED=true in .env.
 *
 * @package EbookDummyGateway
 */

defined( 'ABSPATH' ) || exit;

define( 'EBOOK_DUMMY_GATEWAY_FILE', __FILE__ );

/**
 * Whether the .env switch allows the gateway (DUMMY_GATEWAY_ENABLED=true).
 *
 * @return bool
 */
function ebook_dummy_gateway_env_enabled() {
	$value = function_exists( 'env' ) ? env( 'DUMMY_GATEWAY_ENABLED', false ) : getenv( 'DUMMY_GATEWAY_ENABLED' );
	return true === $value || in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Safety guard: on the live site (APP_ENV=production) the test gateway is
 * offered only to logged-in shop staff (manage_woocommerce), so the owner can
 * still test a purchase but customers can never get eBooks for free — even if
 * DUMMY_GATEWAY_ENABLED is accidentally left on.
 *
 * @return bool
 */
function ebook_dummy_gateway_allowed_for_visitor() {
	if ( 'production' !== wp_get_environment_type() ) {
		return true;
	}
	return is_user_logged_in() && current_user_can( 'manage_woocommerce' );
}

// HPOS compatible; classic checkout only (no Cart/Checkout Blocks integration).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EBOOK_DUMMY_GATEWAY_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', EBOOK_DUMMY_GATEWAY_FILE, false );
		}
	}
);

// Register the gateway only when allowed by .env — otherwise it does not exist at all.
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) || ! ebook_dummy_gateway_env_enabled() ) {
			return;
		}
		require_once __DIR__ . '/includes/class-wc-gateway-ebook-dummy.php';

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $gateways ) {
				$gateways[] = 'WC_Gateway_Ebook_Dummy';
				return $gateways;
			}
		);
	},
	11
);

// Warning on every admin screen while the gateway can take "payments".
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! ebook_dummy_gateway_env_enabled() || ! class_exists( 'WC_Gateway_Ebook_Dummy' ) ) {
			return;
		}
		$settings = get_option( 'woocommerce_ebook_dummy_settings', array() );
		if ( isset( $settings['enabled'] ) && 'no' === $settings['enabled'] ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Dummy payment gateway is active — disable before going live.', 'ebook-dummy-gateway' ),
			esc_html__( 'Set DUMMY_GATEWAY_ENABLED=false in the .env file (or delete the plugin) once a real payment gateway is installed.', 'ebook-dummy-gateway' )
		);
	}
);
