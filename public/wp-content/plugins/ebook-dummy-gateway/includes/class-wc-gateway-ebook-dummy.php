<?php
/**
 * Dummy payment gateway: the customer chooses "successful" or "failed" payment.
 *
 * Success → $order->payment_complete() → normal WooCommerce flow
 *           (virtual + downloadable orders complete automatically, download
 *           access is granted, emails are sent). A real gateway that calls
 *           payment_complete() behaves exactly the same.
 * Failure → order status "Failed", error notice, no download access.
 *
 * @package EbookDummyGateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Gateway_Ebook_Dummy class.
 */
class WC_Gateway_Ebook_Dummy extends WC_Payment_Gateway {

	const RESULT_FIELD = 'ebook_dummy_result';

	/**
	 * Set up the gateway.
	 */
	public function __construct() {
		$this->id                 = 'ebook_dummy';
		$this->has_fields         = true;
		$this->method_title       = __( 'eBook Dummy Payment Gateway (TEST ONLY)', 'ebook-dummy-gateway' );
		$this->method_description = __( 'TEST ONLY. Lets you simulate a successful or failed payment at checkout. No money is charged. Disable it (DUMMY_GATEWAY_ENABLED=false in .env) or delete the plugin before going live.', 'ebook-dummy-gateway' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Admin settings (WooCommerce → Settings → Payments).
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'ebook-dummy-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable the dummy payment gateway (TEST ONLY)', 'ebook-dummy-gateway' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'ebook-dummy-gateway' ),
				'type'    => 'text',
				'default' => __( 'Test payment (TEST ONLY — no real money)', 'ebook-dummy-gateway' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'ebook-dummy-gateway' ),
				'type'    => 'textarea',
				'default' => __( 'This store is in test mode. No money will be charged. Choose the result you want to simulate:', 'ebook-dummy-gateway' ),
			),
		);
	}

	/**
	 * Available only when the .env switch is on (and enabled in settings).
	 *
	 * @return bool
	 */
	public function is_available() {
		return ebook_dummy_gateway_env_enabled() && parent::is_available();
	}

	/**
	 * Checkout fields: success / failure choice.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
		$name = self::RESULT_FIELD;
		?>
		<fieldset class="ebook-dummy-fields">
			<legend class="screen-reader-text"><?php esc_html_e( 'Simulated payment result', 'ebook-dummy-gateway' ); ?></legend>
			<p class="form-row">
				<label>
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="success" checked>
					<?php esc_html_e( 'Simulate successful payment', 'ebook-dummy-gateway' ); ?>
				</label>
			</p>
			<p class="form-row">
				<label>
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="fail">
					<?php esc_html_e( 'Simulate failed payment', 'ebook-dummy-gateway' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Selected result ("success" or "fail"), or '' if missing/invalid.
	 *
	 * WooCommerce has already verified the checkout nonce before calling the gateway.
	 *
	 * @return string
	 */
	private function get_posted_result() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce checkout.
		$value = isset( $_POST[ self::RESULT_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::RESULT_FIELD ] ) ) : '';
		return in_array( $value, array( 'success', 'fail' ), true ) ? $value : '';
	}

	/**
	 * Validate the choice.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		if ( '' === $this->get_posted_result() ) {
			wc_add_notice( __( 'Please choose a simulated payment result.', 'ebook-dummy-gateway' ), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * Process the "payment".
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'ebook-dummy-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		if ( 'fail' === $this->get_posted_result() ) {
			$order->update_status( 'failed', __( 'TEST ONLY: simulated failed payment (dummy gateway).', 'ebook-dummy-gateway' ) );

			// Start a new order on the next attempt, so the failed one stays on record.
			if ( WC()->session ) {
				WC()->session->set( 'order_awaiting_payment', false );
			}

			wc_add_notice( __( 'Payment failed. Your card was not charged. Please try again or choose another payment method.', 'ebook-dummy-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->add_order_note( __( 'TEST ONLY: simulated successful payment (dummy gateway). No money was charged.', 'ebook-dummy-gateway' ) );
		$order->payment_complete( 'DUMMY-' . strtoupper( wp_generate_password( 10, false ) ) );

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
