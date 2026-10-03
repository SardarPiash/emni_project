<?php
/**
 * Security: admin login lockout.
 *
 * - Counted failures: wrong password for a protected (admin) account, or a
 *   username/email that does not exist. Customer accounts are never counted.
 * - After SECURITY_MAX_LOGIN_ATTEMPTS counted failures from one IP (within
 *   SECURITY_LOCKOUT_MINUTES) that IP is blocked for SECURITY_LOCKOUT_MINUTES from
 *   /wp-admin/ (except admin-ajax.php / admin-post.php) and from logging in to
 *   protected accounts on any route — refused without checking the password.
 * - Everything else stays open for that IP: shop, checkout, downloads,
 *   customer login, password reset, registration, wc-ajax, REST/Store API.
 * - Blocking is per IP, never per account: a correct admin login from any
 *   non-blocked IP always works and resets that IP's counter.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Counting
 * ---------------------------------------------------------------------- */

/**
 * Record a counted failure for the current IP and block it when the limit is reached.
 *
 * @param string $username Username tried.
 */
function ebookstore_sec_count_failure( $username ) {
	$ip = ebookstore_sec_ip();
	if ( ebookstore_sec_is_allowlisted( $ip ) || ebookstore_sec_blocked_seconds( $ip ) ) {
		return;
	}
	global $wpdb;
	$s     = ebookstore_sec_settings();
	$t     = ebookstore_sec_tables();
	$route = ebookstore_sec_route();
	$row   = ebookstore_sec_get_ip_row( $ip );

	// Failures older than the lockout window no longer count.
	$window   = $s['lockout_min'] * MINUTE_IN_SECONDS;
	$recent   = $row && $row->last_failure && ( strtotime( $row->last_failure . ' UTC' ) > time() - $window );
	$failures = ( $recent ? (int) $row->failures : 0 ) + 1;

	$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$t['ips'],
		array(
			'ip'            => $ip,
			'failures'      => $failures,
			'last_failure'  => ebookstore_sec_now(),
			'blocked_until' => null,
			'lockouts'      => $row ? (int) $row->lockouts : 0,
			'last_username' => mb_substr( (string) $username, 0, 100 ),
			'last_route'    => $route,
			'blocked_at'    => null,
			'manual'        => 0,
		)
	);
	ebookstore_sec_log( 'failed', $ip, $route, $username, sprintf( 'attempt %d of %d', $failures, $s['max_attempts'] ) );

	if ( $failures >= $s['max_attempts'] ) {
		ebookstore_sec_block_ip( $ip, $s['lockout_min'], false, $username, $route );
		ebookstore_sec_log( 'lockout', $ip, $route, $username, sprintf( 'blocked for %d minutes', $s['lockout_min'] ) );
	}
}

// wp-login.php and the WooCommerce My Account form (both use wp_signon()).
add_action(
	'wp_login_failed',
	static function ( $username ) {
		if ( ebookstore_sec_login_counts( $username ) ) {
			ebookstore_sec_count_failure( $username );
		}
	}
);

// REST API with Basic Auth / Application Passwords.
add_action(
	'application_password_failed_authentication',
	static function () {
		$username = isset( $_SERVER['PHP_AUTH_USER'] ) ? sanitize_user( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) : '';
		if ( ebookstore_sec_login_counts( $username ) ) {
			ebookstore_sec_count_failure( $username );
		}
	}
);

