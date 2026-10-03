<?php
/**
 * Security: password reset anti-flood (invisible to customers).
 *
 * The same account receives at most one reset email per
 * SECURITY_RESET_EMAIL_COOLDOWN_SECONDS. A repeated request is stopped BEFORE
 * WordPress creates a new reset key — otherwise the link in the first email
 * would silently stop working — and the visitor sees the normal
 * "check your email" result.
 *
 * Works for the WooCommerce My Account form and wp-login.php (both fire
 * "lostpassword_post" before the key is created).
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'lostpassword_post',
	static function ( $errors, $user_data ) {
		$cooldown = ebookstore_sec_settings()['reset_cooldown'];
		if ( ! $cooldown || ! $user_data instanceof WP_User || ( is_wp_error( $errors ) && $errors->has_errors() ) ) {
			return;
		}
		$key = 'ebookstore_reset_' . $user_data->ID;
		if ( ! get_transient( $key ) ) {
			set_transient( $key, time(), $cooldown ); // This request goes through normally.
			return;
		}

		// Too soon: show the same success result, send nothing, keep the first link valid.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified its nonce before this hook.
		if ( isset( $_POST['wc_reset_password'] ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
			wp_safe_redirect( add_query_arg( 'reset-link-sent', 'true', wc_get_account_endpoint_url( 'lost-password' ) ) );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only, same as wp-login.php.
			$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed to wp_safe_redirect.
			wp_safe_redirect( apply_filters( 'lostpassword_redirect', $redirect_to ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		}
		exit;
	},
	20,
	2
);
