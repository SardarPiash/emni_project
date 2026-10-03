<?php
/**
 * Security: server-side checks for our customised customer fields.
 *
 * Names: letters from every language (\p{L}) plus combining marks (\p{M} —
 * needed for Bengali/Hindi vowel signs and decomposed accents), spaces,
 * apostrophes (' and the iPhone ’), hyphens and dots. Max 60 characters.
 * José, Zoë, O'Brien, O’Brien, Nguyễn, Anne-Marie, সাকিব all pass.
 * Emails are already checked by WooCommerce with is_email().
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_NAME_MAX = 60;

/**
 * Error message for a name value, or '' if it is fine.
 *
 * @param string $value Name.
 * @param string $label Field label.
 * @return string
 */
function ebookstore_sec_name_error( $value, $label ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return ''; // "Required" is WooCommerce's own check.
	}
	if ( mb_strlen( $value ) > EBOOKSTORE_NAME_MAX ) {
		/* translators: 1: field label, 2: max characters */
		return sprintf( __( '%1$s is too long (maximum %2$d characters).', 'ebook-store' ), $label, EBOOKSTORE_NAME_MAX );
	}
	if ( ! preg_match( "/^[\\p{L}\\p{M}][\\p{L}\\p{M}'\x{2019} .\\-]*$/u", $value ) ) {
		/* translators: %s: field label */
		return sprintf( __( '%s can only contain letters, spaces, apostrophes, hyphens and dots.', 'ebook-store' ), $label );
	}
	return '';
}

// Checkout.
add_action(
	'woocommerce_after_checkout_validation',
	static function ( $data, $errors ) {
		$fields = array(
			'billing_first_name' => __( 'First name', 'ebook-store' ),
			'billing_last_name'  => __( 'Last name', 'ebook-store' ),
		);
		foreach ( $fields as $key => $label ) {
			$message = ebookstore_sec_name_error( $data[ $key ] ?? '', $label );
			if ( $message ) {
				$errors->add( 'validation', $message, array( 'id' => $key ) );
			}
		}
	},
	10,
	2
);

// My Account → Account details.
add_action(
	'woocommerce_save_account_details_errors',
	static function ( $errors ) {
		$fields = array(
			'account_first_name' => __( 'First name', 'ebook-store' ),
			'account_last_name'  => __( 'Last name', 'ebook-store' ),
		);
		foreach ( $fields as $key => $label ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the nonce before this hook.
			$value   = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$message = ebookstore_sec_name_error( $value, $label );
			if ( $message ) {
				$errors->add( $key, $message );
			}
		}
	}
);

// HTML limits on the checkout name fields (the JS in the theme adds inline messages).
add_filter(
	'woocommerce_checkout_fields',
	static function ( $fields ) {
		foreach ( array( 'billing_first_name', 'billing_last_name' ) as $key ) {
			if ( isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ]['custom_attributes'] = array_merge(
					(array) ( $fields['billing'][ $key ]['custom_attributes'] ?? array() ),
					array(
						'maxlength'       => EBOOKSTORE_NAME_MAX,
						'data-ebook-name' => '1',
					)
				);
			}
		}
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['custom_attributes'] = array_merge(
				(array) ( $fields['billing']['billing_email']['custom_attributes'] ?? array() ),
				array( 'maxlength' => 100 )
			);
		}
		return $fields;
	},
	30
);