// A correct admin login resets that IP's counter.
add_action(
	'wp_login',
	static function ( $login, $user ) {
		if ( ebookstore_sec_is_protected_user( $user ) ) {
			$ip  = ebookstore_sec_ip();
			$row = ebookstore_sec_get_ip_row( $ip );
			if ( $row && ! $row->blocked_until ) {
				ebookstore_sec_unblock_ip( $ip );
			}
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Refusing logins from a blocked IP
 * ---------------------------------------------------------------------- */

// Before any password check: blocked IP + protected or unknown account → 429.
add_filter(
	'authenticate',
	static function ( $user, $username ) {
		if ( '' === (string) $username ) {
			return $user;
		}
		$seconds = ebookstore_sec_blocked_seconds( ebookstore_sec_ip() );
		if ( $seconds && ebookstore_sec_login_counts( $username ) ) {
			ebookstore_sec_log( 'blocked', ebookstore_sec_ip(), ebookstore_sec_route(), $username, 'login refused (IP blocked)' );
			ebookstore_sec_send_blocked( $seconds );
		}
		return $user;
	},
	1,
	2
);

// REST API Basic Auth: refuse before WordPress checks the application password.
add_filter(
	'determine_current_user',
	static function ( $user_id ) {
		if ( $user_id || empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $user_id;
		}
		$username = sanitize_user( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
		$seconds  = ebookstore_sec_blocked_seconds( ebookstore_sec_ip() );
		if ( $seconds && ebookstore_sec_login_counts( $username ) ) {
			ebookstore_sec_log( 'blocked', ebookstore_sec_ip(), 'rest-api', $username, 'login refused (IP blocked)' );
			ebookstore_sec_send_blocked( $seconds );
		}
		return $user_id;
	},
	19
);

// Admin area: blocked IPs cannot open /wp-admin/ pages (admin-ajax.php and admin-post.php stay open).
add_action(
	'init',
	static function () {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}
		$seconds = ebookstore_sec_blocked_seconds( ebookstore_sec_ip() );
		if ( $seconds ) {
			ebookstore_sec_send_blocked( $seconds );
		}
	},
	0
);

/* -------------------------------------------------------------------------
 * Generic login error (never reveal whether a username exists)
 * ---------------------------------------------------------------------- */

add_filter(
	'authenticate',
	static function ( $user ) {
		if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo' ) ) ) {
			return new WP_Error( 'ebookstore_invalid_login', __( '<strong>Error:</strong> Invalid username or password.', 'ebook-store' ) );
		}
		return $user;
	},
	100
);

/* -------------------------------------------------------------------------
 * Blocked response (HTTP 429)
 * ---------------------------------------------------------------------- */

/**
 * Send the blocked response and stop.
 *
 * @param int $seconds Seconds left.
 */
function ebookstore_sec_send_blocked( $seconds ) {
	$minutes = max( 1, (int) ceil( $seconds / MINUTE_IN_SECONDS ) );
	/* translators: %d: minutes */
	$message = sprintf( _n( 'Too many failed login attempts from your network. Please try again in %d minute.', 'Too many failed login attempts from your network. Please try again in %d minutes.', $minutes, 'ebook-store' ), $minutes );

	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	status_header( 429 );
	header( 'Retry-After: ' . (int) $seconds );

	$json = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'rest-api' === ebookstore_sec_route() || wp_is_json_request();
	if ( $json ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'code'    => 'too_many_attempts',
				'message' => $message,
				'data'    => array( 'status' => 429 ),
			)
		);
		exit;
	}

	header( 'Content-Type: text/html; charset=utf-8' );
	ebookstore_sec_render_page(
		__( 'Please try again later', 'ebook-store' ),
		'<p>' . esc_html( $message ) . '</p>' . ebookstore_sec_unlock_form_html()
	);
	exit;
}

/**
 * Minimal standalone page (no theme, works in the admin area too).
 *
 * @param string $title Title.
 * @param string $body  Escaped HTML body.
 */
function ebookstore_sec_render_page( $title, $body ) {
	?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
<style>
	body { margin: 0; padding: 48px 16px; background: #FAF7F2; color: #1F2933; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
	main { max-width: 480px; margin: 0 auto; padding: 32px; background: #fff; border: 1px solid #E5E1DA; border-radius: 10px; box-shadow: 0 4px 16px rgba(21, 40, 66, .08); }
	h1 { margin: 0 0 12px; color: #1F3A5F; font: 700 1.5rem/1.3 Georgia, serif; }
	label { display: block; margin: 20px 0 6px; font-weight: 600; }
	input[type=email] { box-sizing: border-box; width: 100%; min-height: 44px; padding: 8px 12px; border: 1px solid #6B7280; border-radius: 8px; font: inherit; }
	button { min-height: 44px; margin-top: 12px; padding: 8px 18px; border: 0; border-radius: 8px; background: #1F3A5F; color: #fff; font: 600 1rem/1 inherit; cursor: pointer; }
	.muted { color: #6B7280; font-size: .9375rem; }
	a { color: #1F3A5F; }
</style>
</head>
<body>
<main>
	<h1><?php echo esc_html( $title ); ?></h1>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts by the caller. ?>
	<p class="muted"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the store', 'ebook-store' ); ?></a></p>
</main>
</body>
</html>
	<?php
}
